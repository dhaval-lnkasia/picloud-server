<?php
/**
 * @author Carla Schroder <carla@owncloud.com>
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

namespace OC\Core\Command\Integrity;

use OC\IntegrityCheck\Checker;
use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CheckCore
 *
 * @package OC\Core\Command\Integrity
 */
class CheckCore extends Base {
	/**
	 * @var Checker
	 */
	private $checker;

	public function __construct(Checker $checker) {
		parent::__construct();
		$this->checker = $checker;
	}

	/**
	 * {@inheritdoc }
	 */
	protected function configure() {
		parent::configure();
		$this
			->setName('integrity:check-core')
			->setDescription('Check integrity of core code using a signature.');
	}

	/**
	 * {@inheritdoc }
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->checker->runInstanceVerification();
		$result = $this->checker->getResults();
		$this->writeArrayInOutputFormat($input, $output, $result);
		if (\count($result)>0) {
			return 1;
		}
		return 0;
	}
}
