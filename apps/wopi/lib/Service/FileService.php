<?php
/**
 * ownCloud Wopi
 *
 * @author Piotr Mrowczynski <piotr@owncloud.com>
 * @copyright 2021 LNKASIA TECHSOL.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\WOPI\Service;

use OC\HintException;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\IUserSession;
use OCP\Share\IManager as IShareManager;
use OCP\Constants;
use OCP\Files\File;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Files\NotFoundException;

class FileService {
	/** @var IRootFolder */
	private $rootFolder;
	/** @var IUserSession */
	private $userSession;
	/** @var IShareManager */
	private $shareManager;

	/**
	 * FileService constructor.
	 *
	 * @param IRootFolder $rootFolder
	 * @param IUserSession $userSession
	 * @param IShareManager $shareManager
	 */
	public function __construct(
		IRootFolder $rootFolder,
		IUserSession $userSession,
		IShareManager $shareManager
	) {
		$this->rootFolder = $rootFolder;
		$this->userSession = $userSession;
		$this->shareManager = $shareManager;
	}

	/**
	 * @param string $shareToken
	 * @param int|null $fileId
	 * @return File
	 * @throws HintException
	 */
	public function getByShareToken($shareToken, $fileId = null): File {
		if (empty($shareToken)) {
			throw new HintException("Share token not provided");
		}

		try {
			$share = $this->shareManager->getShareByToken($shareToken);
		} catch (ShareNotFound $e) {
			throw new HintException("Share for token $shareToken not found");
		}

		if (($share->getPermissions() & Constants::PERMISSION_READ) === 0) {
			throw new HintException("Share for token $shareToken does not have necessarily permissions to be read");
		}

		try {
			$node = $share->getNode();
		} catch (NotFoundException $e) {
			throw new HintException("Node for share with token $shareToken not found");
		}

		if ($node instanceof Folder && $fileId !== null) {
			// file in public link folder
			try {
				$files = $node->getById($fileId, true);
			} catch (\Exception $e) {
				throw new HintException("Node for shared folder with token $shareToken not found");
			}

			if (empty($files)) {
				throw new HintException("Node for shared folder with token $shareToken not found");
			}
			$file = $files[0];
		} else {
			// public link file
			$file = $node;
		}

		if ($file instanceof File) {
			return $file;
		}
		throw new HintException("Resource with $fileId is not a file");
	}

	/**
	 * @param int $fileId
	 * @return File
	 * @throws HintException
	 */
	public function getByFileId($fileId): File {
		$user = $this->userSession->getUser();
		$nodes = $this->rootFolder->getUserFolder($user->getUID())->getById($fileId, true);
		if (empty($nodes)) {
			throw new HintException("File with $fileId not found for current user");
		}

		$file = $nodes[0];
		if ($file instanceof File) {
			return $file;
		}
		throw new HintException("Resource with $fileId is not a file");
	}
}
