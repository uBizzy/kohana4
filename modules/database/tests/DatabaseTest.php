<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the database drivers, the query builder and the result classes
 * against real databases.
 *
 * - SQLite (through the PDO driver) always runs: it needs no server.
 * - MySQL/MariaDB (through the MySQLi driver) runs when the environment
 *   variable KOHANA_TEST_MYSQL_HOST is set. The other settings are
 *   KOHANA_TEST_MYSQL_PORT (3306), KOHANA_TEST_MYSQL_USER (root),
 *   KOHANA_TEST_MYSQL_PASSWORD (empty) and KOHANA_TEST_MYSQL_DATABASE (kohana_test).
 *
 * @package    Kohana/Database
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.database')]
class Kohana_DatabaseTest extends Unittest_TestCase
{
	/**
	 * @var string  Text full of characters that are special in SQL
	 */
	const NASTY = "O'Reilly \"quoted\" \\ back`tick; DROP TABLE kohana_test_people; -- % _ end";

	/**
	 * Provides the name of each database that can be tested here.
	 *
	 * @return array
	 */
	public static function provider_databases(): array
	{
		// Every database is tested twice: with prepared statements (the
		// default) and with them switched off in the connection settings
		$databases = [
			'sqlite'             => ['sqlite'],
			'sqlite, unprepared' => ['sqlite_unprepared'],
		];

		if (getenv('KOHANA_TEST_MYSQL_HOST'))
		{
			$databases['mysqli']             = ['mysqli'];
			$databases['mysqli, unprepared'] = ['mysqli_unprepared'];

			if (extension_loaded('pdo_mysql'))
			{
				// The same server through the PDO driver
				$databases['pdo_mysql'] = ['pdo_mysql'];
			}
		}

		if (getenv('KOHANA_TEST_PGSQL_HOST') AND extension_loaded('pdo_pgsql'))
		{
			$databases['pdo_pgsql'] = ['pdo_pgsql'];
		}

		return $databases;
	}

	/**
	 * Connects to one of the test databases and creates an empty test table.
	 *
	 * @param   string  $name  "sqlite", "mysqli", "pdo_mysql" or "pdo_pgsql", with "_unprepared" added to switch prepared statements off
	 * @return  Database
	 */
	protected function database(string $name): Database
	{
		$prepared = ! str_ends_with($name, '_unprepared');

		if (str_starts_with($name, 'sqlite'))
		{
			if ( ! extension_loaded('pdo_sqlite'))
			{
				$this->markTestSkipped('The pdo_sqlite extension is not available.');
			}

			$config = [
				'type'         => 'PDO',
				'connection'   => ['dsn' => 'sqlite::memory:'],
				'identifier'   => '"',
				'table_prefix' => '',
			];
		}
		elseif (str_starts_with($name, 'pdo_pgsql'))
		{
			$config = [
				'type'       => 'PDO',
				'connection' => [
					'dsn' => 'pgsql:host='.getenv('KOHANA_TEST_PGSQL_HOST')
						.';port='.(getenv('KOHANA_TEST_PGSQL_PORT') ?: 5432)
						.';dbname='.(getenv('KOHANA_TEST_PGSQL_DATABASE') ?: 'kohana_test'),
					'username' => getenv('KOHANA_TEST_PGSQL_USER') ?: 'postgres',
					'password' => (string) getenv('KOHANA_TEST_PGSQL_PASSWORD'),
				],
				'identifier'   => '"',
				'table_prefix' => '',
			];
		}
		elseif (str_starts_with($name, 'pdo_mysql'))
		{
			$config = [
				'type'       => 'PDO',
				'connection' => [
					'dsn' => 'mysql:host='.getenv('KOHANA_TEST_MYSQL_HOST')
						.';port='.(getenv('KOHANA_TEST_MYSQL_PORT') ?: 3306)
						.';dbname='.(getenv('KOHANA_TEST_MYSQL_DATABASE') ?: 'kohana_test')
						.';charset=utf8mb4',
					'username' => getenv('KOHANA_TEST_MYSQL_USER') ?: 'root',
					'password' => (string) getenv('KOHANA_TEST_MYSQL_PASSWORD'),
				],
				'identifier'   => '`',
				'table_prefix' => '',
			];
		}
		else
		{
			$config = [
				'type'       => 'MySQLi',
				'connection' => [
					'hostname' => getenv('KOHANA_TEST_MYSQL_HOST'),
					'port'     => getenv('KOHANA_TEST_MYSQL_PORT') ?: 3306,
					'username' => getenv('KOHANA_TEST_MYSQL_USER') ?: 'root',
					'password' => (string) getenv('KOHANA_TEST_MYSQL_PASSWORD'),
					'database' => getenv('KOHANA_TEST_MYSQL_DATABASE') ?: 'kohana_test',
				],
				'charset'      => 'utf8mb4',
				'table_prefix' => '',
			];
		}

		// A fresh connection for every test
		if (isset(Database::$instances['test_'.$name]))
		{
			Database::$instances['test_'.$name]->disconnect();
		}

		$db = Database::instance('test_'.$name, $config + ['prepared' => $prepared]);

		$db->query(null, 'DROP TABLE IF EXISTS kohana_test_people');
		$db->query(null, 'CREATE TABLE kohana_test_people (id INTEGER PRIMARY KEY, name VARCHAR(255), age INTEGER)');

		return $db;
	}

