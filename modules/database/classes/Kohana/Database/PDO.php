<?php
/**
 * PDO database connection.
 *
 * @package    Kohana/Database
 * @category   Drivers
 * 
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Database_PDO extends Database {

	// PDO uses no quoting for identifiers
	protected string $_identifier = '';

	/**
	 * @var  array<string, PDOStatement>  Prepared statements of this connection, by their SQL, kept for reuse
	 */
	protected array $_statements = [];

	/**
	 * @param   array<string, mixed>  $config
	 */
	public function __construct(string $name, array $config)
	{
		parent::__construct($name, $config);

		if (isset($this->_config['identifier']))
		{
			// Allow the identifier to be overloaded per-connection
			$this->_identifier = is_string($this->_config['identifier']) ? $this->_config['identifier'] : '"';
		}
	}

	/**
	 * The open connection. Connects first when that was not done yet.
	 *
	 * @return  PDO
	 * @throws  Database_Exception  if there is no connection
	 */
	protected function _pdo(): PDO
	{
		$this->_connection or $this->connect();

		if ( ! $this->_connection instanceof PDO)
			throw new Database_Exception('There is no connection to the database :name', [':name' => $this->_instance]);

		return $this->_connection;
	}

	public function connect(): void
	{
		if ($this->_connection)
			return;

		// Extract the connection parameters, adding required variabels
		// Connection settings, with their defaults
		/** @var array{dsn: string, username: string|null, password: string|null, persistent: bool, options?: array<int, mixed>} $connection */
		$connection = (array) ($this->_config['connection'] ?? []) + [
			'dsn'        => '',
			'username'   => null,
			'password'   => null,
			'persistent' => false,
		];

		// The settings are kept, so the connection can be opened again after
		// disconnect(). They are hidden from dumps by Database::__debugInfo().
		$this->_register();

		// Force PDO to use exceptions for all errors
		$options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;

		// Let the database itself prepare statements, where the driver has
		// the choice of doing it in PHP (MySQL)
		$options[PDO::ATTR_EMULATE_PREPARES] = false;

		if ( ! empty($connection['persistent']))
		{
			// Make the connection persistent
			$options[PDO::ATTR_PERSISTENT] = true;
		}

		try
		{
			// Create a new PDO connection
			$this->_connection = new PDO($connection['dsn'], $connection['username'], $connection['password'], $options);
		}
		catch (PDOException $e)
		{
			throw new Database_Exception(':error',
				[':error' => $e->getMessage()],
				$e->getCode());
		}

		if ($this->_connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite')
		{
			// SQLite ignores foreign keys unless each connection asks for
			// them; the other databases always follow them
			$this->_connection->exec('PRAGMA foreign_keys = ON');
		}

		if ( ! empty($this->_config['charset']))
		{
			// Set the character set
			$this->set_charset(is_string($this->_config['charset']) ? $this->_config['charset'] : 'utf8');
		}
	}

	/**
	 * Create or redefine a SQL aggregate function.
	 *
	 * [!!] Works only with SQLite
	 *
	 * @link http://php.net/manual/function.pdo-sqlitecreateaggregate
	 *
	 * @param   string      $name       Name of the SQL function to be created or redefined
	 * @param   callable    $step       Called for each row of a result set
	 * @param   callable    $final      Called after all rows of a result set have been processed
	 * @param   int     $arguments  Number of arguments that the SQL function takes
	 *
	 * @return  bool
	 */
	public function create_aggregate(string $name, callable $step, callable $final, int $arguments = -1): bool
	{
		$this->_connection or $this->connect();

		return $this->_pdo()->sqliteCreateAggregate(
			$name, $step, $final, $arguments
		);
	}

	/**
	 * Create or redefine a SQL function.
	 *
	 * [!!] Works only with SQLite
	 *
	 * @link http://php.net/manual/function.pdo-sqlitecreatefunction
	 *
	 * @param   string      $name       Name of the SQL function to be created or redefined
	 * @param   callable    $callback   Callback which implements the SQL function
	 * @param   int     $arguments  Number of arguments that the SQL function takes
	 *
	 * @return  bool
	 */
	public function create_function(string $name, callable $callback, int $arguments = -1): bool
	{
		$this->_connection or $this->connect();

		return $this->_pdo()->sqliteCreateFunction(
			$name, $callback, $arguments
		);
	}

	public function disconnect(): bool
	{
		// The prepared statements belong to the connection
		$this->_statements = [];

		// Destroy the PDO object
		$this->_connection = null;

		return parent::disconnect();
	}

	public function set_charset(string $charset): void
	{
		// Make sure the database is connected
		$this->_connection OR $this->connect();

		// SQLite has no character sets to choose from, it always works in
		// Unicode, and does not know this command. (The "charset" of the
		// default connection in config/database.php would otherwise break it.)
		if ($this->_pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite')
			return;

		// This SQL-92 syntax is not supported by all drivers
		$this->_pdo()->exec('SET NAMES '.$this->quote($charset));
	}

	/**
	 * @param   int|null  $type
	 * @param   string  $sql
	 * @param   bool|string  $as_object
	 * @param   array<int|string, mixed>  $params
	 * @param   array<int, mixed>  $values
	 * @return  ($type is Database::SELECT ? Database_Result : ($type is Database::INSERT ? array{0: mixed, 1: int} : int))
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
			if ($values)
			{
				// A prepared statement: the SQL and its values travel separately
				// Prepare the statement once and reuse it when the same SQL comes again
				if ( ! isset($this->_statements[$sql]))
				{
					if (count($this->_statements) >= 100)
					{
						// Make room: forget the oldest statement
						array_shift($this->_statements);
					}

					$this->_statements[$sql] = $this->_pdo()->prepare($sql);
				}

				$result = $this->_statements[$sql];
				$result->execute($values);
			}
			else
			{
				$result = $this->_pdo()->query($sql);
			}

			if ( ! $result instanceof PDOStatement)
				throw new RuntimeException('The database gave no result');
		}
		catch (Throwable $e)
		{
			if (isset($benchmark))
			{
				// This benchmark is worthless
				Profiler::delete($benchmark);
			}

			// Convert the exception in a database exception
			throw new Database_Exception(':error [ :query ]',
				[
					':error' => $e->getMessage(),
					':query' => $sql
				],
				$e->getCode());
		}

		if (isset($benchmark))
		{
			Profiler::stop($benchmark);
		}

		// Set the last query
		$this->last_query = $sql;
		$this->last_params = $values;

		if ($type === Database::SELECT)
		{
			// Convert the result into an array, as PDOStatement::rowCount is not reliable
			if ($as_object === false)
			{
				$result->setFetchMode(PDO::FETCH_ASSOC);
			}
			elseif (is_string($as_object))
			{
				$result->setFetchMode(PDO::FETCH_CLASS, $as_object, $params);
			}
			else
			{
				$result->setFetchMode(PDO::FETCH_CLASS, 'stdClass');
			}

			$result = array_values($result->fetchAll());

			if (isset($benchmark))
			{
				// How many rows the query returned
				Profiler::note($benchmark, count($result).' rows');
			}

			// Return an iterator of results
			return new Database_Result_Cached($result, $sql, $as_object, $params);
		}

		if (isset($benchmark))
		{
			// How many rows the query changed
			Profiler::note($benchmark, $result->rowCount().' rows');
		}

		if ($type === Database::INSERT)
		{
			// Return a list of insert id and rows created
			return [
				$this->_insert_id(),
				$result->rowCount(),
			];
		}
		else
		{
			// Return the number of rows affected
			return $result->rowCount();
		}
	}

/**
	 * Returns the id the database gave to the row that was just inserted.
	 *
	 * PostgreSQL has no such id when the table does not number its rows:
	 * it raises an error instead, and an error inside a transaction would
	 * make the rest of that transaction fail. A savepoint keeps the
	 * transaction usable, and the id is then "0", as on MySQL.
	 *
	 * [!!] On PostgreSQL the id is the last number any sequence gave in
	 * this connection. After an insert into a table without numbering it
	 * can be the id of an earlier insert.
	 *
	 * @return  string|false
	 */
	protected function _insert_id(): string|false
	{
		if ($this->_pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql')
			return $this->_pdo()->lastInsertId();

		$transaction = $this->_pdo()->inTransaction();

		try
		{
			$transaction and $this->_pdo()->exec('SAVEPOINT kohana_insert_id');

			$id = $this->_pdo()->lastInsertId();

			$transaction and $this->_pdo()->exec('RELEASE SAVEPOINT kohana_insert_id');

			return $id;
		}
		catch (PDOException $e)
		{
			// No row was numbered: undo only the failed question
			$transaction and $this->_pdo()->exec('ROLLBACK TO SAVEPOINT kohana_insert_id');

			return '0';
		}
	}

		public function begin(?string $mode = null): bool
	{
		// Make sure the database is connected
		$this->_connection or $this->connect();

		return $this->_pdo()->beginTransaction();
	}

	public function commit(): bool
	{
		// Make sure the database is connected
		$this->_connection or $this->connect();

		// MySQL ends a transaction by itself when a table is created, altered
		// or dropped. PDO would throw here, as there is nothing left to commit.
		if ( ! $this->_pdo()->inTransaction())
			return false;

		return $this->_pdo()->commit();
	}

	public function rollback(): bool
	{
		// Make sure the database is connected
		$this->_connection or $this->connect();

		// MySQL ends a transaction by itself when a table is created, altered
		// or dropped. PDO would throw here, as there is nothing left to roll back.
		if ( ! $this->_pdo()->inTransaction())
			return false;

		return $this->_pdo()->rollBack();
	}

	/**
	 * List all of the tables in the database. Optionally, a LIKE string can
	 * be used to search for specific tables.
	 *
	 *     $tables = $db->list_tables();
	 *     $tables = $db->list_tables('blog_%');
	 *
	 * Works on SQLite, MySQL/MariaDB and PostgreSQL.
	 *
	 * @param   string|null  $like  table to search for
	 * @return  array<int, string>  list of table names
	 * @throws  Kohana_Exception  on any other database
	 */
	public function list_tables(?string $like = null): array
	{
		if ($this->_driver() === 'sqlite')
		{
			// SQLite keeps its own tables under names that start with "sqlite_"
			$sql = "SELECT name AS table_name FROM sqlite_master WHERE type = 'table' AND substr(name, 1, 7) <> 'sqlite_'";
			$column = 'name';
		}
		else
		{
			$sql = 'SELECT table_name AS table_name FROM information_schema.tables'
				." WHERE table_type = 'BASE TABLE' AND table_schema = ".$this->_schema();
			$column = 'table_name';
		}

		$values = [];

		if (is_string($like))
		{
			$sql .= ' AND '.$column.' LIKE ?';
			$values[] = $like;
		}

		$result = $this->query(Database::SELECT, $sql.' ORDER BY '.$column, false, null, $values);

		/** @var array<int, string> $tables */
		$tables = $result->as_array(null, 'table_name');

		return $tables;
	}

	/**
	 * Lists all of the columns in a table. Optionally, a LIKE string can be
	 * used to search for specific fields.
	 *
	 *     $columns = $db->list_columns('users');
	 *     $columns = $db->list_columns('users', '%name%');
	 *
	 * Every column is an array with the same keys on every database:
	 *
	 * Key                      | Value
	 * -------------------------|------
	 * column_name              | name of the column
	 * data_type                | type as the database names it, in lower case
	 * type                     | "int", "float", "bool" or "string"
	 * is_nullable              | can the column hold null?
	 * column_default           | default value as the database writes it, or null
	 * ordinal_position         | position in the table, starting at 1
	 * character_maximum_length | longest text, or null when not known
	 * numeric_precision        | number of digits, or null when not known
	 * numeric_scale            | digits after the decimal point, or null when not known
	 * key                      | "PRI" for a column of the primary key, otherwise ""
	 *
	 * Works on SQLite, MySQL/MariaDB and PostgreSQL.
	 *
	 * @param   string       $table       table to get columns from
	 * @param   string|null  $like        column to search for
	 * @param   bool         $add_prefix  whether to add the table prefix
	 * @return  array<string, array<string, mixed>>  columns by their name
	 * @throws  Kohana_Exception    on any other database
	 * @throws  Database_Exception  when the table does not exist
	 */
	public function list_columns(string $table, ?string $like = null, bool $add_prefix = true): array
	{
		if ($add_prefix)
		{
			$table = $this->table_prefix().$table;
		}

		$rows = ($this->_driver() === 'sqlite')
			? $this->_sqlite_columns($table, $like)
			: $this->_schema_columns($table, $like);

		// A table always has columns: none at all means there is no such
		// table. The MySQLi driver reports that too.
		if ( ! $rows AND $like === null)
			throw new Database_Exception('Table :table does not exist', [':table' => $table]);

		$columns = [];

		foreach ($rows as $row)
		{
			/** @var array{column_name: string, data_type: string} $row */
			$row['type'] = $this->_simple_type($row['data_type']);

			$columns[$row['column_name']] = $row;
		}

		return $columns;
	}

	/**
	 * Returns the kind of database server, as PDO names it.
	 *
	 * @return  string  "mysql", "pgsql", "sqlite" or the name of another PDO driver
	 */
	public function driver(): string
	{
		// Make sure the database is connected
		$this->_connection or $this->connect();

		$driver = $this->_pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

		return is_string($driver) ? $driver : '';
	}

	/**
	 * Returns the kind of database of this connection, when its tables and
	 * columns can be listed.
	 *
	 * @return  string  "sqlite", "mysql" or "pgsql"
	 * @throws  Kohana_Exception  on any other database
	 */
	protected function _driver(): string
	{
		$driver = $this->driver();

		if ( ! in_array($driver, ['sqlite', 'mysql', 'pgsql'], true))
			throw new Kohana_Exception('Listing tables and columns is not supported by :class for ":driver" databases',
				[':class' => __CLASS__, ':driver' => $driver]);

		return $driver;
	}

	/**
	 * Returns the SQL that gives the schema the tables of this connection
	 * are in, for queries on "information_schema".
	 *
	 * @return  string
	 */
	protected function _schema(): string
	{
		// MySQL calls it the database; PostgreSQL has schemas inside a database
		return ($this->_driver() === 'mysql') ? 'DATABASE()' : 'current_schema()';
	}

	/**
	 * Reads the columns of a SQLite table.
	 *
	 * @param   string       $table  full table name
	 * @param   string|null  $like   column to search for
	 * @return  array<int, array<string, mixed>>  list of columns, see [Database_PDO::list_columns]
	 */
	protected function _sqlite_columns(string $table, ?string $like): array
	{
		$sql = 'SELECT cid, name, type, "notnull", dflt_value, pk FROM pragma_table_info(?)';
		$values = [$table];

		if (is_string($like))
		{
			$sql .= ' WHERE name LIKE ?';
			$values[] = $like;
		}

		$columns = [];

		foreach ($this->query(Database::SELECT, $sql.' ORDER BY cid', false, null, $values) as $row)
		{
			/** @var array{cid: int|string, name: string, type: string|null, notnull: int|string, dflt_value: string|null, pk: int|string} $row */
			// A type is written as "name", "name(length)" or "name(precision, scale)"
			preg_match('/^([^(]*)(?:\((\d+)(?:\s*,\s*(\d+))?\))?/', strtolower(trim( (string) $row['type'])), $type);

			$data_type = trim($type[1] ?? '');
			$number    = ($this->_simple_type($data_type) !== 'string');
			$length    = isset($type[2]) ? (int) $type[2] : null;

			$columns[] = [
				'column_name'              => $row['name'],
				'data_type'                => $data_type,
				// A primary key is never null, also when the table does not say so
				'is_nullable'              => ! $row['notnull'] AND ! $row['pk'],
				'column_default'           => $row['dflt_value'],
				'ordinal_position'         => (int) $row['cid'] + 1,
				'character_maximum_length' => $number ? null : $length,
				'numeric_precision'        => $number ? $length : null,
				'numeric_scale'            => ($number AND isset($type[3])) ? (int) $type[3] : null,
				'key'                      => $row['pk'] ? 'PRI' : '',
			];
		}

		return $columns;
	}

	/**
	 * Reads the columns of a MySQL/MariaDB or PostgreSQL table from
	 * "information_schema", which is the same on both.
	 *
	 * @param   string       $table  full table name
	 * @param   string|null  $like   column to search for
	 * @return  array<int, array<string, mixed>>  list of columns, see [Database_PDO::list_columns]
	 */
	protected function _schema_columns(string $table, ?string $like): array
	{
		$schema = $this->_schema();

		// The columns of the primary key
		$sql = 'SELECT k.column_name AS column_name'
			.' FROM information_schema.table_constraints t'
			.' JOIN information_schema.key_column_usage k ON k.constraint_name = t.constraint_name'
			.' AND k.table_schema = t.table_schema AND k.table_name = t.table_name'
			." WHERE t.constraint_type = 'PRIMARY KEY' AND t.table_schema = ".$schema.' AND t.table_name = ?';

		$primary = $this->query(Database::SELECT, $sql, false, null, [$table])->as_array(null, 'column_name');

		// Every name gets an alias: MySQL would return them in upper case
		$sql = 'SELECT column_name AS column_name, data_type AS data_type, is_nullable AS is_nullable,'
			.' column_default AS column_default, ordinal_position AS ordinal_position,'
			.' character_maximum_length AS character_maximum_length,'
			.' numeric_precision AS numeric_precision, numeric_scale AS numeric_scale'
			.' FROM information_schema.columns WHERE table_schema = '.$schema.' AND table_name = ?';
		$values = [$table];

		if (is_string($like))
		{
			$sql .= ' AND column_name LIKE ?';
			$values[] = $like;
		}

		$columns = [];

		foreach ($this->query(Database::SELECT, $sql.' ORDER BY ordinal_position', false, null, $values) as $row)
		{
			/** @var array<string, string|null> $row */
			// Numbers arrive as text or as null
			$int = fn (?string $value): ?int => ($value === null) ? null : (int) $value;

			$columns[] = [
				'column_name'              => $row['column_name'],
				'data_type'                => strtolower( (string) $row['data_type']),
				'is_nullable'              => ($row['is_nullable'] === 'YES'),
				'column_default'           => $row['column_default'],
				'ordinal_position'         => (int) $row['ordinal_position'],
				'character_maximum_length' => $int($row['character_maximum_length']),
				'numeric_precision'        => $int($row['numeric_precision']),
				'numeric_scale'            => $int($row['numeric_scale']),
				'key'                      => in_array($row['column_name'], $primary, true) ? 'PRI' : '',
			];
		}

		return $columns;
	}

	/**
	 * Returns the PHP type that fits a column type of the database.
	 *
	 * @param   string  $data_type  column type in lower case, without length
	 * @return  string  "int", "float", "bool" or "string"
	 */
	protected function _simple_type(string $data_type): string
	{
		// int, integer, bigint, int4, "unsigned big int", serial, bigserial...
		if (preg_match('/^(unsigned )?(tiny|small|medium|big|big )?int(eger)?[248]?( unsigned)?$|serial$/', $data_type))
			return 'int';

		// float, float8, double, double precision, real, decimal, dec, numeric, money
		if (preg_match('/^(float|double|real|dec|numeric|money)/', $data_type))
			return 'float';

		if ($data_type === 'bool' OR $data_type === 'boolean')
			return 'bool';

		// Text, dates, binary data and everything else
		return 'string';
	}

	public function escape(mixed $value): string
	{
		// Make sure the database is connected
		$this->_connection or $this->connect();

		return $this->_pdo()->quote( (string) $value);
	}

} // End Database_PDO
