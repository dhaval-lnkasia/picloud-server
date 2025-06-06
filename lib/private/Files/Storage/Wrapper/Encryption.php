<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Robin Appelman <icewind@owncloud.com>
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

namespace OC\Files\Storage\Wrapper;

use OC\Encryption\Exceptions\ModuleDoesNotExistsException;
use OC\Encryption\Update;
use OC\Encryption\Util;
use OC\Files\Cache\CacheEntry;
use OC\Files\Filesystem;
use OC\Files\Mount\Manager;
use OC\Files\Storage\LocalTempFileTrait;
use OC\Memcache\ArrayCache;
use OCP\Encryption\Exceptions\GenericEncryptionException;
use OCP\Encryption\IFile;
use OCP\Encryption\IManager;
use OCP\Encryption\Keys\IStorage;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Storage;
use OCP\ILogger;
use OCP\Files\Cache\ICacheEntry;
use OC_Util;

class Encryption extends Wrapper {
	use LocalTempFileTrait;

	/** @var string */
	private $mountPoint;

	/** @var \OC\Encryption\Util */
	private $util;

	/** @var \OCP\Encryption\IManager */
	private $encryptionManager;

	/** @var \OCP\ILogger */
	private $logger;

	/** @var string */
	private $uid;

	/** @var array */
	protected $unencryptedSize;

	/** @var \OCP\Encryption\IFile */
	private $fileHelper;

	/** @var IMountPoint */
	private $mount;

	/** @var IStorage */
	private $keyStorage;

	/** @var Update */
	private $update;

	/** @var Manager */
	private $mountManager;

	/** @var array remember for which path we execute the repair step to avoid recursions */
	private $fixUnencryptedSizeOf = [];

	/** @var  ArrayCache */
	private $arrayCache;

	/** @var array which has information of sourcePath during rename operation */
	private $sourcePath;

	private static $disableWriteEncryption = false;

	/**
	 * @param array $parameters
	 * @param IManager $encryptionManager
	 * @param Util $util
	 * @param ILogger $logger
	 * @param IFile $fileHelper
	 * @param string $uid
	 * @param IStorage $keyStorage
	 * @param Update $update
	 * @param Manager $mountManager
	 * @param ArrayCache $arrayCache
	 */
	public function __construct(
		$parameters,
		IManager $encryptionManager = null,
		Util $util = null,
		ILogger $logger = null,
		IFile $fileHelper = null,
		$uid = null,
		IStorage $keyStorage = null,
		Update $update = null,
		Manager $mountManager = null,
		ArrayCache $arrayCache = null
	) {
		$this->mountPoint = $parameters['mountPoint'];
		$this->mount = $parameters['mount'];
		$this->encryptionManager = $encryptionManager;
		$this->util = $util;
		$this->logger = $logger;
		$this->uid = $uid;
		$this->fileHelper = $fileHelper;
		$this->keyStorage = $keyStorage;
		$this->unencryptedSize = [];
		$this->update = $update;
		$this->mountManager = $mountManager;
		$this->arrayCache = $arrayCache;
		parent::__construct($parameters);
	}

	/**
	 * see http://php.net/manual/en/function.filesize.php
	 * The result for filesize when called on a folder is required to be 0
	 *
	 * @param string $path
	 * @return int
	 */
	public function filesize($path) {
		$fullPath = $this->getFullPath($path);

		/** @var CacheEntry $info */
		$info = $this->getCache()->get($path);
		if (isset($this->unencryptedSize[$fullPath])) {
			$size = $this->unencryptedSize[$fullPath];
			// update file cache
			if ($info instanceof ICacheEntry) {
				'@phan-var \OC\Files\Cache\CacheEntry $info';
				$info = $info->getData();
				$info['encrypted'] = $info['encryptedVersion'];
			} else {
				if (!\is_array($info)) {
					$info = [];
				}
				$info['encrypted'] = true;
			}

			$info['size'] = $size;
			$this->getCache()->put($path, $info);

			return $size;
		}

		if (isset($info['fileid']) && $info['encrypted']) {
			return $this->verifyUnencryptedSize($path, $info['size']);
		}

		return $this->storage->filesize($path);
	}

	/**
	 * @param string $path
	 * @return array
	 */
	public function getMetaData($path) {
		$data = $this->storage->getMetaData($path);
		if ($data === null) {
			return null;
		}
		$fullPath = $this->getFullPath($path);
		$info = $this->getCache()->get($path);

		if (isset($this->unencryptedSize[$fullPath])) {
			$data['encrypted'] = true;
			$data['size'] = $this->unencryptedSize[$fullPath];
		} else {
			if (isset($info['fileid']) && $info['encrypted']) {
				$data['size'] = $this->verifyUnencryptedSize($path, $info['size']);
				$data['encrypted'] = true;
			}
		}

		if (isset($info['encryptedVersion']) && $info['encryptedVersion'] > 1) {
			$data['encryptedVersion'] = $info['encryptedVersion'];
		}

		return $data;
	}

