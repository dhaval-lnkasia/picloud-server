<?php
/**
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Patrick Paysant <patrick.paysant@linagora.com>
 * @author Philipp Schaffrath <github@philippschaffrath.de>
 * @author RealRancor <fisch.666@gmx.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

use OC\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

\define('OC_CONSOLE', 1);

function exceptionHandler($exception) {
	try {
		// try to log the exception
		\OC::$server->getLogger()->logException($exception, ['app' => 'index']);
	} catch (\Throwable $ex) {
		// if we can't log normally, use the crashLog
		\OC::crashLog($exception);
		\OC::crashLog($ex);
	} finally {
		// always show the exception in the console
		echo 'An unhandled exception has been thrown:' . PHP_EOL;
		echo $exception;
		exit(1);
	}
}
try {
	require_once __DIR__ . '/lib/base.php';

	// set to run indefinitely if needed
	\set_time_limit(0);

	if (!OC::$CLI) {
		echo "This script can be run from the command line only" . PHP_EOL;
		exit(1);
	}

	\set_exception_handler('exceptionHandler');

	if (!\function_exists('posix_getuid')) {
		echo "The posix extensions are required - see http://php.net/manual/en/book.posix.php" . PHP_EOL;
		exit(1);
	}
	$user = \posix_getpwuid(\posix_getuid());
	$configUser = \posix_getpwuid(\fileowner(OC::$configDir . 'config.php'));
	if ($user['name'] !== $configUser['name']) {
		echo "Console has to be executed with the user that owns the file config/config.php" . PHP_EOL;
		echo "Current user: " . $user['name'] . PHP_EOL;
		echo "Owner of config.php: " . $configUser['name'] . PHP_EOL;
		echo "Try adding 'sudo -u " . $configUser['name'] . " ' to the beginning of the command (without the single quotes)" . PHP_EOL;
		exit(1);
	}

	$oldWorkingDir = \getcwd();
	if ($oldWorkingDir === false) {
		echo "This script can be run from the ownCloud root directory only." . PHP_EOL;
		echo "Can't determine current working dir - the script will continue to work but be aware of the above fact." . PHP_EOL;
	} elseif ($oldWorkingDir !== __DIR__ && !\chdir(__DIR__)) {
		echo "This script can be run from the ownCloud root directory only." . PHP_EOL;
		echo "Can't change to ownCloud root directory." . PHP_EOL;
		exit(1);
	}

	if (!\function_exists('pcntl_signal') && !\in_array('--no-warnings', $argv)) {
		echo "The process control (PCNTL) extensions are required in case you want to interrupt long running commands - see http://php.net/manual/en/book.pcntl.php" . PHP_EOL;
	}

	$application = new Application(\OC::$server->getConfig(), \OC::$server->getEventDispatcher(), \OC::$server->getRequest());
	$application->loadCommands(new ArgvInput(), new ConsoleOutput());
	$application->run();
} catch (\Throwable $ex) {
	exceptionHandler($ex);
}
