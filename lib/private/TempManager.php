<?php
/**
 * @author Lars <winnetou+github@catolic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Martin Mattel <martin.mattel@diemattels.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Olivier Paroz <github@oparoz.com>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Stefan Weil <sw@weilnetz.de>
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

namespace OC;

use Exception;
use OC_Helper;
use OCP\ILogger;
use OCP\IConfig;
use OCP\ITempManager;
use UnexpectedValueException;

class TempManager implements ITempManager {
	/** @var string[] Current temporary files and folders, used for cleanup */
	protected array $current = [];
	/** @var ?string i.e. /tmp on linux systems */
	protected ?string $tmpBaseDir = null;
	protected ILogger $logger;
	protected IConfig $config;

	/** Prefix */
	public const TMP_PREFIX = 'oc_tmp_';

	/**
	 * @param ILogger $logger
	 * @param IConfig $config
	 */
	public function __construct(ILogger $logger, IConfig $config) {
		$this->logger = $logger;
		$this->config = $config;
		$this->tmpBaseDir = $this->getTempBaseDir();
	}

	/**
	 * Builds the filename with suffix and removes potential dangerous characters
	 * such as directory separators.
	 *
	 * @param string $absolutePath Absolute path to the file / folder
	 * @param string $postFix Postfix appended to the temporary file name, may be user controlled
	 * @return string
	 */
	private function buildFileNameWithSuffix(string $absolutePath, string $postFix = ''): string {
		if ($postFix !== '') {
			$postFix = '.' . \ltrim($postFix, '.');
			$postFix = \str_replace(['\\', '/'], '', $postFix);
			$absolutePath .= '-';
		}

		return $absolutePath . $postFix;
	}

	/**
	 * Create a temporary file and return the path
	 *
	 * @param string $postFix Postfix appended to the temporary file name
	 * @return string
	 */
	public function getTemporaryFile($postFix = '') {
		if (\is_writable($this->tmpBaseDir)) {
			// To create a unique file and prevent the risk of race conditions
			// or duplicated temporary files by other means such as collisions
			// we need to create the file using `tempnam` and append a possible
			// postfix to it later
			$file = \tempnam($this->tmpBaseDir, self::TMP_PREFIX);
			$this->current[] = $file;

			// If a postfix got specified sanitize it and create a postfixed
			// temporary file
			if ($postFix !== '') {
				$fileNameWithPostfix = $this->buildFileNameWithSuffix($file, $postFix);
				$old_umask = \umask(0077);
				\touch($fileNameWithPostfix);
				\umask($old_umask);
				$this->current[] = $fileNameWithPostfix;
				return $fileNameWithPostfix;
			}

			return $file;
		}

		$this->logger->warning(
			'Can not create a temporary file in directory {dir}. Check it exists and has correct permissions',
			[
				'dir' => $this->tmpBaseDir,
			]
		);
		return false;
	}

	/**
	 * Create a temporary folder and return the path
	 *
	 * @param string $postFix Postfix appended to the temporary folder name
	 * @return string
	 */
	public function getTemporaryFolder($postFix = '') {
		if (\is_writable($this->tmpBaseDir)) {
			// To create a unique directory and prevent the risk of race conditions
			// or duplicated temporary files by other means such as collisions
			// we need to create the file using `tempnam` and append a possible
			// postfix to it later
			$uniqueFileName = \tempnam($this->tmpBaseDir, self::TMP_PREFIX);
			$this->current[] = $uniqueFileName;

			// Build a name without postfix
			$path = $this->buildFileNameWithSuffix($uniqueFileName . '-folder', $postFix);
			\mkdir($path, 0700);
			$this->current[] = $path;

			return $path . '/';
		}

		$this->logger->warning(
			'Can not create a temporary folder in directory {dir}. Check it exists and has correct permissions',
			[
				'dir' => $this->tmpBaseDir,
			]
		);
		return false;
	}

	/**
	 * Remove the temporary files and folders generated during this request
	 */
	public function clean() {
		$this->cleanFiles($this->current);
	}

	/**
	 * @param string[] $files
	 */
	protected function cleanFiles(array $files): void {
		foreach ($files as $file) {
			if (\file_exists($file)) {
				try {
					OC_Helper::rmdirr($file);
				} catch (UnexpectedValueException $ex) {
					$this->logger->warning(
						"Error deleting temporary file/folder: {file} - Reason: {error}",
						[
							'file' => $file,
							'error' => $ex->getMessage(),
						]
					);
				}
			}
		}
	}

	/**
	 * Remove old temporary files and folders that were failed to be cleaned
	 */
	public function cleanOld() {
		$this->cleanFiles($this->getOldFiles());
	}

	/**
	 * Get all temporary files and folders generated by oc older than an hour
	 *
	 * @return string[]
	 */
	protected function getOldFiles(): array {
		$cutOfTime = \time() - 3600;
		$files = [];
		$dh = \opendir($this->tmpBaseDir);
		if ($dh) {
			while (($file = \readdir($dh)) !== false) {
				if (\strpos($file, self::TMP_PREFIX) === 0) {
					$path = $this->tmpBaseDir . '/' . $file;
					$mtime = \filemtime($path);
					if ($mtime < $cutOfTime) {
						$files[] = $path;
					}
				}
			}
		}
		return $files;
	}

	/**
	 * Get the temporary base directory configured on the server
	 *
	 * @return string Path to the temporary directory or null
	 * @throws UnexpectedValueException
	 */
	public function getTempBaseDir(): string {
		if ($this->tmpBaseDir) {
			return $this->tmpBaseDir;
		}

		$directories = [];
		if ($temp = $this->config->getSystemValue('tempdirectory', null)) {
			$directories[] = $temp;
		}
		if ($temp = \OC::$server->getIniWrapper()->get('upload_tmp_dir')) {
			$directories[] = $temp;
		}
		if ($temp = \getenv('TMP')) {
			$directories[] = $temp;
		}
		if ($temp = \getenv('TEMP')) {
			$directories[] = $temp;
		}
		if ($temp = \getenv('TMPDIR')) {
			$directories[] = $temp;
		}
		if ($temp = \sys_get_temp_dir()) {
			$directories[] = $temp;
		}

		foreach ($directories as $dir) {
			if ($this->checkTemporaryDirectory($dir)) {
				return $dir;
			}
		}

		$temp = \tempnam(__DIR__, '');
		if (\file_exists($temp)) {
			\unlink($temp);
			return \dirname($temp);
		}
		throw new UnexpectedValueException('Unable to detect system temporary directory');
	}

	/**
	 * Check if a temporary directory is ready for use
	 *
	 * @param mixed $directory
	 * @return bool
	 */
	private function checkTemporaryDirectory($directory): bool {
		// suppress any possible errors caused by is_writable
		// checks missing or invalid path or characters, wrong permissions etc
		try {
			if (\is_writable($directory)) {
				return true;
			}
		} catch (Exception $e) {
		}
		$this->logger->warning(
			'Temporary directory {dir} is not present or writable',
			['dir' => $directory]
		);
		return false;
	}

	/**
	 * Override the temporary base directory
	 *
	 * @param string $directory
	 */
	public function overrideTempBaseDir(string $directory): void {
		$this->tmpBaseDir = $directory;
	}
}
