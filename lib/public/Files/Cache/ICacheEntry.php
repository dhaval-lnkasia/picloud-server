<?php
/**
 * @author Robin Appelman <icewind@owncloud.com>
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

namespace OCP\Files\Cache;

/**
 * meta data for a file or folder
 *
 * @since 9.0.0
 */
interface ICacheEntry {
	public const DIRECTORY_MIMETYPE = 'httpd/unix-directory';

	/**
	 * Get the numeric id of a file
	 *
	 * @return int
	 * @since 9.0.0
	 */
	public function getId();

	/**
	 * Get the numeric id for the storage
	 *
	 * @return int
	 * @since 9.0.0
	 */
	public function getStorageId();

	/**
	 * Get the path of the file relative to the storage root
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getPath();

	/**
	 * Get the file name
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getName();

	/**
	 * Get the full mimetype
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getMimeType();

	/**
	 * Get the first part of the mimetype
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getMimePart();

	/**
	 * Get the file size in bytes
	 *
	 * It should return the size as integer, but it could return it
	 * as float if the size doesn't fit
	 *
	 * @return int|float
	 * @since 9.0.0
	 */
	public function getSize();

	/**
	 * Get the last modified date as unix timestamp
	 *
	 * @return int
	 * @since 9.0.0
	 */
	public function getMTime();

	/**
	 * Get the last modified date on the storage as unix timestamp
	 *
	 * Note that when a file is updated we also update the mtime of all parent folders to make it visible to the user which folder has had updates most recently
	 * This can differ from the mtime on the underlying storage which usually only changes when a direct child is added, removed or renamed
	 *
	 * @return int
	 * @since 9.0.0
	 */
	public function getStorageMTime();

	/**
	 * Get the etag for the file
	 *
	 * An etag is used for change detection of files and folders, an etag of a file changes whenever the content of the file changes
	 * Etag for folders change whenever a file in the folder has changed
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getEtag();

	/**
	 * Get the permissions for the file stored as bitwise combination of \OCP\PERMISSION_READ, \OCP\PERMISSION_CREATE
	 * \OCP\PERMISSION_UPDATE, \OCP\PERMISSION_DELETE and \OCP\PERMISSION_SHARE
	 *
	 * @return int
	 * @since 9.0.0
	 */
	public function getPermissions();

	/**
	 * Check if the file is encrypted
	 *
	 * @return bool
	 * @since 9.0.0
	 */
	public function isEncrypted();
}
