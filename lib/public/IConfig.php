<?php
/**
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
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

/**
 * Public interface of ownCloud for apps to use.
 * Config interface
 *
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes
namespace OCP;

/**
 * Access to all the configuration options ownCloud offers
 * @since 6.0.0
 */
interface IConfig {
	/**
	 * @since 8.2.0
	 */
	public const SENSITIVE_VALUE = '***REMOVED SENSITIVE VALUE***';

	/**
	 * Sets and deletes system wide values
	 *
	 * @param array $configs Associative array with `key => value` pairs
	 *                       If value is null, the config key will be deleted
	 * @since 8.0.0
	 */
	public function setSystemValues(array $configs);

	/**
	 * Sets a new system wide value
	 *
	 * @param string $key the key of the value, under which will be saved
	 * @param mixed $value the value that should be stored
	 * @since 8.0.0
	 */
	public function setSystemValue($key, $value);

	/**
	 * Looks up a system wide defined value
	 *
	 * @param string $key the key of the value, under which it was saved
	 * @param mixed $default the default value to be returned if the value isn't set
	 * @return mixed the value or $default
	 * @since 6.0.0 - parameter $default was added in 7.0.0
	 */
	public function getSystemValue($key, $default = '');

	/**
	 * Looks up a system wide defined value and filters out sensitive data
	 *
	 * @param string $key the key of the value, under which it was saved
	 * @param mixed $default the default value to be returned if the value isn't set
	 * @return mixed the value or $default
	 * @since 8.2.0
	 */
	public function getFilteredSystemValue($key, $default = '');

	/**
	 * Delete a system wide defined value
	 *
	 * @param string $key the key of the value, under which it was saved
	 * @since 8.0.0
	 */
	public function deleteSystemValue($key);

	/**
	 * Get all keys stored for an app
	 *
	 * @param string $appName the appName that we stored the value under
	 * @return string[] the keys stored for the app
	 * @since 8.0.0
	 */
	public function getAppKeys($appName);

	/**
	 * Writes a new app wide value
	 *
	 * @param string $appName the appName that we want to store the value under
	 * @param string|float|int $key the key of the value, under which will be saved
	 * @param string $value the value that should be stored
	 * @return void
	 * @since 6.0.0
	 */
	public function setAppValue($appName, $key, $value);

	/**
	 * Looks up an app wide defined value
	 *
	 * @param string $appName the appName that we stored the value under
	 * @param string $key the key of the value, under which it was saved
	 * @param mixed $default the default value to be returned if the value isn't set
	 * @return mixed the saved value, or default
	 * @since 6.0.0 - parameter $default was added in 7.0.0
	 */
	public function getAppValue($appName, $key, $default = '');

	/**
	 * Delete an app wide defined value
	 *
	 * @param string $appName the appName that we stored the value under
	 * @param string $key the key of the value, under which it was saved
	 * @since 8.0.0
	 */
	public function deleteAppValue($appName, $key);

	/**
	 * Removes all keys in appconfig belonging to the app
	 *
	 * @param string $appName the appName the configs are stored under
	 * @since 8.0.0
	 */
	public function deleteAppValues($appName);

	/**
	 * Set a user defined value
	 *
	 * @param string $userId the userId of the user that we want to store the value under
	 * @param string $appName the appName that we want to store the value under
	 * @param string $key the key under which the value is being stored
	 * @param string|float|int $value the value that you want to store
	 * @param string $preCondition only update if the config value was previously the value passed as $preCondition
	 * @throws \OCP\PreConditionNotMetException if a precondition is specified and is not met
	 * @throws \UnexpectedValueException when trying to store an unexpected value
	 * @since 6.0.0 - parameter $precondition was added in 8.0.0
	 */
	public function setUserValue($userId, $appName, $key, $value, $preCondition = null);

	/**
	 * Shortcut for getting a user defined value
	 *
	 * @param string $userId the userId of the user that we want to store the value under
	 * @param string $appName the appName that we stored the value under
	 * @param string $key the key under which the value is being stored
	 * @param mixed $default the default value to be returned if the value isn't set
	 * @return mixed
	 * @since 6.0.0 - parameter $default was added in 7.0.0
	 */
	public function getUserValue($userId, $appName, $key, $default = '');

	/**
	 * Fetches a mapped list of userId -> value, for a specified app and key and a list of user IDs.
	 *
	 * @param string $appName app to get the value for
	 * @param string $key the key to get the value for
	 * @param array $userIds the user IDs to fetch the values for
	 * @return array Mapped values: userId => value
	 * @since 8.0.0
	 */
	public function getUserValueForUsers($appName, $key, $userIds);

	/**
	 * Get the keys of all stored by an app for the user
	 *
	 * @param string $userId the userId of the user that we want to store the value under
	 * @param string $appName the appName that we stored the value under
	 * @return string[]
	 * @since 8.0.0
	 */
	public function getUserKeys($userId, $appName);

	/**
	 * Delete a user value
	 *
	 * @param string $userId the userId of the user that we want to store the value under
	 * @param string $appName the appName that we stored the value under
	 * @param string $key the key under which the value is being stored
	 * @since 8.0.0
	 */
	public function deleteUserValue($userId, $appName, $key);

	/**
	 * Delete all user values
	 *
	 * @param string $userId the userId of the user that we want to remove all values from
	 * @since 8.0.0
	 */
	public function deleteAllUserValues($userId);

	/**
	 * Delete all user related values of one app
	 *
	 * @param string $appName the appName of the app that we want to remove all values from
	 * @since 8.0.0
	 */
	public function deleteAppFromAllUsers($appName);

	/**
	 * Determines the users that have the given value set for a specific app-key-pair
	 *
	 * @param string $appName the app to get the user for
	 * @param string $key the key to get the user for
	 * @param string $value the value to get the user for
	 * @return array of user IDs
	 * @since 8.0.0
	 */
	public function getUsersForUserValue($appName, $key, $value);

	/**
	 * In some environments the system config file is readonly. Find out if this
	 * is the case.
	 *
	 * @return boolean
	 * @since 10.0.3
	 */
	public function isSystemConfigReadOnly();
}
