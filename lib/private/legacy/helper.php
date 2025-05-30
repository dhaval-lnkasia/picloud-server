<?php
/**
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Fabian Henze <flyser42@gmx.de>
 * @author Felix Moeller <mail@felixmoeller.de>
 * @author Georg Ehrke <georg@owncloud.com>
 * @author Jakob Sack <mail@jakobsack.de>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Martin Mattel <martin.mattel@diemattels.at>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Olivier Paroz <github@oparoz.com>
 * @author Pellaeon Lin <nfsmwlin@gmail.com>
 * @author RealRancor <fisch.666@gmx.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 * @author Simon Könnecke <simonkoennecke@gmail.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
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
use Symfony\Component\Process\ExecutableFinder;

/**
 * Collection of useful functions
 */
class OC_Helper {
	private static $templateManager;

	/**
	 * Creates an absolute url for public use
	 * @param string $service id
	 * @param bool $add_slash
	 * @return string the url
	 *
	 * Returns a absolute url to the given service.
	 */
	public static function linkToPublic($service, $add_slash = false) {
		if ($service === 'files') {
			$url = OC::$server->getURLGenerator()->getAbsoluteURL('/s');
		} else {
			$url = OC::$server->getURLGenerator()->getAbsoluteURL(OC::$server->getURLGenerator()->linkTo('', 'public.php').'?service='.$service);
		}
		return $url . (($add_slash && $service[\strlen($service) - 1] != '/') ? '/' : '');
	}

	/**
	 * Make a human file size
	 * @param int $bytes file size in bytes
	 * @return string a human readable file size
	 *
	 * Makes 2048 to 2 kB.
	 */
	public static function humanFileSize($bytes) {
		if ($bytes < 0) {
			return "?";
		}
		if ($bytes < 1024) {
			return "$bytes B";
		}
		$bytes = \round($bytes / 1024, 0);
		if ($bytes < 1024) {
			return "$bytes KB";
		}
		$bytes = \round($bytes / 1024, 1);
		if ($bytes < 1024) {
			return "$bytes MB";
		}
		$bytes = \round($bytes / 1024, 1);
		if ($bytes < 1024) {
			return "$bytes GB";
		}
		$bytes = \round($bytes / 1024, 1);
		if ($bytes < 1024) {
			return "$bytes TB";
		}

		$bytes = \round($bytes / 1024, 1);
		return "$bytes PB";
	}

	/**
	 * Make a php file size
	 * @param int $bytes file size in bytes
	 * @return string a php parseable file size
	 *
	 * Makes 2048 to 2k and 2^41 to 2048G
	 */
	public static function phpFileSize($bytes) {
		if ($bytes < 0) {
			return "?";
		}
		if ($bytes < 1024) {
			return $bytes . "B";
		}
		$bytes = \round($bytes / 1024, 1);
		if ($bytes < 1024) {
			return $bytes . "K";
		}
		$bytes = \round($bytes / 1024, 1);
		if ($bytes < 1024) {
			return $bytes . "M";
		}
		$bytes = \round($bytes / 1024, 1);
		return $bytes . "G";
	}

	/**
	 * Make a computer file size
	 * @param string $str file size in human readable format
	 * @return float|false a file size in bytes
	 *
	 * Makes 2kB to 2048.
	 *
	 * Inspired by: http://www.php.net/manual/en/function.filesize.php#92418
	 */
	public static function computerFileSize($str) {
		$str = \strtolower($str);
		if (\is_numeric($str)) {
			return \floatval($str);
		}

		$bytes_array = [
			'b' => 1,
			'k' => 1024,
			'kb' => 1024,
			'mb' => 1024 * 1024,
			'm' => 1024 * 1024,
			'gb' => 1024 * 1024 * 1024,
			'g' => 1024 * 1024 * 1024,
			'tb' => 1024 * 1024 * 1024 * 1024,
			't' => 1024 * 1024 * 1024 * 1024,
			'pb' => 1024 * 1024 * 1024 * 1024 * 1024,
			'p' => 1024 * 1024 * 1024 * 1024 * 1024,
		];

		$bytes = \floatval($str);

		if (\preg_match('#([kmgtp]?b?)$#si', $str, $matches) && !empty($bytes_array[$matches[1]])) {
			$bytes *= $bytes_array[$matches[1]];
		} else {
			return false;
		}

		$bytes = \round($bytes);

		return $bytes;
	}

