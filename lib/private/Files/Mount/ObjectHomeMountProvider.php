<?php
/**
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
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

namespace OC\Files\Mount;

use OCP\Files\Config\IHomeMountProvider;
use OCP\Files\Storage\IStorageFactory;
use OCP\IConfig;
use OCP\IUser;

/**
 * Mount provider for object store home storages
 */
class ObjectHomeMountProvider implements IHomeMountProvider {
	/**
	 * @var IConfig
	 */
	private $config;

	/**
	 * ObjectStoreHomeMountProvider constructor.
	 *
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * Get the cache mount for a user
	 *
	 * @param IUser $user
	 * @param IStorageFactory $loader
	 * @return \OCP\Files\Mount\IMountPoint[]
	 */
	public function getHomeMountForUser(IUser $user, IStorageFactory $loader) {
		$config = $this->getMultiBucketObjectStoreConfig($user);
		if ($config === null) {
			$config = $this->getSingleBucketObjectStoreConfig($user);
		}

		if ($config === null) {
			return null;
		}

		return new MountPoint('\OC\Files\ObjectStore\HomeObjectStoreStorage', '/' . $user->getUID(), $config['arguments'], $loader);
	}

	/**
	 * @param IUser $user
	 * @return array|null
	 */
	private function getSingleBucketObjectStoreConfig(IUser $user) {
		$config = $this->config->getSystemValue('objectstore');
		if (!\is_array($config)) {
			return null;
		}

		// sanity checks
		if (empty($config['class'])) {
			\OCP\Util::writeLog('files', 'No class given for objectstore', \OCP\Util::ERROR);
		}
		if (!isset($config['arguments'])) {
			$config['arguments'] = [];
		}
		$config['arguments']['user'] = $user;
		// instantiate object store implementation
		$config['arguments']['objectstore'] = new $config['class']($config['arguments']);

		return $config;
	}

	/**
	 * @param IUser $user
	 * @return array|null
	 */
	private function getMultiBucketObjectStoreConfig(IUser $user) {
		$config = $this->config->getSystemValue('objectstore_multibucket');
		if (!\is_array($config)) {
			return null;
		}

		// sanity checks
		if (empty($config['class'])) {
			\OCP\Util::writeLog('files', 'No class given for objectstore', \OCP\Util::ERROR);
		}
		if (!isset($config['arguments'])) {
			$config['arguments'] = [];
		}
		$config['arguments']['user'] = $user;

		$bucket = $this->config->getUserValue($user->getUID(), 'homeobjectstore', 'bucket', null);

		if ($bucket === null) {
			/*
			 * Use any provided bucket argument as prefix
			 * and add the mapping from username => bucket
			 */
			if (!isset($config['arguments']['bucket'])) {
				$config['arguments']['bucket'] = '';
			}
			if (isset($config['arguments']['mapper-class'])) {
				$mapperClass = $config['arguments']['mapper-class'];
				$mapper = new $mapperClass($user);
			} else {
				$mapper = new \OC\Files\ObjectStore\Mapper($user);
			}
			$config['arguments']['bucket'] .= $mapper->getBucket();

			$this->config->setUserValue($user->getUID(), 'homeobjectstore', 'bucket', $config['arguments']['bucket']);
		} else {
			$config['arguments']['bucket'] = $bucket;
		}

		// instantiate object store implementation
		$config['arguments']['objectstore'] = new $config['class']($config['arguments']);

		return $config;
	}
}
