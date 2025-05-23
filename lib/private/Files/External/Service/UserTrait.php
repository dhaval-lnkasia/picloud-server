<?php
/**
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OC\Files\External\Service;

use \OCP\IUserSession;
use \OCP\IUser;

/**
 * Trait for getting user information in a service
 */
trait UserTrait {
	/** @var IUserSession */
	protected $userSession;

	/**
	 * User override
	 *
	 * @var IUser|null
	 */
	private $user = null;

	/**
	 * @return IUser|null
	 */
	protected function getUser() {
		if ($this->user) {
			return $this->user;
		}
		return $this->userSession->getUser();
	}

	/**
	 * Override the user from the session
	 * Unset with ->resetUser() when finished!
	 *
	 * @param IUser
	 * @return self
	 */
	public function setUser(IUser $user) {
		$this->user = $user;
		return $this;
	}

	/**
	 * Reset the user override
	 *
	 * @return self
	 */
	public function resetUser() {
		$this->user = null;
		return $this;
	}
}
