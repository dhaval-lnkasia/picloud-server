<?php
/**
 * @author Robin Appelman <icewind@owncloud.com>
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

namespace OCA\Files_External\Command;

use OC\Core\Command\Base;
use OCP\Files\External\IStorageConfig;
use OCP\Files\External\NotFoundException;
use OCP\Files\External\Service\IGlobalStoragesService;
use OCP\IGroupManager;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Applicable extends Base {
	/**
	 * @var IGlobalStoragesService
	 */
	protected $globalService;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var IGroupManager
	 */
	private $groupManager;

	public function __construct(
		IGlobalStoragesService $globalService,
		IUserManager $userManager,
		IGroupManager $groupManager
	) {
		parent::__construct();
		$this->globalService = $globalService;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
	}

	protected function configure() {
		$this
			->setName('files_external:applicable')
			->setDescription('Manage applicable users and groups for a mount')
			->addArgument(
				'mount_id',
				InputArgument::REQUIRED,
				'The id of the mount to edit'
			)->addOption(
				'add-user',
				null,
				InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
				'user to add as applicable'
			)->addOption(
				'remove-user',
				null,
				InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
				'user to remove as applicable'
			)->addOption(
				'add-group',
				null,
				InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
				'group to add as applicable'
			)->addOption(
				'remove-group',
				null,
				InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
				'group to remove as applicable'
			)->addOption(
				'remove-all',
				null,
				InputOption::VALUE_NONE,
				'Set the mount to be globally applicable'
			);
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$mountId = $input->getArgument('mount_id');
		try {
			$mount = $this->globalService->getStorage($mountId);
		} catch (NotFoundException $e) {
			$output->writeln('<error>Mount with id "' . $mountId . ' not found, check "occ files_external:list" to get available mounts</error>');
			return 1;
		}

		if ($mount->getType() === IStorageConfig::MOUNT_TYPE_PERSONAl) {
			$output->writeln('<error>Can\'t change applicables on personal mounts</error>');
			return 1;
		}

		$addUsers = $input->getOption('add-user');
		$removeUsers = $input->getOption('remove-user');
		$addGroups = $input->getOption('add-group');
		$removeGroups = $input->getOption('remove-group');

		$applicableUsers = $mount->getApplicableUsers();
		$applicableGroups = $mount->getApplicableGroups();

		if ((\count($addUsers) + \count($removeUsers) + \count($addGroups) + \count($removeGroups) > 0) || $input->getOption('remove-all')) {
			foreach ($addUsers as $addUser) {
				if (!$this->userManager->userExists($addUser)) {
					$output->writeln('<error>User "' . $addUser . '" not found</error>');
					return 1;
				}
			}
			foreach ($addGroups as $addGroup) {
				if (!$this->groupManager->groupExists($addGroup)) {
					$output->writeln('<error>Group "' . $addGroup . '" not found</error>');
					return 1;
				}
			}

			if ($input->getOption('remove-all')) {
				$applicableUsers = [];
				$applicableGroups = [];
			} else {
				$applicableUsers = \array_unique(\array_merge($applicableUsers, $addUsers));
				$applicableUsers = \array_values(\array_diff($applicableUsers, $removeUsers));
				$applicableGroups = \array_unique(\array_merge($applicableGroups, $addGroups));
				$applicableGroups = \array_values(\array_diff($applicableGroups, $removeGroups));
			}
			$mount->setApplicableUsers($applicableUsers);
			$mount->setApplicableGroups($applicableGroups);
			$this->globalService->updateStorage($mount);
		}

		$this->writeArrayInOutputFormat($input, $output, [
			'users' => $applicableUsers,
			'groups' => $applicableGroups
		]);
		return 0;
	}
}
