<?php
/**
 * Database connection wrapper/helper.
 *
 * You may get a database instance using `Database::instance('name')` where
 * name is the [config](database/config) group.
 *
 * This class provides connection instance management via Database Drivers, as
 * well as quoting, escaping and other related functions. Querys are done using
 * [Database_Query] and [Database_Query_Builder] objects, which can be easily
 * created using the [DB] helper class.
 *
 * @package    Kohana/Database
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Database {

	// Query types
	const int SELECT =  1;
	const int INSERT =  2;
	const int UPDATE =  3;
	const int DELETE =  4;

	/**
	 * @var  string  default instance name
	 */
	public static string $default = 'default';

	/**
	 * @var  array<string, Database>  Database instances
	 */
	public static array $instances = [];

	/**
	 * Get a singleton Database instance. If configuration is not specified,
	 * it will be loaded from the database configuration file using the same
	 * group as the name.
	 *
	 *     // Load the default database
	 *     $db = Database::instance();
	 *
	 *     // Create a custom configured instance
	 *     $db = Database::instance('custom', $config);
	 *
	 * @param   string   $name    instance name
	 * @param   array<string, mixed>    $config  configuration parameters
	 * @return  Database
	 */
	public static function instance(?string $name = null, ?array $config = null): Database
	{
		if ($name === null)
		{
			// Use the default instance name
			$name = Database::$default;
		}

		if ( ! isset(Database::$instances[$name]))
		{
			if ($config === null)
			{
				// Load the configuration for this database
				$config = Kohana::$config->load('database')->$name;
			}

			if ( ! is_array($config) OR ! is_string($config['type'] ?? null))
			{
				throw new Kohana_Exception('Database type not defined in :name configuration',
					[':name' => $name]);
			}

			/** @var class-string<Database> $driver  the driver class, e.g. Database_PDO */
			$driver = 'Database_'.ucfirst($config['type']);

			// Create the database connection instance and store it
			Database::$instances[$name] = new $driver($name, $config);
		}

		return Database::$instances[$name];
	}

	/**
	 * @var  string  the last query executed
	 */
	public ?string $last_query = null;

	/**
	 * @var  array<int, mixed>  the values that were sent with the last query, in the
	 *              order of the "?" placeholders of $last_query; empty when
	 *              the query had no separate values
	 */
	public array $last_params = [];

	// Character that is used to quote identifiers
	protected string $_identifier = '"';

	// Instance name
	protected string $_instance;

	// Raw server connection
	protected mixed $_connection = null;

	/**
	 * @var  array<string, mixed>  Configuration array
	 */
	protected array $_config;

	/**
	 * @var  bool  Is a transaction started by Database::transaction() running?
	 */
	protected bool $_in_transaction = false;

	/**
	 * @var  array<int, mixed>|null  While a query is compiled for a prepared statement,
	 *                   the values collected so far; null at any other time
	 */
	protected ?array $_values = null;

	/**
	 * Stores the database configuration locally and name the instance.
	 *
	 * [!!] This method cannot be accessed directly, you must use [Database::instance].
	 *
	 * @param   array<string, mixed>  $config
	 * @return  void
	 */
	public function __construct(string $name, array $config)
	{
		// Set the instance name
		$this->_instance = $name;

		// Store the config locally
		$this->_config = $config;

		if (empty($this->_config['table_prefix']))
		{
			$this->_config['table_prefix'] = '';
		}
	}

	/**
	 * Disconnect from the database when the object is destroyed.
	 *
	 *     // Destroy the database instance
	 *     unset(Database::instances[(string) $db], $db);
	 *
	 * [!!] Calling `unset($db)` is not enough to destroy the database, as it
	 * will still be stored in `Database::$instances`.
	 *
	 * @return  void
	 */
	public function __destruct()
	{
		$this->disconnect();
	}

	/**
	 * Returns the database instance name.
	 *
	 *     echo (string) $db;
	 *
	 * @return  string
	 */
	public function __toString(): string
	{
		return $this->_instance;
	}

	/**
	 * Connect to the database. This is called automatically when the first
	 * query is executed.
	 *
	 *     $db->connect();
	 *
	 * @throws  Database_Exception
	 * @return  void
	 */
	abstract public function connect(): void;

	/**
	 * Disconnect from the database. This is called automatically by [Database::__destruct].
	 * Clears the database instance from [Database::$instances].
	 *
	 *     $db->disconnect();
	 *
	 * The object can be used again afterwards: the next query, or
	 * [Database::connect], opens a new connection with the same settings.
	 *
	 * @return  bool
	 */
	public function disconnect(): bool
	{
		unset(Database::$instances[$this->_instance]);

		return true;
	}

	/**
	 * Closes the connection and opens a new one with the same settings.
	 * For tasks that run a long time: a database server closes a
	 * connection that was not used for a while ("server has gone away").
	 *
	 *     $db->reconnect();
	 *
	 * [!!] A transaction that is open is lost, and so are temporary
	 * tables and session variables of the old connection.
	 *
	 * @return  void
	 * @throws  Database_Exception  when the new connection fails
	 */
	public function reconnect(): void
	{
		$this->disconnect();
		$this->connect();
	}

	/**
	 * Puts this object back in [Database::$instances] when it connects,
	 * after disconnect() took it out, so that [Database::instance] keeps
	 * returning the one object and no second connection is made.
	 *
	 * @return  void
	 */
	protected function _register(): void
	{
		if ($this instanceof Database AND ! isset(Database::$instances[$this->_instance]))
		{
			Database::$instances[$this->_instance] = $this;
		}
	}

	/**
	 * What var_dump() and the like show of a connection: everything but
	 * the settings of the connection, which hold the password.
	 *
	 * @return  array<string, mixed>
	 */
	public function __debugInfo(): array
	{
		/** @var array<string, mixed> $info */
		$info = get_object_vars($this);

		if (is_array($info['_config']) AND isset($info['_config']['connection']))
		{
			$info['_config']['connection'] = '*** hidden ***';
		}

		return $info;
	}

	/**
	 * Set the connection character set. This is called automatically by [Database::connect].
	 *
	 *     $db->set_charset('utf8');
	 *
	 * @throws  Database_Exception
	 * @param   string   $charset  character set name
	 * @return  void
	 */
	abstract public function set_charset(string $charset): void;

	/**
	 * Perform an SQL query of the given type.
	 *
	 *     // Make a SELECT query and use objects for results
	 *     $db->query(Database::SELECT, 'SELECT * FROM groups', true);
	 *
	 *     // Make a SELECT query and use "Model_User" for the results
	 *     $db->query(Database::SELECT, 'SELECT * FROM users LIMIT 1', 'Model_User');
	 *
	 * @param   int  $type       Database::SELECT, Database::INSERT, etc
	 * @param   string   $sql        SQL query
	 * @param   bool|string    $as_object  result object class string, true for stdClass, false for assoc array
	 * @param   array<int|string, mixed>    $params     object construct parameters for result class
	 * @param   array<int, mixed>    $values     values for the "?" placeholders in the SQL; when given, the query
	 *                               is run as a prepared statement (see Database::prepare_query)
	 * @return  ($type is Database::SELECT ? Database_Result : ($type is Database::INSERT ? array{0: mixed, 1: int} : int))  Database_Result for SELECT queries, array(insert_id, affected_rows) for INSERT queries, affected_rows for all other queries
	 */
	abstract public function query(?int $type, string $sql, bool|string $as_object = false, ?array $params = null, array $values = []): mixed;

	/**
	 * Start a SQL transaction
	 *
	 *     // Start the transactions
	 *     $db->begin();
	 *
	 *     try {
	 *          DB::insert('users')->values($user1)...
	 *          DB::insert('users')->values($user2)...
	 *          // Insert successful commit the changes
	 *          $db->commit();
	 *     }
	 *     catch (Database_Exception $e)
	 *     {
	 *          // Insert failed. Rolling back changes...
	 *          $db->rollback();
	 *      }
	 *
	 * @param string $mode  transaction mode
	 * @return  bool
	 */
	abstract public function begin(?string $mode = null): bool;

	/**
	 * Commit the current transaction
	 *
	 *     // Commit the database changes
	 *     $db->commit();
	 *
	 * @return  bool
	 */
	abstract public function commit(): bool;

	/**
	 * Abort the current transaction
	 *
	 *     // Undo the changes
	 *     $db->rollback();
	 *
	 * @return  bool
	 */
	abstract public function rollback(): bool;

	/**
	 * Starts the benchmark of a query for the profiler. The benchmark
	 * remembers the place in the code that ran the query: the nearest
	 * step inside the application, or else the nearest one outside this
	 * module and the system.
	 *
	 * Only called while [Kohana::$profiling] is on.
	 *
	 * @param   string  $sql  the query
	 * @return  string  token for [Profiler::stop]
	 */
	protected function _benchmark(string $sql): string
	{
		// The classes of this module: where the search starts from
		$module = dirname(__DIR__);

		$file = $line = null;

		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30) as $step)
		{
			if ( ! isset($step['file']) OR str_starts_with($step['file'], $module) OR str_starts_with($step['file'], SYSPATH))
				continue;

			if ($file === null)
			{
				// The nearest step outside the database code (the ORM, another module)
				[$file, $line] = [$step['file'], $step['line'] ?? 0];
			}

			if (str_starts_with($step['file'], APPPATH))
			{
				// The nearest step in the application: the best answer
				[$file, $line] = [$step['file'], $step['line'] ?? 0];

				break;
			}
		}

		return Profiler::start("Database ({$this->_instance})", $sql, $file, $line);
	}

	/**
	 * Runs a function inside a transaction: everything it does to the
	 * database is kept if it finishes, and undone if it throws.
	 *
	 *     $order = $db->transaction(function (Database $db) use ($cart)
	 *     {
	 *         $order = ORM::factory('Order')->values($cart)->create();
	 *         DB::update('stock')->set(['amount' => DB::expr('amount - 1')])->execute($db);
	 *
	 *         return $order;
	 *     });
	 *
	 * The exception that undid the transaction is thrown again, so the
	 * caller still sees it. A call made inside another one joins the
	 * transaction that is already running.
	 *
	 * @param   callable     $callback  function to run; it receives this database
	 * @param   string|null  $mode      transaction mode, as for Database::begin()
	 * @return  mixed        what the function returned
	 * @throws  Throwable    whatever the function threw
	 */
	public function transaction(callable $callback, ?string $mode = null): mixed
	{
		if ($this->_in_transaction)
		{
			// Already inside a transaction: the outer call commits or rolls back
			return $callback($this);
		}

		$this->begin($mode);
		$this->_in_transaction = true;

		try
		{
			$result = $callback($this);

			$this->commit();

			return $result;
		}
		catch (Throwable $e)
		{
			$this->rollback();

			throw $e;
		}
		finally
		{
			$this->_in_transaction = false;
		}
	}

	/**
	 * Tells whether queries are run as prepared statements on this
	 * connection. They are, unless the connection is configured with
	 * `'prepared' => false`.
	 *
	 * @return  bool
	 */
	public function prepared(): bool
	{
		return (bool) ($this->_config['prepared'] ?? true);
	}

	/**
	 * Compiles a query for a prepared statement: the SQL with a "?" where
	 * each text value belongs, and the list of those values in the same order.
	 *
	 *     list($sql, $values) = $db->prepare_query(DB::select()->from('users')->where('name', '=', 'Ana'));
	 *     // 'SELECT * FROM "users" WHERE "name" = ?', ['Ana']
	 *
	 * The database receives the SQL and the values separately, so a value
	 * can never be read as part of the SQL, whatever it contains. Numbers,
	 * null and true/false are harmless and stay in the SQL.
	 *
	 * @param   Database_Query  $query  query to compile
	 * @return  array{string, array<int, mixed>}  list of: SQL string, values
	 */
	public function prepare_query(Database_Query $query): array
	{
		// Remember the state, in case this is called while another query is compiled
		$previous = $this->_values;

		// From here on, quote() collects text values instead of escaping them
		$this->_values = [];

		try
		{
			$sql = $query->compile($this);

			/** @var array<int, mixed> $collected  the values quote() collected while the query was compiled */
			$collected = $this->_values;
		}
		finally
		{
			$this->_values = $previous;
		}

		$values = [];

		// The markers are in the SQL in the order the database must receive
		// the values, which is not always the order they were collected in.
		// A value used twice in the query is sent twice.
		$sql = (string) preg_replace_callback('/\x02KV(\d+)\x03/', function (array $match) use ($collected, &$values): string
		{
			$values[] = $collected[(int) $match[1]];

			return '?';
		}, $sql);

		return [$sql, $values];
	}

	/**
	 * Count the number of records in a table.
	 *
	 *     // Get the total number of records in the "users" table
	 *     $count = $db->count_records('users');
	 *
	 * @param   mixed    $table  table name string or array(query, alias)
	 * @return  int
	 */
	public function count_records(mixed $table): int
	{
		// Quote the table name
		$table = $this->quote_table($table);

		/** @var int|string $count */
		$count = $this->query(Database::SELECT, 'SELECT COUNT(*) AS total_row_count FROM '.$table, false)
			->get('total_row_count');

		return (int) $count;
	}

	/**
	 * Returns a normalized array describing the SQL data type
	 *
	 *     $db->datatype('char');
	 *
	 * @param   string  $type  SQL data type
	 * @return  array<string, mixed>
	 */
	public function datatype(string $type): array
	{
		/** @var array<string, array<string, mixed>> $types */
		static $types = [
			// SQL-92
			'bit'                           => ['type' => 'string', 'exact' => true],
			'bit varying'                   => ['type' => 'string'],
			'char'                          => ['type' => 'string', 'exact' => true],
			'char varying'                  => ['type' => 'string'],
			'character'                     => ['type' => 'string', 'exact' => true],
			'character varying'             => ['type' => 'string'],
			'date'                          => ['type' => 'string'],
			'dec'                           => ['type' => 'float', 'exact' => true],
			'decimal'                       => ['type' => 'float', 'exact' => true],
			'double precision'              => ['type' => 'float'],
			'float'                         => ['type' => 'float'],
			'int'                           => ['type' => 'int', 'min' => '-2147483648', 'max' => '2147483647'],
			'integer'                       => ['type' => 'int', 'min' => '-2147483648', 'max' => '2147483647'],
			'interval'                      => ['type' => 'string'],
			'national char'                 => ['type' => 'string', 'exact' => true],
			'national char varying'         => ['type' => 'string'],
			'national character'            => ['type' => 'string', 'exact' => true],
			'national character varying'    => ['type' => 'string'],
			'nchar'                         => ['type' => 'string', 'exact' => true],
			'nchar varying'                 => ['type' => 'string'],
			'numeric'                       => ['type' => 'float', 'exact' => true],
			'real'                          => ['type' => 'float'],
			'smallint'                      => ['type' => 'int', 'min' => '-32768', 'max' => '32767'],
			'time'                          => ['type' => 'string'],
			'time with time zone'           => ['type' => 'string'],
			'timestamp'                     => ['type' => 'string'],
			'timestamp with time zone'      => ['type' => 'string'],
			'varchar'                       => ['type' => 'string'],

			// SQL:1999
			'binary large object'               => ['type' => 'string', 'binary' => true],
			'blob'                              => ['type' => 'string', 'binary' => true],
			'boolean'                           => ['type' => 'bool'],
			'char large object'                 => ['type' => 'string'],
			'character large object'            => ['type' => 'string'],
			'clob'                              => ['type' => 'string'],
			'national character large object'   => ['type' => 'string'],
			'nchar large object'                => ['type' => 'string'],
			'nclob'                             => ['type' => 'string'],
			'time without time zone'            => ['type' => 'string'],
			'timestamp without time zone'       => ['type' => 'string'],

			// SQL:2003
			'bigint'    => ['type' => 'int', 'min' => '-9223372036854775808', 'max' => '9223372036854775807'],

			// SQL:2008
			'binary'            => ['type' => 'string', 'binary' => true, 'exact' => true],
			'binary varying'    => ['type' => 'string', 'binary' => true],
			'varbinary'         => ['type' => 'string', 'binary' => true],
		];

		if (isset($types[$type]))
			return $types[$type];

		return [];
	}

	/**
	 * Returns the kind of database server behind this connection: "mysql",
	 * "pgsql" or "sqlite" (or what another PDO driver calls itself). For
	 * code that writes SQL each database spells differently, such as a
	 * migration that creates tables.
	 *
	 *     $id = ($db->driver() === 'pgsql') ? 'id SERIAL PRIMARY KEY' : ...;
	 *
	 * @return  string  an empty string when the driver does not tell
	 */
	public function driver(): string
	{
		return '';
	}

	/**
	 * List all of the tables in the database. Optionally, a LIKE string can
	 * be used to search for specific tables.
	 *
	 *     // Get all tables in the current database
	 *     $tables = $db->list_tables();
	 *
	 *     // Get all user-related tables
	 *     $tables = $db->list_tables('user%');
	 *
	 * @param   string   $like  table to search for
	 * @return  array<int, string>
	 */
	abstract public function list_tables(?string $like = null): array;

	/**
	 * Lists all of the columns in a table. Optionally, a LIKE string can be
	 * used to search for specific fields.
	 *
	 *     // Get all columns from the "users" table
	 *     $columns = $db->list_columns('users');
	 *
	 *     // Get all name-related columns
	 *     $columns = $db->list_columns('users', '%name%');
	 *
	 *     // Get the columns from a table that doesn't use the table prefix
	 *     $columns = $db->list_columns('users', null, false);
	 *
	 * @param   string  $table       table to get columns from
	 * @param   string  $like        column to search for
	 * @param   bool $add_prefix  whether to add the table prefix automatically or not
	 * @return  array<string, array<string, mixed>>
	 */
	abstract public function list_columns(string $table, ?string $like = null, bool $add_prefix = true): array;

	/**
	 * Extracts the text between parentheses, if any.
	 *
	 *     // Returns: array('CHAR', '6')
	 *     list($type, $length) = $db->_parse_type('CHAR(6)');
	 *
	 * @param   string  $type
	 * @return  array<int, string|null>   list containing the type and length, if any
	 */
	protected function _parse_type(string $type): array
	{
		if (($open = strpos($type, '(')) === false)
		{
			// No length specified
			return [$type, null];
		}

		// Closing parenthesis
		$close = strrpos($type, ')', $open);

		// Length without parentheses
		$length = substr($type, $open + 1, $close - 1 - $open);

		// Type without the length
		$type = substr($type, 0, $open).substr($type, $close + 1);

		return [$type, $length];
	}

	/**
	 * Return the table prefix defined in the current configuration.
	 *
	 *     $prefix = $db->table_prefix();
	 *
	 * @return  string
	 */
	public function table_prefix(): string
	{
		return is_string($prefix = $this->_config['table_prefix'] ?? '') ? $prefix : '';
	}

	/**
	 * Quote a value for an SQL query.
	 *
	 *     $db->quote(null);   // 'null'
	 *     $db->quote(10);     // 10
	 *     $db->quote('fred'); // 'fred'
	 *
	 * Objects passed to this function will be converted to strings.
	 * [Database_Expression] objects will be compiled.
	 * [Database_Query] objects will be compiled and converted to a sub-query.
	 * [stdClass] objects will be serialized using the `serialize` method.
	 * All other objects will be converted using the `__toString` method.
	 *
	 * @param   mixed   $value  any value to quote
	 * @return  string
	 * @uses    Database::escape
	 */
	public function quote(mixed $value): string|int
	{
		if ($value === null)
		{
			return 'NULL';
		}
		elseif ($value === true)
		{
			return "'1'";
		}
		elseif ($value === false)
		{
			return "'0'";
		}
		elseif (is_object($value))
		{
			if ($value instanceof Database_Query)
			{
				// Create a sub-query
				return '('.$value->compile($this).')';
			}
			elseif ($value instanceof Database_Expression)
			{
				// Compile the expression
				return $value->compile($this);
			}
			elseif ($value instanceof stdClass)
			{
				// Convert the object to a string
				// Object of class stdClass could not be converted to string
				return $this->quote(serialize($value));
			}
			else
			{
				// Convert the object to a string. An object that cannot be
				// written as text is stored like any other object.
				return $this->quote(($value instanceof Stringable) ? (string) $value : serialize($value));
			}
		}
		elseif (is_array($value))
		{
			return '('.implode(', ', array_map([$this, __FUNCTION__], $value)).')';
		}
		elseif (is_int($value))
		{
			return $value;
		}
		elseif (is_float($value))
		{
			// Convert to non-locale aware float to prevent possible commas
			return sprintf('%F', $value);
		}

		/** @var string $value  null, booleans, objects, lists and numbers were handled above */
		if ($this->_values !== null)
		{
			// The query is being compiled for a prepared statement: keep the
			// value aside and leave a marker where it belongs in the SQL
			$this->_values[] = (string) $value;

			// (The control characters cannot appear in SQL written by hand,
			// and are not removed by trim() as a NUL byte would be.)
			return "\x02KV".(count($this->_values) - 1)."\x03";
		}

		return $this->escape($value);
	}

	/**
	 * Quote a database column name and add the table prefix if needed.
	 *
	 *     $column = $db->quote_column($column);
	 *
	 * You can also use SQL methods within identifiers.
	 *
	 *     $column = $db->quote_column(DB::expr('COUNT(`column`)'));
	 *
	 * Objects passed to this function will be converted to strings.
	 * [Database_Expression] objects will be compiled.
	 * [Database_Query] objects will be compiled and converted to a sub-query.
	 * All other objects will be converted using the `__toString` method.
	 *
	 * @param   mixed   $column  column name or array(column, alias)
	 * @return  string
	 * @uses    Database::quote_identifier
	 * @uses    Database::table_prefix
	 */
	public function quote_column(mixed $column): string
	{
		// Identifiers are escaped by repeating them
		$escaped_identifier = $this->_identifier.$this->_identifier;

		if (is_array($column))
		{
			/** @var array{0: mixed, 1: string} $column  the name and its alias */
			list($column, $alias) = $column;
			$alias = str_replace($this->_identifier, $escaped_identifier, $alias);
		}

		if ($column instanceof Database_Query)
		{
			// Create a sub-query
			$column = '('.$column->compile($this).')';
		}
		elseif ($column instanceof Database_Expression)
		{
			// Compile the expression
			$column = $column->compile($this);
		}
		else
		{
			// Convert to a string
			$column = is_scalar($column) ? (string) $column : '';

			$column = str_replace($this->_identifier, $escaped_identifier, $column);

			if ($column === '*')
			{
				return $column;
			}
			elseif (strpos($column, '.') !== false)
			{
				$parts = explode('.', $column);

				if ($prefix = $this->table_prefix())
				{
					// Get the offset of the table name, 2nd-to-last part
					$offset = count($parts) - 2;

					// Add the table prefix to the table name
					$parts[$offset] = $prefix.$parts[$offset];
				}

				foreach ($parts as & $part)
				{
					if ($part !== '*')
					{
						// Quote each of the parts
						$part = $this->_identifier.$part.$this->_identifier;
					}
				}

				$column = implode('.', $parts);
			}
			else
			{
				$column = $this->_identifier.$column.$this->_identifier;
			}
		}

		if (isset($alias))
		{
			$column .= ' AS '.$this->_identifier.$alias.$this->_identifier;
		}

		return $column;
	}

	/**
	 * Quote a database table name and adds the table prefix if needed.
	 *
	 *     $table = $db->quote_table($table);
	 *
	 * Objects passed to this function will be converted to strings.
	 * [Database_Expression] objects will be compiled.
	 * [Database_Query] objects will be compiled and converted to a sub-query.
	 * All other objects will be converted using the `__toString` method.
	 *
	 * @param   mixed   $table  table name or array(table, alias)
	 * @return  string
	 * @uses    Database::quote_identifier
	 * @uses    Database::table_prefix
	 */
	public function quote_table(mixed $table): string
	{
		// Identifiers are escaped by repeating them
		$escaped_identifier = $this->_identifier.$this->_identifier;

		if (is_array($table))
		{
			/** @var array{0: mixed, 1: string} $table  the name and its alias */
			list($table, $alias) = $table;
			$alias = str_replace($this->_identifier, $escaped_identifier, $alias);
		}

		if ($table instanceof Database_Query)
		{
			// Create a sub-query
			$table = '('.$table->compile($this).')';
		}
		elseif ($table instanceof Database_Expression)
		{
			// Compile the expression
			$table = $table->compile($this);
		}
		else
		{
			// Convert to a string
			$table = is_scalar($table) ? (string) $table : '';

			$table = str_replace($this->_identifier, $escaped_identifier, $table);

			if (strpos($table, '.') !== false)
			{
				$parts = explode('.', $table);

				if ($prefix = $this->table_prefix())
				{
					// Get the offset of the table name, last part
					$offset = count($parts) - 1;

					// Add the table prefix to the table name
					$parts[$offset] = $prefix.$parts[$offset];
				}

				foreach ($parts as & $part)
				{
					// Quote each of the parts
					$part = $this->_identifier.$part.$this->_identifier;
				}

				$table = implode('.', $parts);
			}
			else
			{
				// Add the table prefix
				$table = $this->_identifier.$this->table_prefix().$table.$this->_identifier;
			}
		}

		if (isset($alias))
		{
			// Attach table prefix to alias
			$table .= ' AS '.$this->_identifier.$this->table_prefix().$alias.$this->_identifier;
		}

		return $table;
	}

	/**
	 * Quote a database identifier
	 *
	 * Objects passed to this function will be converted to strings.
	 * [Database_Expression] objects will be compiled.
	 * [Database_Query] objects will be compiled and converted to a sub-query.
	 * All other objects will be converted using the `__toString` method.
	 *
	 * @param   mixed   $value  any identifier
	 * @return  string
	 */
	public function quote_identifier(mixed $value): string
	{
		// Identifiers are escaped by repeating them
		$escaped_identifier = $this->_identifier.$this->_identifier;

		if (is_array($value))
		{
			/** @var array{0: mixed, 1: string} $value  the name and its alias */
			list($value, $alias) = $value;
			$alias = str_replace($this->_identifier, $escaped_identifier, $alias);
		}

		if ($value instanceof Database_Query)
		{
			// Create a sub-query
			$value = '('.$value->compile($this).')';
		}
		elseif ($value instanceof Database_Expression)
		{
			// Compile the expression
			$value = $value->compile($this);
		}
		else
		{
			// Convert to a string
			$value = is_scalar($value) ? (string) $value : '';

			$value = str_replace($this->_identifier, $escaped_identifier, $value);

			if (strpos($value, '.') !== false)
			{
				$parts = explode('.', $value);

				foreach ($parts as & $part)
				{
					// Quote each of the parts
					$part = $this->_identifier.$part.$this->_identifier;
				}

				$value = implode('.', $parts);
			}
			else
			{
				$value = $this->_identifier.$value.$this->_identifier;
			}
		}

		if (isset($alias))
		{
			$value .= ' AS '.$this->_identifier.$alias.$this->_identifier;
		}

		return $value;
	}

	/**
	 * Sanitize a string by escaping characters that could cause an SQL
	 * injection attack.
	 *
	 *     $value = $db->escape('any string');
	 *
	 * @param   string   $value  value to quote
	 * @return  string
	 */
	abstract public function escape(mixed $value): string;

} // End Database_Connection
