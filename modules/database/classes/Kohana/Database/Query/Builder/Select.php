<?php
/**
 * Database query builder for SELECT statements. See [Query Builder](/database/query/builder) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Database_Query_Builder_Select extends Database_Query_Builder_Where {

	/**
	 * @var  array<int|string, mixed>  SELECT ...
	 */
	protected array $_select = [];

	// DISTINCT
	protected bool $_distinct = false;

	/**
	 * @var  array<int|string, mixed>  FROM ...
	 */
	protected array $_from = [];

	/**
	 * @var  array<int, Database_Query_Builder_Join>  JOIN ...
	 */
	protected array $_join = [];

	/**
	 * @var  array<int|string, mixed>  GROUP BY ...
	 */
	protected array $_group_by = [];

	/**
	 * @var  array<int, array<string, mixed>>  HAVING ...
	 */
	protected array $_having = [];

	// OFFSET ...
	protected ?int $_offset = null;

	/**
	 * @var  array<int, array{select: Database_Query_Builder_Select, all: bool}>  UNION ...
	 */
	protected array $_union = [];

	// The last JOIN statement created
	protected ?Database_Query_Builder_Join $_last_join = null;

	/**
	 * Sets the initial columns to select from.
	 *
	 * @param   array<int|string, mixed>  $columns  column list
	 * @return  void
	 */
	public function __construct(?array $columns = null)
	{
		if ( ! empty($columns))
		{
			// Set the initial columns
			$this->_select = $columns;
		}

		// Start the query with no actual SQL statement
		parent::__construct(Database::SELECT, '');
	}

	/**
	 * Enables or disables selecting only unique columns using "SELECT DISTINCT"
	 *
	 * @param   bool  $value  enable or disable distinct columns
	 * @return  $this
	 */
	public function distinct(bool $value): static
	{
		$this->_distinct = (bool) $value;

		return $this;
	}

	/**
	 * Choose the columns to select from.
	 *
	 * @param   mixed  $columns  column name or array($column, $alias) or object
	 * @return  $this
	 */
	public function select(mixed ...$columns): static
	{
		$this->_select = array_merge($this->_select, $columns);

		return $this;
	}

	/**
	 * Choose the columns to select from, using an array.
	 *
	 * @param   array<int|string, mixed>  $columns  list of column names or aliases
	 * @return  $this
	 */
	public function select_array(array $columns): static
	{
		$this->_select = array_merge($this->_select, $columns);

		return $this;
	}

	/**
	 * Choose the tables to select "FROM ..."
	 *
	 * @param   mixed  ...$tables  table name or array($table, $alias) or object
	 * @return  $this
	 */
	public function from(mixed ...$tables): static
	{
		$this->_from = array_merge($this->_from, $tables);

		return $this;
	}

	/**
	 * Adds addition tables to "JOIN ...".
	 *
	 * @param   mixed   $table  column name or array($column, $alias) or object
	 * @param   string  $type   join type (LEFT, RIGHT, INNER, etc)
	 * @return  $this
	 */
	public function join(mixed $table, ?string $type = null): static
	{
		$this->_join[] = $this->_last_join = new Database_Query_Builder_Join($table, $type);

		return $this;
	}

	/**
	 * The join that on() and using() add to: the one made last.
	 *
	 * @param   string  $method  name of the calling method, for the message
	 * @return  Database_Query_Builder_Join
	 * @throws  Kohana_Exception  if join() was not called first
	 */
	protected function _join_for(string $method): Database_Query_Builder_Join
	{
		if ($this->_last_join === null)
			throw new Kohana_Exception(':method() must follow a join()', [':method' => $method]);

		return $this->_last_join;
	}

	/**
	 * Adds "ON ..." conditions for the last created JOIN statement.
	 *
	 * @param   mixed   $c1  column name or array($column, $alias) or object
	 * @param   string  $op  logic operator
	 * @param   mixed   $c2  column name or array($column, $alias) or object
	 * @return  $this
	 */
	public function on(mixed $c1, string $op, mixed $c2): static
	{
		$this->_join_for('on')->on($c1, $op, $c2);

		return $this;
	}

	/**
	 * Adds "USING ..." conditions for the last created JOIN statement.
	 *
	 * @param   string  $columns  column name
	 * @return  $this
	 */
	public function using(mixed ...$columns): static
	{
		$this->_join_for('using')->using(...$columns);

		return $this;
	}

	/**
	 * Creates a "GROUP BY ..." filter.
	 *
	 * @param   mixed   $columns  column name or array($column, $alias) or object
	 * @return  $this
	 */
	public function group_by(mixed ...$columns): static
	{
		$this->_group_by = array_merge($this->_group_by, $columns);

		return $this;
	}

	/**
	 * Alias of and_having()
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  $this
	 */
	public function having(mixed $column, ?string $op, mixed $value = null): static
	{
		return $this->and_having($column, $op, $value);
	}

	/**
	 * Creates a new "AND HAVING" condition for the query.
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  $this
	 */
	public function and_having(mixed $column, ?string $op, mixed $value = null): static
	{
		$this->_having[] = ['AND' => [$column, $op, $value]];

		return $this;
	}

	/**
	 * Creates a new "OR HAVING" condition for the query.
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  $this
	 */
	public function or_having(mixed $column, ?string $op, mixed $value = null): static
	{
		$this->_having[] = ['OR' => [$column, $op, $value]];

		return $this;
	}

	/**
	 * Alias of and_having_open()
	 *
	 * @return  $this
	 */
	public function having_open(): static
	{
		return $this->and_having_open();
	}

	/**
	 * Opens a new "AND HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function and_having_open(): static
	{
		$this->_having[] = ['AND' => '('];

		return $this;
	}

	/**
	 * Opens a new "OR HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function or_having_open(): static
	{
		$this->_having[] = ['OR' => '('];

		return $this;
	}

	/**
	 * Closes an open "AND HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function having_close(): static
	{
		return $this->and_having_close();
	}

	/**
	 * Closes an open "AND HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function and_having_close(): static
	{
		$this->_having[] = ['AND' => ')'];

		return $this;
	}

	/**
	 * Closes an open "OR HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function or_having_close(): static
	{
		$this->_having[] = ['OR' => ')'];

		return $this;
	}

	/**
	 * Adds an other UNION clause.
	 *
	 * @param Database_Query_Builder_Select|string $select  if string, it must be the name of a table. Else
	 *  must be an instance of Database_Query_Builder_Select
	 * @param bool $all  decides if it's an UNION or UNION ALL clause
	 * @return $this
	 */
	public function union(Database_Query_Builder_Select|string $select, bool $all = true): static
	{
		if (is_string($select))
		{
			$select = DB::select()->from($select);
		}
		$this->_union []= ['select' => $select, 'all' => $all];
		return $this;
	}

	/**
	 * Start returning results after "OFFSET ..."
	 *
	 * @param   int   $number  starting result number or null to reset
	 * @return  $this
	 */
	public function offset(?int $number): static
	{
		$this->_offset = $number;

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

		// Callback to quote columns
		$quote_column = [$db, 'quote_column'];

		// Callback to quote tables
		$quote_table = [$db, 'quote_table'];

		// Start a selection query
		$query = 'SELECT ';

		if ($this->_distinct === true)
		{
			// Select only unique results
			$query .= 'DISTINCT ';
		}

		if (empty($this->_select))
		{
			// Select all columns
			$query .= '*';
		}
		else
		{
			// Select all columns
			$query .= implode(', ', array_unique(array_map($quote_column, $this->_select)));
		}

		if ( ! empty($this->_from))
		{
			// Set tables to select from
			$query .= ' FROM '.implode(', ', array_unique(array_map($quote_table, $this->_from)));
		}

		if ( ! empty($this->_join))
		{
			// Add tables to join
			$query .= ' '.$this->_compile_join($db, $this->_join);
		}

		if ( ! empty($this->_where))
		{
			// Add selection conditions
			$query .= ' WHERE '.$this->_compile_conditions($db, $this->_where);
		}

		if ( ! empty($this->_group_by))
		{
			// Add grouping
			$query .= ' '.$this->_compile_group_by($db, $this->_group_by);
		}

		if ( ! empty($this->_having))
		{
			// Add filtering conditions
			$query .= ' HAVING '.$this->_compile_conditions($db, $this->_having);
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

		if ($this->_offset !== null)
		{
			// Add offsets
			$query .= ' OFFSET '.$this->_offset;
		}

		if ( ! empty($this->_union))
		{
			$query = '('.$query.')';
			foreach ($this->_union as $u) {
				$query .= ' UNION ';
				if ($u['all'] === true)
				{
					$query .= 'ALL ';
				}
				$query .= '('.$u['select']->compile($db).')';
			}
		}

		$this->_sql = $query;

		return parent::compile($db);
	}

	public function reset(): static
	{
		$this->_select   =
		$this->_from     =
		$this->_join     =
		$this->_where    =
		$this->_group_by =
		$this->_having   =
		$this->_order_by =
		$this->_union = [];

		$this->_distinct = false;

		$this->_limit     =
		$this->_offset    =
		$this->_last_join = null;

		$this->_parameters = [];

		$this->_sql = '';

		return $this;
	}

} // End Database_Query_Select
