<?php
/**
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Carlos Damken <carlos@damken.com>
 * @author Felix Moeller <mail@felixmoeller.de>
 * @author Georg Ehrke <georg@owncloud.com>
 * @author Ilja Neumann <ineumann@owncloud.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
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

/**
 * Versions
 *
 * A class to handle the versioning of files.
 */

namespace OCA\Files_Versions;

use OC\Files\Filesystem;
use OC\Files\ObjectStore\ObjectStoreStorage;
use OC\Files\View;
use OCA\Files_Versions\AppInfo\Application;
use OCA\Files_Versions\Command\Expire;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IVersionedStorage;
use OCP\Lock\ILockingProvider;
use OCP\User;

class Storage {
	public const DEFAULTENABLED=true;
	public const DEFAULTMAXSIZE=50; // unit: percentage; 50% of available disk space/quota
	public const VERSIONS_ROOT = 'files_versions/';

	public const DELETE_TRIGGER_MASTER_REMOVED = 0;
	public const DELETE_TRIGGER_RETENTION_CONSTRAINT = 1;
	public const DELETE_TRIGGER_QUOTA_EXCEEDED = 2;

	// files for which we can remove the versions after the delete operation was successful
	private static $deletedFiles = [];

	private static $sourcePathAndUser = [];

	private static $max_versions_per_interval = [
		//first 10sec, one version every 2sec
		1 => ['intervalEndsAfter' => 10,      'step' => 2],
		//next minute, one version every 10sec
		2 => ['intervalEndsAfter' => 60,      'step' => 10],
		//next hour, one version every minute
		3 => ['intervalEndsAfter' => 3600,    'step' => 60],
		//next 24h, one version every hour
		4 => ['intervalEndsAfter' => 86400,   'step' => 3600],
		//next 30days, one version per day
		5 => ['intervalEndsAfter' => 2592000, 'step' => 86400],
		//until the end one version per week
		6 => ['intervalEndsAfter' => -1,      'step' => 604800],
	];

	/** @var \OCA\Files_Versions\AppInfo\Application */
	private static $application;

	/** @var MetaStorage|null */
	private static $metaData = null;

	/**
	 * Enables the "versioning-metadata"  feature by receiving
	 * the non-static MetaStorage. Mostly a workaround to keep this class from getting
	 * convoluted.
	 *
	 * @see MetaStorage
	 */
	public static function enableMetaData(?MetaStorage $metaStorage) {
		self::$metaData = $metaStorage;
	}

	/**
	 * Can only be enabled with LocalStorage, fails silently.
	 */
	public static function metaEnabled() : bool {
		return self::$metaData instanceof MetaStorage && !self::$metaData->isObjectStoreEnabled();
	}

	/**
	 * get the UID of the owner of the file and the path to the file relative to
	 * owners files folder
	 *
	 * @param string $filename
	 * @return array
	 * @throws \OC\User\NoUserException
	 */
	public static function getUidAndFilename($filename) {
		$uid = Filesystem::getOwner($filename);
		$userManager = \OC::$server->getUserManager();
		// if the user with the UID doesn't exists, e.g. because the UID points
		// to a remote user with a federated cloud ID we use the current logged-in
		// user. We need a valid local user to create the versions
		if (!$userManager->userExists($uid)) {
			$uid = User::getUser();
		}
		Filesystem::initMountPoints($uid);
		if ($uid != User::getUser()) {
			$info = Filesystem::getFileInfo($filename);
			$ownerView = new View('/'.$uid.'/files');
			try {
				$filename = $ownerView->getPath($info['fileid']);
				// make sure that the file name doesn't end with a trailing slash
				// can for example happen single files shared across servers
				$filename = \rtrim($filename, '/');
			} catch (NotFoundException $e) {
				$filename = null;
			}
		}
		return [$uid, $filename];
	}
	/**
	 * Remember the owner and the owner path of the source file
	 *
	 * @param string $source source path
	 */
	public static function setSourcePathAndUser($source) {
		list($uid, $path) = self::getUidAndFilename($source);
		self::$sourcePathAndUser[$source] = ['uid' => $uid, 'path' => $path];
	}

	/**
	 * Gets the owner and the owner path from the source path
	 *
	 * @param string $source source path
	 * @return array with user id and path
	 */
	public static function getSourcePathAndUser($source) {
		if (isset(self::$sourcePathAndUser[$source])) {
			$uid = self::$sourcePathAndUser[$source]['uid'];
			$path = self::$sourcePathAndUser[$source]['path'];
			unset(self::$sourcePathAndUser[$source]);
		} else {
			$uid = $path = false;
		}
		return [$uid, $path];
	}