	/**
	 * see http://php.net/manual/en/function.file_get_contents.php
	 *
	 * @param string $path
	 * @return string
	 */
	public function file_get_contents($path) {
		if ($this->encryptionManager->isEnabled() !== false) {
			$encryptionModule = $this->getEncryptionModule($path);

			if ($encryptionModule) {
				$handle = $this->fopen($path, "r");
				if (!$handle) {
					return false;
				}
				$data = \stream_get_contents($handle);
				\fclose($handle);
				return $data;
			}
		}
		return $this->storage->file_get_contents($path);
	}

	/**
	 * see http://php.net/manual/en/function.file_put_contents.php
	 *
	 * @param string $path
	 * @param string $data
	 * @return bool
	 */
	public function file_put_contents($path, $data) {
		// file put content will always be translated to a stream write
		$handle = $this->fopen($path, 'w');
		if (\is_resource($handle)) {
			$written = \fwrite($handle, $data);
			\fclose($handle);
			return $written;
		}

		return false;
	}

	/**
	 * see http://php.net/manual/en/function.unlink.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function unlink($path) {
		$fullPath = $this->getFullPath($path);
		if ($this->util->isExcluded($fullPath)) {
			return $this->storage->unlink($path);
		}

		$encryptionModule = ($this->encryptionManager->isEnabled()) ? $this->getEncryptionModule($path) : "";
		if ($encryptionModule) {
			$this->keyStorage->deleteAllFileKeys($this->getFullPath($path));
		}

		return $this->storage->unlink($path);
	}

	/**
	 * see http://php.net/manual/en/function.rename.php
	 *
	 * @param string $path1
	 * @param string $path2
	 * @return bool
	 */
	public function rename($path1, $path2) {
		$renameOk = false;
		$copyKeysOk = true;  // assume keys are copied, in case we deal with versions

		$isVersion = $this->isVersion($path2) || $this->isVersion($path1);
		// if encryption is disabled, consider it's a version in order to skip
		// moving the keys
		$isVersion = $isVersion || !$this->encryptionManager->isEnabled();

		$source = $this->getFullPath($path1);
		$target = $this->getFullPath($path2);
		$keysExcluded = $this->util->isExcluded($source);

		if (!$isVersion && !$keysExcluded) {
			// versions always use the keys from the original file, so we can skip
			// this step for versions
			// Keys can also be excluded if we're moving the keys themselves. It could
			// happen with the encryption:change-key-storage-root command
			if (isset($this->unencryptedSize[$source])) {
				$this->unencryptedSize[$target] = $this->unencryptedSize[$source];
			}

			$copyKeysOk = $this->keyStorage->copyKeys($source, $target);
			if ($copyKeysOk) {
				$module = $this->getEncryptionModule($path2);
				if ($module) {
					$module->update($target, $this->uid, []);
				}
			}
		}

		if ($copyKeysOk) {
			$renameOk = $this->storage->rename($path1, $path2);
			if ($isVersion || $keysExcluded) {
				// no need to deal with the keys
				return $renameOk;
			}

			// need to remove the keys, either the old ones if the rename
			// succeeded, or the new ones if the rename failed
			if ($renameOk) {
				$sourceKeyDeleteOk = $this->keyStorage->deleteAllFileKeys($source);
				if (!$sourceKeyDeleteOk) {
					$this->logger->error("Renaming {$path1} to {$path2} succeeded, but key for file {$target} wasn't removed from its original location");
				}
			} else {
				$this->logger->error("Renaming {$path1} to {$path2} failed");
				$targetKeyDeleteOk = $this->keyStorage->deleteAllFileKeys($target);
				if (!$targetKeyDeleteOk) {
					$this->logger->error("Copied key for file {$source} wasn't removed from its target location");
				}
			}
		} else {
			$this->logger->error("Failed to copy keys while renaming {$path1} to {$path2}");
		}

		return $renameOk && $copyKeysOk;
	}

