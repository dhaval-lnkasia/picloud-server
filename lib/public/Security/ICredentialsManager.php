<?php
/**
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OCP\Security;

/**
 * Store and retrieve credentials for external services
 *
 * @package OCP\Security
 * @since 8.2.0
 */
interface ICredentialsManager {
	/**
	 * Store a set of credentials
	 *
	 * @param string|null $userId Null for system-wide credentials
	 * @param string $identifier
	 * @param mixed $credentials
	 * @since 8.2.0
	 */
	public function store($userId, $identifier, $credentials);

	/**
	 * Retrieve a set of credentials
	 *
	 * @param string|null $userId Null for system-wide credentials
	 * @param string $identifier
	 * @return mixed
	 * @since 8.2.0
	 */
	public function retrieve($userId, $identifier);

	/**
	 * Delete a set of credentials
	 *
	 * @param string|null $userId Null for system-wide credentials
	 * @param string $identifier
	 * @return int rows removed
	 * @since 8.2.0
	 */
	public function delete($userId, $identifier);

	/**
	 * Erase all credentials stored for a user
	 *
	 * @param string $userId
	 * @return int rows removed
	 * @since 8.2.0
	 */
	public function erase($userId);
}
