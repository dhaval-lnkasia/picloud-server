<?php
/**
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCP\DB\QueryBuilder;

use Doctrine\DBAL\Query\Expression\ExpressionBuilder;

/**
 * This class provides a wrapper around Doctrine's ExpressionBuilder
 * @since 8.2.0
 */
interface IExpressionBuilder {
	/**
	 * @since 9.0.0
	 */
	public const EQ  = ExpressionBuilder::EQ;
	/**
	 * @since 9.0.0
	 */
	public const NEQ = ExpressionBuilder::NEQ;
	/**
	 * @since 9.0.0
	 */
	public const LT  = ExpressionBuilder::LT;
	/**
	 * @since 9.0.0
	 */
	public const LTE = ExpressionBuilder::LTE;
	/**
	 * @since 9.0.0
	 */
	public const GT  = ExpressionBuilder::GT;
	/**
	 * @since 9.0.0
	 */
	public const GTE = ExpressionBuilder::GTE;

	/**
	 * Creates a conjunction of the given boolean expressions.
	 *
	 * Example:
	 *
	 *     [php]
	 *     // (u.type = ?) AND (u.role = ?)
	 *     $expr->andX('u.type = ?', 'u.role = ?'));
	 *
	 * @param mixed $x Optional clause. Defaults = null, but requires
	 *                 at least one defined when converting to string.
	 *
	 * @return \OCP\DB\QueryBuilder\ICompositeExpression
	 * @since 8.2.0
	 */
	public function andX($x = null);

	/**
	 * Creates a disjunction of the given boolean expressions.
	 *
	 * Example:
	 *
	 *     [php]
	 *     // (u.type = ?) OR (u.role = ?)
	 *     $qb->where($qb->expr()->orX('u.type = ?', 'u.role = ?'));
	 *
	 * @param mixed $x Optional clause. Defaults = null, but requires
	 *                 at least one defined when converting to string.
	 *
	 * @return \OCP\DB\QueryBuilder\ICompositeExpression
	 * @since 8.2.0
	 */
	public function orX($x = null);

	/**
	 * Creates a comparison expression.
	 *
	 * @param mixed $x The left expression.
	 * @param string $operator One of the IExpressionBuilder::* constants.
	 * @param mixed $y The right expression.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 * @since 8.2.0 - Parameter $type was added in 9.0.0
	 */
	public function comparison($x, $operator, $y, $type = null);

	/**
	 * Creates an equality comparison expression with the given arguments.
	 *
	 * First argument is considered the left expression and the second is the right expression.
	 * When converted to string, it will generated a <left expr> = <right expr>. Example:
	 *
	 *     [php]
	 *     // u.id = ?
	 *     $expr->eq('u.id', '?');
	 *
	 * @param mixed $x The left expression.
	 * @param mixed $y The right expression.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 * @since 8.2.0 - Parameter $type was added in 9.0.0
	 */
	public function eq($x, $y, $type = null);

	/**
	 * Creates a non equality comparison expression with the given arguments.
	 * First argument is considered the left expression and the second is the right expression.
	 * When converted to string, it will generated a <left expr> <> <right expr>. Example:
	 *
	 *     [php]
	 *     // u.id <> 1
	 *     $q->where($q->expr()->neq('u.id', '1'));
	 *
	 * @param mixed $x The left expression.
	 * @param mixed $y The right expression.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 * @since 8.2.0 - Parameter $type was added in 9.0.0
	 */
	public function neq($x, $y, $type = null);

	/**
	 * Creates a lower-than comparison expression with the given arguments.
	 * First argument is considered the left expression and the second is the right expression.
	 * When converted to string, it will generated a <left expr> < <right expr>. Example:
	 *
	 *     [php]
	 *     // u.id < ?
	 *     $q->where($q->expr()->lt('u.id', '?'));
	 *
	 * @param mixed $x The left expression.
	 * @param mixed $y The right expression.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 * @since 8.2.0 - Parameter $type was added in 9.0.0
	 */
	public function lt($x, $y, $type = null);

