<?php
/**
 * @author Lukas Reschke <lukas@statuscode.ch>
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
use OC\IntegrityCheck\Helpers\FileAccessHelper;
use phpseclib3\Crypt\RSA;
use phpseclib3\File\X509;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SignCore
 *
 * @package OC\Core\Command\Integrity
 */
class SignCore extends Command {
	/** @var Checker */
	private $checker;
	/** @var FileAccessHelper */
	private $fileAccessHelper;

	/**
	 * @param Checker $checker
	 * @param FileAccessHelper $fileAccessHelper
	 */
	public function __construct(
		Checker $checker,
		FileAccessHelper $fileAccessHelper
	) {
		parent::__construct(null);
		$this->checker = $checker;
		$this->fileAccessHelper = $fileAccessHelper;
	}

	protected function configure() {
		$this
			->setName('integrity:sign-core')
			->setDescription('Sign core using a private key.')
			->addOption('privateKey', null, InputOption::VALUE_REQUIRED, 'Path to private key to use for signing.')
			->addOption('certificate', null, InputOption::VALUE_REQUIRED, 'Path to certificate to use for signing.')
			->addOption('path', null, InputOption::VALUE_REQUIRED, 'Path of core to sign.');
	}

	/**
	 * {@inheritdoc }
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$privateKeyPath = $input->getOption('privateKey');
		$keyBundlePath = $input->getOption('certificate');
		$path = $input->getOption('path');
		if ($privateKeyPath === null || $keyBundlePath === null || $path === null) {
			$output->writeln('--privateKey, --certificate and --path are required.');
			return 1;
		}

		$privateKey = $this->fileAccessHelper->file_get_contents($privateKeyPath);
		$keyBundle = $this->fileAccessHelper->file_get_contents($keyBundlePath);

		if ($privateKey === false) {
			$output->writeln(\sprintf('Private key "%s" does not exist.', $privateKeyPath));
			return 1;
		}

		if ($keyBundle === false) {
			$output->writeln(\sprintf('Certificate "%s" does not exist.', $keyBundlePath));
			return 1;
		}

		/** @var RSA $rsa */
		/** @phan-suppress-next-line PhanUndeclaredMethod */
		$rsa = RSA::load($privateKey)->withHash('sha1');
		$x509 = new X509();
		$certificate = $x509->loadX509($keyBundle);

		try {
			$this->checker->writeCoreSignature($path, $certificate, $x509, $rsa);
			$output->writeln('Successfully signed "core"');
		} catch (\Exception $e) {
			$output->writeln('Error: ' . $e->getMessage());
			return 1;
		}
		return 0;
	}
}
