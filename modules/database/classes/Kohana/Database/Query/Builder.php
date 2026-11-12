<?php
/**
 * Database query builder. See [Query Builder](/database/query/builder) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query
 * 
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Database_Query_Builder extends Database_Query {

	/**
	 * @var  array<int, string>  Operators that may be used in where(), having() and on(),
	 *              in uppercase. Add to this list if your database has an
	 *              operator that is missing:
	 *
	 *                  Database_Query_Builder::$operators[] = 'MEMBER OF';
	 */
	public static array $operators = [
		// Comparison
		'=', '!=', '<>', '<', '>', '<=', '>=', '<=>',
		// NULL and boolean tests
		'IS', 'IS NOT', 'IS DISTINCT FROM', 'IS NOT DISTINCT FROM',
		// Lists and ranges
		'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS',
		// Patterns
		'LIKE', 'NOT LIKE', 'ILIKE', 'NOT ILIKE', 'SIMILAR TO', 'NOT SIMILAR TO',
		'REGEXP', 'NOT REGEXP', 'RLIKE', 'NOT RLIKE', 'SOUNDS LIKE', 'GLOB', 'MATCH',
		// PostgreSQL pattern, array and JSON operators
		'~', '~*', '!~', '!~*', '@>', '<@', '&&', '?', '?|', '?&', '@@',
		// Bitwise
		'&', '|', '^', '<<', '>>',
	];

	/**
	 * Compiles an array of JOIN statements into an SQL partial.
	 *
	 * @param   Database  $db     Database instance
	 * @param   array<int, Database_Query_Builder_Join>   $joins  join statements
	 * @return  string
	 */
	protected function _compile_join(Database $db, array $joins): string
	{
		$statements = [];

		foreach ($joins as $join)
		{
			// Compile each of the join statements
			$statements[] = $join->compile($db);
		}

		return implode(' ', $statements);
	}

	/**
	 * Compiles an array of conditions into an SQL partial. Used for WHERE
	 * and HAVING.
	 *
	 * @param   Database  $db          Database instance
	 * @param   array<int, array<string, mixed>>   $conditions  condition statements: "(", ")" or [column, operator, value]
	 * @return  string
	 */
	protected function _compile_conditions(Database $db, array $conditions): string
	{
		$last_condition = null;

		$sql = '';
		foreach ($conditions as $group)
		{
			// Process groups of conditions
			foreach ($group as $logic => $condition)
			{
				if ($condition === ')')
				{
					$sql .= ')';
				}
				else
				{
					if ( ! empty($sql) AND $last_condition !== '(')
					{
						// Add the logic operator (AND / OR)
						$sql .= ' '.$logic.' ';
					}

					// Either open a group, or add one "column operator value" condition
					$sql .= is_array($condition) ? $this->_compile_condition($db, $condition) : '(';
				}

				$last_condition = $condition;
			}
		}

		return $sql;
	}

	/**
	 * Compiles one condition, array(column, operator, value), into SQL.
	 *
	 * @param   Database  $db         Database instance
	 * @param   array<int|string, mixed>     $condition  column, operator and value
	 * @return  string
	 * @throws  Database_Exception  if the operator is not allowed
	 */
	protected function _compile_condition(Database $db, array $condition): string
	{
		// Split the condition
		/** @var array{0: mixed, 1: string|null, 2: mixed} $condition */
		list($column, $op, $value) = $condition;

		if ($value === null)
		{
			if ($op === '=')
			{
				// Convert "val = null" to "val IS NULL"
				$op = 'IS';
			}
			elseif ($op === '!=' OR $op === '<>')
			{
				// Convert "val != null" to "val IS NOT NULL"
				$op = 'IS NOT';
			}
		}

		// Uppercase the operator and make sure it is an allowed one
		$op = $this->_operator($op);

		if ($op === 'BETWEEN' AND is_array($value))
		{
			// BETWEEN always has exactly two arguments
			list($min, $max) = $value;

			$value = $this->_quote_value($db, $min).' AND '.$this->_quote_value($db, $max);
		}
		elseif ($op === 'IN' AND is_array($value) AND count($value) === 0)
		{
			// An empty list matches nothing
			$value = '(NULL)';
		}
		else
		{
			$value = $this->_quote_value($db, $value);
		}

		if ($column)
		{
			if (is_array($column))
			{
				// Use the column name
				$column = $db->quote_identifier(reset($column));
			}
			else
			{
				// Apply proper quoting to the column
				$column = $db->quote_column($column);
			}
		}
		else
		{
			// No column: a condition on the value alone
			$column = is_scalar($column) ? (string) $column : '';
		}

		// The statement: column, operator and value
		return trim($column.' '.$op.' '.$value);
	}

	/**
	 * Quotes a value for use in SQL, unless it is the name of a parameter
	 * of this query (such as ":id"), which is replaced later.
	 *
	 * @param   Database  $db     Database instance
	 * @param   mixed     $value  value to quote
	 * @return  string|int
	 */
	protected function _quote_value(Database $db, mixed $value): string|int
	{
		if (is_string($value) AND array_key_exists($value, $this->_parameters))
		{
			// A parameter: leave the placeholder as it is
			return $value;
		}

		return $db->quote($value);
	}

	/**
	 * Returns an operator in the form it is written into SQL: uppercase,
	 * with single spaces.
	 *
	 * Values and column names are quoted, but an operator cannot be: it is
	 * written into the query as it is. So only the operators listed in
	 * [Database_Query_Builder::$operators] are accepted. This matters when an
	 * application lets a request choose the operator (a search form with
	 * "contains / equals / greater than", for example).
	 *
	 * @param   string|null  $op  operator; null or '' means "no operator"
	 * @return  string
	 * @throws  Database_Exception  if the operator is not in the list
	 */
	protected function _operator(?string $op): string
	{
		// Database operators are always uppercase
		$op = strtoupper(trim( (string) preg_replace('/\s+/', ' ', (string) $op)));

		if ($op !== '' AND ! in_array($op, Database_Query_Builder::$operators, true))
		{
			throw new Database_Exception(
				'Invalid SQL operator ":operator". To allow it, add it to Database_Query_Builder::$operators',
				[':operator' => $op]
			);
		}

		return $op;
	}

	/**
	 * Compiles an array of set values into an SQL partial. Used for UPDATE.
	 *
	 * @param   Database  $db      Database instance
	 * @param   array<int, array<int, mixed>>   $values  updated values
	 * @return  string
	 */
	protected function _compile_set(Database $db, array $values): string
	{
		$set = [];
		foreach ($values as $group)
		{
			// Split the set
			list ($column, $value) = $group;

			// Quote the column name
			$column = $db->quote_column($column);

			if ((is_string($value) AND array_key_exists($value, $this->_parameters)) === false)
			{
				// Quote the value, it is not a parameter
				$value = $db->quote($value);
			}

			$set[$column] = $column.' = '.$value;
		}

		return implode(', ', $set);
	}

	/**
	 * Compiles an array of GROUP BY columns into an SQL partial.
	 *
	 * @param   Database  $db       Database instance
	 * @param   array<int|string, mixed>   $columns
	 * @return  string
	 */
	protected function _compile_group_by(Database $db, array $columns): string
	{
		$group = [];

		foreach ($columns as $column)
		{
			if (is_array($column))
			{
				// Use the column alias
				$column = $db->quote_identifier(end($column));
			}
			else
			{
				// Apply proper quoting to the column
				$column = $db->quote_column($column);
			}

			$group[] = $column;
		}

		return 'GROUP BY '.implode(', ', $group);
	}

	/**
	 * Compiles an array of ORDER BY statements into an SQL partial.
	 *
	 * @param  Database  $db       Database instance
	 * @param  array<int, array<int, mixed>>     $columns  sorting columns
	 *
	 * @return string
	 * @throws Database_Exception
	 */
	protected function _compile_order_by(Database $db, array $columns): string
	{
		$sort = [];
		foreach ($columns as $group)
		{
			list ($column, $direction) = $group;

			if (is_array($column))
			{
				// Use the column alias
				$column = $db->quote_identifier(end($column));
			}
			else
			{
				// Apply proper quoting to the column
				$column = $db->quote_column($column);
			}

			if ($direction)
			{
				// Make the direction uppercase
				$direction = ' '.strtoupper(is_string($direction) ? $direction : '');

				// Make sure direction is either ASC or DESC to prevent injections
				if ( ! in_array($direction, [' ASC', ' DESC'])) {
					throw new Database_Exception('Invalid sorting direction: ' . $direction);
				}
			}
			else
			{
				// No direction given, the database decides
				$direction = '';
			}

			$sort[] = $column.$direction;
		}

		return 'ORDER BY '.implode(', ', $sort);
	}

	/**
	 * Reset the current builder status.
	 *
	 * @return  $this
	 */
	abstract public function reset(): static;

} // End Database_Query_Builder
