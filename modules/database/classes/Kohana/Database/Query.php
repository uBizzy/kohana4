<?php
/**
 * Database query wrapper.  See [Parameterized Statements](database/query/parameterized) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Database_Query {

	// Query type
	protected ?int $_type = null;

	// Execute the query during a cache hit
	protected bool $_force_execute = false;

	// Cache lifetime
	protected ?int $_lifetime = null;

	// SQL statement
	protected string $_sql = '';

	/**
	 * @var  array<string, mixed>  Quoted query parameters
	 */
	protected array $_parameters = [];

	// Return results as associative arrays or objects
	protected bool|string $_as_object = false;

	/**
	 * @var  array<int|string, mixed>  Parameters for __construct when using object results
	 */
	protected array $_object_params = [];

	/**
	 * Creates a new SQL query of the specified type.
	 *
	 * @param   int  $type  query type: Database::SELECT, Database::INSERT, etc
	 * @param   string   $sql   query string
	 * @return  void
	 */
	public function __construct(?int $type, string $sql)
	{
		$this->_type = $type;
		$this->_sql = $sql;
	}

	/**
	 * Return the SQL query string.
	 *
	 * @return  string
	 */
	public function __toString(): string
	{
		try
		{
			// Return the SQL string
			return $this->compile(Database::instance());
		}
		catch (Throwable $e)
		{
			return Kohana_Exception::text($e);
		}
	}

	/**
	 * Get the type of the query.
	 *
	 * @return  int
	 */
	public function type(): ?int
	{
		return $this->_type;
	}

	/**
	 * Enables the query to be cached for a specified amount of time.
	 *
	 * @param   int  $lifetime  number of seconds to cache, 0 deletes it from the cache
	 * @param   bool  $force	whether or not to execute the query during a cache hit
	 * @return  $this
	 * @uses    Kohana::$cache_life
	 */
	public function cached(?int $lifetime = null, bool $force = false): static
	{
		if ($lifetime === null)
		{
			// Use the global setting
			$lifetime = Kohana::$cache_life;
		}

		$this->_force_execute = $force;
		$this->_lifetime = $lifetime;

		return $this;
	}

	/**
	 * Returns results as associative arrays
	 *
	 * @return  $this
	 */
	public function as_assoc(): static
	{
		$this->_as_object = false;

		$this->_object_params = [];

		return $this;
	}

	/**
	 * Returns results as objects
	 *
	 * @param   string  $class  classname or true for stdClass
	 * @param   array<int|string, mixed>   $params
	 * @return  $this
	 */
	public function as_object(bool|string $class = true, ?array $params = null): static
	{
		$this->_as_object = $class;

		if ($params)
		{
			// Add object parameters
			$this->_object_params = $params;
		}

		return $this;
	}

	/**
	 * Set the value of a parameter in the query.
	 *
	 * @param   string   $param  parameter key to replace
	 * @param   mixed    $value  value to use
	 * @return  $this
	 */
	public function param(string $param, mixed $value): static
	{
		// Add or overload a new parameter
		$this->_parameters[$param] = $value;

		return $this;
	}

	/**
	 * Bind a variable to a parameter in the query.
	 *
	 * @param   string  $param  parameter key to replace
	 * @param   mixed   $var    variable to use
	 * @return  $this
	 */
	public function bind(string $param, mixed & $var): static
	{
		// Bind a value to a variable
		$this->_parameters[$param] =& $var;

		return $this;
	}

	/**
	 * Add multiple parameters to the query.
	 *
	 * @param   array<string, mixed>  $params  list of parameters
	 * @return  $this
	 */
	public function parameters(array $params): static
	{
		// Merge the new parameters in
		$this->_parameters = $params + $this->_parameters;

		return $this;
	}

	/**
	 * Compile the SQL query and return it. Replaces any parameters with their
	 * given values.
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

		// Import the SQL locally
		$sql = $this->_sql;

		if ( ! empty($this->_parameters))
		{
			// Quote all of the values
			$values = array_map([$db, 'quote'], $this->_parameters);

			// Replace the values in the SQL
			$sql = strtr($sql, $values);
		}

		return $sql;
	}

	/**
	 * Execute the current query on the given database.
	 *
	 * @param   Database|string|null    $db  Database instance or name of instance
	 * @param   bool|string|null  $as_object  result object classname, true for stdClass or false for array
	 * @param   array<int|string, mixed>|null  $object_params  result object constructor arguments
	 * @return  ($this is Database_Query_Builder_Select ? Database_Result : ($this is Database_Query_Builder_Insert ? array{0: mixed, 1: int} : ($this is Database_Query_Builder_Update|Database_Query_Builder_Delete ? int : mixed)))  a Database_Result for SELECT queries, the insert id and number of rows for INSERT, the number of rows for anything else
	 */
	public function execute(Database|string|null $db = null, bool|string|null $as_object = null, ?array $object_params = null): mixed
	{
		if ( ! is_object($db))
		{
			// Get the database instance
			$db = Database::instance($db);
		}

		if ($as_object === null)
		{
			$as_object = $this->_as_object;
		}

		if ($object_params === null)
		{
			$object_params = $this->_object_params;
		}

		if ($db->prepared())
		{
			// The SQL with placeholders, and the values to send with it
			list($sql, $values) = $db->prepare_query($this);
		}
		else
		{
			// The SQL with the values escaped and written into it
			$sql    = $this->compile($db);
			$values = [];
		}

		if ($this->_lifetime !== null AND $this->_type === Database::SELECT)
		{
			// Set the cache key based on the database instance name, the SQL and its values
			$cache_key = 'Database::query("'.$db.'", "'.$sql.'"'.($values ? ', '.hash('sha256', serialize($values)) : '').')';

			// Read the cache first to delete a possible hit with lifetime <= 0
			if (is_array($result = Kohana::cache($cache_key, null, $this->_lifetime))
				AND ! $this->_force_execute)
			{
				// Return a cached result
				return new Database_Result_Cached(array_values($result), $sql, $as_object, $object_params);
			}
		}

		// Execute the query
		$result = $db->query($this->_type, $sql, $as_object, $object_params, $values);

		if (isset($cache_key) AND $this->_lifetime > 0 AND $result instanceof Database_Result)
		{
			// Cache the result array
			Kohana::cache($cache_key, $result->as_array(), $this->_lifetime);
		}

		return $result;
	}

} // End Database_Query
