<?php
/**
 * @author Christoph Wurst <christoph@owncloud.com>
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

namespace OC\Core\Command\TwoFactorAuth;

use OC\Authentication\TwoFactorAuth\Manager;
use OC\User\Manager as UserManager;
use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Enable extends Base {
	/** @var Manager */
	private $manager;

	/** @var UserManager */
	private $userManager;

	public function __construct(Manager $manager, UserManager $userManager) {
		parent::__construct('twofactorauth:enable');
		$this->manager = $manager;
		$this->userManager = $userManager;
	}

	protected function configure() {
		parent::configure();

		$this->setName('twofactorauth:enable');
		$this->setDescription('Enable two-factor authentication for a user.');
		$this->addArgument('uid', InputArgument::REQUIRED);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$uid = $input->getArgument('uid');
		$user = $this->userManager->get($uid);
		if ($user === null) {
			$output->writeln("<error>Invalid UID</error>");
			return 1;
		}
		$this->manager->enableTwoFactorAuthentication($user);
		$output->writeln("Two-factor authentication enabled for user $uid");
		return 0;
	}
}
