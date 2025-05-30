<?php
/**
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright LNKASIA TECHSOL
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Files\Storage\Wrapper;

use OCP\Files\Cache\ICacheEntry;
use OC\Files\Filesystem;
use OC_Util;

class Quota extends Wrapper {
	/**
	 * @var int $quota
	 */
	protected $quota;

	/**
	 * @var string $sizeRoot
	 */
	protected $sizeRoot;

	/** @var string $mountPoint */
	protected $mountPoint;

	/**
	 * @param array $parameters
	 */
	public function __construct($parameters) {
		parent::__construct($parameters);
		$this->quota = $parameters['quota'];
		$this->sizeRoot = isset($parameters['root']) ? $parameters['root'] : '';
		$this->mountPoint = $parameters['mountPoint'] ?? '';
	}

	/**
	 * @return int quota value
	 */
	public function getQuota() {
		return $this->quota;
	}

	/**
	 * @param string $path
	 * @param \OC\Files\Storage\Storage $storage
	 */
	protected function getSize($path, $storage = null) {
		if ($storage === null) {
			$cache = $this->getCache();
		} else {
			$cache = $storage->getCache();
		}
		$data = $cache->get($path);
		if ($data instanceof ICacheEntry and isset($data['size'])) {
			return $data['size'];
		} else {
			return \OCP\Files\FileInfo::SPACE_NOT_COMPUTED;
		}
	}

	/**
	 * Get free space as limited by the quota
	 *
	 * @param string $path
	 * @return int
	 */
	public function free_space($path) {
		if ($this->quota < 0) {
			return $this->storage->free_space($path);
		} else {
			$used = $this->getSize($this->sizeRoot);
			if ($used < 0) {
				return \OCP\Files\FileInfo::SPACE_NOT_COMPUTED;
			} else {
				$free = $this->storage->free_space($path);
				$quotaFree = \max($this->quota - $used, 0);
				// if free space is known
				if ($free >= 0) {
					$free = \min($free, $quotaFree);
				} else {
					$free = $quotaFree;
				}
				return $free;
			}
		}
	}

	/**
	 * see http://php.net/manual/en/function.file_put_contents.php
	 *
	 * @param string $path
	 * @param string $data
	 * @return bool
	 */
	public function file_put_contents($path, $data) {
		$free = $this->free_space('');
		if ($free < 0 or \strlen($data) < $free) {
			return $this->storage->file_put_contents($path, $data);
		} else {
			return false;
		}
	}

	/**
	 * see http://php.net/manual/en/function.copy.php
	 *
	 * @param string $source
	 * @param string $target
	 * @return bool
	 */
	public function copy($source, $target) {
		$free = $this->free_space('');
		if ($free < 0 or $this->getSize($source) < $free) {
			return $this->storage->copy($source, $target);
		} else {
			return false;
		}
	}

	/**
	 * see http://php.net/manual/en/function.fopen.php
	 *
	 * @param string $path
	 * @param string $mode
	 * @return resource
	 */
	public function fopen($path, $mode) {
		$source = $this->storage->fopen($path, $mode);

		$used = \OCP\Files\FileInfo::SPACE_NOT_COMPUTED;
		$free = $this->free_space('');
		// if it's a .part file, check if we're trying to overwrite a file
		if ($this->isPartFile($path)) {
			$strippedPath = OC_Util::stripPartialFileExtension($path);
			$used = $this->getSize($strippedPath);

			$view = new \OC\Files\View();
			$fullPath = Filesystem::normalizePath("{$this->mountPoint}/{$strippedPath}", true, true, true);

			$fInfo = $view->getFileInfo($fullPath);
			if ($fInfo && $fInfo->isShared()) {
				$free = $view->free_space($fullPath);
				$used = $fInfo->getSize();
			}
		}

		if ($used >= 0) {
			// if we're overwriting a file, add the size of that file to the available space
			// so it's possible to overwrite in case the quota is limited
			$free += $used;
		}
		if ($source && $free >= 0 && $mode !== 'r' && $mode !== 'rb') {
			// only apply quota for files, not metadata, trash or others
			if (\strpos(\ltrim($path, '/'), 'files/') === 0) {
				return \OC\Files\Stream\Quota::wrap($source, $free);
			}
		}
		return $source;
	}

	/**
	 * Checks whether the given path is a part file
	 *
	 * @param string $path Path that may identify a .part file
	 * @return string File path without .part extension
	 * @note this is needed for reusing keys
	 */
	private function isPartFile($path) {
		$extension = \pathinfo($path, PATHINFO_EXTENSION);

		return ($extension === 'part');
	}

	/**
	 * @param \OCP\Files\Storage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @return bool
	 */
	public function copyFromStorage(\OCP\Files\Storage $sourceStorage, $sourceInternalPath, $targetInternalPath) {
		$free = $this->free_space('');
		if ($free < 0 or $this->getSize($sourceInternalPath, $sourceStorage) < $free) {
			return $this->storage->copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
		} else {
			return false;
		}
	}

	/**
	 * @param \OCP\Files\Storage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @return bool
	 */
	public function moveFromStorage(\OCP\Files\Storage $sourceStorage, $sourceInternalPath, $targetInternalPath) {
		$free = $this->free_space('');
		if ($free < 0 or $this->getSize($sourceInternalPath, $sourceStorage) < $free) {
			return $this->storage->moveFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
		} else {
			return false;
		}
	}
}
