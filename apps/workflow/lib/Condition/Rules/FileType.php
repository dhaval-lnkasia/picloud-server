<?php
/**
 * ownCloud Workflow
 *
 * @author Joas Schilling <nickvergessen@owncloud.com>
 * @copyright 2015 ownCloud, Inc.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Workflow\Condition\Rules;

use OCA\Workflow\Condition\Operators;

class FileType extends BaseRule {
	/**
	 * @param mixed $ruleValue
	 * @param string $ruleId
	 * @throws \OutOfBoundsException when the value is not allowed
	 */
	public function validateRuleValue($ruleValue, $ruleId) {
		if (\is_string($ruleValue)) {
			return;
		}

		throw new \OutOfBoundsException('The rule value "' . \json_encode($ruleValue) . '" is not valid for rule "' . $ruleId . '"');
	}

	/**
	 * Return an array with the allowed operators
	 *
	 * @return string[]
	 */
	protected function getValidOperators() {
		return [
			Operators::OPERATOR_EQUALS, Operators::OPERATOR_NOT_EQUALS,
			Operators::OPERATOR_BEGINS_WITH, Operators::OPERATOR_NOT_BEGINS_WITH,
			Operators::OPERATOR_CONTAINS, Operators::OPERATOR_NOT_CONTAINS,
			Operators::OPERATOR_ENDS_WITH, Operators::OPERATOR_NOT_ENDS_WITH,
		];
	}

	/**
	 * @param string $operator
	 * @param mixed $ruleValue
	 * @return boolean True if the check passes, false if the check fails
	 */
	public function doCheck($operator, $ruleValue) {
		$uploadType = $this->context->getUploadType();

		if ($uploadType === null) {
			// null means no upload, so we don't compare the value at all
			return false;
		}

		return $this->operators->assert($uploadType, $operator, $ruleValue);
	}
}
