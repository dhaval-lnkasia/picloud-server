<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
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

namespace OC\Encryption\Exceptions;

use OCP\Encryption\Exceptions\GenericEncryptionException;

class ModuleAlreadyExistsException extends GenericEncryptionException {
	/**
	 * @param string $id
	 * @param string $name
	 */
	public function __construct($id, $name) {
		parent::__construct('Id "' . $id . '" already used by encryption module "' . $name . '"');
	}
}
