<?php
/**
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OC\Settings\Controller;

use OC\AppFramework\Http;
use OC\Group\MetaData;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @package OC\Settings\Controller
 */
class GroupsController extends Controller {
	/** @var IGroupManager */
	private $groupManager;
	/** @var IL10N */
	private $l10n;
	/** @var IUserSession */
	private $userSession;
	/** @var bool */
	private $isAdmin;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IGroupManager $groupManager
	 * @param IUserSession $userSession
	 * @param bool $isAdmin
	 * @param IL10N $l10n
	 */
	public function __construct(
		$appName,
		IRequest $request,
		IGroupManager $groupManager,
		IUserSession $userSession,
		$isAdmin,
		IL10N $l10n
	) {
		parent::__construct($appName, $request);
		$this->groupManager = $groupManager;
		$this->userSession = $userSession;
		$this->isAdmin = $isAdmin;
		$this->l10n = $l10n;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $pattern
	 * @param bool $filterGroups
	 * @param int $sortGroups
	 * @return DataResponse
	 */
	public function index($pattern = '', $filterGroups = false, $sortGroups = MetaData::SORT_USERCOUNT) {
		$groupPattern = $filterGroups ? $pattern : '';

		$groupsInfo = new MetaData(
			$this->userSession->getUser()->getUID(),
			$this->isAdmin,
			$this->groupManager,
			$this->userSession
		);
		$groupsInfo->setSorting($sortGroups);
		list($adminGroups, $groups) = $groupsInfo->get($groupPattern, $pattern);

		return new DataResponse(
			[
				'data' => ['adminGroups' => $adminGroups, 'groups' => $groups]
			]
		);
	}

	/**
	 * @param string $id
	 * @return DataResponse
	 */
	public function create($id) {
		if ($this->groupManager->groupExists($id)) {
			return new DataResponse(
				[
					'status' => 'error',
					'message' => (string)$this->l10n->t('Group already exists.')
				],
				Http::STATUS_CONFLICT
			);
		}
		if ($groupObj = $this->groupManager->createGroup($id)) {
			return new DataResponse(
				[
					'gid' => $groupObj->getGID(),
					'name' => $groupObj->getDisplayName(),
				],
				Http::STATUS_CREATED
			);
		}

		return new DataResponse(
			[
				'status' => 'error',
				'message' => (string)$this->l10n->t('Unable to add group.')
			],
			Http::STATUS_FORBIDDEN
		);
	}

	/**
	 * @param string $id
	 * @return DataResponse
	 */
	public function destroy($id) {
		$group = $this->groupManager->get(\urldecode($id));
		if ($group) {
			if ($group->delete()) {
				return new DataResponse(
					[
						'status' => 'success',
						'data' => [
							'groupname' => $id
						]
					],
					Http::STATUS_NO_CONTENT
				);
			}
		}
		return new DataResponse(
			[
				'status' => 'error',
				'data' => [
					'message' => (string)$this->l10n->t('Unable to delete group.')
				],
			],
			Http::STATUS_FORBIDDEN
		);
	}

	/**
	 * Get available groups for assigning and removing via WebUI.
	 *
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getAssignableAndRemovableGroups() {
		$assignableGroups = [];
		$removableGroups = [];

		$currentUser = $this->userSession->getUser();
		/* @phan-suppress-next-line PhanUndeclaredMethod */
		$subAdmin = $this->groupManager->getSubAdmin();

		if ($this->groupManager->isAdmin($currentUser->getUID())) {
			foreach ($this->groupManager->getBackends() as $backend) {
				if (!$backend->implementsActions($backend::ADD_TO_GROUP) && !$backend->implementsActions($backend::REMOVE_FROM_GROUP)) {
					continue;
				}

				$groups = $backend->getGroups();
				foreach ($groups as $group) {
					$groupObject = $this->groupManager->get($group);
					if ($backend->implementsActions($backend::ADD_TO_GROUP)) {
						$assignableGroups[$groupObject->getGID()] = [
							'gid' => $groupObject->getGID(),
							'name' => $groupObject->getDisplayName(),
						];
					}
					if ($backend->implementsActions($backend::REMOVE_FROM_GROUP)) {
						$removableGroups[$groupObject->getGID()] = [
							'gid' => $groupObject->getGID(),
							'name' => $groupObject->getDisplayName(),
						];
					}
				}
			}
		} elseif ($subAdmin->isSubAdmin($currentUser)) {
			$subAdminGroups = $subAdmin->getSubAdminsGroups($currentUser);
			foreach ($subAdminGroups as $subAdminGroup) {
				$backend = $subAdminGroup->getBackend();
				if ($backend->implementsActions($backend::ADD_TO_GROUP)) {
					$assignableGroups[$subAdminGroup->getGID()] = [
						'gid' => $subAdminGroup->getGID(),
						'name' => $subAdminGroup->getDisplayName(),
					];
				}
				if ($backend->implementsActions($backend::REMOVE_FROM_GROUP)) {
					$removableGroups[$subAdminGroup->getGID()] = [
						'gid' => $subAdminGroup->getGID(),
						'name' => $subAdminGroup->getDisplayName()
					];
				}
			}
		}

		return new DataResponse(
			[
				'data' => [
					'assignableGroups' => $assignableGroups,
					'removableGroups' => $removableGroups,
				],
				Http::STATUS_OK
			]
		);
	}
}
