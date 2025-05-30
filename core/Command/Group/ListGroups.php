<?php
/**
 * @author Phil Davis <phil@jankaritech.com>
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

namespace OC\Core\Command\Group;

use OC\Core\Command\Base;
use OCP\IGroup;
use OCP\IGroupManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class ListGroups extends Base {
	/** @var \OCP\IGroupManager */
	protected $groupManager;

	/**
	 * @param IGroupManager $groupManager
	 */
	public function __construct(IGroupManager $groupManager) {
		parent::__construct();
		$this->groupManager = $groupManager;
	}

	protected function configure() {
		parent::configure();

		$this
			->setName('group:list')
			->setDescription('List groups.')
			->addArgument(
				'search-pattern',
				InputArgument::OPTIONAL,
				'Restrict the list to groups whose name contains the string.'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$groupNameSubString = $input->getArgument('search-pattern');
		$groups = $this->groupManager->search($groupNameSubString, null, null, 'management', true);
		$groups = \array_map(function ($group) {
			/** @var IGroup $group */
			return $group->getGID();
		}, $groups);
		parent::writeArrayInOutputFormat($input, $output, $groups);
		return 0;
	}
}
