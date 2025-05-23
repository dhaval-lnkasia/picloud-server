<?php
/**
 *
 * @copyright Copyright (c) 2022, LNKASIA TECHSOL
 * @license OCL
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\FilesClassifier;

use OC\AppFramework\Utility\SimpleContainer;
use OCP\AppFramework\App;
use OCP\IL10N;
use OCP\ILogger;

class Application extends App {
	private static $isWrapperRegistered = false;

	private $appName;

	public function __construct($appName, array $urlParams = []) {
		$this->appName = $appName;
		parent::__construct($appName, $urlParams);
		$this->setup();
	}

	private function setup() {
		$container = $this->getContainer();
		$server = $container->getServer();
		$container->registerService(Handler::class, function (SimpleContainer $c) use ($server) {
			return new Handler(
				$server->getUserSession(),
				$server->getSystemTagManager(),
				$server->getSystemTagObjectMapper(),
				$server->getShareManager(),
				$server->getRootFolder(),
				$c->query(Persistence::class),
				$c->query(DocumentProperties::class),
				$c->query(IL10N::class),
				$c->query(ILogger::class)
			);
		});

		$handler = $container->query(Handler::class);

		$eventDispatcher = \OC::$server->getEventDispatcher();
		$eventDispatcher->addListener('file.beforerename', [$handler, 'preMoveAndCopy']);
		$eventDispatcher->addListener('file.beforecopy', [$handler, 'preMoveAndCopy']);
		$eventDispatcher->addListener('file.afterrename', [$handler, 'postMoveAndCopy']);
		$eventDispatcher->addListener('file.aftercopy', [$handler, 'postMoveAndCopy']);
		$eventDispatcher->addListener('file.aftercreate', [$handler, 'postWrite']);
		$eventDispatcher->addListener('file.afterupdate', [$handler, 'postWrite']);
		\OCP\Util::connectHook('OCP\Share', 'pre_shared', $handler, 'policyDisallowLinkShares');
		\OCP\Util::connectHook('\OC\Share', 'verifyExpirationDate', $handler, 'policyExpireLinkNoPassword');
		\OCP\Util::connectHook('OCP\Share', 'post_update_password', $handler, 'policyExpireLinkNoPasswordOnPasswordChange');
		\OCP\App::registerAdmin('files_classifier', 'settings/admin');
	}

	/**
	 * Add wrapper for local storages
	 *
	 * @return void
	 */
	public function setupWrapper() {
		$server = $this->getContainer()->getServer();
		if (self::$isWrapperRegistered === false
			&& $server->getRequest()->getMethod() === 'PUT'
		) {
			self::$isWrapperRegistered = true;
			\OC\Files\Filesystem::addStorageWrapper(
				'oc_files_classifier',
				function ($mountPoint, $storage) use ($server) {
					/**
					 * @var \OC\Files\Storage\Storage $storage
					 */
					if ($storage instanceof \OC\Files\Storage\Storage) {
						$wrapper = new ClassifierWrapper(
							[
								'storage' => $storage,
								'documentProperties' => $this->getContainer()->query(DocumentProperties::class),
								'handler' => $this->getContainer()->query(Handler::class),
								'userSession' =>  $server->getUserSession(),
								'tempManager' =>  $server->getTempManager(),
							]
						);
						return $wrapper;
					} else {
						return $storage;
					}
				},
				1
			);
		}
	}
}
