<?php
/**
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
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

namespace OCA\Files_Versions\AppInfo;

use OC\AllConfig;
use OCA\Files_Versions\Expiration;
use OCA\Files_Versions\FileHelper;
use OCA\Files_Versions\MetaStorage;
use OCA\Files_Versions\Storage;
use OCP\AppFramework\App;

class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('files_versions', $urlParams);

		$container = $this->getContainer();

		/*
		 * Register capabilities
		 */
		$container->registerCapability('OCA\Files_Versions\Capabilities');

		/*
		 * Register expiration
		 */
		$container->registerService('Expiration', function ($c) {
			return new Expiration(
				$c->query('ServerContainer')->getConfig(),
				$c->query('OCP\AppFramework\Utility\ITimeFactory')
			);
		});

		/*
		 * Register FileHelper
		 */
		$container->registerService('FileHelper', function ($c) {
			return new FileHelper();
		});

		/** @var AllConfig $config */
		$config = $container->query('ServerContainer')->getConfig();
		$metaEnabled = ($config->getSystemValue('file_storage.save_version_metadata', false) === true);
		if ($metaEnabled) {
			$container->registerService(
				MetaStorage::class,
				function ($c) {
					return new MetaStorage(
						$c->query('ServerContainer')->getConfig()->getSystemValue('datadirectory'),
						$c->query('FileHelper'),
					);
				}
			);

			Storage::enableMetaData($container->query(MetaStorage::class));
		}
	}
}
