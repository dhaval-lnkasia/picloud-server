<?php
/**
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author sualko <klaus@jsxc.org>
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

namespace OCP\AppFramework\Http;

/**
 * Class ContentSecurityPolicy is a simple helper which allows applications to
 * modify the Content-Security-Policy sent by ownCloud. Per default only JavaScript,
 * stylesheets, images, fonts, media and connections from the same domain
 * ('self') are allowed.
 *
 * Even if a value gets modified above defaults will still get appended. Please
 * notice that ownCloud ships already with sensible defaults and those policies
 * should require no modification at all for most use-cases.
 *
 * @package OCP\AppFramework\Http
 * @since 8.1.0
 */
class ContentSecurityPolicy extends EmptyContentSecurityPolicy {
	/** @var bool Whether inline JS snippets are allowed */
	protected $inlineScriptAllowed = false;
	/**
	 * @var bool Whether eval in JS scripts is allowed
	 * TODO: Disallow per default
	 * @link https://github.com/owncloud/core/issues/11925
	 */
	protected $evalScriptAllowed = true;
	/** @var array Domains from which scripts can get loaded */
	protected $allowedScriptDomains = [
		'\'self\'',
	];
	/**
	 * @var bool Whether inline CSS is allowed
	 * TODO: Disallow per default
	 * @link https://github.com/owncloud/core/issues/13458
	 */
	protected $inlineStyleAllowed = true;
	/** @var array Domains from which CSS can get loaded */
	protected $allowedStyleDomains = [
		'\'self\'',
	];
	/** @var array Domains from which images can get loaded */
	protected $allowedImageDomains = [
		'\'self\'',
		'data:',
		'blob:',
	];
	/** @var array Domains to which connections can be done */
	protected $allowedConnectDomains = [
		'\'self\'',
	];
	/** @var array Domains from which media elements can be loaded */
	protected $allowedMediaDomains = [
		'\'self\'',
	];
	/** @var array Domains from which object elements can be loaded */
	protected $allowedObjectDomains = [];
	/** @var array Domains from which iframes can be loaded */
	protected $allowedFrameDomains = [];
	/** @var array Domains from which fonts can be loaded */
	protected $allowedFontDomains = [
		'\'self\'',
	];
	/** @var array Domains from which web-workers and nested browsing content can load elements */
	protected $allowedChildSrcDomains = [];
}
