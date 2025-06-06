<?php
/**
 * @author Benedikt Kulmann <bkulmann@owncloud.com>
 * @copyright (C) 2019 LNKASIA TECHSOL
 * @license ownCloud Commercial License
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Metrics\Controller;

use OC\AppFramework\Http;
use OCA\Metrics\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;

/**
 * @package OCA\Metrics
 */
class PageController extends Controller {
	/**
	 * @var IConfig
	 */
	private $config;

	/**
	 * PageController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 */
	public function __construct(
		$appName,
		IRequest $request,
		IConfig $config
	) {
		parent::__construct($appName, $request);
		$this->config = $config;
	}

	/**
	 * Shows the metrics app.
	 *
	 * @AdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 */
	public function get(): TemplateResponse {
		return new TemplateResponse(Application::APPID, 'page');
	}

	/**
	 * Provides the token required for using the API.
	 *
	 * @AdminRequired
	 * @NoCSRFRequired
	 *
	 * @return DataResponse
	 */
	public function token(): DataResponse {
		$apiToken = $this->config->getSystemValue('metrics_shared_secret');
		if ($apiToken) {
			return new DataResponse(['token' => $apiToken]);
		}

		return new DataResponse(['error' => 'system value not configured'], Http::STATUS_INTERNAL_SERVER_ERROR);
	}
}
