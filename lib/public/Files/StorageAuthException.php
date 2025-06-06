<?php
/**
 * @author Jesús Macias <jmacias@solidgear.es>
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
namespace OCP\Files;

/**
 * Storage authentication exception
 * @since 9.0.0
 */
class StorageAuthException extends StorageNotAvailableException {
	/**
	 * StorageAuthException constructor.
	 *
	 * @param string $message
	 * @param \Exception $previous
	 * @since 9.0.0
	 */
	public function __construct($message = '', \Exception $previous = null) {
		$l = \OC::$server->getL10N('lib');
		parent::__construct($l->t('Storage unauthorized. %s', $message), self::STATUS_UNAUTHORIZED, $previous);
	}
}
