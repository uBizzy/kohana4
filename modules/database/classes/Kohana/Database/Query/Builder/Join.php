<?php
/**
 * Database query builder for JOIN statements. See [Query Builder](/database/query/builder) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query
 * 
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Database_Query_Builder_Join extends Database_Query_Builder {

	// Type of JOIN
	protected ?string $_join_type = null;

	// JOIN ...
	protected mixed $_table = null;

	/**
	 * @var  array<int, array<int, mixed>>  ON ...
	 */
	protected array $_on = [];

	/**
	 * @var  array<int|string, mixed>  USING ...
	 */
	protected array $_using = [];

	/**
	 * Creates a new JOIN statement for a table. Optionally, the type of JOIN
	 * can be specified as the second parameter.
	 *
	 * @param   mixed   $table  column name or array($column, $alias) or object
	 * @param   string  $type   type of JOIN: INNER, RIGHT, LEFT, etc
	 * @return  void
	 */
	public function __construct(mixed $table, ?string $type = null)
	{
		// Set the table to JOIN on
		$this->_table = $table;

		if ($type !== null)
		{
			// The type is written into the SQL as it is, so it may only be
			// made of words: LEFT, RIGHT OUTER, INNER, CROSS, NATURAL, ...
			if ( ! preg_match('/^[a-z]+(?: [a-z]+)*$/i', $type))
				throw new Database_Exception('Invalid JOIN type: :type', [':type' => $type]);

			// Set the JOIN type
			$this->_join_type = $type;
		}
	}

	/**
	 * Adds a new condition for joining.
	 *
	 * @param   mixed   $c1  column name or array($column, $alias) or object
	 * @param   string  $op  logic operator
	 * @param   mixed   $c2  column name or array($column, $alias) or object
	 * @return  $this
	 */
	public function on(mixed $c1, string $op, mixed $c2): static
	{
		if ( ! empty($this->_using))
		{
			throw new Kohana_Exception('JOIN ... ON ... cannot be combined with JOIN ... USING ...');
		}

		$this->_on[] = [$c1, $op, $c2];

		return $this;
	}

	/**
	 * Adds a new condition for joining.
	 *
	 * @param   string  $columns  column name
	 * @return  $this
	 */
	public function using(mixed ...$columns): static
	{
		if ( ! empty($this->_on))
		{
			throw new Kohana_Exception('JOIN ... ON ... cannot be combined with JOIN ... USING ...');
		}

		$this->_using = array_merge($this->_using, $columns);

		return $this;
	}

	/**
	 * Compile the SQL partial for a JOIN statement and return it.
	 *
	 * @param   Database|string|null  $db  Database instance or name of instance
	 * @return  string
	 */
	public function compile(Database|string|null $db = null): string
	{
		if ( ! is_object($db))
		{
			// Get the database instance
			$db = Database::instance($db);
		}

		if ($this->_join_type)
		{
			$sql = strtoupper($this->_join_type).' JOIN';
		}
		else
		{
			$sql = 'JOIN';
		}

		// Quote the table name that is being joined
		$sql .= ' '.$db->quote_table($this->_table);

		if ( ! empty($this->_using))
		{
			// Quote and concat the columns
			$sql .= ' USING ('.implode(', ', array_map([$db, 'quote_column'], $this->_using)).')';
		}
		else
		{
			$conditions = [];
			foreach ($this->_on as $condition)
			{
				// Split the condition
				/** @var array{0: mixed, 1: string|null, 2: mixed} $condition */
				list($c1, $op, $c2) = $condition;

				// Uppercase the operator and make sure it is an allowed one
				$op = $this->_operator($op);

				if ($op !== '')
				{
					// Space it from the column
					$op = ' '.$op;
				}

				// Quote each of the columns used for the condition
				$conditions[] = $db->quote_column($c1).$op.' '.$db->quote_column($c2);
			}

			// Concat the conditions "... AND ..."
			$sql .= ' ON ('.implode(' AND ', $conditions).')';
		}

		return $sql;
	}

	public function reset(): static
	{
		$this->_join_type =
		$this->_table = null;

		$this->_on = [];

		return $this;
	}

} // End Database_Query_Builder_Join
