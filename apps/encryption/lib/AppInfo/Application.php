<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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
namespace OCA\Encryption\AppInfo;

use OC\Encryption\Exceptions\ModuleAlreadyExistsException;
use OC\Files\View;
use OC\Helper\EnvironmentHelper;
use OCA\Encryption\Controller\RecoveryController;
use OCA\Encryption\Controller\SettingsController;
use OCA\Encryption\Controller\StatusController;
use OCA\Encryption\Crypto\Crypt;
use OCA\Encryption\Crypto\DecryptAll;
use OCA\Encryption\Crypto\EncryptAll;
use OCA\Encryption\Crypto\Encryption;
use OCA\Encryption\HookManager;
use OCA\Encryption\Hooks\UserHooks;
use OCA\Encryption\KeyManager;
use OCA\Encryption\Recovery;
use OCA\Encryption\Session;
use OCA\Encryption\Users\Setup;
use OCA\Encryption\Util;
use OCA\Encryption\Crypto\CryptHSM;
use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\QueryException;
use OCP\Encryption\IManager;
use OCP\IConfig;
use Symfony\Component\Console\Helper\QuestionHelper;

class Application extends App {
	/** @var IManager */
	private $encryptionManager;
	private IConfig $config;

	/**
	 * @throws QueryException
	 */
	public function __construct($urlParams = [], $encryptionSystemReady = true) {
		parent::__construct('encryption', $urlParams);
		$this->encryptionManager = \OC::$server->getEncryptionManager();
		$this->config = \OC::$server->getConfig();
		$this->registerServices();

		# in case neither master key nor user based key is enabled -> setup master-key
		if ($this->encryptionManager->isEnabled()) {
			$masterKeyEnabled = $this->config->getAppValue('encryption', 'useMasterKey', '');
			$userKeyEnabled = $this->config->getAppValue('encryption', 'userSpecificKey', '');
			if (($masterKeyEnabled === '') && ($userKeyEnabled === '')) {
				$this->config->setAppValue('encryption', 'useMasterKey', '1');
			}
		}

		if ($encryptionSystemReady === false) {
			/** @var Session $session */
			$session = $this->getContainer()->query('Session');
			$session->setStatus(Session::RUN_MIGRATION);
		}
		if ($this->encryptionManager->isEnabled() && $encryptionSystemReady) {
			/** @var Setup $setup */
			$setup = $this->getContainer()->query('UserSetup');
			$setup->setupSystem();
		}
	}

	/**
	 * register hooks
	 *
	 * @throws QueryException
	 */

	public function registerHooks(): void {
		if (!$this->config->getSystemValue('maintenance', false)) {
			$container = $this->getContainer();
			$server = $container->getServer();
			// Register our hooks and fire them.
			$hookManager = new HookManager();

			$hookManager->registerHook([
				new UserHooks(
					$container->query('KeyManager'),
					$server->getUserManager(),
					$server->getLogger(),
					$container->query('UserSetup'),
					$server->getUserSession(),
					$container->query('Util'),
					$container->query('Session'),
					$container->query('Crypt'),
					$container->query('Recovery'),
					$server->getConfig(),
					$server->getEventDispatcher()
				)
			]);

			$hookManager->fireHooks();
		} else {
			// Logout user if we are in maintenance to force re-login
			$this->getContainer()->getServer()->getUserSession()->logout();
		}
	}

	/**
	 * @throws ModuleAlreadyExistsException
	 */
	public function registerEncryptionModule(): void {
		$container = $this->getContainer();

		$this->encryptionManager->registerEncryptionModule(
			Encryption::ID,
			Encryption::DISPLAY_NAME,
			function () use ($container) {
				return new Encryption(
					$container->query('Crypt'),
					$container->query('KeyManager'),
					$container->query('Util'),
					$container->query('Session'),
					$container->query('EncryptAll'),
					$container->query('DecryptAll'),
					$container->getServer()->getLogger(),
					$container->getServer()->getL10N($container->getAppName())
				);
			}
		);
	}

