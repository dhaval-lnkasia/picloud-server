<?php
/**
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Files_Versions\Command;

use OC\Command\FileAccess;
use OCA\Files_Versions\Storage;
use OCP\Command\ICommand;

class Expire implements ICommand {
	use FileAccess;

	/**
	 * @var string
	 */
	private $fileName;

	/**
	 * @var string
	 */
	private $user;

	/**
	 * @param string $user
	 * @param string $fileName
	 */
	public function __construct($user, $fileName) {
		$this->user = $user;
		$this->fileName = $fileName;
	}

	public function handle() {
		$userManager = \OC::$server->getUserManager();
		if (!$userManager->userExists($this->user)) {
			// User has been deleted already
			return;
		}

		Storage::expire($this->fileName, $this->user);
	}
}
