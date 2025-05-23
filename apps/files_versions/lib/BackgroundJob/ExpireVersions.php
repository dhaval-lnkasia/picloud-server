<?php
/**
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

namespace OCA\Files_Versions\BackgroundJob;

use OCA\Files_Versions\AppInfo\Application;
use OCA\Files_Versions\Expiration;
use OCA\Files_Versions\Storage;
use OCP\IUser;
use OCP\IUserManager;

class ExpireVersions extends \OC\BackgroundJob\TimedJob {
	public const ITEMS_PER_SESSION = 1000;

	/**
	 * @var Expiration
	 */
	private $expiration;
	
	/**
	 * @var IUserManager
	 */
	private $userManager;

	public function __construct(IUserManager $userManager = null, Expiration $expiration = null) {
		// Run once per 30 minutes
		$this->setInterval(60 * 30);

		if ($expiration === null || $userManager === null) {
			$this->fixDIForJobs();
		} else {
			$this->expiration = $expiration;
			$this->userManager = $userManager;
		}
	}

	protected function fixDIForJobs() {
		$application = new Application();
		$this->expiration = $application->getContainer()->query('Expiration');
		$this->userManager = \OC::$server->getUserManager();
	}

	protected function run($argument) {
		$maxAge = $this->expiration->getMaxAgeAsTimestamp();
		if (!$maxAge) {
			return;
		}

		$this->userManager->callForSeenUsers(function (IUser $user) {
			$uid = $user->getUID();
			if (!$this->setupFS($uid)) {
				return;
			}
			Storage::expireOlderThanMaxForUser($uid);
		});
	}

	/**
	 * Act on behalf on trash item owner
	 * @param string $user
	 * @return boolean
	 */
	protected function setupFS($user) {
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($user, false);

		// Check if this user has a versions directory
		$view = new \OC\Files\View('/' . $user);
		if (!$view->is_dir('/files_versions')) {
			return false;
		}

		return true;
	}
}