	/**
	 * Inserts three known rows.
	 *
	 * @param   Database  $db
	 * @return  void
	 */
	protected function fill(Database $db): void
	{
		DB::insert('kohana_test_people', ['id', 'name', 'age'])
			->values([1, 'Ana', 30], [2, 'Bruno', 41])
			->values([3, 'Carla', 25])
			->execute($db);
	}

	/**
	 * SQLite follows foreign keys only when the connection asks for them;
	 * the driver does, also after connecting again.
	 */
	public function test_sqlite_follows_foreign_keys(): void
	{
		$db = $this->database('sqlite');

		$db->query(null, 'CREATE TABLE kohana_fk_parents (id INTEGER PRIMARY KEY)');
		$db->query(null, 'CREATE TABLE kohana_fk_children (id INTEGER PRIMARY KEY, parent_id INTEGER NOT NULL, '
			.'FOREIGN KEY (parent_id) REFERENCES kohana_fk_parents (id) ON DELETE CASCADE)');

		DB::insert('kohana_fk_parents', ['id'])->values([1])->execute($db);
		DB::insert('kohana_fk_children', ['id', 'parent_id'])->values([1, 1])->execute($db);
		DB::delete('kohana_fk_parents')->where('id', '=', 1)->execute($db);

		$this->assertSame(0, $db->count_records('kohana_fk_children'));

		// A child without parent is refused
		$this->expectException(Database_Exception::class);
		DB::insert('kohana_fk_children', ['id', 'parent_id'])->values([2, 99])->execute($db);
	}

	/**
	 * A connection tells which kind of server it talks to.
	 */
	#[DataProvider('provider_databases')]
	public function test_driver_names_the_kind_of_database(string $name): void
	{
		$expected = match (true) {
			str_contains($name, 'sqlite') => 'sqlite',
			str_contains($name, 'pgsql')  => 'pgsql',
			default                       => 'mysql',
		};

		$this->assertSame($expected, $this->database($name)->driver());
	}

	#[DataProvider('provider_databases')]
	public function test_insert_select_update_delete(string $name): void
	{
		$db = $this->database($name);
		$this->fill($db);

		$this->assertSame(3, $db->count_records('kohana_test_people'));

		$rows = DB::select('id', 'name')->from('kohana_test_people')
			->where('age', '>', 26)->order_by('name', 'desc')->execute($db)->as_array();

		$this->assertEquals([['id' => 2, 'name' => 'Bruno'], ['id' => 1, 'name' => 'Ana']], $rows);

		$affected = DB::update('kohana_test_people')->set(['age' => 31])->where('id', '=', 1)->execute($db);
		$this->assertSame(1, $affected);
		$this->assertEquals(31, DB::select('age')->from('kohana_test_people')->where('id', '=', 1)->execute($db)->get('age'));

		$this->assertSame(2, DB::delete('kohana_test_people')->where('id', 'IN', [2, 3])->execute($db));
		$this->assertSame(1, $db->count_records('kohana_test_people'));
	}

