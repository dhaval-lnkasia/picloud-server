<?php
/**
 * @author Thomas Heinisch <t.heinisch@bw-tech.de>
 * @author Felix Heidecke <felix@heidecke.me>
 * @author Ilja Neumann <ineumann@owncloud.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 *
 * @copyright Copyright (c) 2017, LNKASIA TECHSOL
 * @license GPL-2.0
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

use \OCA\Guests\AppInfo\Application;
use \OCA\Guests\Hooks;
use \OCP\Util;

$app = new Application();
$groupBackend = $app->registerBackend();
$app->registerListeners($groupBackend);

// this will initialize the
Util::addScript('guests', 'app');
Util::connectHook('\OCP\Config', 'js', Hooks::class, 'extendJsConfig');