	/**
	 * store a new version of a file.
	 */
	public static function store($filename) {
		if (\OC::$server->getConfig()->getSystemValue('files_versions', Storage::DEFAULTENABLED)=='true') {
			// if the file gets streamed we need to remove the .part extension
			// to get the right target
			$ext = \pathinfo($filename, PATHINFO_EXTENSION);
			if ($ext === 'part') {
				$filename = \substr($filename, 0, \strlen($filename) - 5);
			}

			// we don't support versioned directories
			if (Filesystem::is_dir($filename)) {
				return false;
			}

			// no versions yet
			if (!Filesystem::file_exists($filename)) {
				return false;
			}

			list($uid, $filename) = self::getUidAndFilename($filename);
			// $filename is expected to start with "/" and be a
			// full path such as "/folder1/folder2/filename"
			$fullFileName = "/$uid/files$filename";
			/** @var \OCP\Files\Storage\IStorage $storage */
			list($storage, $internalPath) = Filesystem::resolvePath($fullFileName);
			if ($storage->instanceOfStorage(IVersionedStorage::class)) {
				/** @var IVersionedStorage $storage */
				if ($storage->saveVersion($internalPath)) {
					return true;
				}
			}

			// fallback implementation below - need to go into class Common
			$files_view = new View('/' . $uid . '/files');
			$users_view = new View('/' . $uid);

			// no use making versions for empty files
			if ($files_view->filesize($filename) === 0) {
				return false;
			}

			// create all parent folders
			self::getFileHelper()->createMissingDirectories($users_view, $filename);
			self::scheduleExpire($uid, $filename);

			$filename = \ltrim($filename, '/');

			// store a new version of a file
			$mtime = $users_view->filemtime('files/' . $filename);
			$sourceFileInfo = $users_view->getFileInfo("files/$filename");

			$versionFileName = "files_versions/$filename.v$mtime";
			if ($users_view->copy("files/$filename", $versionFileName)) {
				// call getFileInfo to enforce a file cache entry for the new version
				$fileInfo = $users_view->getFileInfo($versionFileName);
				// update checksum of the version
				$users_view->putFileInfo($versionFileName, [
					'checksum' => $sourceFileInfo->getChecksum(),
				]);

				if (self::metaEnabled()) {
					// version last current file metadata into noncurrent version
					self::$metaData->copyCurrentToVersion($filename, $fileInfo, $uid);

					// create new current file metadata
					self::$metaData->createForCurrent($filename, $uid, true);
				}
			}
		}
	}

	/**
	 * Called after the file is written to create meta-data for the current file
	 * @param string $filename
	 * @throws \Exception
	 */
	public static function postStore(string $filename) {
		if (self::metaEnabled()) {
			// we don't support versioned directories
			if (Filesystem::is_dir($filename) || !Filesystem::file_exists($filename)) {
				return false;
			}

			list($uid, $currentFileName) = self::getUidAndFilename($filename);
			$versionMetadata = self::$metaData->getCurrentMetadata($currentFileName, $uid);
			if (!$versionMetadata) {
				// make sure metadata for current exists
				self::$metaData->createForCurrent($currentFileName, $uid, true);
			}
		}
	}

	/**
	 * mark file as deleted so that we can remove the versions if the file is gone
	 * @param string $path
	 */
	public static function markDeletedFile($path) {
		list($uid, $filename) = self::getUidAndFilename($path);
		self::$deletedFiles[$path] = [
			'uid' => $uid,
			'filename' => $filename];
	}

