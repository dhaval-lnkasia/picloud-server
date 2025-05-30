<?php
/**
 * ownCloud Workflow
 *
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
 * @copyright 2019 LNKASIA TECHSOL.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Workflow\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use OCP\Migration\ISchemaMigration;

class Version20191002101015 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];
		if (!$schema->hasTable("{$prefix}workflows")) {
			// clean install
			$table = $schema->createTable("{$prefix}workflows");
			/* @phan-suppress-next-line PhanDeprecatedClassConstant */
			$table->addColumn('id', Type::INTEGER, [
				'autoincrement' => true,
				'unsigned' => true,
				'notnull' => true,
				'length' => 11,
			]);
			/* @phan-suppress-next-line PhanDeprecatedClassConstant */
			$table->addColumn('workflow_name', Type::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			/* @phan-suppress-next-line PhanDeprecatedClassConstant */
			$table->addColumn('workflow_type', Type::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			/* @phan-suppress-next-line PhanDeprecatedClassConstant */
			$table->addColumn('workflow_conditions', Type::TEXT, [
				'notnull' => true,
			]);
			/* @phan-suppress-next-line PhanDeprecatedClassConstant */
			$table->addColumn('workflow_actions', Type::TEXT, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
		} else {
			// update workflow_type field if needed
			$table = $schema->getTable("{$prefix}workflows");
			$typeColumn = $table->getColumn('workflow_type');
			/* @phan-suppress-next-line PhanDeprecatedClassConstant */
			if ($typeColumn->getType()->getName() !== Type::STRING) {
				/* @phan-suppress-next-line PhanDeprecatedClassConstant */
				$typeColumn->setType(Type::getType(Type::STRING));
				$typeColumn->setOptions(['length' => 64]);
			}
		}
	}
}
