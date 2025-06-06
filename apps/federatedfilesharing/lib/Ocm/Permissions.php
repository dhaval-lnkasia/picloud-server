<?php
/**
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
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

namespace OCA\FederatedFileSharing\Ocm;

use OCP\Constants;

/**
 * Class Permissions
 *
 * @package OCA\FederatedFileSharing\Ocm
 */
class Permissions {
	public const OCM_PERMISSION_READ = 'read';
	public const OCM_PERMISSION_WRITE = 'write';
	public const OCM_PERMISSION_SHARE = 'share';

	/**
	 * Maps numeric permissions to an array of string permissions
	 *
	 * @param int $ocPermissions
	 *
	 * @return array
	 */
	public static function toOcmPermissions(int $ocPermissions): array {
		$ocPermissions = (int) $ocPermissions;
		$ocmPermissions = [];
		if ($ocPermissions & Constants::PERMISSION_READ) {
			$ocmPermissions[] = self::OCM_PERMISSION_READ;
		}
		if (($ocPermissions & Constants::PERMISSION_CREATE)
			|| ($ocPermissions & Constants::PERMISSION_UPDATE)
		) {
			$ocmPermissions[] = self::OCM_PERMISSION_WRITE;
		}
		if ($ocPermissions & Constants::PERMISSION_SHARE) {
			$ocmPermissions[] = self::OCM_PERMISSION_SHARE;
		}
		return $ocmPermissions;
	}

	/**
	 * @param string[] $ocmPermissions
	 *
	 * @return int
	 */
	public static function toOcPermissions(array $ocmPermissions): int {
		$permissionMap = [
			self::OCM_PERMISSION_READ => Constants::PERMISSION_READ,
			self::OCM_PERMISSION_WRITE => Constants::PERMISSION_CREATE + Constants::PERMISSION_UPDATE,
			self::OCM_PERMISSION_SHARE => Constants::PERMISSION_SHARE
		];
		$ocPermissions = 0;
		foreach ($ocmPermissions as $ocmPermission) {
			if (isset($permissionMap[$ocmPermission])) {
				$ocPermissions += $permissionMap[$ocmPermission];
			}
		}

		return $ocPermissions;
	}

	public static function isNewPermissionHigher(int $existingPermission, int $newPermission): bool {
		return ($existingPermission | $newPermission) !== $existingPermission;
	}
}
