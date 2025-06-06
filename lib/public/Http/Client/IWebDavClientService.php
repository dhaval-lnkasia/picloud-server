<?php
/**
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

namespace OCP\Http\Client;

use Sabre\HTTP\Client;

/**
 * Interface IWebDavClientService
 *
 * @package OCP\Http
 * @since 10.0.4
 */
interface IWebDavClientService {
	/**
	 * Settings are provided through the 'settings' argument. The following
	 * settings are supported:
	 *
	 *   * baseUri
	 *   * userName (optional)
	 *   * password (optional)
	 *   * proxy (optional)
	 *   * authType (optional)
	 *   * encoding (optional)
	 *
	 *  authType must be a bitmap, using self::AUTH_BASIC, self::AUTH_DIGEST
	 *  and self::AUTH_NTLM. If you know which authentication method will be
	 *  used, it's recommended to set it, as it will save a great deal of
	 *  requests to 'discover' this information.
	 *
	 *  Encoding is a bitmap with one of the ENCODING constants.
	 *
	 * @param $settings Sabre client settings
	 * @return Client
	 * @since 10.0.4
	 */
	public function newClient($settings);
}
