<?php
/**
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 * @author Tom Needham <tom@owncloud.com>
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

namespace OCA\Files;

use OCP\Capabilities\ICapability;
use OCP\Files\FileInfo;
use OCP\IConfig;

/**
 * Class Capabilities
 *
 * @package OCA\Files
 */
class Capabilities implements ICapability {
	/** @var IConfig */
	protected $config;

	/**
	 * Capabilities constructor.
	 *
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * Return this classes capabilities
	 *
	 * @return array
	 */
	public function getCapabilities() {
		return [
			'checksums' => [
				'supportedTypes' => ['SHA1'],
				'preferredUploadType' => 'SHA1'
			],
			'files' => [
				'privateLinks' => true,
				'privateLinksDetailsParam' => true,
				'bigfilechunking' => true,
				'blacklisted_files' => $this->config->getSystemValue('blacklisted_files', ['.htaccess']),
				'blacklisted_files_regex' => FileInfo::BLACKLIST_FILES_REGEX,
				'favorites' => true,
				'file_locking_support' => true,
				'file_locking_enable_file_action' => (boolean)($this->config->getAppValue('files', 'enable_lock_file_action', 'no') === 'yes'),
			],
		];
	}
}
