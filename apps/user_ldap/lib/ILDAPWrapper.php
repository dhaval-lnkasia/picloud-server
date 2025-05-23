<?php
/**
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 *
 * @copyright Copyright (c) 2016, LNKASIA TECHSOL.
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

namespace OCA\User_LDAP;

interface ILDAPWrapper {
	// see http://php.net/manual/en/function.ldap-errno.php#20665
	// and https://ldapwiki.com/wiki/LDAP%20Result%20Codes
	public const LDAP_SUCCESS = 0x00;
	public const LDAP_OPERATIONS_ERROR = 0x01;
	public const LDAP_REFERRAL = 0x0a;
	public const LDAP_NO_SUCH_OBJECT = 0x20;
	public const LDAP_INAPPROPRIATE_AUTH = 0x30;
	public const LDAP_INVALID_CREDENTIALS = 0x31;

	//LDAP functions in use

	/**
	 * Bind to LDAP directory
	 * @param resource $link LDAP link resource
	 * @param string $dn an RDN to log in with
	 * @param string $password the password
	 * @return bool true on success, false otherwise
	 *
	 * with $dn and $password as null a anonymous bind is attempted.
	 */
	public function bind($link, $dn, $password);

	/**
	 * connect to an LDAP server
	 * @param string $host The host to connect to
	 * @param string $port The port to connect to
	 * @return mixed a link resource on success, otherwise false
	 */
	public function connect($host, $port);

	/**
	 * Send LDAP pagination control
	 * @param resource $link LDAP link resource
	 * @param int $pageSize number of results per page
	 * @param bool $isCritical Indicates whether the pagination is critical of not.
	 * @param string $cookie structure sent by LDAP server
	 * @return bool true on success, false otherwise
	 */
	public function controlPagedResult($link, $pageSize, $isCritical, $cookie);

	/**
	 * Retrieve the LDAP pagination cookie
	 * @param resource $link LDAP link resource
	 * @param resource $result LDAP result resource
	 * @param string $cookie structure sent by LDAP server
	 * @param int $estimated The estimated number of entries to retrieve.
	 * @return bool true on success, false otherwise
	 *
	 * Corresponds to ldap_control_paged_result_response
	 */
	public function controlPagedResultResponse($link, $result, &$cookie = null, &$estimated = null);

	/**
	 * Count the number of entries in a search
	 * @param resource $link LDAP link resource
	 * @param resource $result LDAP result resource
	 * @return int|false number of results on success, false otherwise
	 */
	public function countEntries($link, $result);

	/**
	 * Return the LDAP error number of the last LDAP command
	 * @param resource $link LDAP link resource
	 * @return string error message as string
	 */
	public function errno($link);

	/**
	 * Return the LDAP error message of the last LDAP command
	 * @param resource $link LDAP link resource
	 * @return int error code as integer
	 */
	public function error($link);

	/**
	 * Escape a string for use in an LDAP filter or DN
	 * @param string $value The value to escape.
	 * @param string $ignore Characters to ignore when escaping.
	 * @param int $flags The context the escaped string will be used in:
	 *   LDAP_ESCAPE_FILTER for filters to be used with ldap_search(), or
	 *   LDAP_ESCAPE_DN for DNs.
	 * @return string Returns the escaped string.
	 * @link http://www.php.net/manual/en/function.ldap-escape.php
	 */
	public function escape($value, $ignore = null, $flags = null);

	/**
	 * Splits DN into its component parts
	 * @param string $dn
	 * @param int $withAttrib
	 * @return array|false
	 * @link http://www.php.net/manual/en/function.ldap-explode-dn.php
	 */
	public function explodeDN($dn, $withAttrib);

	/**
	 * Return first result id
	 * @param resource $link LDAP link resource
	 * @param resource $result LDAP result resource
	 * @return Resource an LDAP search result resource
	 * */
	public function firstEntry($link, $result);

	/**
	 * Get attributes from a search result entry
	 * @param resource $link LDAP link resource
	 * @param resource $result LDAP result resource
	 * @return array containing the results, false on error
	 * */
	public function getAttributes($link, $result);

	/**
	 * Get the DN of a result entry
	 * @param resource $link LDAP link resource
	 * @param resource $result LDAP result resource
	 * @return string containing the DN, false on error
	 */
	public function getDN($link, $result);

	/**
	 * Get all result entries
	 * @param resource $link LDAP link resource
	 * @param resource $result LDAP result resource
	 * @return array containing the results, false on error
	 */
	public function getEntries($link, $result);

	/**
	 * Return next result id
	 * @param resource $link LDAP link resource
	 * @param resource $result LDAP entry result resource
	 * @return resource an LDAP search result resource
	 * */
	public function nextEntry($link, $result);

	/**
	 * Read an entry
	 * @param resource $link LDAP link resource
	 * @param string $baseDN The DN of the entry to read from
	 * @param string $filter An LDAP filter
	 * @param array $attr array of the attributes to read
	 * @return resource an LDAP search result resource
	 */
	public function read($link, $baseDN, $filter, $attr);

	/**
	 * Search LDAP tree
	 * @param resource $link LDAP link resource
	 * @param string $baseDN The DN of the entry to read from
	 * @param string $filter An LDAP filter
	 * @param array $attr array of the attributes to read
	 * @param int $attrsOnly optional, 1 if only attribute types shall be returned
	 * @param int $limit optional, limits the result entries
	 * @return resource|false|array an LDAP search result resource, false on error
	 */
	public function search($link, $baseDN, $filter, $attr, $attrsOnly = 0, $limit = 0);

	/**
	 * Sets the value of the specified option to be $value
	 * @param resource $link LDAP link resource
	 * @param string $option a defined LDAP Server option
	 * @param int $value the new value for the option
	 * @return bool true on success, false otherwise
	 */
	public function setOption($link, $option, $value);

	/**
	 * establish Start TLS
	 * @param resource $link LDAP link resource
	 * @return bool true on success, false otherwise
	 */
	public function startTls($link);

	/**
	 * Unbind from LDAP directory
	 * @param resource $link LDAP link resource
	 * @return bool true on success, false otherwise
	 */
	public function unbind($link);

	//additional required methods in ownCloud

	/**
	 * Checks whether the server supports LDAP
	 * @return bool true if it the case, false otherwise
	 * */
	public function areLDAPFunctionsAvailable();

	/**
	 * Checks whether PHP supports LDAP Paged Results
	 * @return bool true if it the case, false otherwise
	 * */
	public function hasPagedResultSupport();

	/**
	 * Checks whether the submitted parameter is a resource
	 * @param resource $resource the resource variable to check
	 * @return bool true if it is a resource, false otherwise
	 */
	public function isResource($resource);
}
