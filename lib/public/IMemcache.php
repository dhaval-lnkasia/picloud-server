<?php
/**
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

/**
 * Public interface of ownCloud for apps to use.
 * Cache interface
 *
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes
namespace OCP;

/**
 * This interface defines method for accessing the file based user cache.
 *
 * @since 8.1.0
 */
interface IMemcache extends ICache {
	/**
	 * Set a value in the cache if it's not already stored
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl Time To Live in seconds. Defaults to 60*60*24
	 * @return bool
	 * @since 8.1.0
	 */
	public function add($key, $value, $ttl = 0);

	/**
	 * Increase a stored number
	 *
	 * @param string $key
	 * @param int $step
	 * @return int | bool
	 * @since 8.1.0
	 */
	public function inc($key, $step = 1);

	/**
	 * Decrease a stored number
	 *
	 * @param string $key
	 * @param int $step
	 * @return int | bool
	 * @since 8.1.0
	 */
	public function dec($key, $step = 1);

	/**
	 * Compare and set
	 *
	 * @param string $key the key to check
	 * @param mixed $old the expected value to compare against what is currently set.
	 * If the "old" value matches, the "new" value will be set. If it doesn't match,
	 * the "new" value will be ignored.
	 * @param mixed $new the "new" value we want to set if the comparison matches
	 * @return bool true if the "new" value is effectively set by this client. Note that
	 * if the value is set by other clients, this method will return false.
	 * @since 8.1.0
	 */
	public function cas($key, $old, $new);

	/**
	 * Compare and delete
	 *
	 * @param string $key
	 * @param mixed $old
	 * @return bool
	 * @since 8.1.0
	 */
	public function cad($key, $old);
}
