<?php
/**
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
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

namespace OCP\Http\Client;

/**
 * Interface IResponse
 *
 * @package OCP\Http
 * @since 8.1.0
 */
interface IResponse {
	/**
	 * @return string|resource
	 * @since 8.1.0
	 */
	public function getBody();

	/**
	 * @return int
	 * @since 8.1.0
	 */
	public function getStatusCode();

	/**
	 * @param $key
	 * @return string
	 * @since 8.1.0
	 */
	public function getHeader($key);

	/**
	 * @return array
	 * @since 8.1.0
	 */
	public function getHeaders();
}
