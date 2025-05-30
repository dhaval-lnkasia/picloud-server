<?php
/**
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Piotr Mrowczynski <piotr@owncloud.com>
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

namespace OCP\Diagnostics;

use Doctrine\DBAL\Logging\SQLLogger;

/**
 * Interface IQueryLogger
 *
 * @package OCP\Diagnostics
 * @since 8.0.0
 */
interface IQueryLogger extends SQLLogger {
	/**
	 * Mark the start of a query providing query SQL statement, its parameters and types.
	 * This method should be called as close to the DB as possible and after
	 * query is finished finalized with stopQuery() method.
	 *
	 * @param string $sql
	 * @param array $params
	 * @param array $types
	 * @since 8.0.0
	 */
	public function startQuery($sql, array $params = null, array $types = null);

	/**
	 * Mark the end of the current active query. Ending query should store \OCP\Diagnostics\IQuery to
	 * be returned with getQueries() method.
	 *
	 * @return mixed
	 * @since 8.0.0
	 */
	public function stopQuery();

	/**
	 * This method should return all \OCP\Diagnostics\IQuery objects stored using
	 * startQuery()/stopQuery() methods.
	 *
	 * @return \OCP\Diagnostics\IQuery[]
	 * @since 8.0.0
	 */
	public function getQueries();

	/**
	 * Activate the module for the duration of the request. Deactivated module
	 * does not create and store \OCP\Diagnostics\IQuery objects.
	 * Only activated module should create and store objects to be
	 * returned with getQueries() call.
	 *
	 * @since 10.0.0
	 */
	public function activate();

	/**
	 * Clears out the query log
	 *
	 * @since 10.14.0
	 */
	public function flush(): void;
}
