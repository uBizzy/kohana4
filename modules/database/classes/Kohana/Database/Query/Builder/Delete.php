<?php
/**
 * Database query builder for DELETE statements. See [Query Builder](/database/query/builder) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Database_Query_Builder_Delete extends Database_Query_Builder_Where {

	// DELETE FROM ...
	protected mixed $_table = null;

	/**
	 * Set the table for a delete.
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
		parent::__construct(Database::DELETE, '');
	}

	/**
	 * Sets the table to delete from.
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

		// Start a deletion query
		$query = 'DELETE FROM '.$db->quote_table($this->_table);

		if ( ! empty($this->_where))
		{
			// Add deletion conditions
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
		$this->_where = [];

		$this->_parameters = [];

		$this->_sql = '';

		return $this;
	}

} // End Database_Query_Builder_Delete
