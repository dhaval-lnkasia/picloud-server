<?php
/**
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCP\Notification;

/**
 * Interface IApp
 *
 * @package OCP\Notification
 * @since 9.0.0
 */
interface IApp {
	/**
	 * @param INotification $notification
	 * @return void
	 * @throws \InvalidArgumentException When the notification is not valid
	 * @since 9.0.0
	 */
	public function notify(INotification $notification);

	/**
	 * @param INotification $notification
	 * @return void
	 * @since 9.0.0
	 */
	public function markProcessed(INotification $notification);

	/**
	 * @param INotification $notification
	 * @return int
	 * @since 9.0.0
	 */
	public function getCount(INotification $notification);
}
