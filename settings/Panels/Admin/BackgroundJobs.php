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

use OCP\Settings\ISettings;
use OCP\Template;
use OCP\IConfig;

class BackgroundJobs implements ISettings {
	protected $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	public function getPriority() {
		return 0;
	}

	public function getPanel() {
		$tmpl = new Template('settings', 'panels/admin/backgroundjobs');
		$tmpl->assign('cron_log', $this->config->getSystemValue('cron_log', true));
		$tmpl->assign('lastcron', $this->config->getAppValue('core', 'lastcron', false));
		$tmpl->assign('backgroundjobs_mode', $this->config->getAppValue('core', 'backgroundjobs_mode', 'ajax'));
		return $tmpl;
	}

	public function getSectionID() {
		return 'general';
	}
}