	public function registerServices(): void {
		$container = $this->getContainer();

		$container->registerService(
			'Crypt',
			function (IAppContainer $c) {
				/** @var \OC\Server $server */
				$server = $c->getServer();

				if ($this->config->getAppValue('encryption', 'hsm.url', '') !== '') {
					$this->config->setAppValue('crypto.engine', 'internal', 'hsm');
				}

				if ($this->config->getAppValue('crypto.engine', 'internal', '') === 'hsm') {
					return new CryptHSM(
						$server->getLogger(),
						$server->getUserSession(),
						$server->getConfig(),
						$server->getL10N($c->getAppName()),
						$server->getHTTPClientService(),
						$server->getRequest(),
						/** @phan-suppress-next-line PhanUndeclaredMethod */
						$server->getTimeFactory()
					);
				}

				return new Crypt(
					$server->getLogger(),
					$server->getUserSession(),
					$server->getConfig(),
					$server->getL10N($c->getAppName())
				);
			}
		);

		$container->registerService(
			'Session',
			function (IAppContainer $c) {
				$server = $c->getServer();
				return new Session($server->getSession());
			}
		);

		$container->registerService(
			'KeyManager',
			function (IAppContainer $c) {
				$server = $c->getServer();

				return new KeyManager(
					$server->getEncryptionKeyStorage(),
					$c->query('Crypt'),
					$server->getConfig(),
					$server->getUserSession(),
					new Session($server->getSession()),
					$server->getLogger(),
					$c->query('Util')
				);
			}
		);

		$container->registerService(
			'Recovery',
			function (IAppContainer $c) {
				$server = $c->getServer();

				return new Recovery(
					$server->getUserSession(),
					$c->query('Crypt'),
					$server->getSecureRandom(),
					$c->query('KeyManager'),
					$server->getConfig(),
					$server->getEncryptionKeyStorage(),
					$server->getEncryptionFilesHelper(),
					new View()
				);
			}
		);

		$container->registerService('RecoveryController', function (IAppContainer $c) {
			$server = $c->getServer();
			return new RecoveryController(
				$c->getAppName(),
				$server->getRequest(),
				$server->getConfig(),
				$server->getL10N($c->getAppName()),
				$c->query('Recovery')
			);
		});

		$container->registerService('StatusController', function (IAppContainer $c) {
			$server = $c->getServer();
			return new StatusController(
				$c->getAppName(),
				$server->getRequest(),
				$server->getL10N($c->getAppName()),
				$c->query('Session')
			);
		});

		$container->registerService('SettingsController', function (IAppContainer $c) {
			$server = $c->getServer();
			return new SettingsController(
				$c->getAppName(),
				$server->getRequest(),
				$server->getL10N($c->getAppName()),
				$server->getUserManager(),
				$server->getUserSession(),
				$c->query('KeyManager'),
				$c->query('Crypt'),
				$c->query('Session'),
				$server->getSession(),
				$c->query('Util')
			);
		});

		$container->registerService(
			'UserSetup',
			function (IAppContainer $c) {
				$server = $c->getServer();
				return new Setup(
					$server->getLogger(),
					$server->getUserSession(),
					$c->query('Crypt'),
					$c->query('KeyManager')
				);
			}
		);

		$container->registerService(
			'Util',
			function (IAppContainer $c) {
				$server = $c->getServer();

				return new Util(
					new View(),
					$c->query('Crypt'),
					$server->getLogger(),
					$server->getUserSession(),
					$server->getConfig(),
					$server->getUserManager()
				);
			}
		);

		$container->registerService(
			'EncryptAll',
			function (IAppContainer $c) {
				$server = $c->getServer();
				return new EncryptAll(
					$c->query('UserSetup'),
					$c->getServer()->getUserManager(),
					new View(),
					$c->query('KeyManager'),
					$c->query('Util'),
					$server->getConfig(),
					$server->getMailer(),
					$server->getL10N('encryption'),
					new QuestionHelper(),
					$server->getSecureRandom()
				);
			}
		);

		$container->registerService(
			'DecryptAll',
			function (IAppContainer $c) {
				return new DecryptAll(
					$c->query('Util'),
					$c->query('KeyManager'),
					$c->query('Crypt'),
					$c->query('Session'),
					$c->getServer()->getUserManager(),
					new QuestionHelper(),
					new EnvironmentHelper()
				);
			}
		);
		$container->registerService('OCP\Encryption\Keys\IStorage', function (IAppContainer $c) {
			return $c->getServer()->getEncryptionKeyStorage();
		});
	}
}