	/**
	 * Values must come back exactly as they went in, whatever characters
	 * they contain: nothing of a value may ever be read as SQL.
	 */
	#[DataProvider('provider_databases')]
	public function test_values_cannot_inject_sql(string $name): void
	{
		$db = $this->database($name);

		DB::insert('kohana_test_people', ['id', 'name', 'age'])->values([1, self::NASTY, 1])->execute($db);

		// Through the query builder
		$found = DB::select('name')->from('kohana_test_people')->where('name', '=', self::NASTY)->execute($db);
		$this->assertSame(1, count($found));
		$this->assertSame(self::NASTY, $found->get('name'));

		// Through a bound parameter of a hand-written query
		$found = DB::query(Database::SELECT, 'SELECT name FROM kohana_test_people WHERE name = :name')
			->param(':name', self::NASTY)->execute($db);
		$this->assertSame(self::NASTY, $found->get('name'));

		if (str_starts_with($name, 'mysqli'))
		{
			// MySQL also stores a NUL byte unchanged. (SQLite ends a text
			// literal at a NUL byte, so that is not tested there.)
			$binary = "before\0after";
			DB::update('kohana_test_people')->set(['name' => $binary])->execute($db);
			$this->assertSame($binary, DB::select('name')->from('kohana_test_people')->execute($db)->get('name'));
			DB::update('kohana_test_people')->set(['name' => self::NASTY])->execute($db);
		}

		// The classic attack string selects nothing and deletes nothing
		$attack = DB::select()->from('kohana_test_people')->where('name', '=', "x' OR '1'='1")->execute($db);
		$this->assertSame(0, count($attack));
		$this->assertSame(1, $db->count_records('kohana_test_people'));
	}

	/**
	 * A result can be read by position, looped over more than once, and
	 * turned into arrays keyed by a column.
	 */
	#[DataProvider('provider_databases')]
	public function test_result_access(string $name): void
	{
		$db = $this->database($name);
		$this->fill($db);

		$result = DB::select()->from('kohana_test_people')->order_by('id')->execute($db);

		$this->assertSame(3, count($result));

		// By position, in any order
		$this->assertSame('Carla', $result[2]['name']);
		$this->assertSame('Ana', $result[0]['name']);
		$this->assertNull($result[3]);
		$this->assertTrue(isset($result[1]));
		$this->assertFalse(isset($result[5]));

		// Looping twice gives the same rows twice
		$names = [];
		foreach ($result as $row) $names[] = $row['name'];
		foreach ($result as $row) $names[] = $row['name'];
		$this->assertSame(['Ana', 'Bruno', 'Carla', 'Ana', 'Bruno', 'Carla'], $names);

		$this->assertEquals([1 => 'Ana', 2 => 'Bruno', 3 => 'Carla'], $result->as_array('id', 'name'));
		$this->assertSame(['Ana', 'Bruno', 'Carla'], $result->as_array(null, 'name'));

		// A cached copy holds the same rows without the database
		$this->assertEquals($result->as_array(), $result->cached()->as_array());
	}

	#[DataProvider('provider_databases')]
	public function test_results_as_objects(string $name): void
	{
		$db = $this->database($name);
		$this->fill($db);

		$row = DB::select()->from('kohana_test_people')->where('id', '=', 2)->as_object()->execute($db)->current();
		$this->assertInstanceOf('stdClass', $row);
		$this->assertSame('Bruno', $row->name);

		$row = DB::select()->from('kohana_test_people')->where('id', '=', 2)
			->as_object('Kohana_DatabaseTest_Person')->execute($db)->current();
		$this->assertInstanceOf('Kohana_DatabaseTest_Person', $row);
		$this->assertSame('Bruno', $row->name);
	}

	#[DataProvider('provider_databases')]
	public function test_transactions(string $name): void
	{
		$db = $this->database($name);
		$this->fill($db);

		$db->begin();
		DB::delete('kohana_test_people')->execute($db);
		$db->rollback();
		$this->assertSame(3, $db->count_records('kohana_test_people'));

		$db->begin();
		DB::delete('kohana_test_people')->where('id', '=', 1)->execute($db);
		$db->commit();
		$this->assertSame(2, $db->count_records('kohana_test_people'));
	}

	/**
	 * transaction() keeps the work of a function that finishes, undoes the
	 * work of one that throws, and passes the exception on.
	 */
	#[DataProvider('provider_databases')]
	public function test_transaction_helper(string $name): void
	{
		$db = $this->database($name);
		$this->fill($db);

		$result = $db->transaction(function (Database $db)
		{
			DB::delete('kohana_test_people')->where('id', '=', 1)->execute($db);

			return 'done';
		});

		$this->assertSame('done', $result);
		$this->assertSame(2, $db->count_records('kohana_test_people'));

		try
		{
			$db->transaction(function (Database $db)
			{
				DB::delete('kohana_test_people')->execute($db);

				// A call inside another joins the running transaction
				$db->transaction(fn (Database $db) => DB::insert('kohana_test_people', ['id', 'name', 'age'])->values([9, 'Zed', 1])->execute($db));

				throw new RuntimeException('something went wrong');
			});

			$this->fail('The exception was swallowed');
		}
		catch (RuntimeException $e)
		{
			$this->assertSame('something went wrong', $e->getMessage());
		}

		// Nothing of the failed function was kept
		$this->assertSame(2, $db->count_records('kohana_test_people'));

		// And a new transaction can start afterwards
		$db->transaction(fn (Database $db) => DB::delete('kohana_test_people')->execute($db));
		$this->assertSame(0, $db->count_records('kohana_test_people'));
	}

