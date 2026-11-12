<?php
/**
 * Database query builder for UPDATE statements. See [Query Builder](/database/query/builder) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Database_Query_Builder_Update extends Database_Query_Builder_Where {

	// UPDATE ...
	protected mixed $_table = null;

	/**
	 * @var  array<int, array<int, mixed>>  SET ...
	 */
	protected array $_set = [];

	/**
	 * Set the table for a update.
	 *
	 * @param   mixed  $table  table name or array($table, $alias) or object
	 * @return  void
	 */
	public function __construct(mixed $table = null)
	{
		if ($table)
		{
			// Set the inital table name
			$this->_table = $table;
		}

		// Start the query with no SQL
		parent::__construct(Database::UPDATE, '');
	}

	/**
	 * Sets the table to update.
	 *
	 * @param   mixed  $table  table name or array($table, $alias) or object
	 * @return  $this
	 */
	public function table(mixed $table): static
	{
		$this->_table = $table;

		return $this;
	}

	/**
	 * Set the values to update with an associative array.
	 *
	 * @param   array<string, mixed>   $pairs  associative (column => value) list
	 * @return  $this
	 */
	public function set(array $pairs): static
	{
		foreach ($pairs as $column => $value)
		{
			$this->_set[] = [$column, $value];
		}

		return $this;
	}

	/**
	 * Set the value of a single column.
	 *
	 * @param   mixed  $column  table name or array($table, $alias) or object
	 * @param   mixed  $value   column value
	 * @return  $this
	 */
	public function value(mixed $column, mixed $value): static
	{
		$this->_set[] = [$column, $value];

		return $this;
	}

	/**
	 * Compile the SQL query and return it.
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

		// Start an update query
		$query = 'UPDATE '.$db->quote_table($this->_table);

		// Add the columns to update
		$query .= ' SET '.$this->_compile_set($db, $this->_set);

		if ( ! empty($this->_where))
		{
			// Add selection conditions
			$query .= ' WHERE '.$this->_compile_conditions($db, $this->_where);
		}

		if ( ! empty($this->_order_by))
		{
			// Add sorting
			$query .= ' '.$this->_compile_order_by($db, $this->_order_by);
		}

		if ($this->_limit !== null)
		{
			// Add limiting
			$query .= ' LIMIT '.$this->_limit;
		}

		$this->_sql = $query;

		return parent::compile($db);
	}

	public function reset(): static
	{
		$this->_table = null;

		$this->_set   =
		$this->_where = [];

		$this->_limit = null;

		$this->_parameters = [];

		$this->_sql = '';

		return $this;
	}


} // End Database_Query_Builder_Update
