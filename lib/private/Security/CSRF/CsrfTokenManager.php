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

use OC\Security\CSRF\TokenStorage\SessionStorage;

/**
 * Class CsrfTokenManager is the manager for all CSRF token related activities.
 *
 * @package OC\Security\CSRF
 */
class CsrfTokenManager {
	/** @var CsrfTokenGenerator */
	private $tokenGenerator;
	/** @var SessionStorage */
	private $sessionStorage;

	/**
	 * @param CsrfTokenGenerator $tokenGenerator
	 * @param SessionStorage $storageInterface
	 */
	public function __construct(
		CsrfTokenGenerator $tokenGenerator,
		SessionStorage $storageInterface
	) {
		$this->tokenGenerator = $tokenGenerator;
		$this->sessionStorage = $storageInterface;
	}

	/**
	 * Returns the current CSRF token, if none set it will create a new one.
	 *
	 * @return CsrfToken
	 */
	public function getToken() {
		if ($this->sessionStorage->hasToken()) {
			$value = $this->sessionStorage->getToken();
		} else {
			$value = $this->tokenGenerator->generateToken();
			$this->sessionStorage->setToken($value);
		}

		return new CsrfToken($value);
	}

	/**
	 * Invalidates any current token and sets a new one.
	 *
	 * @return CsrfToken
	 */
	public function refreshToken() {
		$value = $this->tokenGenerator->generateToken();
		$this->sessionStorage->setToken($value);
		return new CsrfToken($value);
	}

	/**
	 * Remove the current token from the storage.
	 */
	public function removeToken() {
		$this->sessionStorage->removeToken();
	}

	/**
	 * Verifies whether the provided token is valid.
	 *
	 * @param CsrfToken $token
	 * @return bool
	 */
	public function isTokenValid(CsrfToken $token) {
		if (!$this->sessionStorage->hasToken()) {
			return false;
		}

		return \hash_equals(
			$this->sessionStorage->getToken(),
			$token->getDecryptedValue()
		);
	}
}