	/**
	 * A failing statement must always surface as a Database_Exception,
	 * whatever exception the PHP extension itself throws.
	 */
	#[DataProvider('provider_databases')]
	public function test_invalid_sql_throws_database_exception(string $name): void
	{
		$db = $this->database($name);

		$this->expectException(Database_Exception::class);

		$db->query(Database::SELECT, 'SELECT * FROM kohana_test_no_such_table');
	}

	#[DataProvider('provider_databases')]
	public function test_joins_and_aliases(string $name): void
	{
		$db = $this->database($name);
		$this->fill($db);

		$rows = DB::select(['a.name', 'older'], ['b.name', 'younger'])
			->from(['kohana_test_people', 'a'])
			->join(['kohana_test_people', 'b'], 'inner')->on('a.age', '>', 'b.age')
			->where('a.id', '=', 1)
			->execute($db)->as_array();

		$this->assertEquals([['older' => 'Ana', 'younger' => 'Carla']], $rows);
	}

	/**
	 * Parts of a query that are written into the SQL as they are must be
	 * refused when they are not what they claim to be.
	 */
	public function test_join_type_must_be_words(): void
	{
		$this->expectException(Database_Exception::class);

		DB::select()->from('a')->join('b', 'LEFT JOIN c ON 1=1 --');
	}

	/**
	 * Provides operators and whether the query builder accepts them.
	 *
	 * @return array of $operator, $allowed
	 */
	public static function provider_operators(): array
	{
		return [
			['=', true], ['!=', true], ['>=', true], ['like', true], ['NOT  LIKE', true],
			['in', true], ['between', true], ['is not', true], ['regexp', true],
			["= '' OR 1=1 --", false],
			['; DROP TABLE kohana_test_people; --', false],
			['UNION SELECT', false],
			['=/**/', false],
		];
	}

	/**
	 * An operator is written into the SQL as it is, so only known operators
	 * are accepted, in where(), having() and join conditions alike.
	 */
#[DataProvider('provider_operators')]
	public function test_operators_are_validated(string $operator, bool $allowed): void
	{
		$db = $this->database('sqlite');

		$queries = [
			DB::select()->from('kohana_test_people')->where('age', $operator, [1, 2]),
			DB::select()->from('kohana_test_people')->group_by('age')->having('age', $operator, [1, 2]),
			DB::select()->from('kohana_test_people')->join('kohana_test_people')->on('id', $operator, 'id'),
		];

		foreach ($queries as $query)
		{
			try
			{
				$sql = $query->compile($db);
				$this->assertTrue($allowed, 'The operator "'.$operator.'" was accepted: '.$sql);
			}
			catch (Database_Exception $e)
			{
				$this->assertFalse($allowed, 'The operator "'.$operator.'" was refused');
			}
		}
	}

	/**
	 * The list of operators can be extended by the application.
	 */
	public function test_operators_can_be_added(): void
	{
		$db = $this->database('sqlite');

		$this->setEnvironment(['Database_Query_Builder::$operators' => array_merge(Database_Query_Builder::$operators, ['MEMBER OF'])]);

		$sql = DB::select()->from('kohana_test_people')->where('age', 'member of', 1)->compile($db);

		$this->assertStringContainsString('"age" MEMBER OF 1', $sql);
	}

	/**
	 * The sort direction is limited to ASC and DESC.
	 */
	#[DataProvider('provider_databases')]
	public function test_order_direction_is_validated(string $name): void
	{
		$db = $this->database($name);

		$this->expectException(Database_Exception::class);

		DB::select()->from('kohana_test_people')->order_by('id', 'ASC; DROP TABLE kohana_test_people')->compile($db);
	}

