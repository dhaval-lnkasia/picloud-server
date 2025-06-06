<?php
/**
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCA\Files_External\Lib\Backend;

use OCP\Files\External\Auth\AuthMechanism;
use OCP\Files\External\Backend\Backend as ExternalBackend;
use OCP\Files\External\DefinitionParameter;
use OCP\IL10N;

class SFTP extends ExternalBackend {
	public function __construct(IL10N $l) {
		$this
			->setIdentifier('sftp')
			->addIdentifierAlias('\OC\Files\Storage\SFTP') // legacy compat
			->setStorageClass('\OCA\Files_External\Lib\Storage\SFTP')
			->setText($l->t('SFTP'))
			->addParameters([
				(new DefinitionParameter('host', $l->t('Host'))),
				(new DefinitionParameter('root', $l->t('Root')))
					->setFlag(DefinitionParameter::FLAG_OPTIONAL),
			])
			->addAuthScheme(AuthMechanism::SCHEME_PASSWORD)
			->addAuthScheme(AuthMechanism::SCHEME_PUBLICKEY)
		;
	}
}
