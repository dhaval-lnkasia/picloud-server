<?php
/**
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Christoph Wurst <christoph@owncloud.com>
 * @author Felix Rupp <github@felixrupp.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Semih Serhat Karakaya <karakayasemi@itu.edu.tr>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OC\User;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Exception;
use OC;
use OC\Authentication\Exceptions\InvalidTokenException;
use OC\Authentication\Exceptions\PasswordlessTokenException;
use OC\Authentication\Exceptions\PasswordLoginForbiddenException;
use OC\Authentication\LoginPolicies\LoginPolicyManager;
use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\IToken;
use OC\Hooks\Emitter;
use OC\Hooks\PublicEmitter;
use OC_User;
use OC_Util;
use OCA\DAV\Connector\Sabre\Auth;
use OCP\App\IServiceLoader;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Authentication\IApacheBackend;
use OCP\Authentication\IAuthModule;
use OCP\Events\EventEmitterTrait;
use OCP\Files\NoReadAccessException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Session\Exceptions\SessionNotAvailableException;
use OCP\UserInterface;
use OCP\Util;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Session
 *
 * Hooks available in scope \OC\User:
 * - preSetPassword(\OC\User\User $user, string $password, string $recoverPassword)
 * - postSetPassword(\OC\User\User $user, string $password, string $recoverPassword)
 * - preDelete(\OC\User\User $user)
 * - postDelete(\OC\User\User $user)
 * - preCreateUser(string $uid, string $password)
 * - postCreateUser(\OC\User\User $user)
 * - preLogin(string $user, string $password)
 * - postLogin(\OC\User\User $user, string $password)
 * - failedLogin(string $user)
 * - logout()
 * - postLogout()
 *
 * @package OC\User
 */
class Session implements IUserSession, Emitter {
	use EventEmitterTrait;
	/** @var IUserManager | PublicEmitter $manager */
	private $manager;

	/** @var ISession $session */
	private $session;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var IProvider */
	private $tokenProvider;

	/** @var IConfig */
	private $config;

	/** @var ILogger */
	private $logger;

	/** @var User $activeUser */
	protected $activeUser;

	/** @var IServiceLoader */
	private $serviceLoader;

	/** @var SyncService */
	protected $userSyncService;

	/** @var EventDispatcher */
	protected $eventDispatcher;