	/**
	 * Identifiers are quoted, so a quote character inside a name cannot end it.
	 */
	#[DataProvider('provider_databases')]
	public function test_identifiers_are_quoted(string $name): void
	{
		$db = $this->database($name);
		$q  = str_contains($name, 'mysql') ? '`' : '"';

		$this->assertSame($q.'col'.$q.$q.'umn'.$q, $db->quote_column('col'.$q.'umn'));
		$this->assertSame($q.'t'.$q.'.'.$q.'c'.$q, $db->quote_column('t.c'));
		$this->assertSame('NULL', $db->quote(null));
		$this->assertSame(5, $db->quote(5));
		$this->assertSame('(1, 2)', $db->quote([1, 2]));
	}

	/**
	 * Database sessions: written, read back, expired and corrupt sessions.
	 */
	#[DataProvider('provider_databases')]
	public function test_database_sessions(string $name): void
	{
		$db = $this->database($name);

		$db->query(null, 'DROP TABLE IF EXISTS sessions');
		$db->query(null, 'CREATE TABLE sessions (session_id VARCHAR(24) PRIMARY KEY, last_active INTEGER NOT NULL, contents TEXT NOT NULL)');

		$this->setEnvironment(['Cookie::$salt' => 'database-test-salt']);

		$config = ['group' => 'test_'.$name, 'lifetime' => 100];

		$session = new Kohana_DatabaseTest_Session($config);
		$id = $session->id();
		$this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{24}$/', $id);

		$session->set('user', 'ana');
		$this->assertTrue($session->store());

		$again = new Kohana_DatabaseTest_Session($config, $id);
		$this->assertSame('ana', $again->get('user'));

		// Expired: not loaded, a new session starts
		$db->query(Database::UPDATE, 'UPDATE sessions SET last_active = '.(time() - 1000));
		$expired = new Kohana_DatabaseTest_Session($config, $id);
		$this->assertNull($expired->get('user'));
		$this->assertNotSame($id, $expired->id());

		// Corrupt: discarded, its row deleted, a new session starts
		$db->query(Database::UPDATE, 'UPDATE sessions SET last_active = '.time().", contents = 'garbage!!'");
		$corrupt = new Kohana_DatabaseTest_Session($config, $id);
		$this->assertSame([], $corrupt->as_array());
		$this->assertNotSame($id, $corrupt->id());
		$this->assertSame(0, $db->count_records('sessions'));
	}

	/**
	 * With prepared statements, text values are not in the SQL at all: the
	 * SQL holds a "?" for each, and the values are sent beside it.
	 */
	public function test_prepare_query_separates_sql_and_values(): void
	{
		$db = $this->database('sqlite');

		$query = DB::select('id')->from('kohana_test_people')
			->where('name', '=', "O'Reilly")
			->where('age', '>', 18)
			->where('name', 'IN', ['Ana', 'Bruno'])
			->where('age', '=', null)
			->where('name', '!=', DB::expr('UPPER(:text)', [':text' => 'x']))
			->limit(5);

		list($sql, $values) = $db->prepare_query($query);

		$this->assertSame(
			'SELECT "id" FROM "kohana_test_people" WHERE "name" = ? AND "age" > 18 AND "name" IN (?, ?) AND "age" IS NULL AND "name" != UPPER(?) LIMIT 5',
			$sql
		);
		$this->assertSame(["O'Reilly", 'Ana', 'Bruno', 'x'], $values);

		// compile() still gives the complete SQL, for logs and debugging
		$this->assertStringContainsString("\"name\" = 'O''Reilly'", $query->compile($db));
		$this->assertStringNotContainsString('?', $query->compile($db));
	}

	/**
	 * Values are sent in the order of their placeholders, also when a named
	 * parameter is used more than once or written in another order.
	 */
	public function test_prepare_query_orders_named_parameters(): void
	{
		$db = $this->database('sqlite');

		$query = DB::query(Database::SELECT, 'SELECT * FROM kohana_test_people WHERE name = :b OR name = :a OR name = :b')
			->parameters([':a' => 'first', ':b' => 'second', ':unused' => 'never']);

		list($sql, $values) = $db->prepare_query($query);

		$this->assertSame('SELECT * FROM kohana_test_people WHERE name = ? OR name = ? OR name = ?', $sql);
		$this->assertSame(['second', 'first', 'second'], $values);
	}

