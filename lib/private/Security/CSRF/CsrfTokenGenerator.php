<?php
/**
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OC\Security\CSRF;

use OCP\Security\ISecureRandom;

/**
 * Class CsrfTokenGenerator is used to generate a cryptographically secure
 * pseudo-random number for the token.
 *
 * @package OC\Security\CSRF
 */
class CsrfTokenGenerator {
	/** @var ISecureRandom  */
	private $random;

	/**
	 * @param ISecureRandom $random
	 */
	public function __construct(ISecureRandom $random) {
		$this->random = $random;
	}

	/**
	 * Generate a new CSRF token.
	 *
	 * @param int $length Length of the token in characters.
	 * @return string
	 */
	public function generateToken($length = 32) {
		return $this->random->generate($length);
	}
}
