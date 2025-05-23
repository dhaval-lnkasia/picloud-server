<?php
/**
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
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

namespace OC\Core\Command\User;

use OC\User\Account;
use OC\User\AccountMapper;
use OC\User\Backend;
use OC\User\Sync\BackendUsersIterator;
use OC\User\Sync\SeenUsersIterator;
use OC\User\SyncService;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;
use OCP\User\NotPermittedActionException;
use OCP\UserInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class SyncBackend extends Command {
	public const VALID_ACTIONS = ['disable', 'remove'];

	protected AccountMapper $accountMapper;
	private IConfig $config;
	private IUserManager $userManager;
	private ILogger $logger;

	/**
	 * @param AccountMapper $accountMapper
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param ILogger $logger
	 */
	public function __construct(
		AccountMapper $accountMapper,
		IConfig $config,
		IUserManager $userManager,
		ILogger $logger
	) {
		parent::__construct();
		$this->accountMapper = $accountMapper;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->logger = $logger;
	}

	protected function configure() {
		$this
			->setName('user:sync')
			->setDescription('Synchronize users from a given backend to the accounts table.')
			->addArgument(
				'backend-class',
				InputArgument::OPTIONAL,
				"The quoted PHP class name for the backend, eg" . PHP_EOL
						." - LDAP:\t\t\"OCA\User_LDAP\User_Proxy\"" . PHP_EOL
						." - Samba:\t\t\"OCA\User\SMB\"" . PHP_EOL
						." - Shibboleth:\t\"OCA\User_Shibboleth\UserBackend\"" . PHP_EOL
						."For easier usage of these three cases you can also use 'ldap', 'samba' or 'shibboleth"
			)
			->addOption(
				'list',
				'l',
				InputOption::VALUE_NONE,
				'List all enabled backend classes'
			)
			->addOption(
				'uid',
				'u',
				InputOption::VALUE_REQUIRED,
				'Sync only the user with the given user id'
			)
			->addOption(
				'seenOnly',
				's',
				InputOption::VALUE_NONE,
				'Sync only seen users'
			)
			->addOption(
				'showCount',
				'c',
				InputOption::VALUE_NONE,
				'Calculate user count before syncing'
			)
			->addOption(
				'missing-account-action',
				'm',
				InputOption::VALUE_REQUIRED,
				'Action to take if the account isn\'t connected to a backend any longer. Options are "disable" and "remove". Note that removing the account will also remove the stored data and files for that account.'
			)
			->addOption(
				're-enable',
				'r',
				InputOption::VALUE_NONE,
				'When syncing multiple accounts re-enable accounts that are disabled in ownCloud but available in the synced backend.'
			);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 * @throws NotPermittedActionException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		if ($input->getOption('list')) {
			$backends = $this->userManager->getBackends();
			foreach ($backends as $backend) {
				$output->writeln(\get_class($backend));
			}
			return 0;
		}
		$backendClassName = $input->getArgument('backend-class');
		if ($backendClassName === null) {
			$output->writeln('<error>No backend class name given. Please run ./occ help user:sync to understand how this command works.</error>');
			return 1;
		}
		$backend = $this->getBackend($backendClassName);
		if ($backend === null) {
			$output->writeln("<error>The backend <$backendClassName> does not exist. Did you forget to enable the app?</error>");
			return 1;
		}
		if (!$backend->hasUserListings()) {
			$output->writeln("<error>The backend <$backendClassName> does not allow user listing. No sync is possible</error>");
			return 1;
		}

		if ($input->getOption('missing-account-action') !== null) {
			$missingAccountsAction = $input->getOption('missing-account-action');
			if (!\in_array($missingAccountsAction, self::VALID_ACTIONS, true)) {
				$output->writeln('<error>Unknown action. Choose between "disable" or "remove"</error>');
				return 1;
			}
		} else {
			// ask (if possible) how to handle missing accounts. Disable the accounts by default.
			$helper = $this->getHelper('question');
			$question = new ChoiceQuestion(
				'If unknown users are found, what do you want to do with their accounts? (removing the account will also remove its data)',
				\array_merge(self::VALID_ACTIONS, ['ask later']),
				0
			);
			$missingAccountsAction = $helper->ask($input, $output, $question);
		}

		$syncService = new SyncService($this->config, $this->logger, $this->accountMapper);

		$uid = $input->getOption('uid');

		if ($uid) {
			$syncSuccess = $this->syncSingleUser($input, $output, $syncService, $backend, $uid, $missingAccountsAction);
		} else {
			$syncSuccess = $this->syncMultipleUsers($input, $output, $syncService, $backend, $missingAccountsAction);
		}

		if (!$syncSuccess) {
			return 1;
		}
		return 0;
	}

	/**
	 * Sync multiple users. Returns true if all users
	 * were successfully synced, and returns false if at least 1 user had error
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @param SyncService $syncService
	 * @param UserInterface $backend
	 * @param string $missingAccountsAction
	 * @return bool
	 * @throws NotPermittedActionException
	 */
	private function syncMultipleUsers(
		InputInterface $input,
		OutputInterface $output,
		SyncService $syncService,
		UserInterface $backend,
		$missingAccountsAction
	): bool {
		$output->writeln('Analysing known accounts ...');
		$p = new ProgressBar($output);
		[$removedUsers, $reappearedUsers] = $syncService->analyzeExistingUsers($backend, function () use ($p) {
			$p->advance();
		});
		$p->finish();
		$output->writeln('');
		$output->writeln('');

		$this->handleRemovedUsers($removedUsers, $input, $output, $missingAccountsAction);

		$output->writeln('');

		if ($input->getOption('re-enable')) {
			$this->reEnableUsers($reappearedUsers, $output);
		}

		$output->writeln('');
		$backendClass = \get_class($backend);
		if ($input->getOption('seenOnly')) {
			$output->writeln("Updating seen accounts from $backendClass ...");
			$iterator = new SeenUsersIterator($this->accountMapper, $backendClass);
		} else {
			$output->writeln("Inserting new and updating all known users from $backendClass ...");
			$iterator = new BackendUsersIterator($backend);
		}

		$progress = new ProgressBar($output);
		$max = null;
		if ($backend->implementsActions(Backend::COUNT_USERS) && $input->getOption('showCount')) {
			/* @phan-suppress-next-line PhanUndeclaredMethod */
			$max = $backend->countUsers();
		}
		$progress->start($max);

		$syncErrors = [];
		$syncService->run($backend, $iterator, function ($uid, $syncError) use ($progress, &$syncErrors) {
			if ($syncError !== null) {
				// NOTE: we cannot output here exception as this would break the progress bar
				$syncErrors[] = "Sync error for $uid: {$syncError->getMessage()}";
			}
			$progress->advance();
		});
		$progress->finish();
		$output->writeln('');

		foreach ($syncErrors as $syncError) {
			$output->writeln("<error>$syncError</error>");
		}

		$output->writeln('');
		$output->writeln('');

		$syncErrorCount = \count($syncErrors);
		$output->writeln("Sync of users finished, encountered $syncErrorCount errors.");
		return $syncErrorCount === 0;
	}

	/**
	 * Sync single user. Returns true if user
	 * was successfully synced, and returns false if there was an error
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @param SyncService $syncService
	 * @param UserInterface $backend
	 * @param string $uid
	 * @param string $missingAccountsAction
	 * @return bool
	 * @throws NotPermittedActionException
	 */
	private function syncSingleUser(
		InputInterface $input,
		OutputInterface $output,
		SyncService $syncService,
		UserInterface $backend,
		$uid,
		$missingAccountsAction
	): bool {
		$output->writeln("Searching for $uid ...");

		$iterator = new BackendUsersIterator($backend, $uid);
		$userToSync = null;
		foreach ($iterator as $userUid) {
			if ($userUid === $uid) {
				if ($userToSync === null) {
					$userToSync = $userUid;
				} else {
					$output->writeln("<error>Multiple users returned from backend for: $uid. Cancelling sync.</error>");
					return false;
				}
			}
		}

		$dummy = new Account(); // to prevent null pointer when writing messages

		if ($userToSync !== null) {
			$syncSuccess = true;
			// Run the sync using the internal username if mapped
			$output->writeln("Syncing $uid ...");
			$syncService->run($backend, new \ArrayIterator([$userToSync]), function ($uid, $syncError) use ($output, &$syncSuccess) {
				if ($syncError !== null) {
					$syncSuccess = false;
					$output->writeln("<error>Sync error for $uid : {$syncError->getMessage()}</error>");
				}
			});

			if (!$syncSuccess) {
				$output->writeln("Sync for $uid failed!");
				return false;
			}
			$output->writeln("Synced $uid ");
		} else {
			// Not found
			$output->writeln("Exact match for user $uid not found in the backend.");
			$this->handleRemovedUsers([$uid => $dummy], $input, $output, $missingAccountsAction);
		}

		$output->writeln('');

		if ($input->getOption('re-enable')) {
			$this->reEnableUsers([$uid => $dummy], $output);
		}

		return true;
	}
	/**
	 * @param $backend
	 * @return null|UserInterface
	 */
	private function getBackend($backend): ?UserInterface {
		switch ($backend) {
			case 'ldap':
				$backend = 'OCA\User_LDAP\User_Proxy';
				break;
			case 'samba':
				$backend = 'OCA\User\SMB';
				break;
			case 'shibboleth':
				$backend = 'OCA\User_Shibboleth\UserBackend';
				break;
		}
		$backends = $this->userManager->getBackends();
		$match = \array_filter($backends, static function ($b) use ($backend) {
			return \get_class($b) === $backend;
		});
		if (empty($match)) {
			return null;
		}
		return \array_pop($match);
	}

	/**
	 * @param array $uidToAccountMap a list of user ids to account objects
	 * @param callable $callbackExists the callback used if the account for the uid exists. The
	 * uid and the specific account will be passed as parameter to the callback in that order
	 * @param callable|null $callbackMissing the callback used if the account doesn't exist.
	 * The uid and account are passed as parameters to the callback
	 */
	private function doActionForAccountUids(array $uidToAccountMap, callable $callbackExists, callable $callbackMissing = null): void {
		foreach ($uidToAccountMap as $uid => $account) {
			$user = $this->userManager->get($uid);
			if ($user === null) {
				$callbackMissing($uid, $account);
			} else {
				$callbackExists($uid, $user);
			}
		}
	}

	/**
	 * @param array $removedUsers
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @param $missingAccountsAction
	 * @throws NotPermittedActionException
	 */
	private function handleRemovedUsers(array $removedUsers, InputInterface $input, OutputInterface $output, $missingAccountsAction): void {
		if (empty($removedUsers)) {
			$output->writeln('No removed users have been detected.');
		} else {
			// define some actions to be used
			$disableAction = function ($uid, IUser $user) use ($output) {
				if ($user->isEnabled()) {
					$user->setEnabled(false);
					$output->writeln("$uid, {$user->getDisplayName()}, {$user->getEMailAddress()} disabled");
				} else {
					$output->writeln("$uid, {$user->getDisplayName()}, {$user->getEMailAddress()} skipped, already disabled");
				}
			};
			$deleteAction = function ($uid, IUser $user) use ($output) {
				$user->delete();
				$output->writeln("$uid, {$user->getDisplayName()}, {$user->getEMailAddress()} deleted");
			};
			$writeNotExisting = function ($uid, Account $account) use ($output) {
				$output->writeln("$uid, {$account->getDisplayName()}, {$account->getEmail()} (no longer exists in the backend)");
			};

			switch ($missingAccountsAction) {
				case 'disable':
					$output->writeln('Disabling accounts:');
					$this->doActionForAccountUids(
						$removedUsers,
						$disableAction,
						$writeNotExisting
					);
					break;
				case 'remove':
					$output->writeln('Deleting accounts:');
					$this->doActionForAccountUids(
						$removedUsers,
						$deleteAction,
						$writeNotExisting
					);
					break;
				case 'ask later':
					$output->writeln('These accounts that are no longer available in the backend:');
					$this->doActionForAccountUids(
						$removedUsers,
						function ($uid) use ($output) {
							$output->writeln($uid);
						},
						$writeNotExisting
					);

					$helper = $this->getHelper('question');
					$question = new ChoiceQuestion(
						'What do you want to do with their accounts? (removing the account will also remove its data)',
						self::VALID_ACTIONS,
						0
					);
					$missingAccountsAction2 = $helper->ask($input, $output, $question);
					switch ($missingAccountsAction2) {
						// if "nothing" is selected, just ignore and finish
						case 'disable':
							$output->writeln('Disabling accounts');
							$this->doActionForAccountUids(
								$removedUsers,
								$disableAction,
								$writeNotExisting
							);
							break;
						case 'remove':
							$output->writeln('Deleting accounts:');
							$this->doActionForAccountUids(
								$removedUsers,
								$deleteAction,
								$writeNotExisting
							);
							break;
					}
					break;
			}
		}
	}

	/**
	 * Re-enable disabled accounts
	 *
	 * @param array $reappearedUsers map of user ids to account objects
	 * @param OutputInterface $output
	 * @throws NotPermittedActionException
	 */
	private function reEnableUsers(array $reappearedUsers, OutputInterface $output): void {
		if (empty($reappearedUsers)) {
			$output->writeln('No existing accounts to re-enable.');
		} else {
			$output->writeln('Re-enabling accounts:');

			$this->doActionForAccountUids(
				$reappearedUsers,
				function ($uid, IUser $user) use ($output) {
					if ($user->isEnabled()) {
						$output->writeln("$uid, {$user->getDisplayName()}, {$user->getEMailAddress()} skipped, already enabled");
					} else {
						$user->setEnabled(true);
						$output->writeln("$uid, {$user->getDisplayName()}, {$user->getEMailAddress()} enabled");
					}
				},
				function ($uid) use ($output) {
					$output->writeln("$uid not enabled (no existing account found)");
				}
			);
		}
	}
}
