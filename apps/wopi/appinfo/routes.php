<?php
/**
 * ownCloud Wopi
 *
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Piotr Mrowczynski <piotr@owncloud.com>
 * @copyright 2018 LNKASIA TECHSOL.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

return [
	'routes' => [
		// Read Office Online Discovery XML
		['name' => 'discovery#index', 'url' => '/discovery.json', 'verb' => 'GET'],

		// load Office Online for authenticated user
		['name' => 'page#Office', 'url' => '/office/{_action}/{fileId}', 'verb' => 'GET', 'requirements' => ['_action' => 'edit|view|editnew']],
		
		// load Office Online for public page
		// TODO: add support for editnew
		['name' => 'page#OfficePublicLink', 'url' => '/office/{_action}/share/{shareToken}', 'verb' => 'GET', 'requirements' => ['_action' => 'edit|view']],

		// Generate access token
		['name' => 'wopi#GenerateNewAuthUserAccessToken', 'url' => '/token/user', 'verb' => 'POST'],
		['name' => 'wopi#GenerateNewPublicLinkAccessToken', 'url' => '/token/publiclink', 'verb' => 'POST'],

		// https://wopirest.readthedocs.io/en/latest/files/CheckFileInfo.html#get--wopi-files-(file_id)
		['name' => 'wopi#CheckFileInfo', 'url' => '/files/{fileId}', 'verb' => 'GET'],

		// https://wopirest.readthedocs.io/en/latest/files/Lock.html#post--wopi-files-(file_id)
		// https://wopirest.readthedocs.io/en/latest/files/Unlock.html#post--wopi-files-(file_id)
		// https://wopirest.readthedocs.io/en/latest/files/RefreshLock.html#post--wopi-files-(file_id)
		// https://wopirest.readthedocs.io/en/latest/files/UnlockAndRelock.html#post--wopi-files-(file_id)
		// https://wopirest.readthedocs.io/en/latest/files/DeleteFile.html#post--wopi-files-(file_id)
		// https://wopirest.readthedocs.io/en/latest/files/PutRelativeFile.html#post--wopi-files-(file_id)
		// https://wopirest.readthedocs.io/en/latest/files/RenameFile.html#post--wopi-files-(file_id)
		['name' => 'wopi#FileOperation', 'url' => '/files/{fileId}', 'verb' => 'POST'],

		// https://wopirest.readthedocs.io/en/latest/files/GetFile.html#get--wopi-files-(file_id)-contents
		['name' => 'wopi#GetFile', 'url' => '/files/{fileId}/contents', 'verb' => 'GET'],

		// https://wopirest.readthedocs.io/en/latest/files/PutFile.html#post--wopi-files-(file_id)-contents
		['name' => 'wopi#PutFile', 'url' => '/files/{fileId}/contents', 'verb' => 'POST'],
	]
];
