<?php
/**
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

namespace OCA\DAV\Command;

use OC\Files\View;
use OCA\DAV\Upload\UploadFolder;
use OCA\DAV\Upload\UploadHome;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupChunks extends Command {
	/** @var IUserManager */
	protected $userManager;

	/**
	 * @param IUserManager $userManager
	 */
	public function __construct(IUserManager $userManager) {
		parent::__construct();
		$this->userManager = $userManager;
	}

	protected function configure() {
		$this
			->setName('dav:cleanup-chunks')
			->setDescription('Cleanup outdated chunks')
			->addArgument(
				'minimum-age-in-days',
				InputArgument::OPTIONAL,
				'minimum age of uploads to cleanup (in days - minimum 2 days - maximum 100)',
				2
			)
			->addOption(
				'local',
				'l',
				InputOption::VALUE_NONE,
				'only delete chunks that exist on the local filesystem,
				this applies to setups with multiple servers connected to the same database and
				chunk folder is not shared among'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$checkUploadExistsLocal = $input->getOption('local') === true;
		$d = $input->getArgument('minimum-age-in-days');
		$d = \max(2, \min($d, 100));
		$cutOffTime = new \DateTime("$d days ago");
		$output->writeln("Cleaning chunks older than $d days({$cutOffTime->format('c')})");
		$this->userManager->callForSeenUsers(function (IUser $user) use ($output, $cutOffTime, $checkUploadExistsLocal) {
			\OC_Util::tearDownFS();
			\OC_Util::setupFS($user->getUID());

			$view = new View('/' . $user->getUID() . '/uploads');
			$home = new UploadHome(['user' => $user]);
			$uploads = $home->getChildren();
			$filteredUploads = [];

			foreach ($uploads as $upload) {
				/** @var UploadFolder $upload */
				if ($upload->getLastModified() >= $cutOffTime->getTimestamp()) {
					continue;
				}

				if ($checkUploadExistsLocal === true &&
					$view->file_exists($upload->getName()) !== true) {
					continue;
				}

				$filteredUploads[] = $upload;
			}

			if (empty($filteredUploads)) {
				return;
			}

			$output->writeln(sprintf("Cleaning %d chunks for %s", \count($filteredUploads), $user->getUID()));

			$p = new ProgressBar($output);
			$p->start(\count($filteredUploads));

			$failures = [];
			foreach ($filteredUploads as $upload) {
				$p->advance();
				/** @var UploadFolder $upload */
				try {
					$upload->delete();
				} catch (\Exception $e) {
					$failures[$upload->getName()] = $e->getMessage();
				}
			}

			$p->finish();
			$output->writeln('');
			if (\count($failures) > 0) {
				$output->writeln("<error>there were problems deleting the following folders:</error>");
				foreach ($failures as $key => $value) {
					$output->writeln("<error>{$key} -> {$value}</error>");
				}
			}
		});
		return 0;
	}
}