	/**
	 * Recursive copying of folders
	 * @param string $src source folder
	 * @param string $dest target folder
	 *
	 */
	public static function copyr($src, $dest) {
		if (\is_dir($src)) {
			if (!\is_dir($dest)) {
				\mkdir($dest);
			}
			$files = \scandir($src);
			foreach ($files as $file) {
				if ($file != "." && $file != "..") {
					self::copyr("$src/$file", "$dest/$file");
				}
			}
		} elseif (\file_exists($src) && !\OC\Files\Filesystem::isForbiddenFileOrDir($src)) {
			\copy($src, $dest);
		}
	}

	/**
	 * Recursive deletion of folders
	 * @param string $dir path to the folder
	 * @param bool $deleteSelf if set to false only the content of the folder will be deleted
	 * @return bool
	 */
	public static function rmdirr($dir, $deleteSelf = true) {
		if (\is_dir($dir)) {
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ($files as $fileInfo) {
				/** @var SplFileInfo $fileInfo */
				if ($fileInfo->isLink()) {
					\unlink($fileInfo->getPathname());
				} elseif ($fileInfo->isDir()) {
					\rmdir($fileInfo->getRealPath());
				} else {
					\unlink($fileInfo->getRealPath());
				}
			}
			if ($deleteSelf) {
				\rmdir($dir);
			}
		} elseif (\file_exists($dir)) {
			if ($deleteSelf) {
				\unlink($dir);
			}
		}
		if (!$deleteSelf) {
			return true;
		}

		return !\file_exists($dir);
	}

	/**
	 * @return \OC\Files\Type\TemplateManager
	 */
	public static function getFileTemplateManager() {
		if (!self::$templateManager) {
			self::$templateManager = new \OC\Files\Type\TemplateManager();
		}
		return self::$templateManager;
	}

