<?php
/**
 * @author Juan Pablo Villafáñez <jvillafanez@solidgear.es>
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
namespace OCA\Files_Sharing\Panels\Admin;
use OCP\Settings\ISettings;
use OCP\Template;
use OCA\Files_Sharing\SharingBlacklist;
use OCA\Files_Sharing\SharingAllowlist;

class SettingsPanel implements ISettings {
	/** @var SharingBlacklist */
	private $sharingBlacklist;

	/** @var SharingAllowlist */
	private $sharingAllowlist;

	public function __construct(SharingBlacklist $sharingBlacklist, SharingAllowlist $sharingAllowlist) {
		$this->sharingBlacklist = $sharingBlacklist;
		$this->sharingAllowlist= $sharingAllowlist;
	}

	public function getPanel() {
		$tmpl = new Template('files_sharing', 'settings');
		$tmpl->assign('blacklistedReceivers', \implode('|', $this->sharingBlacklist->getBlacklistedReceiverGroups()));
		$tmpl->assign('publicShareSharersGroupsAllowlist', \implode('|', $this->sharingAllowlist->getPublicShareSharersGroupsAllowlist()));
		$tmpl->assign('publicShareSharersGroupsAllowlistEnabled', $this->sharingAllowlist->isPublicShareSharersGroupsAllowlistEnabled() ? 'yes' : 'no');
		return $tmpl;
	}

	public function getPriority() {
		return 95;
	}

	public function getSectionID() {
		return 'sharing';
	}
}