	/**
	 * Session constructor.
	 *
	 * @param IUserManager $manager
	 * @param ISession $session
	 * @param ITimeFactory $timeFactory
	 * @param IProvider $tokenProvider
	 * @param IConfig $config
	 * @param ILogger $logger
	 * @param IServiceLoader $serviceLoader
	 * @param SyncService $userSyncService
	 * @param EventDispatcher $eventDispatcher
	 */
	public function __construct(
		IUserManager $manager,
		ISession $session,
		ITimeFactory $timeFactory,
		IProvider $tokenProvider,
		IConfig $config,
		ILogger $logger,
		IServiceLoader $serviceLoader,
		SyncService $userSyncService,
		EventDispatcher $eventDispatcher
	) {
		$this->manager = $manager;
		$this->session = $session;
		$this->timeFactory = $timeFactory;
		$this->tokenProvider = $tokenProvider;
		$this->config = $config;
		$this->logger = $logger;
		$this->serviceLoader = $serviceLoader;
		$this->userSyncService = $userSyncService;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * @param IProvider $provider
	 */
	public function setTokenProvider(IProvider $provider) {
		$this->tokenProvider = $provider;
	}

	/**
	 * @param string $scope
	 * @param string $method
	 * @param callable $callback
	 */
	public function listen($scope, $method, callable $callback) {
		$this->manager->listen($scope, $method, $callback);
	}

	/**
	 * @param string $scope optional
	 * @param string $method optional
	 * @param callable $callback optional
	 */
	public function removeListener($scope = null, $method = null, callable $callback = null) {
		$this->manager->removeListener($scope, $method, $callback);
	}

	/**
	 * get the session object
	 *
	 * @return ISession
	 */
	public function getSession() {
		return $this->session;
	}

	/**
	 * set the session object
	 *
	 * @param ISession $session
	 */
	public function setSession(ISession $session) {
		if ($this->session instanceof ISession) {
			$this->session->close();
		}
		$this->session = $session;
		$this->activeUser = null;
	}

	/**
	 * set the currently active user
	 *
	 * @param IUser|null $user
	 */
	public function setUser($user) {
		if ($user === null) {
			$this->session->remove('user_id');
		} else {
			$this->session->set('user_id', $user->getUID());
		}
		$this->activeUser = $user;
	}

	/**
	 * get the current active user
	 *
	 * @return IUser|null Current user, otherwise null
	 */
	public function getUser() {
		// FIXME: This is a quick'n dirty work-around for the incognito mode as
		// described at https://github.com/owncloud/core/pull/12912#issuecomment-67391155
		if (OC_User::isIncognitoMode()) {
			return null;
		}
		if ($this->activeUser === null) {
			$uid = $this->session->get('user_id');
			if ($uid === null) {
				return null;
			}
			$this->activeUser = $this->manager->get($uid);
			if ($this->activeUser === null) {
				return null;
			}
		}
		return $this->activeUser;
	}

	/**
	 * Validate whether the current session is valid
	 *
	 * - For token-authenticated clients, the token validity is checked
	 * - For browsers, the session token validity is checked
	 */
	public function validateSession() {
		$sessionUser = $this->getUser();
		if (!$sessionUser) {
			return;
		}

		$token = null;
		$appPassword = $this->session->get('app_password');

		if ($appPassword === null) {
			try {
				$token = $this->session->getId();
			} catch (SessionNotAvailableException $ex) {
				$this->logger->logException($ex, ['app' => __METHOD__]);
				return;
			}
		} else {
			$token = $appPassword;
		}

		if (!$sessionUser->isEnabled()) {
			$this->tokenProvider->invalidateToken($token);
			$this->logout();
		}

		if (!$this->validateToken($token)) {
			// Session was invalidated
			$this->logout();
		}
	}

	/**
	 * Checks whether the user is logged in
	 *
	 * @return bool if logged in
	 */
	public function isLoggedIn() {
		$user = $this->getUser();
		if ($user === null) {
			return false;
		}

		return $user->isEnabled();
	}

	/**
	 * set the login name
	 *
	 * @param string|null $loginName for the logged in user
	 */
	public function setLoginName($loginName) {
		if ($loginName === null) {
			$this->session->remove('loginname');
		} else {
			$this->session->set('loginname', $loginName);
		}
	}

	/**
	 * get the login name of the current user
	 *
	 * @return string
	 */
	public function getLoginName() {
		if ($this->activeUser) {
			return $this->session->get('loginname');
		}

		$uid = $this->session->get('user_id');
		if ($uid) {
			$this->activeUser = $this->manager->get($uid);
			return $this->session->get('loginname');
		}

		return null;
	}

	/**
	 * try to log in with the provided credentials
	 *
	 * @param string $uid
	 * @param string $password
	 * @return boolean|null
	 * @throws LoginException
	 */
	public function login($uid, $password) {
		$this->logger->debug(
			'regenerating session id for uid {uid}, password {password}',
			[
				'app' => __METHOD__,
				'uid' => $uid,
				'password' => empty($password) ? 'empty' : 'set'
			]
		);
		$this->session->regenerateId();

		if ($this->validateToken($password, $uid)) {
			return $this->loginWithToken($password);
		}
		return $this->loginWithPassword($uid, $password);
	}

	/**
	 * Tries to log in a client
	 *
	 * Checks token auth enforced
	 * Checks 2FA enabled
	 *
	 * @param string $user
	 * @param string $password
	 * @param IRequest $request
	 * @throws \InvalidArgumentException
	 * @throws LoginException
	 * @throws PasswordLoginForbiddenException
	 * @return boolean
	 */
	public function logClientIn($user, $password, IRequest $request) {
		$isTokenPassword = $this->isTokenPassword($password);
		if ($user === null || \trim($user) === '') {
			throw new \InvalidArgumentException('$user cannot be empty');
		}
		if (!$isTokenPassword
			&& ($this->isTokenAuthEnforced() || $this->isTwoFactorEnforced($user))
		) {
			$this->logger->warning("Login failed: '$user' (Remote IP: '{$request->getRemoteAddress()}')", ['app' => 'core']);
			$this->emitFailedLogin($user);
			throw new PasswordLoginForbiddenException();
		}
		if (!$this->login($user, $password)) {
			if ($this->config->getSystemValue('strict_login_enforced', false) === true) {
				return false;
			}

			$users = $this->manager->getByEmail($user);
			if (\count($users) === 1) {
				return $this->login($users[0]->getUID(), $password);
			}
			return false;
		}

		if ($isTokenPassword) {
			$this->session->set('app_password', $password);
		} elseif ($this->supportsCookies($request)) {
			// Password login, but cookies supported -> create (browser) session token
			$this->createSessionToken($request, $this->getUser()->getUID(), $user, $password);
		}

		return true;
	}

	protected function supportsCookies(IRequest $request) {
		if ($request->getCookie('cookie_test') !== null) {
			return true;
		}
		\setcookie('cookie_test', 'test', $this->timeFactory->getTime() + 3600);
		return false;
	}

	private function isTokenAuthEnforced() {
		return $this->config->getSystemValue('token_auth_enforced', false);
	}

	protected function isTwoFactorEnforced($username) {
		$handled = false;
		// the $handled var will be sent as reference so the listeners can use it as a flag
		// in order to know if the event has been processed already or not.
		Util::emitHook(
			'\OCA\Files_Sharing\API\Server2Server',
			'preLoginNameUsedAsUserName',
			['uid' => &$username, 'hasBeenHandled' => &$handled]
		);
		$user = $this->manager->get($username);
		if ($user === null) {
			if ($this->config->getSystemValue('strict_login_enforced', false) === true) {
				return false;
			}
			$users = $this->manager->getByEmail($username);
			if (empty($users)) {
				return false;
			}
			if (\count($users) !== 1) {
				return true;
			}
			$user = $users[0];
		}
		// DI not possible due to cyclic dependencies :'-/
		return OC::$server->getTwoFactorAuthManager()->isTwoFactorAuthenticated($user);
	}

	/**
	 * Check if the given 'password' is actually a device token
	 *
	 * @param string $password
	 * @return boolean
	 */
	public function isTokenPassword($password) {
		try {
			$this->tokenProvider->getToken($password);
			return true;
		} catch (InvalidTokenException $ex) {
			return false;
		}
	}

	/**
	 * Unintentional public
	 *
	 * @param bool $firstTimeLogin
	 */
	public function prepareUserLogin($firstTimeLogin = false) {
		// TODO: mock/inject/use non-static
		// Refresh the token
		\OC::$server->getCsrfTokenManager()->refreshToken();
		//we need to pass the user name, which may differ from login name
		$user = $this->getUser()->getUID();
		OC_Util::setupFS($user);

		//trigger creation of user home and /files folder
		$userFolder = \OC::$server->getUserFolder($user);
		if ($firstTimeLogin) {
			// TODO: lock necessary?

			try {
				// copy skeleton
				\OC_Util::copySkeleton($user, $userFolder);
			} catch (NotPermittedException $ex) {
				// possible if files directory is in an readonly jail
				$this->logger->warning(
					'Skeleton not created due to missing write permission'
				);
			} catch (NoReadAccessException $ex) {
				// possible if the skeleton directory does not have read access
				$this->logger->warning(
					'Skeleton not created due to missing read permission in skeleton directory'
				);
			} catch (\OC\HintException $hintEx) {
				// only if Skeleton no existing Dir
				$this->logger->error($hintEx->getMessage());
			}

			// trigger any other initialization
			$this->eventDispatcher->dispatch(new GenericEvent($this->getUser()), IUser::class . '::firstLogin');
			$this->eventDispatcher->dispatch(new GenericEvent($this->getUser()), 'user.firstlogin');
		}
	}

	/**
	 * Tries to login the user with HTTP Basic Authentication
	 *
	 * @todo do not allow basic auth if the user is 2FA enforced
	 * @param IRequest $request
	 * @return boolean if the login was successful
	 * @throws LoginException
	 */
	public function tryBasicAuthLogin(IRequest $request) {
		if (!empty($request->server['PHP_AUTH_USER']) && !empty($request->server['PHP_AUTH_PW'])) {
			try {
				if ($this->logClientIn($request->server['PHP_AUTH_USER'], $request->server['PHP_AUTH_PW'], $request)) {
					/**
					 * Add DAV authenticated. This should in an ideal world not be
					 * necessary but the iOS App reads cookies from anywhere instead
					 * only the DAV endpoint.
					 * This makes sure that the cookies will be valid for the whole scope
					 *
					 * @see https://github.com/owncloud/core/issues/22893
					 */
					$this->session->set(
						Auth::DAV_AUTHENTICATED,
						$this->getUser()->getUID()
					);
					return true;
				}
			} catch (PasswordLoginForbiddenException $ex) {
				// Nothing to do
			}
		}
		return false;
	}

	/**
	 * Log an user in via login name and password
	 *
	 * @param string $login
	 * @param string $password
	 * @return boolean
	 * @throws LoginException if an app canceled the login process or the user is not enabled
	 *
	 * Two new keys 'login' in the before event and 'user' in the after event
	 * are introduced. We should use this keys in future when trying to listen
	 * the events emitted from this method. We have kept the key 'uid' for
	 * compatibility.
	 */
	private function loginWithPassword($login, $password) {
		$user = $this->manager->checkPassword($login, $password);
		if ($user === false) {
			$this->emitFailedLogin($login);
			return false;
		}
		return $this->loginInOwnCloud('password', $user, $password);
	}

	/**
	 * Log an user in with a given token (id)
	 *
	 * @param string $token
	 * @return boolean
	 * @throws LoginException if an app canceled the login process or the user is not enabled
	 * @throws InvalidTokenException
	 */
	private function loginWithToken($token) {
		try {
			$dbToken = $this->tokenProvider->getToken($token);
		} catch (InvalidTokenException $ex) {
			return false;
		}
		$uid = $dbToken->getUID();

		// When logging in with token, the password must be decrypted first before passing to login hook
		$password = '';
		try {
			$password = $this->tokenProvider->getPassword($dbToken, $token);
		} catch (PasswordlessTokenException $ex) {
			// Ignore and use empty string instead
		}

		$user = $this->manager->get($uid);
		if ($user === null) {
			// user does not exist
			$this->emitFailedLogin($uid);
			return false;
		}

		$loginOk = $this->loginInOwnCloud('token', $user, $password);

		// set the app password
		if ($loginOk) {
			$this->session->set('app_password', $token);
		}

		return $loginOk;
	}

	/**
	 * Try to login a user, assuming authentication
	 * has already happened (e.g. via Single Sign On).
	 *
	 * Log in a user and regenerate a new session.
	 *
	 * @param \OCP\Authentication\IApacheBackend $apacheBackend
	 * @return bool
	 * @throws LoginException
	 */
	public function loginWithApache(IApacheBackend $apacheBackend) {
		$uidAndBackend = $apacheBackend->getCurrentUserId();
		if (\is_array($uidAndBackend)
			&& \count($uidAndBackend) === 2
			&& $uidAndBackend[0] !== ''
			&& $uidAndBackend[0] !== null
			&& $uidAndBackend[1] instanceof UserInterface
		) {
			list($uid, $backend) = $uidAndBackend;
		} elseif (\is_string($uidAndBackend)) {
			$uid = $uidAndBackend;
			if ($apacheBackend instanceof UserInterface) {
				$backend = $apacheBackend;
			} else {
				$this->logger->error('Apache backend failed to provide a valid backend for the user');
				return false;
			}
		} else {
			$this->logger->debug('No valid user detected from apache user backend');
			return false;
		}

		if ($this->getUser() !== null && $uid === $this->getUser()->getUID()) {
			return true; // nothing to do
		}
		$this->logger->debug(
			'regenerating session id for uid {uid}',
			[
				'app' => __METHOD__,
				'uid' => $uid
			]
		);
		$this->session->regenerateId();

		// Die here if not valid
		if (!$apacheBackend->isSessionActive()) {
			return false;
		}

		// Now we try to create the account or sync
		$this->userSyncService->createOrSyncAccount($uid, $backend);

		$user = $this->manager->get($uid);
		if ($user === null) {
			$this->emitFailedLogin($uid);
			return false;
		}

		$loginOk = $this->loginInOwnCloud('apache', $user, '');
		if ($loginOk) {
			$request = OC::$server->getRequest();
			$this->createSessionToken($request, $uid, $uid);
		}
		return $loginOk;
	}

	/**
	 * Create a new session token for the given user credentials
	 *
	 * @param IRequest $request
	 * @param string $uid user UID
	 * @param string $loginName login name
	 * @param string $password
	 * @return boolean
	 */
	public function createSessionToken(IRequest $request, $uid, $loginName, $password = null) {
		if ($this->manager->get($uid) === null) {
			// User does not exist
			return false;
		}
		$name = isset($request->server['HTTP_USER_AGENT']) ? $request->server['HTTP_USER_AGENT'] : 'unknown browser';
		try {
			$sessionId = $this->session->getId();
			$pwd = $this->getPassword($password);
			$this->tokenProvider->generateToken($sessionId, $uid, $loginName, $pwd, $name);
			return true;
		} catch (SessionNotAvailableException $ex) {
			// This can happen with OCC, where a memory session is used
			// if a memory session is used, we shouldn't create a session token anyway
			$this->logger->logException($ex, ['app' => __METHOD__]);
			return false;
		} catch (UniqueConstraintViolationException $ex) {
			$this->logger->error(
				'There are code paths that trigger the generation of an auth ' .
				'token for the same session twice. We log this to trace the code ' .
				'paths. Please send all log lines belonging to this request id.',
				['app' => __METHOD__]
			);
			$this->logger->logException($ex, ['app' => __METHOD__]);
			return true; // the session already has an auth token, go ahead.
		}
	}

	/**
	 * Invalidate the session token. This can be used if the session is lost but the user didn't log out,
	 * in order to clean up the previously created token. Note that this assumes that the session id
	 * is the same as the one that was used previously (if the session id is new, it shouldn't matter)
	 */
	public function invalidateSessionToken() {
		$sessionId = $this->session->getId();
		$this->tokenProvider->invalidateToken($sessionId);
	}

	/**
	 * Checks if the given password is a token.
	 * If yes, the password is extracted from the token.
	 * If no, the same password is returned.
	 *
	 * @param string $password either the login password or a device token
	 * @return string|null the password or null if none was set in the token
	 */
	private function getPassword($password) {
		if ($password === null) {
			// This is surely no token ;-)
			return null;
		}
		try {
			$token = $this->tokenProvider->getToken($password);
			try {
				return $this->tokenProvider->getPassword($token, $password);
			} catch (PasswordlessTokenException $ex) {
				return null;
			}
		} catch (InvalidTokenException $ex) {
			return $password;
		}
	}

	/**
	 * @param IToken $dbToken
	 * @param string $token
	 * @return boolean
	 */
	private function checkTokenCredentials(IToken $dbToken, $token) {
		// Check whether login credentials are still valid and the user was not disabled
		// This check is performed each 5 minutes per default
		// However, we try to read last_check_timeout from the appconfig table so the
		// administrator could change this 5 minutes timeout
		$lastCheck = $dbToken->getLastCheck() ? : 0;
		$now = $this->timeFactory->getTime();
		$last_check_timeout = \intval($this->config->getAppValue('core', 'last_check_timeout', 5));
		if ($lastCheck > ($now - 60 * $last_check_timeout)) {
			// Checked performed recently, nothing to do now
			return true;
		}
		$this->logger->debug(
			'checking credentials for token {token} with token id {tokenId}, last check at {lastCheck} was more than {last_check_timeout} min ago',
			[
				'app' => __METHOD__,
				'token' => $this->hashToken($token),
				'tokenId' => $dbToken->getId(),
				'lastCheck' => $lastCheck,
				'last_check_timeout' => $last_check_timeout
			]
		);

		try {
			$pwd = $this->tokenProvider->getPassword($dbToken, $token);
		} catch (InvalidTokenException $ex) {
			$this->logger->error(
				'An invalid token password was used for token {token} with token id {tokenId}',
				['app' => __METHOD__, 'token' => $this->hashToken($token), 'tokenId' => $dbToken->getId()]
			);
			$this->logger->logException($ex, ['app' => __METHOD__]);
			return false;
		} catch (PasswordlessTokenException $ex) {
			// Token has no password

			$dbToken->setLastCheck($now);
			$this->tokenProvider->updateToken($dbToken);
			return true;
		}
				
		// in case LDAP connection is temporary unavailable we do not want to invalidate the token
		$uid = $dbToken->getUID();
		$user = $this->manager->get($uid);
		if ($user->getBackendClassName() === 'LDAP' && $this->activeUser == null) {
			return true;
		}

		if ($this->manager->checkPassword($dbToken->getLoginName(), $pwd) === false) {
			$this->logger->debug(
				'password changed for user with login name {loginName}',
				[
					'app' => __METHOD__,
					'loginName' => $dbToken->getLoginName(),
				]
			);

			$this->tokenProvider->invalidateToken($token);
			// Password has changed or user was disabled -> log user out
			return false;
		}
		$dbToken->setLastCheck($now);
		$this->tokenProvider->updateToken($dbToken);
		return true;
	}

	/**
	 * Check if the given token exists and performs password/user-enabled checks
	 *
	 * Invalidates the token if checks fail
	 *
	 * @param string $token
	 * @param string $user login name
	 * @return boolean
	 */
	private function validateToken($token, $user = null) {
		try {
			$dbToken = $this->tokenProvider->getToken($token);
		} catch (InvalidTokenException $ex) {
			$this->logger->debug(
				'token {token}, not found',
				['app' => __METHOD__, 'token' => $this->hashToken($token)]
			);
			return false;
		}
		$this->logger->debug(
			'token {token} with token id {tokenId} found, validating',
			['app' => __METHOD__, 'token' => $this->hashToken($token), 'tokenId' => $dbToken->getId()]
		);

		// Check if login names match
		if ($user !== null && \strcasecmp($dbToken->getLoginName(), $user) !== 0) {
			// TODO: this makes it impossible to use different login names on browser and client
			// e.g. login by e-mail 'user@example.com' on browser for generating the token will not
			//      allow to use the client token with the login name 'user'.
			$this->logger->error(
				'user {user} does not match login {tokenLogin} of user {tokenUid} in token {token} with token id {tokenId}',
				[
					'app' => __METHOD__,
					'user' => $user,
					'tokenUid' => $dbToken->getLoginName(),
					'tokenLogin' => $dbToken->getLoginName(),
					'token' => $this->hashToken($token),
					'tokenId' => $dbToken->getId()
				]
			);
			return false;
		}

		if (!$this->checkTokenCredentials($dbToken, $token)) {
			$this->logger->error(
				'invalid credentials in token {token} with token id {tokenId}',
				[
					'app' => __METHOD__,
					'token' => $this->hashToken($token),
					'tokenId' => $dbToken->getId()
				]
			);
			return false;
		}

		$this->tokenProvider->updateTokenActivity($dbToken);

		return true;
	}

	/**
	 * Tries to login the user with auth token header
	 *
	 * @param IRequest $request
	 * @todo check remember me cookie
	 * @return boolean
	 * @throws LoginException
	 */
	public function tryTokenLogin(IRequest $request) {
		$authHeader = $request->getHeader('Authorization');
		if ($authHeader === null || \strpos($authHeader, 'token ') === false) {
			// No auth header, let's try session id
			try {
				$token = $this->session->getId();
			} catch (SessionNotAvailableException $ex) {
				return false;
			}
		} else {
			$token = \substr($authHeader, 6);
		}

		if ($this->validateToken($token)) {
			return $this->loginWithToken($token);
		}
		return false;
	}

	public function tryRememberMeLogin(IRequest $request) {
		$isRememberMe = $request->getCookie('oc_remember_login');
		$token = $request->getCookie('oc_token');
		$username = $request->getCookie('oc_username');
		if (!isset($isRememberMe, $token, $username)) {
			return false;
		}

		if (!\OC_Util::rememberLoginAllowed() || !$this->loginWithCookie($username, $token)) {
			$this->unsetMagicInCookie();
			return false;
		}

		$loggedInUser = $this->getUser();
		$this->createSessionToken($request, $loggedInUser->getUID(), $loggedInUser->getDisplayName());
		$this->logger->debug("{$loggedInUser->getUID()} logged in via rememberme token", ['app' => __METHOD__]);
		return true;
	}

	/**
	 * Tries to login with an AuthModule provided by an app
	 *
	 * @param IRequest $request The request
	 * @return bool True if request can be authenticated, false otherwise
	 * @throws Exception If the auth module could not be loaded
	 */
	public function tryAuthModuleLogin(IRequest $request) {
		foreach ($this->getAuthModules(false) as $authModule) {
			$user = $authModule->auth($request);
			if ($user !== null) {
				if (!$user->isEnabled()) {
					// injecting l10n does not work - there is a circular dependency between session and \OCP\L10N\IFactory
					$message = \OC::$server->getL10N('lib')->t('User disabled');
					throw new LoginException($message);
				}
				$uid = $user->getUID();
				$password = $authModule->getUserPassword($request);
				$loginOk = $this->loginUser($user, $password, \get_class($authModule));
				if ($loginOk) {
					$this->createSessionToken($request, $uid, $uid, $password);
					$this->session->set(Auth::DAV_AUTHENTICATED, $uid);
				}
				return $loginOk;
			}
		}

		return false;
	}

	/**
	 * Log an user in
	 *
	 * @param IUser $user The user
	 * @param String $password The user's password
	 * @param string $authModuleClass the classname of the module used to login
	 * @return boolean True if the user can be authenticated, false otherwise
	 * @throws LoginException if an app canceled the login process or the user is not enabled
	 */
	public function loginUser(IUser $user = null, $password = null, $authModuleClass = '') {
		if ($user === null) {
			$this->emitFailedLogin(null);
			return false;
		}
		// openidconnect calls the loginUser method. It might not have an $authModuleClass
		return $this->loginInOwnCloud($authModuleClass, $user, $password);
	}

	/**
	 * perform login using the magic cookie (remember login)
	 *
	 * @param string $uid the username
	 * @param string $currentToken
	 * @return bool
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function loginWithCookie($uid, $currentToken) {
		$this->logger->debug(
			'regenerating session id for uid {uid}, currentToken {currentToken}',
			['app' => __METHOD__, 'uid' => $uid, 'currentToken' => $currentToken]
		);
		$this->session->regenerateId();
		$user = $this->manager->get($uid);
		if ($user === null) {
			// user does not exist
			return false;
		}

		$hashedToken = \hash('snefru', $currentToken);
		// get stored tokens
		$storedTokenTime = $this->config->getUserValue($uid, 'login_token', $hashedToken, null);
		if ($storedTokenTime === null) {
			$this->emitFailedLogin($uid);
			return false;
		}

		// the current token will be deleted regardless success of failure
		$this->config->deleteUserValue($uid, 'login_token', $hashedToken);
		$cutoff = $this->timeFactory->getTime() - $this->config->getSystemValue('remember_login_cookie_lifetime', 60 * 60 * 24 * 15);
		if (\intval($storedTokenTime) < $cutoff) {
			// expired token already deleted
			$this->emitFailedLogin($uid);
			return false;
		}

		//login
		$loginOk = $this->loginInOwnCloud('cookie', $user, null, ['ignoreEvents' => true]);

		// replace successfully used token with a new one
		if ($loginOk) {
			$this->setNewRememberMeTokenForLoggedInUser();
		}

		return $loginOk;
	}

	/**
	 * @return bool true if logged in, false otherwise
	 * @throws LoginException
	 */
	private function loginInOwnCloud($loginType, $user, $password, $options = []) {
		$login = $user->getUID();

		// check the login policies first. It will throw a LoginException if needed
		// The LoginPolicyManager can't be injected due to cyclic dependency
		try {
			$loginPolicyManager = \OC::$server->getLoginPolicyManager();
			$loginPolicyManager->checkUserLogin($loginType, $user);
		} catch (LoginException $e) {
			$this->emitFailedLogin($login);
			throw $e;
		}

		if (!isset($options['ignoreEvents']) || !$options['ignoreEvents'] === true) {
			// loginWithCookie won't trigger these events
			$beforeEvent = new GenericEvent(null, ['loginType' => $loginType, 'login' => $login, 'uid' => $login, '_uid' => 'deprecated: please use \'login\', the real uid is not yet known', 'password' => $password]);
			$this->eventDispatcher->dispatch($beforeEvent, 'user.beforelogin');
			$this->manager->emit('\OC\User', 'preLogin', [$login, $password]);
		}

		$this->logger->info("login {$user->getUID()} using \"$loginType\" login type", ['app' => __METHOD__]);

		if ($user->isEnabled()) {
			$this->setUser($user);
			$this->setLoginName($login);
			$firstTimeLogin = $user->getLastLogin() === 0;

			if (!isset($options['ignoreEvents']) || !$options['ignoreEvents'] === true) {
				// loginWithCookie won't trigger these events
				$this->manager->emit('\OC\User', 'postLogin', [$user, $password]);
				$afterEvent = new GenericEvent(null, ['loginType' => $loginType, 'user' => $user, 'uid' => $user->getUID(), 'password' => $password]);
				$this->eventDispatcher->dispatch($afterEvent, 'user.afterlogin');
			}

			if ($this->isLoggedIn()) {
				$this->prepareUserLogin($firstTimeLogin);
				$user->updateLastLoginTimestamp();
				return true;
			}

			// injecting l10n does not work - there is a circular dependency between session and \OCP\L10N\IFactory
			$message = \OC::$server->getL10N('lib')->t('Login canceled by app');
			throw new LoginException($message);
		} else {
			$this->logger->info("login {$user->getUID()} cancelled. User disabled", ['app' => __METHOD__]);
		}

		// injecting l10n does not work - there is a circular dependency between session and \OCP\L10N\IFactory
		$message = \OC::$server->getL10N('lib')->t('User disabled');
		throw new LoginException($message);
	}

	public function setNewRememberMeTokenForLoggedInUser() {
		$user = $this->getUser();
		$uid = $user->getUID();
		$newToken = OC::$server->getSecureRandom()->generate(32);
		$hashedToken = \hash('snefru', $newToken);
		$this->config->setUserValue($uid, 'login_token', $hashedToken, $this->timeFactory->getTime());
		$this->setMagicInCookie($uid, $newToken);
	}

	/**
	 * Removes the remember me tokens associated to the current logged in user
	 * from the DB.
	 * This function will remove any obsolete token for the current logged in user.
	 * If a targetToken is passed, that token will also be removed (if it belongs to
	 * the current logged in user)
	 * @params string|null $targetToken if null, only obsolete tokens for the user
	 * will be removed, if a token is provided, obsolete tokens plus that token will
	 * be removed (always for the current user)
	 */
	public function clearRememberMeTokensForLoggedInUser($targetToken) {
		$user = $this->getUser();
		$uid = $user->getUID();
		if ($targetToken !== null) {
			$hashedToken = \hash('snefru', $targetToken);
		} else {
			$hashedToken = "";
		}

		$keys = $this->config->getUserKeys($uid, 'login_token');
		foreach ($keys as $key) {
			if ($key === $hashedToken) {
				$this->config->deleteUserValue($uid, 'login_token', $key);
			} else {
				$storedTokenTime = $this->config->getUserValue($uid, 'login_token', $key, null);
				$cutoff = $this->timeFactory->getTime() - $this->config->getSystemValue('remember_login_cookie_lifetime', 60 * 60 * 24 * 15);
				if (\intval($storedTokenTime) < $cutoff) {
					$this->config->deleteUserValue($uid, 'login_token', $key);
				}
			}
		}
	}

	/**
	 * logout the user from the session
	 *
	 * @return bool
	 */
	public function logout() {
		return $this->emittingCall(function () {
			$event = new GenericEvent(null, ['cancel' => false]);
			$this->eventDispatcher->dispatch($event, '\OC\User\Session::pre_logout');

			$this->manager->emit('\OC\User', 'preLogout');

			if ($event['cancel'] === true) {
				return true;
			}

			$this->manager->emit('\OC\User', 'logout');
			try {
				$this->tokenProvider->invalidateToken($this->session->getId());
			} catch (SessionNotAvailableException $ex) {
				$this->logger->logException($ex, ['app' => __METHOD__]);
			}
			$this->setUser(null);
			$this->setLoginName(null);
			$this->unsetMagicInCookie();
			OC::$server->getSessionCryptoWrapper()->deleteCookie();
			$this->session->clear();
			$this->manager->emit('\OC\User', 'postLogout');
			return true;
		}, ['before' => ['uid' => ''], 'after' => ['uid' => '']], 'user', 'logout');
	}

	/**
	 * Set cookie value to use in next page load
	 *
	 * @param string $username username to be set
	 * @param string $token
	 */
	public function setMagicInCookie($username, $token) {
		$webRoot = \OC::$WEBROOT;
		if ($webRoot === '') {
			$webRoot = '/';
		}
		$secureCookie = OC::$server->getRequest()->getServerProtocol() === 'https';
		$expires = $this->timeFactory->getTime() + OC::$server->getConfig()->getSystemValue('remember_login_cookie_lifetime', 60 * 60 * 24 * 15);
		$cookieOpts = [
			'expires' => $expires,
			'path' => $webRoot,
			'domain' => '',
			'secure' => $secureCookie,
			'httponly' => true,
			'samesite' => 'strict',
		];
		\setcookie('oc_username', $username, $cookieOpts);
		\setcookie('oc_token', $token, $cookieOpts);
		\setcookie('oc_remember_login', '1', $cookieOpts);
	}

	/**
	 * Remove cookie for "remember username"
	 */
	public function unsetMagicInCookie() {
		//TODO: DI for cookies and IRequest
		$webRoot = \OC::$WEBROOT;
		if ($webRoot === '') {
			$webRoot = '/';
		}

		$secureCookie = OC::$server->getRequest()->getServerProtocol() === 'https';

		unset($_COOKIE['oc_username'], $_COOKIE['oc_token'], $_COOKIE['oc_remember_login']); //TODO: DI

		$cookieOpts = [
			'expires' => $this->timeFactory->getTime() - 3600,
			'path' => $webRoot,
			'domain' => '',
			'secure' => $secureCookie,
			'httponly' => true,
			'samesite' => 'strict',
		];
		\setcookie('oc_username', '', $cookieOpts);
		\setcookie('oc_token', '', $cookieOpts);
		\setcookie('oc_remember_login', '', $cookieOpts);
	}

	/**
	 * Update password of the browser session token if there is one
	 *
	 * @param string $password
	 */
	public function updateSessionTokenPassword($password) {
		try {
			$sessionId = $this->session->getId();
			$token = $this->tokenProvider->getToken($sessionId);
			$this->tokenProvider->setPassword($token, $sessionId, $password);
		} catch (SessionNotAvailableException $ex) {
			// Nothing to do
		} catch (InvalidTokenException $ex) {
			// Nothing to do
		}
	}

	public function verifyAuthHeaders($request) {
		$shallLogout = false;
		try {
			$lastUser = null;
			foreach ($this->getAuthModules(true) as $module) {
				$user = $module->auth($request);
				if ($user !== null) {
					if ($this->isLoggedIn() && $this->getUser()->getUID() !== $user->getUID()) {
						$shallLogout = true;
						break;
					}
					if ($lastUser !== null && $user->getUID() !== $lastUser->getUID()) {
						$shallLogout = true;
						break;
					}
					$lastUser = $user;
				}
			}
		} catch (Exception $ex) {
			$shallLogout = true;
		}
		if ($shallLogout) {
			// the session is bad -> kill it
			$this->logout();
			return false;
		}
		return true;
	}

	/**
	 * @param $includeBuiltIn
	 * @return \Generator | IAuthModule[]
	 * @throws Exception
	 */
	protected function getAuthModules($includeBuiltIn) {
		if ($includeBuiltIn) {
			yield new TokenAuthModule($this->session, $this->tokenProvider, $this->manager);
		}

		$modules = $this->serviceLoader->load(['auth-modules']);
		foreach ($modules as $module) {
			if ($module instanceof IAuthModule) {
				yield $module;
			} else {
				continue;
			}
		}

		if ($includeBuiltIn) {
			yield new BasicAuthModule($this->config, $this->logger, $this->manager, $this->session, $this->timeFactory);
		}
	}

	/**
	 * This method triggers symfony event for failed login as well as
	 * emits via the emitter in user manager
	 * @param string $user
	 */
	protected function emitFailedLogin($user) {
		$this->manager->emit('\OC\User', 'failedLogin', [$user]);

		$loginFailedEvent = new GenericEvent(null, ['user' => $user]);
		$this->eventDispatcher->dispatch($loginFailedEvent, 'user.loginfailed');
	}

	/**
	 * @param string $token
	 * @return string
	 */
	private function hashToken($token) {
		$secret = $this->config->getSystemValue('secret');
		return \hash('sha512', $token . $secret);
	}
}
