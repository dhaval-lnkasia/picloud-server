<?php
/**
 *
 * @copyright Copyright (c) 2021, LNKASIA TECHSOL
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
namespace OCP\License\Exceptions;

/**
 * @since 10.8.0
 * The LicenseManager can throw this exception due to multiple reasons
 *
 * Apps might catch this exceptions, but they mustn't throw it by themselves.
 */
class LicenseManagerException extends \Exception {
}