	/**
	 * see http://php.net/manual/en/function.rmdir.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function rmdir($path) {
		$result = $this->storage->rmdir($path);
		$fullPath = $this->getFullPath($path);
		if ($result &&
			$this->util->isExcluded($fullPath) === false &&
			$this->encryptionManager->isEnabled()
		) {
			$this->keyStorage->deleteAllFileKeys($fullPath);
		}

		return $result;
	}

	/**
	 * check if a file can be read
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isReadable($path) {
		$isReadable = true;

		$metaData = $this->getMetaData($path);
		if (
			!$this->is_dir($path) &&
			isset($metaData['encrypted']) &&
			$metaData['encrypted'] === true
		) {
			$fullPath = $this->getFullPath($path);
			$module = $this->getEncryptionModule($path);
			$isReadable = $module->isReadable($fullPath, $this->uid);
		}

		return $this->storage->isReadable($path) && $isReadable;
	}

	/**
	 * see http://php.net/manual/en/function.copy.php
	 *
	 * @param string $path1
	 * @param string $path2
	 * @return bool
	 */
	public function copy($path1, $path2) {
		$source = $this->getFullPath($path1);

		if ($this->util->isExcluded($source)) {
			return $this->storage->copy($path1, $path2);
		}

		// need to stream copy file by file in case we copy between a encrypted
		// and a unencrypted storage

		if (!($this->isVersion($path2) || $this->isVersion($path1))) {
			// NOTE: this is legacy code-path, and it is not clear why this is needed for standard files,
			// however for versions we should not unlink, because versions should always use the
			// key from the original file. Just create a 1:1 copy and done
			// ref. https://github.com/owncloud/encryption/issues/383
			$this->unlink($path2);
		}
		$result = $this->copyFromStorage($this, $path1, $path2);

		return $result;
	}

	/**
	 * see http://php.net/manual/en/function.fopen.php
	 *
	 * @param string $path
	 * @param string $mode
	 * @return resource|bool
	 * @throws GenericEncryptionException
	 * @throws ModuleDoesNotExistsException
	 */
	public function fopen($path, $mode) {
		// check if the file is stored in the array cache, this means that we
		// copy a file over to the versions folder, in this case we don't want to
		// decrypt it
		if ($this->arrayCache->hasKey('encryption_copy_version_' . $path)) {
			$this->arrayCache->remove('encryption_copy_version_' . $path);
			return $this->storage->fopen($path, $mode);
		}

		$encryptionEnabled = $this->encryptionManager->isEnabled();
		$shouldEncrypt = false;
		$encryptionModule = null;
		$header = $this->getHeader($path);
		$signed = (isset($header['signed']) && $header['signed'] === 'true') ? true : false;
		$fullPath = $this->getFullPath($path);
		$encryptionModuleId = $this->util->getEncryptionModuleId($header);

		if ($this->util->isExcluded($fullPath) === false) {
			$size = $unencryptedSize = 0;
			$realFile = OC_Util::stripPartialFileExtension($path);
			$targetExists = $this->file_exists($realFile) || $this->file_exists($path);
			$targetIsEncrypted = false;
			if ($targetExists) {
				// in case the file exists we require the explicit module as
				// specified in the file header - otherwise we need to fail hard to
				// prevent data loss on client side
				if (!empty($encryptionModuleId)) {
					$data = $this->storage->getCache()->get($path);
					if (isset($data['encrypted']) && $data['encrypted'] === true) {
						$targetIsEncrypted = true;
						$encryptionModule = $this->encryptionManager->getEncryptionModule($encryptionModuleId);
					}
				}

				if ($this->file_exists($path)) {
					$size = $this->storage->filesize($path);
					$unencryptedSize = $this->filesize($path);
				} else {
					$size = $unencryptedSize = 0;
				}
			}

			try {
				if (
					$mode === 'w'
					|| $mode === 'w+'
					|| $mode === 'wb'
					|| $mode === 'wb+'
				) {
					// don't overwrite encrypted files if encryption is not enabled
					if ($targetIsEncrypted && $encryptionEnabled === false) {
						throw new GenericEncryptionException('Tried to access encrypted file but encryption is not enabled');
					}
					if ($encryptionEnabled) {
						// if $encryptionModuleId is empty, the default module will be used
						$encryptionModule = $this->encryptionManager->getEncryptionModule($encryptionModuleId);
						$shouldEncrypt = $encryptionModule->shouldEncrypt($fullPath);
						$signed = true;
					}
				} else {
					$info = $this->getCache()->get($path);
					// only get encryption module if we found one in the header
					// or if file should be encrypted according to the file cache
					if (!empty($encryptionModuleId)) {
						$encryptionModule = $this->encryptionManager->getEncryptionModule($encryptionModuleId);
						$shouldEncrypt = true;
					} elseif (empty($encryptionModuleId) && $info['encrypted'] === true) {
						// we come from a old installation. No header and/or no module defined
						// but the file is encrypted. In this case we need to use the
						// OC_DEFAULT_MODULE to read the file
						$encryptionModule = $this->encryptionManager->getEncryptionModule('OC_DEFAULT_MODULE');
						$shouldEncrypt = true;
						$targetIsEncrypted = true;
					}
				}
			} catch (ModuleDoesNotExistsException $e) {
				$this->logger->warning('Encryption module "' . $encryptionModuleId .
					'" not found, file will be stored unencrypted (' . $e->getMessage() . ')');
			}

			// encryption disabled on write of new file and write to existing unencrypted file -> don't encrypt
			if (!$encryptionEnabled || !$this->mount->getOption('encrypt', true)) {
				if (!$targetExists || !$targetIsEncrypted) {
					$shouldEncrypt = false;
				}
			}

			if ($shouldEncrypt === true && $encryptionModule !== null) {
				/**
				 * The check of $disableWriteEncryption, required to get the file in the decrypted state.
				 * It will help us get the normal file handler. And hence we can re-encrypt
				 * the file when necessary, later. The true/false of $getDecryptedFile decides whether
				 * to keep the file decrypted or not. The intention is to get the data decrypt
				 * for write mode.
				 */
				if (self::$disableWriteEncryption && ($mode !== 'r')) {
					return $this->getWrapperStorage()->fopen($path, $mode);
				}

				$headerSize = $this->getHeaderSize($path);
				$source = $this->storage->fopen($path, $mode);
				if (!\is_resource($source)) {
					return false;
				}

				if (isset($this->sourcePath[$path])) {
					$sourceFileOfRename = $this->sourcePath[$path];
				} else {
					$sourceFileOfRename = null;
				}
				$handle = \OC\Files\Stream\Encryption::wrap(
					$source,
					$path,
					$fullPath,
					$header,
					$this->uid,
					$encryptionModule,
					$this->storage,
					$this,
					$this->util,
					$this->fileHelper,
					$mode,
					$size,
					$unencryptedSize,
					$headerSize,
					$signed,
					$sourceFileOfRename
				);
				unset($this->sourcePath[$path]);

				return $handle;
			}
		}

		return $this->storage->fopen($path, $mode);
	}