	/**
	 * detect if a given program is found in the search PATH
	 *
	 * @param string $name
	 * @param bool $path
	 * @internal param string $program name
	 * @internal param string $optional search path, defaults to $PATH
	 * @return bool    true if executable program found in path
	 */
	public static function canExecute($name, $path = false) {
		// path defaults to PATH from environment if not set
		if ($path === false) {
			$path = \getenv("PATH");
		}
		// we look for an executable file of that name
		$exts = [""];
		$check_fn = "is_executable";
		// Default check will be done with $path directories :
		$dirs = \explode(PATH_SEPARATOR, $path);
		// WARNING : We have to check if open_basedir is enabled :
		$obd = OC::$server->getIniWrapper()->getString('open_basedir');
		if ($obd != "none") {
			$obd_values = \explode(PATH_SEPARATOR, $obd);
			if (\count($obd_values) > 0 and $obd_values[0]) {
				// open_basedir is in effect !
				// We need to check if the program is in one of these dirs :
				$dirs = $obd_values;
			}
		}
		foreach ($dirs as $dir) {
			foreach ($exts as $ext) {
				if ($check_fn("$dir/$name" . $ext)) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * copy the contents of one stream to another
	 *
	 * @param resource $source
	 * @param resource $target
	 * @return array the number of bytes copied and result
	 */
	public static function streamCopy($source, $target) {
		if (!$source or !$target) {
			return [0, false];
		}
		$bufSize = 8192;
		$result = true;
		$count = 0;
		while (!\feof($source)) {
			$buf = \fread($source, $bufSize);
			$bytesWritten = \fwrite($target, $buf);
			if ($bytesWritten !== false) {
				$count += $bytesWritten;
			}
			// note: strlen is expensive so only use it when necessary,
			// on the last block
			if ($bytesWritten === false
				|| ($bytesWritten < $bufSize && $bytesWritten < \strlen($buf))
			) {
				// write error, could be disk full ?
				$result = false;
				break;
			}
		}
		return [$count, $result];
	}

	/**
	 * Adds a suffix to the name in case the file exists
	 *
	 * @param string $path
	 * @param string $filename
	 * @return string
	 */
	public static function buildNotExistingFileName($path, $filename) {
		$view = \OC\Files\Filesystem::getView();
		return self::buildNotExistingFileNameForView($path, $filename, $view);
	}

	/**
	 * Adds a suffix to the name in case the file exists
	 *
	 * @param string $path
	 * @param string $filename
	 * @return string
	 */
	public static function buildNotExistingFileNameForView($path, $filename, \OC\Files\View $view) {
		if ($path === '/') {
			$path = '';
		}
		if ($pos = \strrpos($filename, '.')) {
			$name = \substr($filename, 0, $pos);
			$ext = \substr($filename, $pos);
		} else {
			$name = $filename;
			$ext = '';
		}

		$newpath = $path . '/' . $filename;
		if ($view->file_exists($newpath)) {
			if (\preg_match_all('/\((\d+)\)/', $name, $matches, PREG_OFFSET_CAPTURE)) {
				//Replace the last "(number)" with "(number+1)"
				$last_match = \count($matches[0]) - 1;
				$counter = $matches[1][$last_match][0] + 1;
				$offset = $matches[0][$last_match][1];
				$match_length = \strlen($matches[0][$last_match][0]);
			} else {
				$counter = 2;
				$match_length = 0;
				$offset = false;
			}
			do {
				if ($offset) {
					//Replace the last "(number)" with "(number+1)"
					$newname = \substr_replace($name, '(' . $counter . ')', $offset, $match_length);
				} else {
					$newname = $name . ' (' . $counter . ')';
				}
				$newpath = $path . '/' . $newname . $ext;
				$counter++;
			} while ($view->file_exists($newpath));
		}

		return $newpath;
	}

	/**
	 * Checks if $sub is a subdirectory of $parent
	 *
	 * @param string $sub
	 * @param string $parent
	 * @return bool
	 */
	public static function isSubDirectory($sub, $parent) {
		$realpathSub = \realpath($sub);
		$realpathParent = \realpath($parent);

		// realpath() may return false in case the directory does not exist
		// since we can not be sure how different PHP versions may behave here
		// we do an additional check whether realpath returned false
		if ($realpathSub === false ||  $realpathParent === false) {
			return false;
		}

		// Check whether $sub is a subdirectory of $parent
		if (\strpos($realpathSub, $realpathParent) === 0) {
			return true;
		}

		return false;
	}

	/**
	 * Returns an array with all keys from input lowercased or uppercased. Numbered indices are left as is.
	 *
	 * @param array $input The array to work on
	 * @param int $case Either MB_CASE_UPPER or MB_CASE_LOWER (default)
	 * @param string $encoding The encoding parameter is the character encoding. Defaults to UTF-8
	 * @return array
	 *
	 * Returns an array with all keys from input lowercased or uppercased. Numbered indices are left as is.
	 * based on http://www.php.net/manual/en/function.array-change-key-case.php#107715
	 *
	 */
	public static function mb_array_change_key_case($input, $case = MB_CASE_LOWER, $encoding = 'UTF-8') {
		$case = ($case != MB_CASE_UPPER) ? MB_CASE_LOWER : MB_CASE_UPPER;
		$ret = [];
		foreach ($input as $k => $v) {
			$ret[\mb_convert_case($k, $case, $encoding)] = $v;
		}
		return $ret;
	}

	/**
	 * performs a search in a nested array
	 * @param array $haystack the array to be searched
	 * @param string $needle the search string
	 * @param string $index optional, only search this key name
	 * @return mixed the key of the matching field, otherwise false
	 *
	 * performs a search in a nested array
	 *
	 * taken from http://www.php.net/manual/en/function.array-search.php#97645
	 */
	public static function recursiveArraySearch($haystack, $needle, $index = null) {
		$aIt = new RecursiveArrayIterator($haystack);
		$it = new RecursiveIteratorIterator($aIt);

		while ($it->valid()) {
			if (((isset($index) and ($it->key() == $index)) or (!isset($index))) and ($it->current() == $needle)) {
				return $aIt->key();
			}

			$it->next();
		}

		return false;
	}

	/**
	 * calculates the maximum upload size respecting system settings, free space and user quota
	 *
	 * @param string $dir the current folder where the user currently operates
	 * @param int $freeSpace the number of bytes free on the storage holding $dir, if not set this will be received from the storage directly
	 * @return int number of bytes representing
	 */
	public static function maxUploadFilesize($dir, $freeSpace = null) {
		if ($freeSpace === null || $freeSpace < 0) {
			$freeSpace = self::freeSpace($dir);
		}
		return \min($freeSpace, self::uploadLimit());
	}

	/**
	 * Calculate free space left within user quota
	 *
	 * @param string $dir the current folder where the user currently operates
	 * @return int number of bytes representing
	 */
	public static function freeSpace($dir) {
		$freeSpace = \OC\Files\Filesystem::free_space($dir);
		if ($freeSpace < \OCP\Files\FileInfo::SPACE_UNLIMITED) {
			$freeSpace = \max($freeSpace, 0);
			return $freeSpace;
		} else {
			return (INF > 0)? INF: PHP_INT_MAX; // work around https://bugs.php.net/bug.php?id=69188
		}
	}

	/**
	 * Calculate PHP upload limit
	 *
	 * @return int PHP upload file size limit
	 */
	public static function uploadLimit() {
		$ini = \OC::$server->getIniWrapper();
		$upload_max_filesize = OCP\Util::computerFileSize($ini->get('upload_max_filesize'));
		$post_max_size = OCP\Util::computerFileSize($ini->get('post_max_size'));
		if ((int)$upload_max_filesize === 0 and (int)$post_max_size === 0) {
			return INF;
		} elseif ((int)$upload_max_filesize === 0 or (int)$post_max_size === 0) {
			return \max($upload_max_filesize, $post_max_size); //only the non 0 value counts
		} else {
			return \min($upload_max_filesize, $post_max_size);
		}
	}

	/**
	 * Checks if a function is available
	 *
	 * @param string $function_name
	 * @return bool
	 */
	public static function is_function_enabled($function_name) {
		if (!\function_exists($function_name)) {
			return false;
		}
		$ini = \OC::$server->getIniWrapper();
		$disabled = \explode(',', $ini->get('disable_functions'));
		$disabled = \array_map('trim', $disabled);
		if (\in_array($function_name, $disabled)) {
			return false;
		}
		$disabled = \explode(',', $ini->get('suhosin.executor.func.blacklist') ?? '');
		$disabled = \array_map('trim', $disabled);
		if (\in_array($function_name, $disabled)) {
			return false;
		}
		return true;
	}

	/**
	 * Try to find a program
	 *
	 * @param string $program
	 * @return null|string
	 */
	public static function findBinaryPath($program) {
		$memcache = \OC::$server->getMemCacheFactory()->create('findBinaryPath');
		if ($memcache->hasKey($program)) {
			return $memcache->get($program);
		}
		$result = null;
		if (self::is_function_enabled('exec')) {
			$exeSniffer = new ExecutableFinder();
			// Returns null if nothing is found
			$result = $exeSniffer->find($program);
			if (empty($result)) {
				$command = 'find ' . self::getCleanedPath(\getenv('PATH')) . ' -name ' . \escapeshellarg($program) . ' 2> /dev/null';
				\exec($command, $output, $returnCode);
				if (\count($output) > 0) {
					$result = \escapeshellcmd($output[0]);
				}
			}
		}
		// store the value for 5 minutes
		$memcache->set($program, $result, 300);
		return $result;
	}

	/**
	 * Return a validated (sanitised) version of the system path
	 *
	 * The system path needs to be validated/sanitised before being used, as identified in http://bit.ly/2CEUagp (HackerOne).
	 * This method filters out of the retrieved system path, only valid path directories, if the path is defined. Otherwise
	 * it returns a defined set of paths.
	 *
	 * @param string $path
	 * @return string|null
	 */
	public static function getCleanedPath($path = '') {
		$pattern = "((\/[\w\d]*)+)";

		if (\preg_match_all($pattern, $path, $matches) > 0) {
			return \implode(' ', $matches[0]);
		}

		return '/usr/local/bin /usr/bin /opt/bin /bin';
	}

	/**
	 * Calculate the disc space for the given path
	 *
	 * @param string $path
	 * @param \OCP\Files\FileInfo $rootInfo (optional)
	 * @return array
	 * @throws \OCP\Files\NotFoundException
	 */
	public static function getStorageInfo($path, $rootInfo = null) {
		// return storage info without adding mount points
		$includeExtStorage = \OC::$server->getSystemConfig()->getValue('quota_include_external_storage', false);

		if (!$rootInfo) {
			$rootInfo = \OC\Files\Filesystem::getFileInfo($path, false);
		}
		if (!$rootInfo instanceof \OCP\Files\FileInfo) {
			throw new \OCP\Files\NotFoundException();
		}
		$used = $rootInfo->getSize();
		if ($used < 0) {
			$used = 0;
		}
		$quota = \OCP\Files\FileInfo::SPACE_UNLIMITED;
		$storage = $rootInfo->getStorage();
		$sourceStorage = $storage;
		if ($storage->instanceOfStorage('\OCA\Files_Sharing\SharedStorage')) {
			$includeExtStorage = false;
			'@phan-var \OCA\Files_Sharing\SharedStorage $storage';
			$sourceStorage = $storage->getSourceStorage();
		}
		if ($includeExtStorage) {
			$quota = OC_Util::getUserQuota(\OCP\User::getUser());
			if ($quota !== \OCP\Files\FileInfo::SPACE_UNLIMITED) {
				// always get free space / total space from root + mount points
				return self::getGlobalStorageInfo();
			}
		}

		// TODO: need a better way to get total space from storage
		if ($sourceStorage->instanceOfStorage('\OC\Files\Storage\Wrapper\Quota')) {
			/** @var \OC\Files\Storage\Wrapper\Quota $sourceStorage */
			'@phan-var \OC\Files\Storage\Wrapper\Quota $sourceStorage';
			$quota = $sourceStorage->getQuota();
		}
		$free = $sourceStorage->free_space('');
		if ($free >= 0) {
			$total = $free + $used;
		} else {
			$total = $free; //either unknown or unlimited
		}
		if ($total > 0) {
			if ($quota > 0 && $total > $quota) {
				$total = $quota;
			}
			// prevent division by zero or error codes (negative values)
			$relative = \round(($used / $total) * 10000) / 100;
		} else {
			$relative = 0;
		}

		$ownerId = $storage->getOwner($path);
		$ownerDisplayName = '';
		$owner = \OC::$server->getUserManager()->get($ownerId);
		if ($owner) {
			$ownerDisplayName = $owner->getDisplayName();
		}

		return [
			'free' => $free,
			'used' => $used,
			'quota' => $quota,
			'total' => $total,
			'relative' => $relative,
			'owner' => $ownerId,
			'ownerDisplayName' => $ownerDisplayName,
		];
	}

	/**
	 * Get storage info including all mount points and quota
	 *
	 * @return array
	 */
	private static function getGlobalStorageInfo() {
		$quota = OC_Util::getUserQuota(\OCP\User::getUser());

		$rootInfo = \OC\Files\Filesystem::getFileInfo('', 'ext');
		$used = $rootInfo['size'];
		if ($used < 0) {
			$used = 0;
		}

		$total = $quota;
		$free = $quota - $used;

		if ($total > 0) {
			if ($quota > 0 && $total > $quota) {
				$total = $quota;
			}
			// prevent division by zero or error codes (negative values)
			$relative = \round(($used / $total) * 10000) / 100;
		} else {
			$relative = 0;
		}

		return ['free' => $free, 'used' => $used, 'total' => $total, 'relative' => $relative];
	}
}
