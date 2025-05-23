<?php
/**
 * @author Hemant Mann <hemant.mann121@gmail.com>
 *
 * @copyright Copyright (c) 2017, LNKASIA TECHSOL.
 * @license GPL-2.0
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */

namespace OCA\Files_external_dropbox\Controller;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use OCA\Files_external_dropbox\OAuth2Store;

/**
 * Oauth controller for Dropbox
 */
class OauthController extends Controller {
	/**
	 * L10N service
	 *
	 * @var IL10N
	 */
	protected $l10n;

	/**
	 * Creates a new storages controller.
	 *
	 * @param string $AppName application name
	 * @param IRequest $request request
	 * @param IL10N $l10n l10n service
	 */
	public function __construct(
		$AppName,
		IRequest $request,
		IL10N $l10n
	) {
		parent::__construct($AppName, $request);
		$this->l10n = $l10n;
	}

	/**
	 * Create a storage from its parameters
	 *
	 * @param string $client_id
	 * @param string $client_secret
	 * @param string $redirect
	 * @param int $step
	 * @param string $code
	 * @return DataResponse
	 * @NoAdminRequired
	 */
	public function receiveToken(
		$client_id,
		$client_secret,
		$redirect,
		$step,
		$code
	) {
		$clientId = $client_id;
		$clientSecret = $client_secret;
		if ($clientId !== null && $clientSecret !== null && $redirect !== null) {
			$app = new \Kunnu\Dropbox\DropboxApp($clientId, $clientSecret);
			$dropbox = new \Kunnu\Dropbox\Dropbox($app);
			$authHelper = $dropbox->getAuthHelper();

			if ($step == 1) {
				$authUrl = $authHelper->getAuthUrl($redirect, [], null, 'offline');
				return new DataResponse([
					'status' => 'success',
					'data' => ['url' => $authUrl]
				]);
			} elseif ($step == 2 && isset($code)) {
				try {
					$accessToken = $authHelper->getAccessToken($code, null, $redirect);
					$accessTokenData = $accessToken->getData();
					// consider expiration 10 minutes before the expected time so we can refresh
					// the token without causing problems
					$accessTokenData['expTimestamp'] = \time() + $accessTokenData['expires_in'] - OAuth2Store::TOKEN_EXP_OFFSET;

					$oauth2Store = OAuth2Store::getGlobalInstance();
					$pubToken = $oauth2Store->storeData($accessTokenData, $clientId);

					return new DataResponse([
						'status' => 'success',
						'data' => ['token' => $pubToken]
					]);
				} catch (\Exception $ex) {
					return new DataResponse([
						'data' => [
							'message' => $this->l10n->t('Step 2 failed. Exception: %s', [$ex->getMessage()])
						]
					], Http::STATUS_UNPROCESSABLE_ENTITY);
				}
			}
		}
		return new DataResponse(
			['data' => ['message' => $this->l10n->t('Invalid Request Params!!')]],
			Http::STATUS_BAD_REQUEST
		);
	}
}