	/**
	 * perform some plausibility checks if the the unencrypted size is correct.
	 * If not, we calculate the correct unencrypted size and return it
	 *
	 * @param string $path internal path relative to the storage root
	 * @param int $unencryptedSize size of the unencrypted file
	 *
	 * @return int unencrypted size
	 */
	protected function verifyUnencryptedSize($path, $unencryptedSize) {
		$size = $this->storage->filesize($path);
		$result = $unencryptedSize;

		if ($unencryptedSize < 0 ||
			($size > 0 && $unencryptedSize === $size)
		) {
			// check if we already calculate the unencrypted size for the
			// given path to avoid recursions
			if (isset($this->fixUnencryptedSizeOf[$this->getFullPath($path)]) === false) {
				$this->fixUnencryptedSizeOf[$this->getFullPath($path)] = true;
				try {
					$result = $this->fixUnencryptedSize($path, $size, $unencryptedSize);
				} catch (\Exception $e) {
					$this->logger->error('Couldn\'t re-calculate unencrypted size for '. $path);
					$this->logger->logException($e);
				}
				unset($this->fixUnencryptedSizeOf[$this->getFullPath($path)]);
			}
		}

		return $result;
	}

	/**
	 * calculate the unencrypted size
	 *
	 * @param string $path internal path relative to the storage root
	 * @param int $size size of the physical file
	 * @param int $unencryptedSize size of the unencrypted file
	 *
	 * @return int calculated unencrypted size
	 */
	protected function fixUnencryptedSize($path, $size, $unencryptedSize) {
		$headerSize = $this->getHeaderSize($path);
		$header = $this->getHeader($path);
		$encryptionModule = $this->getEncryptionModule($path);

		$stream = $this->storage->fopen($path, 'r');

		// if we couldn't open the file we return the old unencrypted size
		if (!\is_resource($stream)) {
			$this->logger->error('Could not open ' . $path . '. Recalculation of unencrypted size aborted.');
			return $unencryptedSize;
		}

		$newUnencryptedSize = 0;
		$size -= $headerSize;
		$blockSize = $this->util->getBlockSize();

		// if a header exists we skip it
		if ($headerSize > 0) {
			\fread($stream, $headerSize);
		}

		// fast path, else the calculation for $lastChunkNr is bogus
		if ($size === 0) {
			return 0;
		}

		// initialize encryption module to get the correct unencrypted block-size
		$encryptionModule->begin($this->getFullPath($path), $this->uid, 'r', $header, [], null);
		$signed = (isset($header['signed']) && $header['signed'] === 'true') ? true : false;
		$unencryptedBlockSize = $encryptionModule->getUnencryptedBlockSize($signed);

		// calculate last chunk nr
		// next highest is end of chunks, one subtracted is last one
		// we have to read the last chunk, we can't just calculate it (because of padding etc)

		$lastChunkNr = \ceil($size/ $blockSize)-1;
		// calculate last chunk position
		$lastChunkPos = ($lastChunkNr * $blockSize);
		// try to fseek to the last chunk, if it fails we have to read the whole file
		if (@\fseek($stream, $lastChunkPos, SEEK_CUR) === 0) {
			$newUnencryptedSize += $lastChunkNr * $unencryptedBlockSize;
		}

		$lastChunkContentEncrypted='';
		$count = $blockSize;

		while ($count > 0) {
			$data=\fread($stream, $blockSize);
			$count=\strlen($data);
			$lastChunkContentEncrypted .= $data;
			if (\strlen($lastChunkContentEncrypted) > $blockSize) {
				$newUnencryptedSize += $unencryptedBlockSize;
				$lastChunkContentEncrypted=\substr($lastChunkContentEncrypted, $blockSize);
			}
		}

		\fclose($stream);

		// we have to decrypt the last chunk to get it actual size
		$decryptedLastChunk = $encryptionModule->decrypt($lastChunkContentEncrypted, $lastChunkNr . 'end');
		$decryptedLastChunk .= $encryptionModule->end($this->getFullPath($path), $lastChunkNr . 'end');

		// calc the real file size with the size of the last chunk
		$newUnencryptedSize += \strlen($decryptedLastChunk);

		$this->updateUnencryptedSize($this->getFullPath($path), $newUnencryptedSize);

		// write to cache if applicable
		$cache = $this->storage->getCache();
		if ($cache) {
			$entry = $cache->get($path);
			$cache->update($entry['fileid'], ['size' => $newUnencryptedSize]);
		}

		return $newUnencryptedSize;
	}

