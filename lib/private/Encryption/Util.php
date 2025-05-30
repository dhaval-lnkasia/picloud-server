<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OC\Encryption;

use OC\Encryption\Exceptions\EncryptionHeaderKeyExistsException;
use OC\Encryption\Exceptions\EncryptionHeaderToLargeException;
use OC\Encryption\Exceptions\ModuleDoesNotExistsException;
use OC\Files\Filesystem;
use OC\Files\View;
use OCP\Encryption\IEncryptionModule;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IUser;

class Util {
	public const HEADER_START = 'HBEGIN';
	public const HEADER_END = 'HEND';
	public const HEADER_PADDING_CHAR = '-';

	public const HEADER_ENCRYPTION_MODULE_KEY = 'oc_encryption_module';

	public const ID = 'OC_DEFAULT_MODULE';

	/**
	 * block size will always be 8192 for a PHP stream
	 * @see https://bugs.php.net/bug.php?id=21641
	 * @var integer
	 */
	protected $headerSize = 8192;

	/**
	 * block size will always be 8192 for a PHP stream
	 * @see https://bugs.php.net/bug.php?id=21641
	 * @var integer
	 */
	protected $blockSize = 8192;

	/** @var View */
	protected $rootView;

	/** @var array */
	protected $ocHeaderKeys;

	/** @var \OC\User\Manager */
	protected $userManager;

	/** @var IConfig */
	protected $config;

	/** @var array paths excluded from encryption */
	protected $excludedPaths;

	/** @var \OC\Group\Manager $manager */
	protected $groupManager;

	/**
	 *
	 * @param View $rootView
	 * @param \OC\User\Manager $userManager
	 * @param \OC\Group\Manager $groupManager
	 * @param IConfig $config
	 */
	public function __construct(
		View $rootView,
		\OC\User\Manager $userManager,
		\OC\Group\Manager $groupManager,
		IConfig $config
	) {
		$this->ocHeaderKeys = [
			self::HEADER_ENCRYPTION_MODULE_KEY
		];

		$this->rootView = $rootView;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->config = $config;

		$this->excludedPaths[] = 'files_encryption';
		// contains certificates
		$this->excludedPaths[] = 'files_external';
		$this->excludedPaths[] = 'avatars';
		$this->excludedPaths[] = 'avatar.png';
		$this->excludedPaths[] = 'avatar.jpg';
	}

	/**
	 * read encryption module ID from header
	 *
	 * @param array $header
	 * @return string
	 * @throws ModuleDoesNotExistsException
	 */
	public function getEncryptionModuleId(array $header = null) {
		$id = '';
		$encryptionModuleKey = self::HEADER_ENCRYPTION_MODULE_KEY;

		if (isset($header[$encryptionModuleKey])) {
			$id = $header[$encryptionModuleKey];
		} elseif (isset($header['cipher'])) {
			if (\class_exists('\OCA\Encryption\Crypto\Encryption')) {
				// fall back to default encryption if the user migrated from
				// ownCloud <= 8.0 with the old encryption
				$id = self::ID;
			} else {
				throw new ModuleDoesNotExistsException('Default encryption module missing');
			}
		}

		return $id;
	}

	/**
	 * create header for encrypted file
	 *
	 * @param array $headerData
	 * @param IEncryptionModule $encryptionModule
	 * @return string
	 * @throws EncryptionHeaderToLargeException if header has to many arguments
	 * @throws EncryptionHeaderKeyExistsException if header key is already in use
	 */
	public function createHeader(array $headerData, IEncryptionModule $encryptionModule) {
		$header = self::HEADER_START . ':' . self::HEADER_ENCRYPTION_MODULE_KEY . ':' . $encryptionModule->getId() . ':';
		foreach ($headerData as $key => $value) {
			if (\in_array($key, $this->ocHeaderKeys)) {
				throw new EncryptionHeaderKeyExistsException($key);
			}
			$header .= $key . ':' . $value . ':';
		}
		$header .= self::HEADER_END;

		if (\strlen($header) > $this->getHeaderSize()) {
			throw new EncryptionHeaderToLargeException();
		}

		$paddedHeader = \str_pad($header, $this->headerSize, self::HEADER_PADDING_CHAR, STR_PAD_RIGHT);

		return $paddedHeader;
	}

	/**
	 * go recursively through a dir and collect all files and sub files.
	 *
	 * @param string $dir relative to the users files folder
	 * @return array with list of files relative to the users files folder
	 */
	public function getAllFiles($dir) {
		$result = [];
		$dirList = [$dir];

		while ($dirList) {
			$dir = \array_pop($dirList);
			$content = $this->rootView->getDirectoryContent($dir);

			foreach ($content as $c) {
				if ($c->getType() === 'dir') {
					$dirList[] = $c->getPath();
				} else {
					$result[] =  $c->getPath();
				}
			}
		}

		return $result;
	}

	/**
	 * check if it is a file uploaded by the user stored in data/user/files
	 * or a metadata file
	 *
	 * @param string $path relative to the data/ folder
	 * @return boolean
	 */
	public function isFile($path) {
		$parts = \explode('/', Filesystem::normalizePath($path), 4);
		if (isset($parts[2]) && $parts[2] === 'files') {
			return true;
		}
		return false;
	}

	/**
	 * return size of encryption header
	 *
	 * @return integer
	 */
	public function getHeaderSize() {
		return $this->headerSize;
	}