	/**
	 * A sub-query inside a query is compiled into the same statement.
	 */
	#[DataProvider('provider_databases')]
	public function test_subqueries_and_last_query(string $name): void
	{
		$db = $this->database($name);
		$this->fill($db);

		$older = DB::select('id')->from('kohana_test_people')->where('name', '=', 'Bruno');

		$names = DB::select('name')->from('kohana_test_people')
			->where('id', 'IN', $older)
			->or_where('name', '=', 'Carla')
			->order_by('name')
			->execute($db)->as_array(null, 'name');

		$this->assertSame(['Bruno', 'Carla'], $names);

		if ($db->prepared())
		{
			$this->assertStringContainsString('= ?', $db->last_query);
			$this->assertSame(['Bruno', 'Carla'], $db->last_params);
		}
		else
		{
			$this->assertStringContainsString("'Bruno'", $db->last_query);
			$this->assertSame([], $db->last_params);
		}
	}

	/**
	 * On MySQL, rows must be the same whether a query was prepared or not:
	 * every value is a string (or null), whatever the column type.
	 */
	public function test_mysqli_rows_are_the_same_with_and_without_prepared_statements(): void
	{
		if ( ! getenv('KOHANA_TEST_MYSQL_HOST'))
		{
			$this->markTestSkipped('No MySQL server configured (KOHANA_TEST_MYSQL_HOST).');
		}

		$rows = [];

		foreach (['mysqli', 'mysqli_unprepared'] as $name)
		{
			$db = $this->database($name);

			$db->query(null, 'DROP TABLE IF EXISTS kohana_test_types');
			$db->query(null, 'CREATE TABLE kohana_test_types (label VARCHAR(20), i INT, b BIGINT, f FLOAT, d DOUBLE, m DECIMAL(10,2), t TINYINT(1), n INT NULL)');
			$db->query(null, "INSERT INTO kohana_test_types VALUES ('row', 42, 9007199254740993, 0.1, 1234.5678, 19.90, 1, NULL)");

			// The text value in the condition makes this a prepared statement when they are on
			$query = DB::select()->from('kohana_test_types')->where('label', '=', 'row');

			$rows[$name]        = $query->execute($db)->current();
			$rows[$name.' obj'] = (array) $query->as_object()->execute($db)->current();
		}

		$expected = ['label' => 'row', 'i' => '42', 'b' => '9007199254740993', 'f' => '0.1', 'd' => '1234.5678', 'm' => '19.90', 't' => '1', 'n' => null];

		foreach ($rows as $name => $row)
		{
			$this->assertSame($expected, $row, $name);
		}
	}

	/**
	 * Only the four isolation levels that exist are accepted (MySQL only:
	 * the PDO driver ignores the argument).
	 */
	public function test_mysqli_isolation_level_is_validated(): void
	{
		if ( ! getenv('KOHANA_TEST_MYSQL_HOST'))
		{
			$this->markTestSkipped('No MySQL server configured (KOHANA_TEST_MYSQL_HOST).');
		}

		$db = $this->database('mysqli');

		$this->assertTrue($db->begin('repeatable read'));
		$db->rollback();

		$this->expectException(Database_Exception::class);

		$db->begin('READ COMMITTED; DROP TABLE kohana_test_people');
	}

	/**
	 * A query builder can be emptied and used again. reset() used to stop
	 * with a type error in every builder.
	 */
	public function test_query_builders_can_be_reset(): void
	{
		$db = $this->database('sqlite');
		$this->fill($db);

		$select = DB::select('name')->from('kohana_test_people')->where('id', '=', 1);
		$this->assertSame('Ana', $select->execute($db)->get('name'));

		$this->assertSame($select, $select->reset());
		$this->assertSame('Bruno', $select->select('name')->from('kohana_test_people')->where('id', '=', 2)->execute($db)->get('name'));

		$insert = DB::insert('kohana_test_people', ['id', 'name', 'age'])->values([4, 'Dora', 50]);
		$insert->execute($db);
		$this->assertSame($insert, $insert->reset());

		$update = DB::update('kohana_test_people')->set(['age' => 51])->where('id', '=', 4);
		$update->execute($db);
		$this->assertSame($update, $update->reset());

		$delete = DB::delete('kohana_test_people')->where('id', '=', 4);
		$delete->execute($db);
		$this->assertSame($delete, $delete->reset());

		$join = new Database_Query_Builder_Join('kohana_test_people', 'LEFT');
		$this->assertSame($join, $join->reset());

		$this->assertSame(3, count(DB::select()->from('kohana_test_people')->execute($db)));
	}

