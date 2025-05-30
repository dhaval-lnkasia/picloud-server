<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCP\Encryption\Exceptions;
use OC\HintException;

/**
 * Class GenericEncryptionException
 *
 * @package OCP\Encryption\Exceptions
 * @since 8.1.0
 */
class GenericEncryptionException extends HintException {
	/**
	 * @param string $message
	 * @param string $hint
	 * @param int $code
	 * @param \Exception $previous
	 * @since 8.1.0
	 */
	public function __construct($message = '', $hint = '', $code = 0, \Exception $previous = null) {
		if (empty($message)) {
			$message = 'Unspecified encryption exception';
		}
		parent::__construct($message, $hint, $code, $previous);
	}
}
