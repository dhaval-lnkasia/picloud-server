<?php
/**
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

namespace OC\Settings\Panels\Admin;

use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Template;

class Apps implements ISettings {
	/** @var IConfig */
	protected $config;

	/**
	 * Apps constructor.
	 *
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	public function getPriority() {
		return 0;
	}

	public function getPanel() {
		$tmpl = new Template('settings', 'panels/admin/apps');
		return $tmpl;
	}

	public function getSectionID() {
		return 'apps';
	}
}
