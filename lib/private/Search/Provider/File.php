<?php
/**
 * @author Andrew Brown <andrew@casabrown.com>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Jakob Sack <mail@jakobsack.de>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OC\Search\Provider;
use OC\Files\Filesystem;

/**
 * Provide search results from the 'files' app
 */
class File extends \OCP\Search\Provider {
	/**
	 * Search for files and folders matching the given query
	 * @param string $query
	 * @return \OCP\Search\Result
	 */
	public function search($query) {
		$files = Filesystem::search($query);
		$results = [];
		// edit results
		foreach ($files as $fileData) {
			// skip versions
			if (\strpos($fileData['path'], '_versions') === 0) {
				continue;
			}
			// skip top-level folder
			if ($fileData['name'] === 'files' && $fileData['parent'] === -1) {
				continue;
			}
			// create audio result
			if ($fileData['mimepart'] === 'audio') {
				$result = new \OC\Search\Result\Audio($fileData);
			}
			// create image result
			elseif ($fileData['mimepart'] === 'image') {
				$result = new \OC\Search\Result\Image($fileData);
			}
			// create folder result
			elseif ($fileData['mimetype'] === 'httpd/unix-directory') {
				$result = new \OC\Search\Result\Folder($fileData);
			}
			// or create file result
			else {
				$result = new \OC\Search\Result\File($fileData);
			}
			// add to results
			$results[] = $result;
		}
		// return
		return $results;
	}
}
