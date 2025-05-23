<?php
/**
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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
namespace OC\Core\Command;

use OCP\IConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Check extends Base {
	/**
	 * @var IConfig
	 */
	private $config;

	public function __construct(IConfig $config) {
		parent::__construct();
		$this->config = $config;
	}

	protected function configure() {
		parent::configure();

		$this
			->setName('check')
			->setDescription('Check the server environment\'s dependencies.')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$errors = \OC_Util::checkServer($this->config);
		if (!empty($errors)) {
			$errors = \array_map(function ($item) {
				return (string) $item['error'];
			}, $errors);

			$this->writeArrayInOutputFormat($input, $output, $errors);
			return 1;
		}
		return 0;
	}
}
