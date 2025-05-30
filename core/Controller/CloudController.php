<?php
/**
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tom Needham <tom@owncloud.com>
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

namespace OC\Core\Controller;

use OCP\AppFramework\OCSController;
use OCP\IRequest;

class CloudController extends OCSController {
	public function __construct($appName, IRequest $request) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 *
	 * @return array
	 */
	public function getCapabilities() {
		$result = [];
		$defaults = new \OCP\Defaults();
		list($major, $minor, $micro) = \OCP\Util::getVersion();
		$result['version'] = [
			'major' => $major,
			'minor' => $minor,
			'micro' => $micro,
			'string' => \OC_Util::getVersionString(),
			'edition' => \OC_Util::getEditionString(),
			'product' => $defaults->getName(),
		];

		$result['capabilities'] = \OC::$server->getCapabilitiesManager()->getCapabilities();

		return ['data' => $result];
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 *
	 * @return array
	 */
	public function getCurrentUser() {
		$userObject = \OC::$server->getUserManager()->get(\OC_User::getUser());
		$data  = [
			'id' => $userObject->getUID(),
			'display-name' => $userObject->getDisplayName(),
			'email' => $userObject->getEMailAddress(),
			'language' => \OC::$server->getConfig()->getUserValue($userObject->getUID(), 'core', 'lang', \OC::$server->getConfig()->getSystemValue('default_language', 'en'))
		];
		return ['data' => $data];
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 *
	 * @return array
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function getSigningKey(): array {
		$userId = \OC_User::getUser();
		$signingKey = \OC::$server->getConfig()->getUserValue($userId, 'core', 'signing-key', null);
		if ($signingKey === null) {
			$signingKey = \OC::$server->getSecureRandom()->generate(64);
			\OC::$server->getConfig()->setUserValue($userId, 'core', 'signing-key', $signingKey, null);
		}
		return ['data' => [
			'user' => $userId,
			'signing-key' => $signingKey
		]];
	}
}
