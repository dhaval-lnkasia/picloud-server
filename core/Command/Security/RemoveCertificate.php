<?php
/**
 * @author Carla Schroder <carla@owncloud.com>
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

namespace OC\Core\Command\Security;

use OC\Core\Command\Base;
use OCP\ICertificateManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveCertificate extends Base {
	/** @var ICertificateManager */
	protected $certificateManager;

	public function __construct(ICertificateManager $certificateManager) {
		$this->certificateManager = $certificateManager;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('security:certificates:remove')
			->setDescription('Remove a trusted certificate.')
			->addArgument(
				'name',
				InputArgument::REQUIRED,
				'The file name of the certificate to remove.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$name = $input->getArgument('name');

		if ($this->certificateManager->removeCertificate($name)) {
			return 0;
		}

		$output->writeln('<error>certificate not found</error>');
		return 1;
	}
}
