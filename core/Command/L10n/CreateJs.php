<?php
/**
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

namespace OC\Core\Command\L10n;

use DirectoryIterator;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

class CreateJs extends Command {
	protected function configure() {
		$this
			->setName('l10n:createjs')
			->setDescription('Create javascript translation files for a given app')
			->addArgument(
				'app',
				InputOption::VALUE_REQUIRED,
				'The name of the app.'
			)
			->addArgument(
				'lang',
				InputOption::VALUE_OPTIONAL,
				'The name of the language.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$app = $input->getArgument('app');
		$lang = $input->getArgument('lang');

		$path = \OC_App::getAppPath($app);
		if ($path === false) {
			$output->writeln("The app <$app> is unknown.");
			return 1;
		}
		$languages = $lang;
		if (empty($lang)) {
			$languages= $this->getAllLanguages($path);
		}

		foreach ($languages as $lang) {
			$this->writeFiles($app, $path, $lang, $output);
		}
		return 0;
	}

	private function getAllLanguages($path) {
		$result = [];
		foreach (new DirectoryIterator("$path/l10n") as $fileInfo) {
			if ($fileInfo->isDot()) {
				continue;
			}
			if ($fileInfo->isDir()) {
				continue;
			}
			if ($fileInfo->getExtension() !== 'php') {
				continue;
			}
			$result[]= \substr($fileInfo->getBasename(), 0, -4);
		}

		return $result;
	}

	private function writeFiles($app, $path, $lang, OutputInterface $output) {
		list($translations, $plurals) = $this->loadTranslations($path, $lang);
		$this->writeJsFile($app, $path, $lang, $output, $translations, $plurals);
		$this->writeJsonFile($path, $lang, $output, $translations, $plurals);
	}

	private function writeJsFile($app, $path, $lang, OutputInterface $output, $translations, $plurals) {
		$jsFile = "$path/l10n/$lang.js";
		if (\file_exists($jsFile)) {
			$output->writeln("File already exists: $jsFile");
			return;
		}
		$content = "OC.L10N.register(\n    \"$app\",\n    {\n    ";
		$jsTrans = [];
		foreach ($translations as $id => $val) {
			if (\is_array($val)) {
				$val = '[ ' . \join(',', $val) . ']';
			}
			$jsTrans[] = "\"$id\" : \"$val\"";
		}
		$content .= \join(",\n    ", $jsTrans);
		$content .= "\n},\n\"$plurals\");\n";

		\file_put_contents($jsFile, $content);
		$output->writeln("Javascript translation file generated: $jsFile");
	}

	private function writeJsonFile($path, $lang, OutputInterface $output, $translations, $plurals) {
		$jsFile = "$path/l10n/$lang.json";
		if (\file_exists($jsFile)) {
			$output->writeln("File already exists: $jsFile");
			return;
		}
		$content = ['translations' => $translations, 'pluralForm' => $plurals];
		\file_put_contents($jsFile, \json_encode($content));
		$output->writeln("Json translation file generated: $jsFile");
	}

	private function loadTranslations($path, $lang) {
		$phpFile = "$path/l10n/$lang.php";
		$TRANSLATIONS = [];
		$PLURAL_FORMS = '';
		if (!\file_exists($phpFile)) {
			throw new UnexpectedValueException("PHP translation file <$phpFile> does not exist.");
		}
		require $phpFile;

		return [$TRANSLATIONS, $PLURAL_FORMS];
	}
}
