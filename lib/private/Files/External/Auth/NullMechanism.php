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

namespace OC\Files\External\Auth;

use \OCP\Files\External\Auth\AuthMechanism;

/**
 * Null authentication mechanism
 */
class NullMechanism extends AuthMechanism {
	public function __construct() {
		$l = \OC::$server->getL10N('lib');
		$this
			->setIdentifier('null::null')
			->setScheme(self::SCHEME_NULL)
			->setText($l->t('None'))
		;
	}
}
