<?php
/**
 * Database query builder for INSERT statements. See [Query Builder](/database/query/builder) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Database_Query_Builder_Insert extends Database_Query_Builder {

	// INSERT INTO ...
	protected mixed $_table = null;

	/**
	 * @var  array<int|string, mixed>  (...)
	 */
	protected array $_columns = [];

	/**
	 * @var  array<int, mixed>|Database_Query  VALUES (...)
	 */
	protected array|Database_Query $_values = [];

	/**
	 * Set the table and columns for an insert.
	 *
	 * @param   mixed  $table    table name or array($table, $alias) or object
	 * @param   array<int|string, mixed>  $columns  column names
	 * @return  void
	 */
	public function __construct(mixed $table = null, ?array $columns = null)
	{
		if ($table)
		{
			// Set the inital table name
			$this->table($table);
		}

		if ($columns)
		{
			// Set the column names
			$this->_columns = $columns;
		}

		// Start the query with no SQL
		parent::__construct(Database::INSERT, '');
	}

	/**
	 * Sets the table to insert into.
	 *
	 * @param   mixed  $table  table name: a text, as INSERT allows no alias
	 * @return  $this
	 */
	public function table(mixed $table): static
	{
		if ( ! is_string($table))
			throw new Kohana_Exception('INSERT INTO syntax does not allow table aliasing');

		$this->_table = $table;

		return $this;
	}

	/**
	 * Set the columns that will be inserted.
	 *
	 * @param   array<int|string, mixed>  $columns  column names
	 * @return  $this
	 */
	public function columns(array $columns): static
	{
		$this->_columns = $columns;

		return $this;
	}

	/**
	 * Adds or overwrites values. Multiple value sets can be added.
	 *
	 * @param   array<int, mixed>   $values  values list
	 * @return  $this
	 */
	public function values(array ...$values): static
	{
		if ( ! is_array($this->_values))
		{
			throw new Kohana_Exception('INSERT INTO ... SELECT statements cannot be combined with INSERT INTO ... VALUES');
		}

		foreach ($values as $value)
		{
			$this->_values[] = $value;
		}

		return $this;
	}

	/**
	 * Use a sub-query to for the inserted values.
	 *
	 * @param   Database_Query  $query  Database_Query of SELECT type
	 * @return  $this
	 */
	public function select(Database_Query $query): static
	{
		if ($query->type() !== Database::SELECT)
		{
			throw new Kohana_Exception('Only SELECT queries can be combined with INSERT queries');
		}

		$this->_values = $query;

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

		// Start an insertion query
		$query = 'INSERT INTO '.$db->quote_table($this->_table);

		// Add the column names
		$query .= ' ('.implode(', ', array_map([$db, 'quote_column'], $this->_columns)).') ';

		if (is_array($this->_values))
		{
			// Callback for quoting values

			$groups = [];
			foreach ($this->_values as $group)
			{
				$quoted = [];

				/** @var array<int|string, mixed> $group  the values of one row */
				foreach ($group as $value)
				{
					// The name of a parameter is kept, any other value is quoted
					$quoted[] = (is_string($value) AND array_key_exists($value, $this->_parameters)) ? $value : $db->quote($value);
				}

				$groups[] = '('.implode(', ', $quoted).')';
			}

			// Add the values
			$query .= 'VALUES '.implode(', ', $groups);
		}
		else
		{
			// Add the sub-query
			$query .= (string) $this->_values;
		}

		$this->_sql = $query;

		return parent::compile($db);
	}

	public function reset(): static
	{
		$this->_table = null;

		$this->_columns =
		$this->_values  = [];

		$this->_parameters = [];

		$this->_sql = '';

		return $this;
	}

} // End Database_Query_Builder_Insert
