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
use OCP\IURLGenerator;
use phpseclib3\Crypt\RSA;
use phpseclib3\File\X509;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SignApp
 *
 * @package OC\Core\Command\Integrity
 */
class SignApp extends Command {
	/** @var Checker */
	private $checker;
	/** @var FileAccessHelper */
	private $fileAccessHelper;
	/** @var IURLGenerator */
	private $urlGenerator;

	/**
	 * @param Checker $checker
	 * @param FileAccessHelper $fileAccessHelper
	 * @param IURLGenerator $urlGenerator
	 */
	public function __construct(
		Checker $checker,
		FileAccessHelper $fileAccessHelper,
		IURLGenerator $urlGenerator
	) {
		parent::__construct(null);
		$this->checker = $checker;
		$this->fileAccessHelper = $fileAccessHelper;
		$this->urlGenerator = $urlGenerator;
	}

	protected function configure() {
		$this
			->setName('integrity:sign-app')
			->setDescription('Signs an app using a private key.')
			->addOption('path', null, InputOption::VALUE_REQUIRED, 'Application to sign.')
			->addOption('privateKey', null, InputOption::VALUE_REQUIRED, 'Path to private key to use for signing.')
			->addOption('certificate', null, InputOption::VALUE_REQUIRED, 'Path to certificate to use for signing.');
	}

	/**
	 * {@inheritdoc }
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$path = $input->getOption('path');
		$privateKeyPath = $input->getOption('privateKey');
		$keyBundlePath = $input->getOption('certificate');
		if ($path === null || $privateKeyPath === null || $keyBundlePath === null) {
			$documentationUrl = $this->urlGenerator->linkToDocs('developer-code-integrity');
			$output->writeln('This command requires the --path, --privateKey and --certificate.');
			$output->writeln('Example: ./occ integrity:sign-app --path="/Users/lukasreschke/Programming/myapp/" --privateKey="/Users/lukasreschke/private/myapp.key" --certificate="/Users/lukasreschke/public/mycert.crt"');
			$output->writeln('For more information please consult the documentation: '. $documentationUrl);
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
			$this->checker->writeAppSignature($path, $certificate, $x509, $rsa);
			$output->writeln('Successfully signed "'.$path.'"');
		} catch (\Exception $e) {
			$output->writeln('Error: ' . $e->getMessage());
			return 1;
		}
		return 0;
	}
}