	/**
	 * check whether verion can be expired
	 *
	 * @param View $view
	 * @param string $path
	 * @return bool
	 */
	protected static function isPublishedVersion($view, $path) {
		if (self::metaEnabled()) {
			$versionFileInfo = $view->getFileInfo($path);
			if ($versionFileInfo) {
				$versionMetadata = self::$metaData->getVersionMetadata($versionFileInfo);

				// we should not expire major versions (published workflow)
				$versionTag = $versionMetadata['version_tag'] ?? '';
				if (\substr($versionTag, -\strlen('.0')) === '.0') {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Check if the directory in the view is empty or not.
	 * Folders such as "." and ".." will be ignored, so if the
	 * directory only contains those ones, it will be considered
	 * as empty.
	 * The view's root folder won't be considered as empty even if it
	 * doesn't contain any file, so this function will always
	 * return false in that case.
	 * This function will return false if the directory can't be opened
	 * @param View $view
	 * @param string $dir the directory inside the view
	 * @return bool true if empty, false otherwise
	 */
	private static function isFolderEmpty($view, $dir) {
		if ($dir === '' || $dir === '.' || $dir === '/') {
			// root folder won't be considered as empty
			return false;
		}

		$isEmpty = false;
		$dirResource = $view->opendir($dir);

		if ($dirResource === false) {
			return false;
		}

		// we need to check at most 3 entries (need to ignore "." and "..")
		// in order to know if the folder is empty or not
		for ($i = 0; $i < 3; $i++) {
			$entry = \readdir($dirResource);
			if ($entry === false) {
				// if there aren't any more entries, then the folder is empty. We should
				// have exited earlier otherwise
				$isEmpty = true;
				break;
			} elseif ($entry !== '.' && $entry !== '..') {
				// the folder isn't empty.
				$isEmpty = false;
				break;
			}
			// if we've reached here, $entry is either '.' or '..', so we
			// need to check the next entry
		}

		\closedir($dirResource);
		return $isEmpty;
	}

	/**
	 * Remove parent folders until the current folder isn't empty
	 * or we've reached to root folder
	 * @param View $view
	 * @param string $path the path inside the view that has been
	 * renamed or removed. This function will start checking from
	 * the parent directory of that path.
	 */
	private static function cleanupEmptyVersionFolder($view, $path) {
		// check if folder is empty in order to delete it too
		$parentPath = \dirname($path);
		while (!$view->file_exists($parentPath) && ($parentPath !== '.' && $parentPath !== '/' && $parentPath !== '')) {
			$parentPath = \dirname($parentPath);
		}

		$isEmpty = self::isFolderEmpty($view, $parentPath);
		while ($isEmpty && ($parentPath !== '.' && $parentPath !== '/' && $parentPath !== '')) {
			$view->rmdir($parentPath);

			$parentPath = \dirname($parentPath);
			$isEmpty = self::isFolderEmpty($view, $parentPath);
		}
	}

	/**
	 * delete the version from the storage and cache
	 *
	 * @param View $view
	 * @param string $path
	 */
	protected static function deleteVersion($view, $path) {
		$deleted = $view->unlink($path);

		if ($deleted) {
			/**
			 * @var \OC\Files\Storage\Storage $storage
			 * @var string $internalPath
			 */
			list($storage, $internalPath) = $view->resolvePath($path);
			$cache = $storage->getCache($internalPath);
			$cache->remove($internalPath);

			if (self::metaEnabled()) {
				self::$metaData->deleteForVersion($view, $path);
			}
		}
	}

	/**
	 * Delete versions of a file
	 */
	public static function delete($path) {
		$deletedFile = self::$deletedFiles[$path];
		$uid = $deletedFile['uid'];
		$filename = $deletedFile['filename'];

		if (!Filesystem::file_exists($path)) {
			$view = new View('/' . $uid . '/files_versions');

			$versions = self::getVersions($uid, $filename);
			if (!empty($versions)) {
				foreach ($versions as $v) {
					$hookData = [
						'user' => $uid,
						'path' => $path . $v['version'],
						'original_path' => $path,
						'deleted_revision' => $v['version'],
						'trigger' => self::DELETE_TRIGGER_MASTER_REMOVED
					];
					\OC_Hook::emit('\OCP\Versions', 'preDelete', $hookData);
					self::deleteVersion($view, $filename . '.v' . $v['version']);
					\OC_Hook::emit('\OCP\Versions', 'delete', $hookData);
				}
			}
			if (self::metaEnabled()) {
				self::$metaData->deleteForCurrent($view, $filename);
			}

			self::cleanupEmptyVersionFolder($view, $path);
		}
		unset(self::$deletedFiles[$path]);
	}

	/**
	 * Rename or copy versions of a file of the given paths
	 *
	 * @param string $sourcePath source path of the file to move, relative to
	 * the currently logged in user's "files" folder
	 * @param string $targetPath target path of the file to move, relative to
	 * the currently logged in user's "files" folder
	 * @param string $operation can be 'copy' or 'rename'
	 */
	public static function renameOrCopy($sourcePath, $targetPath, $operation) {
		list($sourceOwner, $sourcePath) = self::getSourcePathAndUser($sourcePath);

		// it was a upload of a existing file if no old path exists
		// in this case the pre-hook already called the store method and we can
		// stop here
		if ($sourcePath === false) {
			return true;
		}

		list($targetOwner, $targetPath) = self::getUidAndFilename($targetPath);

		$sourcePath = \ltrim($sourcePath, '/');
		$targetPath = \ltrim($targetPath, '/');

		$rootView = new View('');

		// did we move a directory ?
		if ($rootView->is_dir('/' . $targetOwner . '/files/' . $targetPath)) {
			// does the directory exists for versions too ?
			if ($rootView->is_dir('/' . $sourceOwner . '/files_versions/' . $sourcePath)) {
				// create missing dirs if necessary
				self::getFileHelper()->createMissingDirectories(new View("/$targetOwner"), $targetPath);

				// move the directory containing the versions
				$rootView->$operation(
					'/' . $sourceOwner . '/files_versions/' . $sourcePath,
					'/' . $targetOwner . '/files_versions/' . $targetPath
				);
			}
		} elseif ($versions = Storage::getVersions($sourceOwner, '/' . $sourcePath)) {
			// create missing dirs if necessary
			self::getFileHelper()->createMissingDirectories(new View("/$targetOwner"), $targetPath);

			if (self::metaEnabled()) {
				// NOTE: we need to move current file first as in case of interuption lack of this file could cause issues

				// Also move/copy the current version
				$src = '/files_versions/' . $sourcePath . MetaStorage::CURRENT_FILE_PREFIX . MetaStorage::VERSION_FILE_EXT;
				$dst = '/files_versions/' . $targetPath . MetaStorage::CURRENT_FILE_PREFIX . MetaStorage::VERSION_FILE_EXT;
				self::$metaData->renameOrCopy($operation, $src, $sourceOwner, $dst, $targetOwner);
			}

			foreach ($versions as $v) {
				// move each version one by one to the target directory
				$rootView->$operation(
					'/' . $sourceOwner . '/files_versions/' . $sourcePath.'.v' . $v['version'],
					'/' . $targetOwner . '/files_versions/' . $targetPath.'.v' . $v['version']
				);

				if (self::metaEnabled()) {
					// move each version json file that holds the name of the user that've made an edit
					$src = '/files_versions/' . $sourcePath . '.v' . $v['version'] . MetaStorage::VERSION_FILE_EXT;
					$dst = '/files_versions/' . $targetPath . '.v' . $v['version'] . MetaStorage::VERSION_FILE_EXT;
					self::$metaData->renameOrCopy($operation, $src, $sourceOwner, $dst, $targetOwner);
				}
			}
		}

		// if we moved versions directly for a file, schedule expiration check for that file
		if (!$rootView->is_dir('/' . $targetOwner . '/files/' . $targetPath)) {
			self::scheduleExpire($targetOwner, $targetPath);
		}

		if ($operation === 'rename') {
			// if it's a rename, try to cleanup possible empty folders
			$view = new View("/{$sourceOwner}/files_versions");
			self::cleanupEmptyVersionFolder($view, $sourcePath);
		}
	}

	public static function restoreVersion($uid, $filename, $fileToRestore, $revision) {
		if (\OC::$server->getConfig()->getSystemValue('files_versions', Storage::DEFAULTENABLED) !== true) {
			return false;
		}
		$users_view = new View('/'.$uid);
		if (!$users_view->isUpdatable("/files$filename")) {
			return false;
		}

		$versionCreated = false;

		// first create a new version
		$version = 'files_versions'.$filename.'.v'.$users_view->filemtime('files'.$filename);
		if (!$users_view->file_exists($version)) {
			$users_view->copy('files'.$filename, $version);
			$versionCreated = true;

			// create metadata for version if enabled
			if (self::metaEnabled()) {
				$versionFileInfo = $users_view->getFileInfo($version);
				self::$metaData->copyCurrentToVersion($filename, $versionFileInfo, $uid);
			}
		}

		// Restore encrypted version of the old file for the newly restored file
		// This has to happen manually here since the file is manually copied below
		$oldVersion = $users_view->getFileInfo($fileToRestore)->getEncryptedVersion();
		$oldFileInfo = $users_view->getFileInfo($fileToRestore);
		$newFileInfo = $users_view->getFileInfo("/files$filename");
		$cache = $newFileInfo->getStorage()->getCache();
		$cache->update(
			$newFileInfo->getId(),
			[
				'encrypted' => $oldVersion,
				'encryptedVersion' => $oldVersion,
				'size' => $oldFileInfo->getSize()
			]
		);

		// rollback
		if (self::copyFileContents($users_view, $fileToRestore, 'files' . $filename)) {
			// restore/revert of versions is technically creating new file, thus increment mtime
			$users_view->touch("/files$filename");

			if (self::metaEnabled()) {
				$versionFileInfo = $users_view->getFileInfo('files_versions'.$filename.'.v'.$revision);
				self::$metaData->restore($filename, $versionFileInfo, $uid);
			}

			Storage::scheduleExpire($uid, $filename);

			\OC_Hook::emit('\OCP\Versions', 'rollback', [
				'path' => $filename,
				'user' => $uid,
				'revision' => $revision,
			]);

			return true;
		} elseif ($versionCreated) {
			self::deleteVersion($users_view, $version);
		}
		return true;
	}

	/**
	 * Stream copy file contents from $path1 to $path2
	 *
	 * @param View $view view to use for copying
	 * @param string $path1 source file to copy
	 * @param string $path2 target file
	 *
	 * @return bool true for success, false otherwise
	 */
	private static function copyFileContents($view, $path1, $path2) {
		/** @var \OC\Files\Storage\Storage $storage1 */
		list($storage1, $internalPath1) = $view->resolvePath($path1);
		/** @var \OC\Files\Storage\Storage $storage2 */
		list($storage2, $internalPath2) = $view->resolvePath($path2);

		$view->lockFile($path1, ILockingProvider::LOCK_EXCLUSIVE);
		$view->lockFile($path2, ILockingProvider::LOCK_EXCLUSIVE);

		if ($storage1->instanceOfStorage('\OC\Files\ObjectStore\ObjectStoreStorage') || $storage2->instanceOfStorage('\OC\Files\ObjectStore\ObjectStoreStorage')) {
			$source = $storage1->fopen($internalPath1, 'r');
			$target = $storage2->fopen($internalPath2, 'w');
			list(, $result) = \OC_Helper::streamCopy($source, $target);
			\fclose($source);
			\fclose($target);
		} else {
			$result = $storage2->copyFromStorage($storage1, $internalPath1, $internalPath2);
		}

		$view->unlockFile($path1, ILockingProvider::LOCK_EXCLUSIVE);
		$view->unlockFile($path2, ILockingProvider::LOCK_EXCLUSIVE);

		return ($result !== false);
	}

	/**
	 * get current version of the file
	 * @param string $uid user id from the owner of the file
	 * @param string $filename file to get versioning data for, relative to the user files dir
	 */
	public static function getCurrentVersion($uid, $filename) {
		$version = [];
		if ($filename === null || $filename === '') {
			return $version;
		}

		// add author information if the feature is enabled
		if (self::metaEnabled()) {
			// handle only allowed metadata values
			$versionMetadata = self::$metaData->getCurrentMetadata($filename, $uid);

			$version['edited_by'] = $versionMetadata['edited_by'] ?? '';
			$version['version_tag'] = $versionMetadata['version_tag'] ?? '';
		}

		return $version;
	}

	/**
	 * Publish the current version into major version
	 * that would persist the version long-term
	 */
	public static function publishCurrentVersion($filename) {
		if (self::metaEnabled()) {
			// we don't support versioned directories
			if (Filesystem::is_dir($filename) || !Filesystem::file_exists($filename)) {
				return false;
			}

			list($uid, $currentFileName) = self::getUidAndFilename($filename);

			// overwrite current file metadata with minor=false to create new major version
			self::$metaData->createForCurrent($currentFileName, $uid, false);
		}
	}

	/**
	 * get a list of all available noncurrent versions of a file in descending chronological order
	 * @param string $uid user id from the owner of the file
	 * @param string $filename file to find versions of, relative to the user files dir
	 *
	 * @return array versions newest version first
	 */
	public static function getVersions($uid, $filename) {
		$versions = [];
		if ($filename === null || $filename === '') {
			return $versions;
		}
		// fetch for old versions
		$view = new View('/' . $uid . '/');

		$pathinfo = \pathinfo($filename);
		$versionedFile = $pathinfo['basename'];

		$dir = Filesystem::normalizePath(self::VERSIONS_ROOT . '/' . $pathinfo['dirname']);

		$dirContent = false;
		if ($view->is_dir($dir)) {
			$dirContent = $view->opendir($dir);
		}

		if ($dirContent === false) {
			return $versions;
		}

		// add historical versions
		if (\is_resource($dirContent)) {
			while (($entryName = \readdir($dirContent)) !== false) {
				if (!Filesystem::isIgnoredDir($entryName)) {
					$pathparts = \pathinfo($entryName);
					$filename = $pathparts['filename'];
					if ($filename === $versionedFile) {
						$pathparts = \pathinfo($entryName);
						$timestamp = \substr($pathparts['extension'], 1);
						$filename = $pathparts['filename'];

						// ordering key
						$key = $timestamp . '#' . $filename;

						// add version info
						$versions[$key]['version'] = $timestamp;
						$versions[$key]['humanReadableTimestamp'] = self::getHumanReadableTimestamp($timestamp);
						$versions[$key]['preview'] = '';
						$versions[$key]['path'] = Filesystem::normalizePath($pathinfo['dirname'] . '/' . $filename);
						$versions[$key]['name'] = $versionedFile;
						$versions[$key]['size'] = $view->filesize($dir . '/' . $entryName);
						$versions[$key]['timestamp'] = $timestamp;
						$versions[$key]['etag'] = $view->getETag($dir . '/' . $entryName);
						$versions[$key]['storage_location'] = "$dir/$entryName";
						$versions[$key]['owner'] = $uid;

						// add version meta info
						if (self::metaEnabled()) {
							$versionFileInfo = $view->getFileInfo("$dir/$entryName");
							if ($versionFileInfo) {
								$versionMetadata = self::$metaData->getVersionMetadata($versionFileInfo);

								$versions[$key]['edited_by'] = $versionMetadata['edited_by'] ?? '';
								$versions[$key]['version_tag'] = $versionMetadata['version_tag'] ?? '';
							}
						}
					}
				}
			}
			\closedir($dirContent);
		}

		// sort with newest version first
		\krsort($versions);

		return $versions;
	}

	/**
	 * Expire versions that older than max version retention time
	 * @param string $uid
	 */
	public static function expireOlderThanMaxForUser($uid) {
		$expiration = self::getExpiration();
		$threshold = $expiration->getMaxAgeAsTimestamp();
		$versions = self::getFileHelper()->getAllVersions($uid);
		if (!$threshold || !\array_key_exists('all', $versions)) {
			return;
		}

		$toDelete = [];
		$dirsToCheck = [];
		foreach (\array_reverse($versions['all']) as $key => $version) {
			if (\intval($version['version'])<$threshold) {
				$toDelete[$key] = $version;
				// we're also interested in the directories. It doesn't matter if
				// the versions are overwritten
				$dirsToCheck[\dirname($version['path'])] = $version['path'];
			} else {
				//Versions are sorted by time - nothing mo to iterate.
				break;
			}
		}

		$view = new View('/' . $uid . '/files_versions');
		if (!empty($toDelete)) {
			foreach ($toDelete as $version) {
				if (self::isPublishedVersion($view, $version['path'] . '.v' . $version['version'])) {
					continue;
				}
				$hookData = [
					'user' => $uid,
					'path' => $version['path'] . '.v' . $version['version'],
					'original_path' => $version['path'],
					'deleted_revision' => $version['version'],
					'trigger' => self::DELETE_TRIGGER_RETENTION_CONSTRAINT
				];
				\OC_Hook::emit('\OCP\Versions', 'preDelete', $hookData);
				self::deleteVersion($view, $version['path'] . '.v' . $version['version']);
				\OC_Hook::emit('\OCP\Versions', 'delete', $hookData);
			}

			foreach ($dirsToCheck as $pathInDir) {
				self::cleanupEmptyVersionFolder($view, $pathInDir);
			}
		}
	}

	/**
	 * translate a timestamp into a string like "5 days ago"
	 * @param int $timestamp
	 * @return string for example "5 days ago"
	 */
	private static function getHumanReadableTimestamp($timestamp) {
		$diff = \time() - $timestamp;

		if ($diff < 60) { // first minute
			return  $diff . " seconds ago";
		} elseif ($diff < 3600) { //first hour
			return \round($diff / 60) . " minutes ago";
		} elseif ($diff < 86400) { // first day
			return \round($diff / 3600) . " hours ago";
		} elseif ($diff < 604800) { //first week
			return \round($diff / 86400) . " days ago";
		} elseif ($diff < 2419200) { //first month
			return \round($diff / 604800) . " weeks ago";
		} elseif ($diff < 29030400) { // first year
			return \round($diff / 2419200) . " months ago";
		} else {
			return \round($diff / 29030400) . " years ago";
		}
	}

	/**
	 * get list of files we want to expire
	 * @param array $versions list of versions
	 * @param integer $time
	 * @param bool $quotaExceeded is versions storage limit reached
	 * @return array containing the list of to deleted versions and the size of them
	 */
	protected static function getExpireList($time, $versions, $quotaExceeded = false) {
		$expiration = self::getExpiration();

		if ($expiration->shouldAutoExpire() && \count($versions) > 0) {
			list($toDelete, $size) = self::getAutoExpireList($time, $versions);
		} else {
			$size = 0;
			$toDelete = [];  // versions we want to delete
		}

		foreach ($versions as $key => $version) {
			if ($expiration->isExpired($version['version'], $quotaExceeded) && !isset($toDelete[$key])) {
				$size += $version['size'];
				$toDelete[$key] = $version['path'] . '.v' . $version['version'];
			}
		}

		return [$toDelete, $size];
	}

	/**
	 * get list of files we want to expire
	 *
	 * @param array $versions list of versions
	 * @param integer $time
	 * @return array containing the list of to deleted versions and the size of them
	 */
	protected static function getAutoExpireList($time, $versions) {
		$size = 0;
		$toDelete = [];  // versions we want to delete

		$interval = 1;
		$step = Storage::$max_versions_per_interval[$interval]['step'];
		if (Storage::$max_versions_per_interval[$interval]['intervalEndsAfter'] == -1) {
			$nextInterval = -1;
		} else {
			$nextInterval = $time - Storage::$max_versions_per_interval[$interval]['intervalEndsAfter'];
		}

		$firstVersion = \reset($versions);
		$firstKey = \key($versions);
		$prevTimestamp = $firstVersion['version'];
		$nextVersion = $firstVersion['version'] - $step;
		unset($versions[$firstKey]);

		foreach ($versions as $key => $version) {
			$newInterval = true;
			while ($newInterval) {
				if ($nextInterval == -1 || $prevTimestamp > $nextInterval) {
					if ($version['version'] > $nextVersion) {
						//distance between two version too small, mark to delete
						$toDelete[$key] = $version['path'] . '.v' . $version['version'];
						$size += $version['size'];
						\OCP\Util::writeLog('files_versions', 'Mark to expire '. $version['path'] .' next version should be ' . $nextVersion . " or smaller. (prevTimestamp: " . $prevTimestamp . "; step: " . $step, \OCP\Util::INFO);
					} else {
						$nextVersion = $version['version'] - $step;
						$prevTimestamp = $version['version'];
					}
					$newInterval = false; // version checked so we can move to the next one
				} else { // time to move on to the next interval
					$interval++;
					$step = Storage::$max_versions_per_interval[$interval]['step'];
					$nextVersion = $prevTimestamp - $step;
					if (Storage::$max_versions_per_interval[$interval]['intervalEndsAfter'] == -1) {
						$nextInterval = -1;
					} else {
						$nextInterval = $time - Storage::$max_versions_per_interval[$interval]['intervalEndsAfter'];
					}
					$newInterval = true; // we changed the interval -> check same version with new interval
				}
			}
		}

		return [$toDelete, $size];
	}

	/**
	 * Schedule versions expiration for the given file
	 *
	 * @param string $uid owner of the file
	 * @param string $fileName file/folder for which to schedule expiration
	 */
	private static function scheduleExpire($uid, $fileName) {
		// let the admin disable auto expire
		$expiration = self::getExpiration();
		if ($expiration->isEnabled()) {
			$command = new Expire($uid, $fileName);
			\OC::$server->getCommandBus()->push($command);
		}
	}

	/**
	 * Expire versions which exceed the quota.
	 *
	 * This will setup the filesystem for the given user but will not
	 * tear it down afterwards.
	 *
	 * @param string $filename path to file to expire
	 * @param string $uid user for which to expire the version
	 * @return bool|int|null
	 */
	public static function expire($filename, $uid) {
		$config = \OC::$server->getConfig();
		$expiration = self::getExpiration();

		if ($config->getSystemValue('files_versions', Storage::DEFAULTENABLED)=='true' && $expiration->isEnabled()) {
			// get available disk space for user
			$user = \OC::$server->getUserManager()->get($uid);

			if ($user === null) {
				$msg = "Backends provided no user object for $uid";
				\OC::$server->getLogger()->error($msg, ['app' => __CLASS__]);
				throw new \OC\User\NoUserException($msg);
			}

			\OC_Util::setupFS($uid);

			if (!Filesystem::file_exists($filename)) {
				return false;
			}

			if (empty($filename)) {
				// file maybe renamed or deleted
				return false;
			}
			$versionsFileview = new View('/'.$uid.'/files_versions');

			$softQuota = true;
			$quota = \OC_Util::getUserQuota($user);
			if ($quota === \OCP\Files\FileInfo::SPACE_UNLIMITED) {
				$quota = Filesystem::free_space('/');
				$softQuota = false;
			} else {
				$quota = \OCP\Util::computerFileSize($quota);
			}

			// make sure that we have the current size of the version history
			$versionsSize = self::getFileHelper()->getVersionsSize($uid);

			// calculate available space for version history
			// subtract size of files and current versions size from quota
			if ($quota >= 0) {
				if ($softQuota) {
					$files_view = new View('/' . $uid . '/files');
					$rootInfo = $files_view->getFileInfo('/', false);
					$free = $quota - $rootInfo['size']; // remaining free space for user
					if ($free > 0) {
						$availableSpace = ($free * self::DEFAULTMAXSIZE / 100) - $versionsSize; // how much space can be used for versions
					} else {
						$availableSpace = $free - $versionsSize;
					}
				} else {
					$availableSpace = $quota;
				}
			} else {
				$availableSpace = PHP_INT_MAX;
			}

			$allVersions = Storage::getVersions($uid, $filename);

			$time = \time();
			list($toDelete, $sizeOfDeletedVersions) = self::getExpireList($time, $allVersions, $availableSpace <= 0);

			$availableSpace = $availableSpace + $sizeOfDeletedVersions;
			$versionsSize = $versionsSize - $sizeOfDeletedVersions;

			// if still not enough free space we rearrange the versions from all files
			if ($availableSpace <= 0) {
				$result = self::getFileHelper()->getAllVersions($uid);
				if ($result) {
					$allVersions = $result['all'];

					foreach ($result['by_file'] as $versions) {
						list($toDeleteNew, $size) = self::getExpireList($time, $versions, $availableSpace <= 0);
						$toDelete = \array_merge($toDelete, $toDeleteNew);
						$sizeOfDeletedVersions += $size;
					}
					$availableSpace = $availableSpace + $sizeOfDeletedVersions;
					$versionsSize = $versionsSize - $sizeOfDeletedVersions;
				}
			}

			// we need to check if we have to remove any empty folder based on the
			// deleted versions
			$dirsToCheck = [];
			foreach ($toDelete as $key => $path) {
				if (self::isPublishedVersion($versionsFileview, $path)) {
					continue;
				}
				$versionInfo = self::getFileHelper()->getPathAndRevision($path);
				$hookData = [
					'user' => $uid,
					'path' => $path,
					'original_path' => $versionInfo['path'],
					'deleted_revision' => $versionInfo['revision'],
					'trigger' => self::DELETE_TRIGGER_QUOTA_EXCEEDED
				];
				\OC_Hook::emit('\OCP\Versions', 'preDelete', $hookData);
				self::deleteVersion($versionsFileview, $path);
				\OC_Hook::emit('\OCP\Versions', 'delete', $hookData);
				unset($allVersions[$key]); // update array with the versions we keep
				\OCP\Util::writeLog('files_versions', "Expire: " . $path, \OCP\Util::INFO);
				$dirsToCheck[\dirname($path)] = $path;
			}

			// Check if enough space is available after versions are rearranged.
			// If not we delete the oldest versions until we meet the size limit for versions,
			// but always keep the two latest versions
			$numOfVersions = \count($allVersions) -2 ;
			$i = 0;
			// sort oldest first and make sure that we start at the first element
			\ksort($allVersions);
			\reset($allVersions);
			while ($availableSpace < 0 && $i < $numOfVersions) {
				$version = \current($allVersions);

				if (self::isPublishedVersion($versionsFileview, $version['path'] . '.v' . $version['version'])) {
					continue;
				}
				$hookData = [
					'user' => $uid,
					'path' => $version['path'].'.v'.$version['version'],
					'original_path' => $version['path'],
					'deleted_revision' => $version['version'],
					'trigger' => self::DELETE_TRIGGER_QUOTA_EXCEEDED
				];
				\OC_Hook::emit('\OCP\Versions', 'preDelete', $hookData);
				self::deleteVersion($versionsFileview, $version['path'] . '.v' . $version['version']);
				\OC_Hook::emit('\OCP\Versions', 'delete', $hookData);
				\OCP\Util::writeLog('files_versions', 'running out of space! Delete oldest version: ' . $version['path'].'.v'.$version['version'], \OCP\Util::INFO);
				$dirsToCheck[\dirname($version['path'])] = $version['path'];
				$versionsSize -= $version['size'];
				$availableSpace += $version['size'];
				\next($allVersions);
				$i++;
			}

			foreach ($dirsToCheck as $pathInDir) {
				self::cleanupEmptyVersionFolder($versionsFileview, $pathInDir);
			}

			return $versionsSize; // finally return the new size of the version history
		}

		return false;
	}

	/**
	 * Static workaround
	 * @return FileHelper
	 */
	protected static function getFileHelper() {
		if (self::$application === null) {
			self::$application = new Application();
		}
		return self::$application->getContainer()->query('FileHelper');
	}

	/**
	 * Static workaround
	 * @return Expiration
	 */
	protected static function getExpiration() {
		if (self::$application === null) {
			self::$application = new Application();
		}
		return self::$application->getContainer()->query('Expiration');
	}

	public static function getContentOfVersion($uid, $storage_location) {
		$users_view = new View('/'.$uid);
		return $users_view->fopen($storage_location, 'r');
	}
}