	/**
	 * Creates a lower-than-equal comparison expression with the given arguments.
	 * First argument is considered the left expression and the second is the right expression.
	 * When converted to string, it will generated a <left expr> <= <right expr>. Example:
	 *
	 *     [php]
	 *     // u.id <= ?
	 *     $q->where($q->expr()->lte('u.id', '?'));
	 *
	 * @param mixed $x The left expression.
	 * @param mixed $y The right expression.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 * @since 8.2.0 - Parameter $type was added in 9.0.0
	 */
	public function lte($x, $y, $type = null);

	/**
	 * Creates a greater-than comparison expression with the given arguments.
	 * First argument is considered the left expression and the second is the right expression.
	 * When converted to string, it will generated a <left expr> > <right expr>. Example:
	 *
	 *     [php]
	 *     // u.id > ?
	 *     $q->where($q->expr()->gt('u.id', '?'));
	 *
	 * @param mixed $x The left expression.
	 * @param mixed $y The right expression.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 * @since 8.2.0 - Parameter $type was added in 9.0.0
	 */
	public function gt($x, $y, $type = null);

	/**
	 * Creates a greater-than-equal comparison expression with the given arguments.
	 * First argument is considered the left expression and the second is the right expression.
	 * When converted to string, it will generated a <left expr> >= <right expr>. Example:
	 *
	 *     [php]
	 *     // u.id >= ?
	 *     $q->where($q->expr()->gte('u.id', '?'));
	 *
	 * @param mixed $x The left expression.
	 * @param mixed $y The right expression.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 * @since 8.2.0 - Parameter $type was added in 9.0.0
	 */
	public function gte($x, $y, $type = null);

	/**
	 * Creates an IS NULL expression with the given arguments.
	 *
	 * @param string $x The field in string format to be restricted by IS NULL.
	 *
	 * @return string
	 * @since 8.2.0
	 */
	public function isNull($x);

	/**
	 * Creates an IS NOT NULL expression with the given arguments.
	 *
	 * @param string $x The field in string format to be restricted by IS NOT NULL.
	 *
	 * @return string
	 * @since 8.2.0
	 */
	public function isNotNull($x);

	/**
	 * Creates a LIKE() comparison expression with the given arguments.
	 *
	 * @param string $x Field in string format to be inspected by LIKE() comparison.
	 * @param mixed $y Argument to be used in LIKE() comparison.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 * @since 8.2.0 - Parameter $type was added in 9.0.0
	 */
	public function like($x, $y, $type = null);

	/**
	 * Creates a NOT LIKE() comparison expression with the given arguments.
	 *
	 * @param string $x Field in string format to be inspected by NOT LIKE() comparison.
	 * @param mixed $y Argument to be used in NOT LIKE() comparison.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 * @since 8.2.0 - Parameter $type was added in 9.0.0
	 */
	public function notLike($x, $y, $type = null);

	/**
	 * Creates a ILIKE() comparison expression with the given arguments.
	 *
	 * @param string $x Field in string format to be inspected by ILIKE() comparison.
	 * @param mixed $y Argument to be used in ILIKE() comparison.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function iLike($x, $y, $type = null);

	/**
	 * Creates a IN () comparison expression with the given arguments.
	 *
	 * @param string $x The field in string format to be inspected by IN() comparison.
	 * @param string|array $y The placeholder or the array of values to be used by IN() comparison.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 * @since 8.2.0 - Parameter $type was added in 9.0.0
	 */
	public function in($x, $y, $type = null);

	/**
	 * Creates a NOT IN () comparison expression with the given arguments.
	 *
	 * @param string $x The field in string format to be inspected by NOT IN() comparison.
	 * @param string|array $y The placeholder or the array of values to be used by NOT IN() comparison.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 * @since 8.2.0 - Parameter $type was added in 9.0.0
	 */
	public function notIn($x, $y, $type = null);

	/**
	 * Quotes a given input parameter.
	 *
	 * @param mixed $input The parameter to be quoted.
	 * @param mixed|null $type One of the IQueryBuilder::PARAM_* constants
	 *
	 * @return string
	 * @since 8.2.0
	 */
	public function literal($input, $type = null);

	/**
	 * Returns a IQueryFunction that casts the column to the given type
	 *
	 * @param string $column
	 * @param mixed $type One of IQueryBuilder::PARAM_*
	 * @return string
	 * @since 9.0.0
	 */
	public function castColumn($column, $type);

	/**
	 * @param $column
	 * @return string
	 * @since 10.0.3
	 */
	public function length($column);
}
