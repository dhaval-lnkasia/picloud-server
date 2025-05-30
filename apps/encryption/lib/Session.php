<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 *
 * @copyright Copyright (c) 2019, LNKASIA TECHSOL
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

namespace OCA\Encryption;

use OCA\Encryption\Exceptions\PrivateKeyMissingException;
use \OCP\ISession;

class Session {
	/** @var ISession */
	protected $session;

	public const NOT_INITIALIZED = '0';
	public const INIT_EXECUTED = '1';
	public const INIT_SUCCESSFUL = '2';
	public const RUN_MIGRATION = '3';

	/**
	 * @param ISession $session
	 */
	public function __construct(ISession $session) {
		$this->session = $session;
	}

	/**
	 * Sets status of encryption app
	 *
	 * @param string $status INIT_SUCCESSFUL, INIT_EXECUTED, NOT_INITIALIZED
	 */
	public function setStatus($status) {
		$this->session->set('encryptionInitialized', $status);
	}

	/**
	 * Gets status if we already tried to initialize the encryption app
	 *
	 * @return string init status INIT_SUCCESSFUL, INIT_EXECUTED, NOT_INITIALIZED
	 */
	public function getStatus() {
		$status = $this->session->get('encryptionInitialized');
		if ($status === null) {
			/** @phan-suppress-next-line PhanDeprecatedFunction */
			if (\OC::$server->getAppConfig()->getValue('encryption', 'useMasterKey', '0') !== '0'
				/** @phan-suppress-next-line PhanDeprecatedFunction */
			  or \OC::$server->getAppConfig()->getValue('encryption', 'userSpecificKey', '') !== '') {
				$status = self::NOT_INITIALIZED;
			}
		}

		return $status;
	}

	/**
	 * Gets user or public share private key from session
	 *
	 * @return string $privateKey The user's plaintext private key
	 * @throws Exceptions\PrivateKeyMissingException
	 */
	public function getPrivateKey() {
		$key = $this->session->get('privateKey');
		if ($key === null) {
			throw new Exceptions\PrivateKeyMissingException('please try to log-out and log-in again');
		}
		return $key;
	}

	/**
	 * check if private key is set
	 *
	 * @return boolean
	 */
	public function isPrivateKeySet() {
		$key = $this->session->get('privateKey');
		if ($key === null) {
			return false;
		}

		return true;
	}

	/**
	 * Sets user private key to session
	 *
	 * @param string $key users private key
	 *
	 * @note this should only be set on login
	 */
	public function setPrivateKey($key) {
		$this->session->set('privateKey', $key);
	}

	/**
	 * store data needed for the decrypt all operation in the session
	 *
	 * @param string $user
	 * @param string $key
	 */
	public function prepareDecryptAll($user, $key) {
		$this->session->set('decryptAll', true);
		$this->session->set('decryptAllKey', $key);
		$this->session->set('decryptAllUid', $user);
	}

	/**
	 * check if we are in decrypt all mode
	 *
	 * @return bool
	 */
	public function decryptAllModeActivated() {
		$decryptAll = $this->session->get('decryptAll');
		return ($decryptAll === true);
	}

	/**
	 * get uid used for decrypt all operation
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getDecryptAllUid() {
		$uid = $this->session->get('decryptAllUid');
		if (($uid === null) && $this->decryptAllModeActivated()) {
			throw new \Exception('No uid found while in decrypt all mode');
		} elseif ($uid === null) {
			throw new \Exception('Please activate decrypt all mode first');
		}

		return $uid;
	}

	/**
	 * get private key for decrypt all operation
	 *
	 * @return string
	 * @throws PrivateKeyMissingException
	 */
	public function getDecryptAllKey() {
		$privateKey = $this->session->get('decryptAllKey');
		if (($privateKey === null) && $this->decryptAllModeActivated()) {
			throw new PrivateKeyMissingException('No private key found while in decrypt all mode');
		} elseif ($privateKey === null) {
			throw new PrivateKeyMissingException('Please activate decrypt all mode first');
		}

		return $privateKey;
	}

	/**
	 * remove keys from session
	 */
	public function clear() {
		$this->session->remove('publicSharePrivateKey');
		$this->session->remove('privateKey');
		$this->session->remove('encryptionInitialized');
		$this->session->remove('decryptAll');
		$this->session->remove('decryptAllKey');
		$this->session->remove('decryptAllUid');
	}
}