	/**
	 * A connection that was closed opens again by itself, with the same
	 * settings, and stays the one object of its name.
	 */
	#[DataProvider('provider_databases')]
	public function test_connection_opens_again_after_disconnect(string $name): void
	{
		// SQLite in memory is a new, empty database on every connection
		if (str_starts_with($name, 'sqlite'))
		{
			$this->markTestSkipped('A database in memory does not outlive its connection.');
		}

		$db = $this->database($name);
		$this->fill($db);

		$this->assertTrue($db->disconnect());
		$this->assertArrayNotHasKey('test_'.$name, Database::$instances);

		// The next query connects again
		$this->assertSame(3, count(DB::select()->from('kohana_test_people')->execute($db)));
		$this->assertSame($db, Database::instance('test_'.$name));

		// Twice in a row, and with a statement that was prepared before
		$db->disconnect();
		$db->disconnect();
		$this->assertSame('Ana', DB::select('name')->from('kohana_test_people')->where('id', '=', 1)->execute($db)->get('name'));

		$db->reconnect();
		$this->assertSame('Ana', DB::select('name')->from('kohana_test_people')->where('id', '=', 1)->execute($db)->get('name'));
	}

	/**
	 * A first attempt that fails does not use the settings up: when the
	 * server is there, the same object connects.
	 */
	public function test_connect_can_be_tried_again_after_it_failed(): void
	{
		if ( ! extension_loaded('pdo_sqlite'))
		{
			$this->markTestSkipped('The pdo_sqlite extension is not available.');
		}

		$directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kohana_db_retry_'.bin2hex(random_bytes(6));

		$db = Database::instance('test_retry', [
			'type'         => 'PDO',
			'connection'   => ['dsn' => 'sqlite:'.$directory.DIRECTORY_SEPARATOR.'test.sqlite'],
			'table_prefix' => '',
		]);

		try
		{
			// The directory of the database file does not exist yet
			$db->connect();

			$this->fail('No exception was thrown');
		}
		catch (Database_Exception $e)
		{
			$this->assertNotSame('', $e->getMessage());
		}

		mkdir($directory);

		try
		{
			$db->connect();

			$this->assertSame('1', (string) $db->query(Database::SELECT, 'SELECT 1 AS one')->get('one'));
		}
		finally
		{
			$db->disconnect();

			@unlink($directory.DIRECTORY_SEPARATOR.'test.sqlite');
			@rmdir($directory);
		}
	}

	/**
	 * The password of a connection does not show in dumps of the object.
	 */
	#[DataProvider('provider_databases')]
	public function test_dumps_do_not_show_the_connection_settings(string $name): void
	{
		$db = $this->database($name);

		// The three ways an object ends up on a screen or in a log
		$dumps = print_r($db, true).var_export($db->__debugInfo(), true).Debug::dump($db);

		$this->assertStringContainsString('hidden', $dumps);

		// None of the settings of the connection is in them: no address, no user, no password
		foreach (['dsn', 'hostname', 'username', 'password', 'sqlite::memory:', 'host='] as $part)
		{
			$this->assertStringNotContainsString($part, $dumps);
		}
	}

	/**
	 * Every driver lists the tables of its database.
	 */
	#[DataProvider('provider_databases')]
	public function test_list_tables(string $name): void
	{
		$db = $this->database($name);

		$this->assertContains('kohana_test_people', $db->list_tables());
		$this->assertSame(['kohana_test_people'], $db->list_tables('kohana_test_peo%'));
		$this->assertSame([], $db->list_tables('kohana_no_such_table%'));

		// A search text is a value, never a part of the SQL
		$this->assertSame([], $db->list_tables("x' OR '1'='1"));
	}

