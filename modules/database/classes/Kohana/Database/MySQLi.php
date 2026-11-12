<?php
/**
 * MySQLi database connection.
 *
 * @package    Kohana/Database
 * @category   Drivers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Database_MySQLi extends Database {

	/**
	 * Database in use by each connection
	 *
	 * @var array<string, string>
	 */
	protected static array $_current_databases = [];

	/**
	 * Use SET NAMES to set the character set
	 *
	 * @var bool|null
	 */
	protected static ?bool $_set_names = null;

	/**
	 * Identifier for this connection within the PHP driver
	 *
	 * @var string
	 */
	protected ?string $_connection_id = null;

	/**
	 * @var  int  Number of prepared statements kept ready for reuse
	 */
	const int STATEMENT_CACHE_SIZE = 100;

	/**
	 * @var  array<string, mysqli_stmt>  Prepared statements of this connection, by their SQL.
	 *              Preparing costs a round trip to the database; a query
	 *              that is run again (in a loop, for example) reuses its
	 *              statement and is then as fast as a plain query.
	 */
	protected array $_statements = [];

	/**
	 * MySQL uses a backtick for identifiers
	 *
	 * @var string
	 */
	protected string $_identifier = '`';

	/**
	 * The open connection. Connects first when that was not done yet.
	 *
	 * @return  mysqli
	 * @throws  Database_Exception  if there is no connection
	 */
	protected function _mysqli(): mysqli
	{
		$this->_connection or $this->connect();

		if ( ! $this->_connection instanceof mysqli)
			throw new Database_Exception('There is no connection to the database :name', [':name' => $this->_instance]);

		return $this->_connection;
	}

	/**
	 * Connect to the database. This is called automatically when the first
	 * query is executed.
	 *
	 * @throws Database_Exception
	 * @return void
	 */
	public function connect(): void
	{
		if ($this->_connection)
			return;

		// mysqli_set_charset() is always available on supported PHP versions
		Database_MySQLi::$_set_names = false;

		// Connection settings, with their defaults
		/** @var array{database: string, hostname: string, username: string, password: string, socket: string, port: int|string, ssl: array<string, string|null>|null, variables?: array<string, mixed>} $connection */
		$connection = (array) ($this->_config['connection'] ?? []) + [
			'database' => '',
			'hostname' => '',
			'username' => '',
			'password' => '',
			'socket'   => '',
			'port'     => 3306,
			'ssl'      => null,
		];

		// The settings are kept, so the connection can be opened again after
		// disconnect(). They are hidden from dumps by Database::__debugInfo().
		$this->_register();

		try
		{
			$this->_connection = mysqli_init();

			if ($this->_connection === false)
				throw new Database_Exception('The MySQLi extension could not start a connection');

			$flags = 0;

			if (is_array($connection['ssl']))
			{
				// Encrypted connection
				$this->_mysqli()->ssl_set(
					$connection['ssl']['client_key_path'] ?? null,
					$connection['ssl']['client_cert_path'] ?? null,
					$connection['ssl']['ca_cert_path'] ?? null,
					$connection['ssl']['ca_dir_path'] ?? null,
					$connection['ssl']['cipher'] ?? null
				);

				$flags = MYSQLI_CLIENT_SSL;
			}

			$connected = $this->_mysqli()->real_connect(
				$connection['hostname'],
				$connection['username'],
				$connection['password'],
				$connection['database'],
				(int) $connection['port'],
				$connection['socket'] ?: null,
				$flags
			);

			if ( ! $connected)
				throw new Database_Exception(':error', [':error' => mysqli_connect_error()], mysqli_connect_errno());
		}
		catch (Database_Exception $e)
		{
			// No connection exists
			$this->_connection = null;

			throw $e;
		}
		catch (Throwable $e)
		{
			// No connection exists
			$this->_connection = null;

			throw new Database_Exception(':error', [':error' => $e->getMessage()], $e->getCode(), $e);
		}

		// \xFF is a better delimiter, but the PHP driver uses underscore
		$this->_connection_id = hash('sha256', $connection['hostname'].'_'.$connection['username'].'_'.$connection['password']);

		if ( ! empty($this->_config['charset']))
		{
			// Set the character set
			$this->set_charset(is_string($this->_config['charset']) ? $this->_config['charset'] : 'utf8');
		}

		if ( ! empty($connection['variables']))
		{
			// Set session variables
			$variables = [];

			foreach ($connection['variables'] as $var => $val)
			{
				$variables[] = 'SESSION '.$var.' = '.$this->quote($val);
			}

			$this->_run('SET '.implode(', ', $variables));
		}
	}

	/**
	 * Runs a statement on the connection and returns what mysqli returns.
	 *
	 * Depending on the PHP configuration, mysqli reports a failed statement
	 * either by returning false or by throwing a mysqli_sql_exception. Both
	 * are turned into a [Database_Exception] here, so callers only ever
	 * have to deal with that one.
	 *
	 * @param   string  $sql     SQL statement
	 * @param   array<int, mixed>   $values  values for the "?" placeholders of a prepared statement
	 * @return  mysqli_result|bool
	 * @throws  Database_Exception
	 */
	protected function _run(string $sql, array $values = []): mysqli_result|bool
	{
		try
		{
			// With values, the statement is prepared and the values are sent separately
			$result = $values
				? $this->_execute_prepared($sql, $values)
				: $this->_mysqli()->query($sql);
		}
		catch (mysqli_sql_exception $e)
		{
			throw new Database_Exception(':error [ :query ]', [
				':error' => $e->getMessage(),
				':query' => $sql,
			], $e->getCode(), $e);
		}

		if ($result === false)
		{
			throw new Database_Exception(':error [ :query ]', [
				':error' => $this->_mysqli()->error,
				':query' => $sql,
			], $this->_mysqli()->errno);
		}

		return $result;
	}

	/**
	 * Runs a statement with separate values, preparing it first unless
	 * the same SQL was prepared before on this connection.
	 *
	 * @param   string  $sql     SQL with "?" placeholders
	 * @param   array<int, mixed>   $values  one value for each placeholder
	 * @return  mysqli_result|bool  the rows of a SELECT; true for other statements
	 * @throws  mysqli_sql_exception  when the database refuses the statement
	 */
	protected function _execute_prepared(string $sql, array $values): mysqli_result|bool
	{
		if ( ! isset($this->_statements[$sql]))
		{
			if (count($this->_statements) >= Database_MySQLi::STATEMENT_CACHE_SIZE)
			{
				// Make room: close and forget the oldest statement
				array_shift($this->_statements)->close();
			}

			$statement = $this->_mysqli()->prepare($sql);

			if ($statement === false)
				return false;

			$this->_statements[$sql] = $statement;
		}

		$statement = $this->_statements[$sql];

		if ( ! $statement->execute($values))
			return false;

		// A statement that returns rows gives a result; any other gives
		// false here without being an error
		$result = $statement->get_result();

		return ($result === false AND $statement->errno === 0) ? true : $result;
	}

	/**
	 * Disconnect from the database. This is called automatically by [Database::__destruct].
	 * Clears the database instance from Database::$instances.
	 *
	 * @return bool
	 */
	public function disconnect(): bool
	{
		// Nothing to close: already disconnected
		if ( ! $this->_connection instanceof mysqli)
			return true;

		try
		{
			$status = $this->_mysqli()->close();
		}
		catch (Throwable $e)
		{
			// The connection was already gone
			$status = true;
		}

		if ($status)
		{
			// The prepared statements belonged to the connection
			$this->_statements = [];

			// Clear the connection
			$this->_connection = null;

			// Clear the instance
			parent::disconnect();
		}

		return $status;
	}

	/**
	 * Set the connection character set. This is called automatically by [Database_MySQLi::connect].
	 *
	 * @param string $charset Character set name
	 * @throws Database_Exception
	 * @return void
	 */
	public function set_charset(string $charset): void
	{
		// Make sure the database is connected
		$this->_connection or $this->connect();

		try
		{
			$status = $this->_mysqli()->set_charset($charset);
		}
		catch (mysqli_sql_exception $e)
		{
			throw new Database_Exception(':error', [':error' => $e->getMessage()], $e->getCode(), $e);
		}

		if ($status === false)
		{
			throw new Database_Exception(':error', [':error' => $this->_mysqli()->error], $this->_mysqli()->errno);
		}
	}

	/**
	 * Execute a query on the database. This method is called by [Database::query].
	 *
	 * @param int    $type      Database::SELECT, Database::INSERT, etc
	 * @param string $sql       SQL query
	 * @param bool|string  $as_object Result object class string, true for stdClass, false for assoc array
	 * @param array<int|string, mixed>|null $params Object construct parameters for result class
	 * @param   array<int, mixed>  $values
	 * @throws Database_Exception
	 * @return ($type is Database::SELECT ? Database_Result : ($type is Database::INSERT ? array{0: mixed, 1: int} : int)) Database_Result for SELECT queries, array(insert_id, affected_rows) for INSERT queries, affected_rows for all other queries
	 */
	public function query(?int $type, string $sql, bool|string $as_object = false, ?array $params = null, array $values = []): mixed
	{
		// Make sure the database is connected
		$this->_connection or $this->connect();

		if (Kohana::$profiling)
		{
			// Benchmark this query for the current instance
			$benchmark = $this->_benchmark($sql);
		}

		try
		{
			// Execute the query
			$result = $this->_run($sql, $values);
		}
		catch (Database_Exception $e)
		{
			if (isset($benchmark))
			{
				// This benchmark is worthless
				Profiler::delete($benchmark);
			}

			throw $e;
		}

		if (isset($benchmark))
		{
			Profiler::stop($benchmark);
		}

		// Set the last query
		$this->last_query = $sql;
		$this->last_params = $values;

		if (isset($benchmark))
		{
			// How many rows the query returned or changed
			Profiler::note($benchmark, (($result instanceof mysqli_result) ? $result->num_rows : $this->_mysqli()->affected_rows).' rows');
		}

		if ($type === Database::SELECT)
		{
			if ( ! $result instanceof mysqli_result)
				throw new Database_Exception('The query gave no rows [ :query ]', [':query' => $sql]);

			// Return an iterator of results
			// A prepared statement returns numbers as numbers; the result
			// turns them back into strings, as every other query returns them
			return (new Database_MySQLi_Result($result, $sql, $as_object, $params))->stringify( (bool) $values);
		}
		elseif ($type === Database::INSERT)
		{
			// Return a list of insert id and rows created
			return [
				$this->_mysqli()->insert_id,
				(int) $this->_mysqli()->affected_rows,
			];
		}
		else
		{
			// Return the number of rows affected
			return (int) $this->_mysqli()->affected_rows;
		}
	}

	/**
	 * Returns an array of column meta information for a table.
	 * Provides the data type for use in [Database::datatype].
	 *
	 * @param string $type Column type
	 * @return array<string, mixed>
	 */
	public function datatype(string $type): array
	{
		/** @var array<string, array<string, mixed>> $types */
		static $types = [
			'blob'                      => ['type' => 'string', 'binary' => true, 'character_maximum_length' => '65535'],
			'bool'                      => ['type' => 'bool'],
			'bigint unsigned'           => ['type' => 'int', 'min' => '0', 'max' => '18446744073709551615'],
			'datetime'                  => ['type' => 'string'],
			'decimal unsigned'          => ['type' => 'float', 'exact' => true, 'min' => '0'],
			'double'                    => ['type' => 'float'],
			'double precision unsigned' => ['type' => 'float', 'min' => '0'],
			'double unsigned'           => ['type' => 'float', 'min' => '0'],
			'enum'                      => ['type' => 'string'],
			'fixed'                     => ['type' => 'float', 'exact' => true],
			'fixed unsigned'            => ['type' => 'float', 'exact' => true, 'min' => '0'],
			'float unsigned'            => ['type' => 'float', 'min' => '0'],
			'geometry'                  => ['type' => 'string', 'binary' => true],
			'int unsigned'              => ['type' => 'int', 'min' => '0', 'max' => '4294967295'],
			'integer unsigned'          => ['type' => 'int', 'min' => '0', 'max' => '4294967295'],
			'longblob'                  => ['type' => 'string', 'binary' => true, 'character_maximum_length' => '4294967295'],
			'longtext'                  => ['type' => 'string', 'character_maximum_length' => '4294967295'],
			'json'                      => ['type' => 'string', 'character_maximum_length' => '4294967295'],
			'mediumblob'                => ['type' => 'string', 'binary' => true, 'character_maximum_length' => '16777215'],
			'mediumint'                 => ['type' => 'int', 'min' => '-8388608', 'max' => '8388607'],
			'mediumint unsigned'        => ['type' => 'int', 'min' => '0', 'max' => '16777215'],
			'mediumtext'                => ['type' => 'string', 'character_maximum_length' => '16777215'],
			'national varchar'          => ['type' => 'string'],
			'numeric unsigned'          => ['type' => 'float', 'exact' => true, 'min' => '0'],
			'nvarchar'                  => ['type' => 'string'],
			'point'                     => ['type' => 'string', 'binary' => true],
			'real unsigned'             => ['type' => 'float', 'min' => '0'],
			'set'                       => ['type' => 'string'],
			'smallint unsigned'         => ['type' => 'int', 'min' => '0', 'max' => '65535'],
			'text'                      => ['type' => 'string', 'character_maximum_length' => '65535'],
			'tinyblob'                  => ['type' => 'string', 'binary' => true, 'character_maximum_length' => '255'],
			'tinyint'                   => ['type' => 'int', 'min' => '-128', 'max' => '127'],
			'tinyint unsigned'          => ['type' => 'int', 'min' => '0', 'max' => '255'],
			'tinytext'                  => ['type' => 'string', 'character_maximum_length' => '255'],
			'year'                      => ['type' => 'string'],
		];

		$type = str_replace(' zerofill', '', $type);

		if (isset($types[$type]))
			return $types[$type];

		return parent::datatype($type);
	}

	/**
	 * Start a SQL transaction
	 *
	 * @link http://dev.mysql.com/doc/refman/5.0/en/set-transaction.html
	 *
	 * @param ?string $mode Isolation level (READ UNCOMMITTED, READ COMMITTED, REPEATABLE READ, SERIALIZABLE)
	 * @throws Database_Exception
	 * @return bool
	 */
	public function begin(?string $mode = null): bool
	{
		// Make sure the database is connected
		$this->_connection or $this->connect();

		if ($mode)
		{
			// The level is written into the SQL as it is: only the four
			// levels that exist are accepted
			$mode = strtoupper($mode);

			if ( ! in_array($mode, ['READ UNCOMMITTED', 'READ COMMITTED', 'REPEATABLE READ', 'SERIALIZABLE'], true))
				throw new Database_Exception('Invalid transaction isolation level: :mode', [':mode' => $mode]);

			$this->_run('SET TRANSACTION ISOLATION LEVEL '.$mode);
		}

		return (bool) $this->_run('START TRANSACTION');
	}

	/**
	 * Commit a SQL transaction
	 *
	 * @return bool
	 */
	public function commit(): bool
	{
		// Make sure the database is connected
		$this->_connection or $this->connect();

		return (bool) $this->_run('COMMIT');
	}

	/**
	 * Rollback a SQL transaction
	 *
	 * @return bool
	 */
	public function rollback(): bool
	{
		// Make sure the database is connected
		$this->_connection or $this->connect();

		return (bool) $this->_run('ROLLBACK');
	}

	/**
	 * Returns the kind of database server: MySQL or MariaDB.
	 *
	 * @return  string  "mysql"
	 */
	public function driver(): string
	{
		return 'mysql';
	}

	/**
	 * List all of the tables in the database. Optionally, a LIKE string can
	 * be used to search for specific tables.
	 *
	 * @param ?string $like Table to search for
	 * @return array<int, string>
	 */
	public function list_tables(?string $like = null): array
	{
		if (is_string($like))
		{
			// Search for table names
			$result = $this->query(Database::SELECT, 'SHOW TABLES LIKE '.$this->quote($like), false);
		}
		else
		{
			// Find all table names
			$result = $this->query(Database::SELECT, 'SHOW TABLES', false);
		}

		$tables = [];
		foreach ($result as $row)
		{
			/** @var array<string, string> $row */
			$tables[] = (string) reset($row);
		}

		return $tables;
	}

	/**
	 * Lists all of the columns in a table. Optionally, a LIKE string can be
	 * used to search for specific fields.
	 *
	 * @param string      $table      Table to get columns from
	 * @param ?string $like       Column to search for
	 * @param bool        $add_prefix Whether to add the table prefix
	 * @return array<string, array<string, mixed>>
	 */
	public function list_columns(string $table, ?string $like = null, bool $add_prefix = true): array
	{
		// Quote the table name
		$table = ($add_prefix === true) ? $this->quote_table($table) : $table;

		if (is_string($like))
		{
			// Search for column names
			$result = $this->query(Database::SELECT, 'SHOW FULL COLUMNS FROM '.$table.' LIKE '.$this->quote($like), false);
		}
		else
		{
			// Find all column names
			$result = $this->query(Database::SELECT, 'SHOW FULL COLUMNS FROM '.$table, false);
		}

		$count = 0;
		$columns = [];
		foreach ($result as $row)
		{
			/** @var array<string, string|null> $row */
			list($type, $length) = $this->_parse_type( (string) $row['Type']);

			$column = $this->datatype( (string) $type);

			$column['column_name']      = $row['Field'];
			$column['column_default']   = $row['Default'];
			$column['data_type']        = $type;
			$column['is_nullable']      = ($row['Null'] == 'YES');
			$column['ordinal_position'] = ++$count;

			switch ($column['type'])
			{
				case 'float':
					if (isset($length))
					{
						list($column['numeric_precision'], $column['numeric_scale']) = explode(',', $length);
					}
				break;
				case 'int':
					if (isset($length))
					{
						// MySQL attribute
						$column['display'] = $length;
					}
				break;
				case 'string':
					switch ($column['data_type'])
					{
						case 'binary':
						case 'varbinary':
							$column['character_maximum_length'] = $length;
						break;
						case 'char':
						case 'varchar':
							$column['character_maximum_length'] = $length;
						case 'text':
						case 'tinytext':
						case 'mediumtext':
						case 'longtext':
						case 'json':
							$column['collation_name'] = $row['Collation'];
						break;
						case 'enum':
						case 'set':
							$column['collation_name'] = $row['Collation'];
							$column['options'] = explode('\',\'', substr( (string) $length, 1, -1));
						break;
					}
				break;
			}

			// MySQL attributes
			$column['comment']      = $row['Comment'];
			$column['extra']        = $row['Extra'];
			$column['key']          = $row['Key'];
			$column['privileges']   = $row['Privileges'];

			$columns[ (string) $row['Field']] = $column;
		}

		return $columns;
	}

	/**
	 * Escape a value for an SQL query.
	 *
	 * @param string $value Value to escape
	 * @throws Database_Exception
	 * @return string
	 */
	public function escape(mixed $value): string
	{
		// Make sure the database is connected
		$this->_connection or $this->connect();

		// Escapes for the character set of the connection, then adds the quotes
		return "'".$this->_mysqli()->real_escape_string( (string) $value)."'";
	}

} // End Database_MySQLi
