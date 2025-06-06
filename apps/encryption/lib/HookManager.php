<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Clark Tomlinson <fallen013@gmail.com>
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

use OCA\Encryption\Hooks\Contracts\IHook;

class HookManager {
	private $hookInstances = [];

	/**
	 * @param array|IHook $instances
	 *        - This accepts either a single instance of IHook or an array of instances of IHook
	 * @return bool
	 */
	public function registerHook($instances) {
		if (\is_array($instances)) {
			foreach ($instances as $instance) {
				if (!$instance instanceof IHook) {
					return false;
				}
				$this->hookInstances[] = $instance;
			}
		} elseif ($instances instanceof IHook) {
			$this->hookInstances[] = $instances;
		}
		return true;
	}

	public function fireHooks() {
		foreach ($this->hookInstances as $instance) {
			/**
			 * Fire off the add hooks method of each instance stored in cache
			 *
			 * @var $instance IHook
			 */
			$instance->addHooks();
		}
	}
}