	/**
	 * return size of block read by a PHP stream
	 *
	 * @return integer
	 */
	public function getBlockSize() {
		return $this->blockSize;
	}

	/**
	 * get the owner and the path for the file relative to the owners files folder
	 *
	 * @param string $path
	 * @return array
	 * @throws \BadMethodCallException
	 */
	public function getUidAndFilename($path) {
		list($storage, $internalPath) = $this->rootView->resolvePath($path);

		$absMountPoint = $this->rootView->getMountPoint($path);
		$parts = \explode('/', $absMountPoint);
		// strip the first 2 directories (expected to be "/<user>/files/path/to/files")
		// so mountPoint is expected to be "/files/path/to..."
		$mountPoint = \implode('/', \array_slice($parts, 2));
		if ($mountPoint !== '') {
			$originalPath = "/{$mountPoint}/{$internalPath}";
		} else {
			$originalPath = "/{$internalPath}";
		}

		if ($storage->instanceOfStorage('\OCA\Files_Sharing\ISharedStorage')) {
			// TODO: Improve sharedStorage detection.
			// Note that ISharedStorage doesn't enforce any method
			if ($storage->instanceOfStorage('\OCA\Files_Sharing\SharedStorage')) {
				// local sharing
				$share = $storage->getShare();
				$node = $share->getNode();
				$originalPath = "/{$node->getInternalPath()}/{$internalPath}";
			} else {
				// remote sharing
				// FIXME: The original owner is a remote one who won't be present locally.
				// However, keeping the previous behavior, we'll use the target user (obtained
				// from the path) as owner. Fixing this properly requires heavy refactoring since
				// the code requires the keys to have an owner and it isn't possible to return null
				// to mark there is no local owner for the keys, or that the keys aren't
				// locally available
				return [$parts[1], $originalPath];
			}
		}

		$checkingPath = "/{$internalPath}";
		$ownerUid = null;
		while ($ownerUid === null) {
			try {
				$ownerUid = $storage->getOwner($checkingPath);
			} catch (NotFoundException $e) {
				// if the path doesn't exist, try the parent.
				$checkingPath = \dirname($checkingPath);
			}
		}

		return [$ownerUid, $originalPath];
	}

	public function getUserWithAccessToMountPoint($users, $groups) {
		$result = [];
		if (\in_array('all', $users)) {
			$result = \OCP\User::getUsers();
		} else {
			$result = \array_merge($result, $users);
			foreach ($groups as $group) {
				$g = \OC::$server->getGroupManager()->get($group);
				if ($g !== null) {
					$users = \array_values(\array_map(function (IUser $u) {
						return $u->getUID();
					}, $g->getUsers()));
					$result = \array_merge($result, $users);
				}
			}
		}

		return $result;
	}

	/**
	 * check if the file is stored on a system wide mount point
	 * @param string $path relative to /data/user with leading '/'
	 * @param string $uid
	 * @return boolean
	 */
	public function isSystemWideMountPoint($path, $uid) {
		if (\OCP\App::isEnabled("files_external")) {
			$mounts = \OC\Files\External\LegacyUtil::getSystemMountPoints();
			foreach ($mounts as $mount) {
				if (\strpos($path, '/files/' . $mount['mountpoint']) === 0) {
					if ($this->isMountPointApplicableToUser($mount, $uid)) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * check if mount point is applicable to user
	 *
	 * @param array $mount contains $mount['applicable']['users'], $mount['applicable']['groups']
	 * @param string $uid
	 * @return boolean
	 */
	private function isMountPointApplicableToUser($mount, $uid) {
		$acceptedUids = ['all', $uid];
		// check if mount point is applicable for the user
		$intersection = \array_intersect($acceptedUids, $mount['applicable']['users']);
		if (!empty($intersection)) {
			return true;
		}
		// check if mount point is applicable for group where the user is a member
		foreach ($mount['applicable']['groups'] as $gid) {
			if ($this->groupManager->isInGroup($uid, $gid)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * check if it is a path which is excluded by ownCloud from encryption
	 *
	 * @param string $path
	 * @return boolean
	 */
	public function isExcluded($path) {
		$normalizedPath = Filesystem::normalizePath($path);
		$root = \explode('/', $normalizedPath, 4);
		if (\count($root) > 1) {
			// detect alternative key storage root
			$rootDir = $this->getKeyStorageRoot();
			if ($rootDir !== '' &&
				\strpos(
					Filesystem::normalizePath($path),
					Filesystem::normalizePath($rootDir)
				) === 0
			) {
				return true;
			}

			//detect system wide folders
			if (\in_array($root[1], $this->excludedPaths)) {
				return true;
			}

			// detect user specific folders
			if ($this->userManager->userExists($root[1])
				&& \in_array($root[2], $this->excludedPaths)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * check if recovery key is enabled for user
	 *
	 * @param string $uid
	 * @return boolean
	 */
	public function recoveryEnabled($uid) {
		$enabled = $this->config->getUserValue($uid, 'encryption', 'recovery_enabled', '0');

		return ($enabled === '1') ? true : false;
	}

	/**
	 * set new key storage root
	 *
	 * @param string $root new key store root relative to the data folder
	 */
	public function setKeyStorageRoot($root) {
		$this->config->setAppValue('core', 'encryption_key_storage_root', $root);
	}

	/**
	 * get key storage root
	 *
	 * @return string key storage root
	 */
	public function getKeyStorageRoot() {
		return $this->config->getAppValue('core', 'encryption_key_storage_root', '');
	}
}
