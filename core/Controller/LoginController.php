<?php
/**
 * @author Christoph Wurst <christoph@owncloud.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Semih Serhat Karakaya <karakayasemi@itu.edu.tr>
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

namespace OC\Core\Controller;

use OC\Authentication\TwoFactorAuth\Manager;
use OC\User\Session;
use OC_App;
use OC_Util;
use OC_User;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\License\ILicenseManager;
use OCP\IConfig;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;

class LoginController extends Controller {
	/** @var IUserManager */
	private $userManager;

	/** @var IConfig */
	private $config;

	/** @var ISession */
	private $session;

	/** @var Session */
	private $userSession;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var Manager */
	private $twoFactorManager;

	/** @var ILicenseManager */
	private $licenseManager;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IUserManager $userManager
	 * @param IConfig $config
	 * @param ISession $session
	 * @param Session $userSession
	 * @param IURLGenerator $urlGenerator
	 * @param Manager $twoFactorManager
	 */
	public function __construct(
		$appName,
		IRequest $request,
		IUserManager $userManager,
		IConfig $config,
		ISession $session,
		Session $userSession,
		IURLGenerator $urlGenerator,
		Manager $twoFactorManager,
		ILicenseManager $licenseManager
	) {
		parent::__construct($appName, $request);
		$this->userManager = $userManager;
		$this->config = $config;
		$this->session = $session;
		$this->userSession = $userSession;
		$this->urlGenerator = $urlGenerator;
		$this->twoFactorManager = $twoFactorManager;
		$this->licenseManager = $licenseManager;
	}

	/**
	 * @NoAdminRequired
	 * @UseSession
	 *
	 * @return RedirectResponse
	 */
	public function logout() {
		$loginToken = $this->request->getCookie('oc_token');
		$this->userSession->clearRememberMeTokensForLoggedInUser($loginToken);
		$this->userSession->logout();

		return new RedirectResponse($this->urlGenerator->linkToRouteAbsolute('core.login.showLoginForm'));
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @UseSession
	 *
	 * @param string $user
	 * @param string $redirect_url
	 * @param string $remember_login
	 *
	 * @return TemplateResponse|RedirectResponse
	 */
	public function showLoginForm($user, $redirect_url, $remember_login) {
		// check if there is apache auth backend available and try to obtain session,
		// if apache backend not registered or failed to login, proceed with show login form
		if ($this->handleApacheAuth()) {
			// apache auth was completed server-side and there is active session,
			// initiate login success redirect on client-side
			//
			// NOTE: as of https://github.com/owncloud/core/pull/31054 to allow alternative login methods
			// one needs to setup https://doc.owncloud.com/server/next/admin_manual/enterprise/user_management/user_auth_shibboleth.html#other-login-mechanisms
			// so that on first login there is no valid apache session, and on click to alternative login from config 'login.alternatives'
			// code reaches handleApacheAuth here after redirect from SSO/SAML provider
			// NOTE: this redirect page is required to correctly preserve session on client, doing redirect using RedirectResponse could cause
			// some apps not being able to load correctly (https://github.com/owncloud/enterprise/issues/5225).
			return new TemplateResponse(
				'core',
				'apacheauthredirect',
				[
					'redirect_url' => $this->getDefaultUrl()
				],
				'guest'
			);
		}

		// check if user logged in already and has active session
		if ($this->userSession->isLoggedIn() || $this->userSession->tryRememberMeLogin($this->request)) {
			// most likely user manually entered this page, redirect to default url
			return new RedirectResponse($this->getDefaultUrl());
		}
		
		$parameters = [];
		$loginMessages = $this->session->get('loginMessages');
		$errors = [];
		$messages = [];
		if (\is_array($loginMessages)) {
			list($errors, $messages) = $loginMessages;
		}
		$this->session->remove('loginMessages');
		foreach ($errors as $value) {
			$parameters[$value] = true;
		}

		$parameters['messages'] = $messages;
		if ($user !== null && $user !== '') {
			// if the user exists, replace the userid with the username, e.g. for LDAP accounts
			// that have the owncloud internal username set to a uuid.
			$u = $this->userManager->get($user);
			if ($u !== null) {
				$parameters['loginName'] = $u->getUserName();
			}
			if (!\is_string($parameters['loginName']) || $parameters['loginName'] === '') {
				$parameters['loginName'] = $user;
			}
			$parameters['user_autofocus'] = false;
		} else {
			$parameters['loginName'] = '';
			$parameters['user_autofocus'] = true;
		}
		if (!empty($redirect_url)) {
			$parameters['redirect_url'] = $redirect_url;
		}

		$parameters['canResetPassword'] = true;
		$parameters['resetPasswordLink'] = $this->config->getSystemValue('lost_password_link', '');
		if (!$parameters['resetPasswordLink']) {
			if ($user !== null && $user !== '') {
				$userObj = $this->userManager->get($user);
				if ($userObj instanceof IUser) {
					$parameters['canResetPassword'] = $userObj->canChangePassword();
				}
			}
		} elseif ($parameters['resetPasswordLink'] === 'disabled') {
			$parameters['canResetPassword'] = false;
		}

		$altLogins = OC_App::getAlternativeLogIns();
		$altLogins2 = $this->config->getSystemValue('login.alternatives');
		if (\is_array($altLogins2) && !empty($altLogins2)) {
			$altLogins = \array_merge($altLogins, $altLogins2);
		}
		$parameters['alt_login'] = $altLogins;
		$parameters['rememberLoginAllowed'] = OC_Util::rememberLoginAllowed();
		$parameters['rememberLoginState'] = !empty($remember_login) ? $remember_login : 0;

		if ($user !== null && $user !== '') {
			$parameters['loginName'] = $user;
			$parameters['user_autofocus'] = false;
		} else {
			$parameters['loginName'] = '';
			$parameters['user_autofocus'] = true;
		}

		/**
		 * If redirect_url is not empty and remember_login is null and
		 * user not logged in and check if the string
		 * webroot+"/index.php/f/" is in redirect_url then
		 * user is trying to access files for which he needs to login.
		 */

		if (!empty($redirect_url) && ($remember_login === null) &&
			($this->userSession->isLoggedIn() === false) &&
			(\strpos(
				$this->urlGenerator->getAbsoluteURL(\urldecode($redirect_url)),
				$this->urlGenerator->getAbsoluteURL('/index.php/f/')
			) !== false)) {
			$parameters['accessLink'] = true;
		}

		$licenseMessageInfo = $this->licenseManager->getLicenseMessageFor('core');
		// show license message only if there is a license
		$licenseState = $licenseMessageInfo['license_state'];
		if ($licenseState !== ILicenseManager::LICENSE_STATE_MISSING) {
			// license type === 1 implies it's a demo license
			if ($licenseMessageInfo['type'] === 1 ||
				($licenseState !== ILicenseManager::LICENSE_STATE_VALID &&
					$licenseState !== ILicenseManager::LICENSE_STATE_ABOUT_TO_EXPIRE)
			) {
				$parameters['licenseMessage'] = \implode('<br/>', $licenseMessageInfo['translated_message']);
			}
		}

		$parameters['strictLoginEnforced'] = $this->config->getSystemValue('strict_login_enforced', false);

		// start the login flow for auth backends (including alternative logins if registered)
		return new TemplateResponse(
			$this->appName,
			'login',
			$parameters,
			'guest'
		);
	}

	/**
	 * @PublicPage
	 * @UseSession
	 *
	 * @param string $user
	 * @param string $password
	 * @param string $redirect_url
	 * @param string $timezone
	 * @param string $remember_login "1" implies we should remember the login; not present (or null)
	 * implies we shouldn't need to do anything
	 * @return RedirectResponse
	 * @throws \OCP\PreConditionNotMetException
	 * @throws \OC\User\LoginException
	 */
	public function tryLogin($user, $password, $redirect_url, $timezone = null, $remember_login = null) {
		$originalUser = $user;
		// TODO: Add all the insane error handling
		$loginResult = $this->userSession->login($user, $password);
		if ($loginResult !== true && $this->config->getSystemValue('strict_login_enforced', false) !== true) {
			$users = $this->userManager->getByEmail($user);
			// we only allow login by email if unique
			if (\count($users) === 1) {
				$user = $users[0]->getUID();
				$loginResult = $this->userSession->login($user, $password);
			}
		}
		if ($loginResult !== true) {
			$this->session->set('loginMessages', [
				['invalidpassword'], []
			]);
			$args = [];
			// Read current user and append if possible - we need to return the unmodified user otherwise we will leak the login name
			if ($user !== null) {
				$args['user'] = $originalUser;
			}
			// keep the redirect url
			if (!empty($redirect_url)) {
				$args['redirect_url'] = $redirect_url;
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('core.login.showLoginForm', $args));
		}
		/* @var $userObject IUser */
		$userObject = $this->userSession->getUser();
		// TODO: remove password checks from above and let the user session handle failures
		// requires https://github.com/owncloud/core/pull/24616
		$this->userSession->createSessionToken($this->request, $userObject->getUID(), $user, $password);
		if ($remember_login) {
			$this->userSession->setNewRememberMeTokenForLoggedInUser();
		}

		// User has successfully logged in, now remove the password reset link, when it is available
		$this->config->deleteUserValue($userObject->getUID(), 'owncloud', 'lostpassword');

		// Save the timezone
		if ($timezone !== null) {
			$this->config->setUserValue($userObject->getUID(), 'core', 'timezone', $timezone);
		}

		if ($this->twoFactorManager->isTwoFactorAuthenticated($userObject)) {
			$this->twoFactorManager->prepareTwoFactorLogin($userObject);
			if ($redirect_url !== null) {
				return new RedirectResponse($this->urlGenerator->linkToRoute('core.TwoFactorChallenge.selectChallenge', [
					'redirect_url' => $redirect_url
				]));
			}
			return new RedirectResponse($this->urlGenerator->linkToRoute('core.TwoFactorChallenge.selectChallenge'));
		}

		if ($redirect_url !== null && $this->userSession->isLoggedIn()) {
			$location = $this->urlGenerator->getAbsoluteURL(\urldecode($redirect_url));
			// Deny the redirect if the URL contains a @
			// This prevents unvalidated redirects like ?redirect_url=:user@domain.com
			if (\strpos($location, '@') === false) {
				return new RedirectResponse($location);
			}
		}

		return new RedirectResponse($this->getDefaultUrl());
	}

	/**
	 * @return boolean|null
	 */
	protected function handleApacheAuth() {
		return OC_User::handleApacheAuth();
	}

	/**
	 * @return string
	 */
	protected function getDefaultUrl() {
		return OC_Util::getDefaultPageUrl();
	}

	/**
	 * @return ISession
	 */
	public function getSession() {
		return $this->session;
	}
}
