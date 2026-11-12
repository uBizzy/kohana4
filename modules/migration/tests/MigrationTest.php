<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the migration runner on real databases: SQLite always, and
 * MySQL/MariaDB (MySQLi and PDO drivers) when KOHANA_TEST_MYSQL_HOST is
 * set, see modules/database/tests/DatabaseTest.php.
 *
 * @package    Kohana/Migration
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.migration')]
class Kohana_MigrationTest extends Unittest_TestCase
{
	/**
	 * @var  string  Directory the test migrations are written to
	 */
	protected string $directory;

	public function setUp(): void
	{
		parent::setUp();

		$this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kohana_migrations_'.bin2hex(random_bytes(6));

		mkdir($this->directory);
	}

	public function tearDown(): void
	{
		array_map('unlink', glob($this->directory.DIRECTORY_SEPARATOR.'*'));
		rmdir($this->directory);

		parent::tearDown();
	}

	public static function provider_databases(): array
	{
		$databases = ['sqlite' => ['sqlite']];

		if (getenv('KOHANA_TEST_MYSQL_HOST'))
		{
			$databases['mysqli'] = ['mysqli'];

			if (extension_loaded('pdo_mysql'))
			{
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
	 * Connects to a test database, without any table of these tests.
	 */
	protected function database(string $name): Database
	{
		$mysql = [
			'hostname' => getenv('KOHANA_TEST_MYSQL_HOST'),
			'port'     => getenv('KOHANA_TEST_MYSQL_PORT') ?: 3306,
			'username' => getenv('KOHANA_TEST_MYSQL_USER') ?: 'root',
			'password' => (string) getenv('KOHANA_TEST_MYSQL_PASSWORD'),
			'database' => getenv('KOHANA_TEST_MYSQL_DATABASE') ?: 'kohana_test',
		];

		$config = match ($name) {
			'sqlite' => [
				'type'       => 'PDO',
				'connection' => ['dsn' => 'sqlite::memory:'],
				'identifier' => '"',
			],
			'mysqli' => [
				'type'       => 'MySQLi',
				'connection' => $mysql,
				'charset'    => 'utf8mb4',
			],
			'pdo_pgsql' => [
				'type'       => 'PDO',
				'connection' => [
					'dsn' => 'pgsql:host='.getenv('KOHANA_TEST_PGSQL_HOST')
						.';port='.(getenv('KOHANA_TEST_PGSQL_PORT') ?: 5432)
						.';dbname='.(getenv('KOHANA_TEST_PGSQL_DATABASE') ?: 'kohana_test'),
					'username' => getenv('KOHANA_TEST_PGSQL_USER') ?: 'postgres',
					'password' => (string) getenv('KOHANA_TEST_PGSQL_PASSWORD'),
				],
				'identifier' => '"',
			],
			'pdo_mysql' => [
				'type'       => 'PDO',
				'connection' => [
					'dsn'      => 'mysql:host='.$mysql['hostname'].';port='.$mysql['port'].';dbname='.$mysql['database'].';charset=utf8mb4',
					'username' => $mysql['username'],
					'password' => $mysql['password'],
				],
				'identifier' => '`',
			],
		};

		if ($name === 'sqlite' AND ! extension_loaded('pdo_sqlite'))
		{
			$this->markTestSkipped('The pdo_sqlite extension is not available.');
		}

		// A fresh connection for every test
		if (isset(Database::$instances['test_migration_'.$name]))
		{
			Database::$instances['test_migration_'.$name]->disconnect();
			unset(Database::$instances['test_migration_'.$name]);
		}

		// The prefix shows that the table of applied migrations gets it too
		$db = Database::instance('test_migration_'.$name, $config + ['table_prefix' => 'kmt_']);

		foreach (['kmt_migrations', 'kmt_one', 'kmt_two', 'kmt_three'] as $table)
		{
			$db->query(null, 'DROP TABLE IF EXISTS '.$table);
		}

		return $db;
	}

	/**
	 * Returns a runner that finds its migrations in the test directory.
	 */
	protected function runner(Database $db): Migration_Runner
	{
		return new Kohana_MigrationTest_Runner($db, $this->directory);
	}

	/**
	 * Writes a migration that creates a table, and drops it on the way back.
	 */
	protected function migration(string $name, string $table, bool $with_down = true, string $extra = ''): void
	{
		$down = $with_down
			? 'public function down(Database $db): void { $db->query(null, "DROP TABLE '.$table.'"); }'
			: '';

		file_put_contents($this->directory.DIRECTORY_SEPARATOR.$name.'.php', '<?php return new class extends Migration {
			public function up(Database $db): void { $db->query(null, "CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY)"); '.$extra.' }
			'.$down.'
		};');
	}

	#[DataProvider('provider_databases')]
	public function test_run_and_rollback(string $name): void
	{
		$db     = $this->database($name);
		$runner = $this->runner($db);

		// Written out of order: the names decide the order
		$this->migration('20260102_000000_two', 'kmt_two');
		$this->migration('20260101_000000_one', 'kmt_one');
		$this->migration('20260103_000000_three', 'kmt_three');

		$this->assertSame([], $runner->applied());
		$this->assertSame(['20260101_000000_one', '20260102_000000_two', '20260103_000000_three'], array_keys($runner->pending()));

		$reported = [];
		$done = $runner->run(function (string $name) use ( & $reported) { $reported[] = $name; });

		$this->assertSame(['20260101_000000_one', '20260102_000000_two', '20260103_000000_three'], $done);
		$this->assertSame($done, $reported);
		$this->assertSame($done, $runner->applied());
		$this->assertSame([], $runner->pending());
		$this->assertEqualsCanonicalizing(['kmt_migrations', 'kmt_one', 'kmt_three', 'kmt_two'], $db->list_tables('kmt_%'));

		// Nothing is applied twice
		$this->assertSame([], $runner->run());

		// One step back by default, newest first
		$this->assertSame(['20260103_000000_three'], $runner->rollback());
		$this->assertSame(['20260101_000000_one', '20260102_000000_two'], $runner->applied());
		$this->assertNotContains('kmt_three', $db->list_tables('kmt_%'));

		// More steps than there are migrations: all that is left
		$this->assertSame(['20260102_000000_two', '20260101_000000_one'], $runner->rollback(5));
		$this->assertSame([], $runner->applied());
		$this->assertSame(['kmt_migrations'], $db->list_tables('kmt_%'));

		// Nothing left to undo
		$this->assertSame([], $runner->rollback());
	}

	/**
	 * A migration made earlier but added later (another branch) is still applied.
	 */
	#[DataProvider('provider_databases')]
	public function test_older_migration_added_later_is_applied(string $name): void
	{
		$runner = $this->runner($this->database($name));

		$this->migration('20260102_000000_two', 'kmt_two');
		$runner->run();

		$this->migration('20260101_000000_one', 'kmt_one');

		$this->assertSame(['20260101_000000_one'], $runner->run());
		$this->assertSame(['20260101_000000_one', '20260102_000000_two'], $runner->applied());
	}

	#[DataProvider('provider_databases')]
	public function test_failing_migration_stops_the_run(string $name): void
	{
		$db     = $this->database($name);
		$runner = $this->runner($db);

		$this->migration('20260101_000000_one', 'kmt_one');
		$this->migration('20260102_000000_two', 'kmt_two', true, '$db->query(null, "THIS IS NOT SQL");');
		$this->migration('20260103_000000_three', 'kmt_three');

		try
		{
			$runner->run();

			$this->fail('No exception was thrown');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('20260102_000000_two could not be applied', $e->getMessage());
			$this->assertInstanceOf(Database_Exception::class, $e->getPrevious());
		}

		// The one before stays, the failed one and the ones after are not recorded
		$this->assertSame(['20260101_000000_one'], $runner->applied());
		$this->assertNotContains('kmt_three', $db->list_tables('kmt_%'));

		if ($name === 'sqlite' OR $name === 'pdo_pgsql')
		{
			// SQLite and PostgreSQL take back the table too. (MySQL cannot:
			// it ends the transaction when a table is created.)
			$this->assertNotContains('kmt_two', $db->list_tables('kmt_%'));
		}
	}

	#[DataProvider('provider_databases')]
	public function test_migration_without_down_stops_the_rollback(string $name): void
	{
		$runner = $this->runner($this->database($name));

		$this->migration('20260101_000000_one', 'kmt_one', false);
		$this->migration('20260102_000000_two', 'kmt_two');
		$runner->run();

		try
		{
			$runner->rollback(2);

			$this->fail('No exception was thrown');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('20260101_000000_one could not be undone', $e->getMessage());
			$this->assertStringContainsString('no down() method', $e->getMessage());
		}

		// The newer one was undone, the one without a way back is still applied
		$this->assertSame(['20260101_000000_one'], $runner->applied());
	}

	public function test_applied_migration_without_file_is_reported(): void
	{
		$runner = $this->runner($this->database('sqlite'));

		$this->migration('20260101_000000_one', 'kmt_one');
		$runner->run();

		unlink($this->directory.DIRECTORY_SEPARATOR.'20260101_000000_one.php');

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('its file was not found');

		$runner->rollback();
	}

	public function test_badly_named_file_is_reported(): void
	{
		$runner = $this->runner($this->database('sqlite'));

		$this->migration('create_users', 'kmt_one');

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('must be named like');

		$runner->pending();
	}

	public function test_file_that_returns_no_migration_is_reported(): void
	{
		$runner = $this->runner($this->database('sqlite'));

		file_put_contents($this->directory.DIRECTORY_SEPARATOR.'20260101_000000_one.php', '<?php return 1;');

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('must return an object that extends Migration');

		$runner->run();
	}

	public function test_create_writes_a_migration_that_runs(): void
	{
		$runner = $this->runner($this->database('sqlite'));

		$file = $runner->create('add_price_to_products', $this->directory);

		$this->assertMatchesRegularExpression('/^\d{8}_\d{6}_add_price_to_products\.php$/', basename($file));
		$this->assertInstanceOf(Migration::class, Kohana::load($file));

		// The new file is found, applied and undone like any other
		$this->assertSame([basename($file, '.php')], $runner->run());
		$this->assertSame([basename($file, '.php')], $runner->rollback());
	}

	/**
	 * A module hands a ready-made migration to the application this way.
	 */
	public function test_create_writes_the_given_content(): void
	{
		$runner  = $this->runner($this->database('sqlite'));
		$content = "<?php\n\nreturn new class extends Migration {\n\tpublic function up(Database \$db): void {}\n};\n";

		$file = $runner->create('create_things', $this->directory, $content);

		$this->assertSame($content, file_get_contents($file));
		$this->assertSame([basename($file, '.php')], $runner->run());
	}

	public function test_create_refuses_a_bad_description(): void
	{
		$runner = $this->runner($this->database('sqlite'));

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('is not valid');

		$runner->create('../Create Users', $this->directory);
	}

	/**
	 * A fresh start drops the tables of this prefix, whatever points to
	 * what, and applies every migration again. Other tables are left alone.
	 */
	#[DataProvider('provider_databases')]
	public function test_fresh_drops_the_tables_and_applies_all(string $name): void
	{
		$db     = $this->database($name);
		$runner = $this->runner($db);

		$this->migration('20260101_000000_one', 'kmt_one');
		$this->migration('20260102_000000_two', 'kmt_two');
		$runner->run();

		// A table made by hand that points to another one, with data, and
		// a table of somebody else in the same database
		$db->query(null, 'INSERT INTO kmt_one (id) VALUES (1)');
		$db->query(null, 'CREATE TABLE kmt_three (id INTEGER PRIMARY KEY, one_id INTEGER NOT NULL, FOREIGN KEY (one_id) REFERENCES kmt_one (id))');
		$db->query(null, 'INSERT INTO kmt_three (id, one_id) VALUES (1, 1)');
		$db->query(null, 'DROP TABLE IF EXISTS kohana_keep_me');
		$db->query(null, 'CREATE TABLE kohana_keep_me (id INTEGER PRIMARY KEY)');

		try
		{
			$this->assertSame(['20260101_000000_one', '20260102_000000_two'], $runner->fresh());

			$this->assertSame(['kmt_migrations', 'kmt_one', 'kmt_two'], $db->list_tables('kmt%'));
			$this->assertSame(0, $db->count_records('one'));
			$this->assertContains('kohana_keep_me', $db->list_tables());
			$this->assertSame([], $runner->pending());

			// Foreign keys are followed again afterwards
			$db->query(null, 'CREATE TABLE kmt_three (id INTEGER PRIMARY KEY, one_id INTEGER NOT NULL, FOREIGN KEY (one_id) REFERENCES kmt_one (id))');

			try
			{
				$db->query(null, 'INSERT INTO kmt_three (id, one_id) VALUES (1, 99)');
				$this->fail('A row that points to nothing must be refused');
			}
			catch (Database_Exception $e)
			{
				$this->assertSame(0, $db->count_records('three'));
			}
		}
		finally
		{
			$db->query(null, 'DROP TABLE IF EXISTS kmt_three');
			$db->query(null, 'DROP TABLE IF EXISTS kohana_keep_me');
		}
	}

	public function test_fresh_never_runs_in_production(): void
	{
		$db     = $this->database('sqlite');
		$runner = $this->runner($db);

		$this->migration('20260101_000000_one', 'kmt_one');
		$runner->run();
		$db->query(null, 'INSERT INTO kmt_one (id) VALUES (1)');

		$this->setEnvironment(['Kohana::$environment' => Kohana::PRODUCTION]);

		try
		{
			$runner->fresh();
			$this->fail('The tables of a production database must not be dropped');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('never dropped', $e->getMessage());
		}

		$this->assertSame(1, $db->count_records('one'));

		// The task refuses too, before it asks or connects
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('never dropped');

		Minion_Task::factory(['task' => 'migration:fresh'])->execute();
	}

	/**
	 * Writes a seed file that adds a row to the table "kmt_one".
	 */
	protected function seed(string $name, int $id, string $extra = ''): void
	{
		file_put_contents($this->directory.DIRECTORY_SEPARATOR.'seed_'.$name.'.php', '<?php return function (Database $db): void {
			DB::insert("one", ["id"])->values(['.$id.'])->execute($db); '.$extra.'
		};');
	}

	public function test_seeds_run_in_the_order_of_their_names(): void
	{
		$db     = $this->database('sqlite');
		$runner = $this->runner($db);

		$this->migration('20260101_000000_one', 'kmt_one');
		$this->seed('b_second', 2);
		$this->seed('a_first', 1);

		$this->assertSame(['a_first', 'b_second'], array_keys($runner->seeds()));

		// After a fresh start the tables are there, and empty
		$runner->fresh();

		$seen = [];
		$this->assertSame(['a_first', 'b_second'], $runner->seed(null, function (string $name) use (&$seen) { $seen[] = $name; }));
		$this->assertSame(['a_first', 'b_second'], $seen);
		$this->assertSame(2, $db->count_records('one'));

		// One seed by its name
		$runner->fresh();
		$this->assertSame(['b_second'], $runner->seed('b_second'));
		$this->assertSame(1, $db->count_records('one'));

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('The seed nothing was not found');

		$runner->seed('nothing');
	}

	/**
	 * A seed that fails leaves none of its rows; the seeds before it stay.
	 */
	public function test_failing_seed_leaves_nothing_of_itself(): void
	{
		$db     = $this->database('sqlite');
		$runner = $this->runner($db);

		$this->migration('20260101_000000_one', 'kmt_one');
		$this->seed('a_good', 1);
		$this->seed('b_bad', 2, 'throw new Exception("no more data");');
		$runner->run();

		try
		{
			$runner->seed();
			$this->fail('The failing seed must be reported');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('The seed b_bad failed: no more data', $e->getMessage());
		}

		$this->assertSame([['id' => 1]], array_map(fn ($row) => ['id' => (int) $row['id']], DB::select('id')->from('one')->execute($db)->as_array()));
	}

	public function test_seed_file_that_returns_no_function_is_reported(): void
	{
		$runner = $this->runner($this->database('sqlite'));

		file_put_contents($this->directory.DIRECTORY_SEPARATOR.'seed_wrong.php', '<?php return "data";');

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('must return a function');

		$runner->seed();
	}

	public function test_tasks_exist(): void
	{
		foreach (['Create', 'Status', 'Run', 'Rollback', 'Fresh', 'Seed'] as $task)
		{
			$this->assertInstanceOf('Task_Migration_'.$task, Minion_Task::factory(['task' => 'migration:'.strtolower($task)]));
		}
	}
}

/**
 * A runner that looks for migrations in one directory only.
 */
class Kohana_MigrationTest_Runner extends Migration_Runner
{
	public function __construct(Database $db, protected string $directory)
	{
		parent::__construct($db);
	}

	protected function _files(): array
	{
		// The seed files of the tests are in the same directory
		return array_values(array_filter(glob($this->directory.DIRECTORY_SEPARATOR.'*.php'), fn ($file) => ! str_starts_with(basename($file), 'seed_')));
	}

	public function seeds(): array
	{
		$seeds = [];

		foreach (glob($this->directory.DIRECTORY_SEPARATOR.'seed_*.php') as $file)
		{
			$seeds[substr(basename($file, '.php'), 5)] = $file;
		}

		ksort($seeds);

		return $seeds;
	}
}
