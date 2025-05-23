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

namespace OCA\Files_Sharing\External;

use OCP\Files\StorageNotAvailableException;

class Watcher extends \OC\Files\Cache\Watcher {
	/**
	 * remove deleted files in $path from the cache
	 *
	 * @param string $path
	 */
	public function cleanFolder($path) {
		// not needed, the scanner takes care of this
	}

	public function needsUpdate($path, $cachedData) {
		try {
			return parent::needsUpdate($path, $cachedData);
		} catch (StorageNotAvailableException $e) {
			return false;
		}
	}
}
