<?php
/**
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes
namespace OCP;

/**
 * Interface for collections of of items implemented by another share backend.
 * Extends the Share_Backend interface.
 * @since 5.0.0
 * @deprecated since 10.0.11 and will be removed in 11.0, please use the share manager instead
 */
interface Share_Backend_Collection extends Share_Backend {
	/**
	 * Get the sources of the children of the item
	 * @param string $itemSource
	 * @return array Returns an array of children each inside an array with the keys: source, target, and file_path if applicable
	 * @since 5.0.0
	 */
	public function getChildren($itemSource);
}