	/**
	 * Every driver lists the columns of a table with the details ORM needs.
	 */
	#[DataProvider('provider_databases')]
	public function test_list_columns(string $name): void
	{
		$db = $this->database($name);

		$db->query(null, 'DROP TABLE IF EXISTS kohana_test_columns');
		$db->query(null, 'CREATE TABLE kohana_test_columns ('
			.'id INTEGER PRIMARY KEY, title VARCHAR(120) NOT NULL, body TEXT NULL, '
			."price DECIMAL(10,2) NOT NULL DEFAULT '9.50', rating FLOAT NULL, views BIGINT NOT NULL DEFAULT 0)");

		$columns = $db->list_columns('kohana_test_columns');

		$this->assertSame(['id', 'title', 'body', 'price', 'rating', 'views'], array_keys($columns));

		// The PHP type of every column
		$this->assertSame(
			['id' => 'int', 'title' => 'string', 'body' => 'string', 'price' => 'float', 'rating' => 'float', 'views' => 'int'],
			array_map(fn ($column) => $column['type'], $columns)
		);

		$this->assertSame('title', $columns['title']['column_name']);
		// The type as the database names it
		$this->assertContains($columns['title']['data_type'], ['varchar', 'character varying']);
		$this->assertEquals(120, $columns['title']['character_maximum_length']);
		$this->assertFalse($columns['title']['is_nullable']);
		$this->assertTrue($columns['body']['is_nullable']);
		$this->assertFalse($columns['id']['is_nullable']);
		$this->assertEquals(2, $columns['title']['ordinal_position']);
		$this->assertEquals(10, $columns['price']['numeric_precision']);
		$this->assertEquals(2, $columns['price']['numeric_scale']);
		$this->assertStringContainsString('9.50', (string) $columns['price']['column_default']);

		// Only the primary key is marked
		$this->assertSame('PRI', $columns['id']['key']);
		$this->assertSame('', $columns['title']['key']);

		// Searching
		$this->assertSame(['title'], array_keys($db->list_columns('kohana_test_columns', 'tit%')));
		$this->assertSame([], $db->list_columns('kohana_test_columns', 'no_such_column%'));

		$db->query(null, 'DROP TABLE kohana_test_columns');

		// A table that does not exist is an error on every driver
		$this->expectException(Database_Exception::class);

		$db->list_columns('kohana_no_such_table');
	}

	/**
	 * The table prefix of the connection is added, unless told otherwise.
	 */
	public function test_pdo_list_columns_adds_the_table_prefix(): void
	{
		if ( ! extension_loaded('pdo_sqlite'))
		{
			$this->markTestSkipped('The pdo_sqlite extension is not available.');
		}

		$db = Database::instance('test_sqlite_prefix', [
			'type'         => 'PDO',
			'connection'   => ['dsn' => 'sqlite::memory:'],
			'identifier'   => '"',
			'table_prefix' => 'pre_',
		]);

		$db->query(null, 'CREATE TABLE IF NOT EXISTS pre_items (id INTEGER PRIMARY KEY, name TEXT)');

		$this->assertSame(['id', 'name'], array_keys($db->list_columns('items')));
		$this->assertSame(['id', 'name'], array_keys($db->list_columns('pre_items', null, false)));

		// Without the prefix there is no table of that name
		$this->expectException(Database_Exception::class);

		$db->list_columns('items', null, false);
	}

	/**
	 * A "charset" setting, as the default connection has, does not break SQLite.
	 */
	public function test_pdo_sqlite_ignores_the_charset(): void
	{
		if ( ! extension_loaded('pdo_sqlite'))
		{
			$this->markTestSkipped('The pdo_sqlite extension is not available.');
		}

		$db = Database::instance('test_sqlite_charset', [
			'type'         => 'PDO',
			'connection'   => ['dsn' => 'sqlite::memory:'],
			'charset'      => 'utf8',
			'table_prefix' => '',
		]);

		$this->assertSame('1', (string) $db->query(Database::SELECT, 'SELECT 1 AS one')->get('one'));
	}

	/**
	 * While profiling, every query remembers the place in the code that
	 * ran it: here, this test.
	 */
	#[DataProvider('provider_databases')]
	public function test_profiler_knows_where_a_query_ran(string $name): void
	{
		$db = $this->database($name);

		$profiling = Kohana::$profiling;
		Kohana::$profiling = true;

		try
		{
			$line = __LINE__ + 1;
			DB::select()->from('kohana_test_people')->where('id', '=', 424242)->execute($db);

			$marks = array_filter(Profiler::marks(), fn ($mark) => str_contains($mark['name'], 'kohana_test_people') AND str_contains($mark['name'], 'WHERE'));
			$mark  = end($marks);

			$this->assertSame(__FILE__, $mark['file']);
			$this->assertSame($line, $mark['line']);
		}
		finally
		{
			Kohana::$profiling = $profiling;
		}
	}
}

/**
 * Class used to test results returned as objects of a given class.
 */
class Kohana_DatabaseTest_Person
{
	public $id;
	public $name;
	public $age;
}

/**
 * Database session that can be written without sending a cookie, which is
 * not possible from the command line.
 */
class Kohana_DatabaseTest_Session extends Session_Database
{
	/**
	 * Stores the session row, as Session::write() does at the end of a request.
	 *
	 * @return bool
	 */
	public function store(): bool
	{
		$this->_data['last_active'] = time();

		try
		{
			return $this->_write();
		}
		catch (Throwable $e)
		{
			// The row is stored before the cookie is sent; only the cookie
			// fails here, because output has already started
			return true;
		}
	}
}
