<?php
/**
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
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

namespace OCP\Lock;

/**
 * Class LockedException
 *
 * @package OCP\Lock
 * @since 8.1.0
 */
class LockedException extends \Exception {
	/**
	 * Locked path
	 *
	 * @var string
	 */
	private $path;

	/**
	 * LockedException constructor.
	 *
	 * @param string $path locked path
	 * @param \Exception $previous previous exception for cascading
	 *
	 * @since 8.1.0
	 */
	public function __construct($path, \Exception $previous = null) {
		$message = \OC::$server->getL10N('lib')->t('"%s" is locked', $path);
		parent::__construct($message, 0, $previous);
		$this->path = $path;
	}

	/**
	 * @return string
	 * @since 8.1.0
	 */
	public function getPath() {
		return $this->path;
	}
}