	/**
	 * @param Storage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @param bool $preserveMtime
	 * @return bool
	 */
	public function moveFromStorage(Storage $sourceStorage, $sourceInternalPath, $targetInternalPath, $preserveMtime = true) {
		if ($sourceStorage === $this) {
			return $this->rename($sourceInternalPath, $targetInternalPath);
		}

		// TODO clean this up once the underlying moveFromStorage in OC\Files\Storage\Wrapper\Common is fixed:
		// - call $this->storage->moveFromStorage() instead of $this->copyBetweenStorage
		// - copy the file cache update from  $this->copyBetweenStorage to this method
		// - copy the copyKeys() call from  $this->copyBetweenStorage to this method
		// - remove $this->copyBetweenStorage

		if (!$sourceStorage->isDeletable($sourceInternalPath)) {
			return false;
		}

		if (!$this->encryptionManager->isEnabled()) {
			return $this->storage->moveFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath, $preserveMtime);
		}
		$result = $this->copyBetweenStorage($sourceStorage, $sourceInternalPath, $targetInternalPath, $preserveMtime, true);
		if ($result) {
			if ($sourceStorage->is_dir($sourceInternalPath)) {
				$result &= $sourceStorage->rmdir($sourceInternalPath);
			} else {
				$result &= $sourceStorage->unlink($sourceInternalPath);
			}
		}
		return $result;
	}

	/**
	 * Set the flag to true, so that the file would be
	 * in the decrypted state.
	 *
	 * @param $isDisabled bool
	 */
	public static function setDisableWriteEncryption($isDisabled) {
		self::$disableWriteEncryption = $isDisabled;
	}

	/**
	 * @param Storage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @param bool $preserveMtime
	 * @param bool $isRename
	 * @return bool
	 */
	public function copyFromStorage(Storage $sourceStorage, $sourceInternalPath, $targetInternalPath, $preserveMtime = false, $isRename = false) {
		// TODO clean this up once the underlying moveFromStorage in OC\Files\Storage\Wrapper\Common is fixed:
		// - call $this->storage->copyFromStorage() instead of $this->copyBetweenStorage
		// - copy the file cache update from  $this->copyBetweenStorage to this method
		// - copy the copyKeys() call from  $this->copyBetweenStorage to this method
		// - remove $this->copyBetweenStorage

		return $this->copyBetweenStorage($sourceStorage, $sourceInternalPath, $targetInternalPath, $preserveMtime, $isRename);
	}

	/**
	 * Update the encrypted cache version in the database
	 *
	 * @param Storage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @param bool $isRename
	 */
	private function updateEncryptedVersion(Storage $sourceStorage, $sourceInternalPath, $targetInternalPath, $isRename) {
		$isEncrypted = $this->encryptionManager->isEnabled() && $this->mount->getOption('encrypt', true) ? 1 : 0;
		$cacheInformation = [
			'encrypted' => (bool)$isEncrypted,
		];
		if ($isEncrypted === 1) {
			$encryptedVersion = null;  // initialize as null for backwards compatibility
			$sourceInfo = $sourceStorage->getCache()->get($sourceInternalPath);
			if ($sourceInfo !== false) {
				$encryptedVersion = $sourceInfo['encryptedVersion'];
			}

			// In case of a move operation from an unencrypted to an encrypted
			// storage the old encrypted version would stay with "0" while the
			// correct value would be "1". Thus we manually set the value to "1"
			// for those cases.
			// See also https://github.com/owncloud/core/issues/23078
			if ($encryptedVersion === 0) {
				$encryptedVersion = 1;
			}

			$cacheInformation['encryptedVersion'] = $encryptedVersion;
		} else {
			$cacheInformation['encryptedVersion'] = 0;
		}

		// in case of a rename we need to manipulate the source cache because
		// this information will be kept for the new target
		if ($isRename && !($this->isVersion($sourceInternalPath) || $this->isVersion($targetInternalPath))) {
			/*
			 * Rename is a process of creating a new file. Here we try to use the
			 * incremented version of source file, for the destination file.
			 * Note that if we're renaming or moving a version, the version is copied
			 * without any change, so the encrypted version mustn't change in order to avoid
			 * problems with the signature
			 */
			$encryptedVersion = $sourceStorage->getCache()->get($sourceInternalPath)['encryptedVersion'];
			if ($this->encryptionManager->isEnabled() && $isEncrypted) {
				$cacheInformation['encryptedVersion'] = $encryptedVersion + 1;
			}
			$sourceStorage->getCache()->put($sourceInternalPath, $cacheInformation);
		} else {
			$this->getCache()->put($targetInternalPath, $cacheInformation);
		}
	}

	/**
	 * copy file between two storages
	 *
	 * @param Storage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @param bool $preserveMtime
	 * @param bool $isRename
	 * @return bool
	 * @throws \Exception
	 */
	private function copyBetweenStorage(Storage $sourceStorage, $sourceInternalPath, $targetInternalPath, $preserveMtime, $isRename) {
		// for versions we have nothing to do, because versions should always use the
		// key from the original file. Just create a 1:1 copy and done
		if ($this->isVersion($targetInternalPath) ||
			$this->isVersion($sourceInternalPath)) {
			// remember that we try to create a version so that we can detect it during
			// fopen($sourceInternalPath) and by-pass the encryption in order to
			// create a 1:1 copy of the file
			$this->arrayCache->set('encryption_copy_version_' . $sourceInternalPath, true);
			$result = $this->storage->copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
			$this->arrayCache->remove('encryption_copy_version_' . $sourceInternalPath);
			if ($result) {
				$info = $this->getCache('', $sourceStorage)->get($sourceInternalPath);
				// make sure that we update the unencrypted size for the version
				if (isset($info['encrypted']) && $info['encrypted'] === true) {
					$this->updateUnencryptedSize(
						$this->getFullPath($targetInternalPath),
						$info['size']
					);
				}
				$this->updateEncryptedVersion($sourceStorage, $sourceInternalPath, $targetInternalPath, $isRename);
			}
			return $result;
		}

		// first copy the keys that we reuse the existing file key on the target location
		// and don't create a new one which would break versions for example.
		$mount = $this->mountManager->findByStorageId($sourceStorage->getId());
		if (\count($mount) > 0) {
			// multiple mount points still refer to the same storage, so pick the first
			$mountPoint = $mount[0]->getMountPoint();
			$source = $mountPoint . '/' . $sourceInternalPath;
			$target = $this->getFullPath($targetInternalPath);
			$this->copyKeys($source, $target);
		} else {
			$this->logger->error(\sprintf('Could not find mount point, can\'t keep encryption keys. MountCount: %s', \count($mount)));
		}

		if ($sourceStorage->is_dir($sourceInternalPath)) {
			$dh = $sourceStorage->opendir($sourceInternalPath);
			$result = $this->mkdir($targetInternalPath);
			if (\is_resource($dh)) {
				while ($result and ($file = \readdir($dh)) !== false) {
					if (!Filesystem::isIgnoredDir($file)) {
						$result &= $this->copyFromStorage($sourceStorage, $sourceInternalPath . '/' . $file, $targetInternalPath . '/' . $file, false, $isRename);
					}
				}
			}
		} else {
			try {
				$source = $sourceStorage->fopen($sourceInternalPath, 'r');
				if ($isRename && (\count($mount) > 0)) {
					$sourceStorageMountPoint = $mount[0]->getMountPoint();
					$this->sourcePath[$targetInternalPath] = $sourceStorageMountPoint . '/' . $sourceInternalPath;
				} else {
					unset($this->sourcePath[$targetInternalPath]);
				}
				$target = $this->fopen($targetInternalPath, 'w');
				list(, $result) = \OC_Helper::streamCopy($source, $target);
				\fclose($source);
				\fclose($target);
			} catch (\Exception $e) {
				Encryption::setDisableWriteEncryption(false);
				\fclose($source);
				\fclose($target);
				throw $e;
			}
			if ($result) {
				if ($preserveMtime) {
					$this->touch($targetInternalPath, $sourceStorage->filemtime($sourceInternalPath));
				}
				if ($sourceStorage->getOwner($sourceInternalPath) === $this->uid
					|| \substr($targetInternalPath, 0, \strlen('files_trashbin/')) !== 'files_trashbin/') {
					// 1. user1 moves file from home storage to admins's shared folder
					// 2. user2 grabs the file from admin's shared folder to his own home storage
					// Admin has a broken copy in his trashbin, which is caused by a wrong encrypted
					// version stored in the DB. We simply won't update the encrypted version in this
					// particular case.
					$this->updateEncryptedVersion($sourceStorage, $sourceInternalPath, $targetInternalPath, $isRename);
				}
			} else {
				// delete partially written target file
				$this->unlink($targetInternalPath);
				// delete cache entry that was created by fopen
				$this->getCache()->remove($targetInternalPath);
			}
		}
		return (bool)$result;
	}

	/**
	 * get the path to a local version of the file.
	 * The local version of the file can be temporary and doesn't have to be persistent across requests
	 *
	 * @param string $path
	 * @return string
	 */
	public function getLocalFile($path) {
		if ($this->encryptionManager->isEnabled()) {
			$cachedFile = $this->getCachedFile($path);
			if (\is_string($cachedFile)) {
				return $cachedFile;
			}
		}
		return $this->storage->getLocalFile($path);
	}

	/**
	 * Returns the wrapped storage's value for isLocal()
	 *
	 * @return bool wrapped storage's isLocal() value
	 */
	public function isLocal() {
		if ($this->encryptionManager->isEnabled()) {
			return false;
		}
		return $this->storage->isLocal();
	}

	/**
	 * see http://php.net/manual/en/function.stat.php
	 * only the following keys are required in the result: size and mtime
	 *
	 * @param string $path
	 * @return array
	 */
	public function stat($path) {
		$stat = $this->storage->stat($path);
		$fileSize = $this->filesize($path);
		$stat['size'] = $fileSize;
		$stat[7] = $fileSize;
		return $stat;
	}

	/**
	 * see http://php.net/manual/en/function.hash.php
	 *
	 * @param string $type
	 * @param string $path
	 * @param bool $raw
	 * @return string
	 */
	public function hash($type, $path, $raw = false) {
		$fh = $this->fopen($path, 'rb');
		$ctx = \hash_init($type);
		\hash_update_stream($ctx, $fh);
		\fclose($fh);
		return \hash_final($ctx, $raw);
	}

	/**
	 * return full path, including mount point
	 *
	 * @param string $path relative to mount point
	 * @return string full path including mount point
	 */
	protected function getFullPath($path) {
		return Filesystem::normalizePath($this->mountPoint . '/' . $path);
	}

	/**
	 * read first block of encrypted file, typically this will contain the
	 * encryption header
	 *
	 * @param string|resource $path
	 * @return string
	 */
	protected function readFirstBlock($path) {
		if (\is_resource($path)) {
			$firstBlock = \fread($path, $this->util->getHeaderSize());
			\rewind($path);
			return $firstBlock;
		}
		$firstBlock = '';
		if ($this->storage->file_exists($path)) {
			$handle = $this->storage->fopen($path, 'r');
			$firstBlock = \fread($handle, $this->util->getHeaderSize());
			\fclose($handle);
		}
		return $firstBlock;
	}

	/**
	 * return header size of given file
	 *
	 * @param string|resource $path
	 * @return int
	 */
	protected function getHeaderSize($path) {
		$headerSize = 0;
		if (!\is_resource($path)) {
			$realFile = OC_Util::stripPartialFileExtension($path);
			if ($this->storage->file_exists($realFile)) {
				$path = $realFile;
			}
		}
		$firstBlock = $this->readFirstBlock($path);

		if (\substr($firstBlock, 0, \strlen(Util::HEADER_START)) === Util::HEADER_START) {
			$headerSize = $this->util->getHeaderSize();
		}

		return $headerSize;
	}

	/**
	 * parse raw header to array
	 *
	 * @param string $rawHeader
	 * @return array
	 */
	protected function parseRawHeader($rawHeader) {
		$result = [];
		if (\substr($rawHeader, 0, \strlen(Util::HEADER_START)) === Util::HEADER_START) {
			$header = $rawHeader;
			$endAt = \strpos($header, Util::HEADER_END);
			if ($endAt !== false) {
				$header = \substr($header, 0, $endAt + \strlen(Util::HEADER_END));

				// +1 to not start with an ':' which would result in empty element at the beginning
				$exploded = \explode(':', \substr($header, \strlen(Util::HEADER_START)+1));

				$element = \array_shift($exploded);
				while ($element !== Util::HEADER_END) {
					$result[$element] = \array_shift($exploded);
					$element = \array_shift($exploded);
				}
			}
		}

		return $result;
	}

	/**
	 * read header from file
	 *
	 * @param string|resource $path
	 * @return array
	 */
	protected function getHeader($path) {
		if (\is_resource($path)) {
			$exists = false;
		} else {
			$realFile = OC_Util::stripPartialFileExtension($path);
			$exists = $this->storage->file_exists($realFile);
			if ($exists) {
				$path = $realFile;
			}
		}

		$firstBlock = $this->readFirstBlock($path);
		$result = $this->parseRawHeader($firstBlock);

		// if the header doesn't contain a encryption module we check if it is a
		// legacy file. If true, we add the default encryption module
		if (!isset($result[Util::HEADER_ENCRYPTION_MODULE_KEY])) {
			if (!empty($result)) {
				$result[Util::HEADER_ENCRYPTION_MODULE_KEY] = 'OC_DEFAULT_MODULE';
			} elseif ($exists) {
				// if the header was empty we have to check first if it is a encrypted file at all
				// We would do query to filecache only if we know that entry in filecache exists
				$info = $this->getCache()->get($path);
				if (isset($info['encrypted']) && $info['encrypted'] === true) {
					$result[Util::HEADER_ENCRYPTION_MODULE_KEY] = 'OC_DEFAULT_MODULE';
				}
			}
		}

		return $result;
	}

	/**
	 * read encryption module needed to read/write the file located at $path
	 *
	 * @param string $path
	 * @return null|\OCP\Encryption\IEncryptionModule
	 * @throws ModuleDoesNotExistsException
	 * @throws \Exception
	 */
	protected function getEncryptionModule($path) {
		$encryptionModule = null;
		$header = $this->getHeader($path);
		$encryptionModuleId = $this->util->getEncryptionModuleId($header);
		if (!empty($encryptionModuleId)) {
			try {
				$encryptionModule = $this->encryptionManager->getEncryptionModule($encryptionModuleId);
			} catch (ModuleDoesNotExistsException $e) {
				$this->logger->critical('Encryption module defined in "' . $path . '" not loaded!');
				throw $e;
			}
		}
		return $encryptionModule;
	}

	/**
	 * @param string $path
	 * @param int $unencryptedSize
	 */
	public function updateUnencryptedSize($path, $unencryptedSize) {
		$this->unencryptedSize[$path] = $unencryptedSize;
	}

	/**
	 * copy keys to new location
	 *
	 * @param string $source path relative to data/
	 * @param string $target path relative to data/
	 * @return bool
	 */
	protected function copyKeys($source, $target) {
		if (!$this->util->isExcluded($source)) {
			return $this->keyStorage->copyKeys($source, $target);
		}

		return false;
	}

	/**
	 *
	 * delete file keys of the file
	 *
	 * @param $path path of the file key to delete
	 * @return bool
	 */
	protected function deleteAllFileKeys($path) {
		$fullPath = $this->getFullPath($path);
		return $this->keyStorage->deleteAllFileKeys($fullPath);
	}

	/**
	 * check if path points to a files version
	 *
	 * @param $path
	 * @return bool
	 */
	protected function isVersion($path) {
		$normalized = Filesystem::normalizePath($path);
		return \substr($normalized, 0, \strlen('/files_versions/')) === '/files_versions/';
	}
}
