<?php
/**
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
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
 * Public interface of ownCloud for apps to use.
 * Files/Folder interface
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes
namespace OCP\Files;

/**
 * @since 6.0.0
 */
interface Folder extends Node {
	/**
	 * Get the full path of an item in the folder within owncloud's filesystem
	 *
	 * @param string $path relative path of an item in the folder
	 * @return string
	 * @throws \OCP\Files\NotPermittedException
	 * @since 6.0.0
	 */
	public function getFullPath($path);

	/**
	 * Get the path of an item in the folder relative to the folder or null
	 * if the path is outside of the folder
	 *
	 * @param string $path absolute path of an item in the folder
	 * @throws \OCP\Files\NotFoundException
	 * @return string|null
	 * @since 6.0.0
	 */
	public function getRelativePath($path);

	/**
	 * check if a node is a (grand-)child of the folder
	 *
	 * @param \OCP\Files\Node $node
	 * @return bool
	 * @since 6.0.0
	 */
	public function isSubNode($node);

	/**
	 * get the content of this directory
	 *
	 * @throws \OCP\Files\NotFoundException
	 * @return \OCP\Files\Node[]
	 * @since 6.0.0
	 */
	public function getDirectoryListing();

	/**
	 * Get the node at $path
	 *
	 * @param string $path relative path of the file or folder
	 * @return \OCP\Files\Node
	 * @throws \OCP\Files\NotFoundException
	 * @since 6.0.0
	 */
	public function get($path);

	/**
	 * Check if a file or folder exists in the folder
	 *
	 * @param string $path relative path of the file or folder
	 * @return bool
	 * @since 6.0.0
	 */
	public function nodeExists($path);

	/**
	 * Create a new folder
	 *
	 * @param string $path relative path of the new folder
	 * @return \OCP\Files\Folder
	 * @throws \OCP\Files\NotPermittedException
	 * @since 6.0.0
	 */
	public function newFolder($path);

	/**
	 * Create a new file
	 *
	 * @param string $path relative path of the new file
	 * @return \OCP\Files\File
	 * @throws \OCP\Files\NotPermittedException
	 * @since 6.0.0
	 */
	public function newFile($path);

	/**
	 * search for files with the name matching $query
	 *
	 * @param string $query
	 * @return \OCP\Files\Node[]
	 * @since 6.0.0
	 */
	public function search($query);

	/**
	 * search for files by mimetype
	 * $mimetype can either be a full mimetype (image/png) or a wildcard mimetype (image)
	 *
	 * @param string $mimetype
	 * @return \OCP\Files\Node[]
	 * @since 6.0.0
	 */
	public function searchByMime($mimetype);

	/**
	 * search for files by tag
	 *
	 * @param string|int $tag tag name or tag id
	 * @param string $userId owner of the tags
	 * @return \OCP\Files\Node[]
	 * @since 8.0.0
	 */
	public function searchByTag($tag, $userId);

	/**
	 * get a file or folder inside the folder by it's internal id
	 *
	 * @param int $id
	 * @param boolean $first only return the first node that is found
	 * @return \OCP\Files\Node[]
	 * @since 6.0.0
	 */
	public function getById($id, $first = false);

	/**
	 * Get the amount of free space inside the folder
	 *
	 * @return int
	 * @since 6.0.0
	 */
	public function getFreeSpace();

	/**
	 * Check if new files or folders can be created within the folder
	 *
	 * @return bool
	 * @since 6.0.0
	 */
	public function isCreatable();

	/**
	 * Add a suffix to the name in case the file exists
	 *
	 * @param string $name
	 * @return string
	 * @throws NotPermittedException
	 * @since 8.1.0
	 */
	public function getNonExistingName($name);
}
