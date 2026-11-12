<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the migration tasks as they are run from the command line, with
 * real files in the application and a database in memory.
 *
 * @package    Kohana/Migration
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.migration')]
class Kohana_MigrationTaskTest extends Unittest_TestCase
{
	/**
	 * @var string  Name of the database connection of these tests
	 */
	const DB = 'kohana_migration_tasks';

	/**
	 * @var resource  Takes what the tasks write
	 */
	protected $output;

	/**
	 * @var array<int, string>  Directories and files made by the test, to remove
	 */
	protected $made = [];

	public function setUp(): void
	{
		parent::setUp();

		if ( ! extension_loaded('pdo_sqlite'))
		{
			$this->markTestSkipped('The pdo_sqlite extension is not available.');
		}

		if (glob(APPPATH.'migrations/*.php') OR glob(APPPATH.'seeds/*.php'))
		{
			$this->markTestSkipped('The application has migrations or seeds of its own.');
		}

		$this->output = fopen('php://memory', 'w+');

		Minion_CLI::$stdout = $this->output;
		Minion_CLI::$stderr = $this->output;

		$this->setEnvironment([
			'Minion_CLI::$interactive' => false,
			'Kohana::$environment'     => Kohana::DEVELOPMENT,
		]);

		Database::instance(self::DB, [
			'type'         => 'PDO',
			'connection'   => ['dsn' => 'sqlite::memory:'],
			'identifier'   => '"',
			'table_prefix' => '',
		]);

		$this->file('migrations/20260101_000000_create_things.php', '<?php return new class extends Migration {
			public function up(Database $db): void { $db->query(null, "CREATE TABLE things (id INTEGER PRIMARY KEY, name VARCHAR(20))"); }
			public function down(Database $db): void { $db->query(null, "DROP TABLE things"); }
		};');

		$this->file('seeds/things.php', '<?php return function (Database $db): void {
			DB::insert("things", ["name"])->values(["first"])->values(["second"])->execute($db);
		};');
	}

	public function tearDown(): void
	{
		Minion_CLI::$stdout = null;
		Minion_CLI::$stderr = null;

		if (isset(Database::$instances[self::DB]))
		{
			Database::$instances[self::DB]->disconnect();
			unset(Database::$instances[self::DB]);
		}

		// Files first, then the directories that were made for them
		foreach (array_merge(glob(APPPATH.'migrations/*_kohana_task_test.php') ?: [], array_reverse($this->made)) as $path)
		{
			is_dir($path) ? @rmdir($path) : @unlink($path);
		}

		parent::tearDown();
	}

	/**
	 * Writes a file in the application, to be removed after the test.
	 */
	protected function file(string $path, string $content): void
	{
		$directory = dirname(APPPATH.$path);

		if ( ! is_dir($directory))
		{
			mkdir($directory);
			$this->made[] = $directory;
		}

		file_put_contents(APPPATH.$path, $content);
		$this->made[] = APPPATH.$path;
	}

	/**
	 * Runs a task on the test database and returns what it wrote.
	 */
	protected function run_task(string $task, array $options = []): string
	{
		ftruncate($this->output, 0);
		rewind($this->output);

		// Every task but "create" works on a database
		$options += ($task === 'migration:create') ? [] : ['db' => self::DB];

		Minion_Task::factory(['task' => $task] + $options)->execute();

		rewind($this->output);

		return stream_get_contents($this->output);
	}

	/**
	 * The number of rows in the table of the test migration, or null
	 * when the table is not there.
	 */
	protected function things(): ?int
	{
		$db = Database::instance(self::DB);

		return in_array('things', $db->list_tables(), true) ? $db->count_records('things') : null;
	}

	public function test_status_run_and_rollback(): void
	{
		$status = $this->run_task('migration:status');

		$this->assertMatchesRegularExpression('/pending\s+20260101_000000_create_things/', $status);
		$this->assertStringContainsString('1 migration, 1 pending.', $status);

		$run = $this->run_task('migration:run');

		$this->assertStringContainsString('applied  20260101_000000_create_things', $run);
		$this->assertStringContainsString('1 migration applied.', $run);
		$this->assertSame(0, $this->things());

		$this->assertStringContainsString('Nothing to apply.', $this->run_task('migration:run'));
		$this->assertMatchesRegularExpression('/applied\s+20260101_000000_create_things/', $this->run_task('migration:status'));

		$back = $this->run_task('migration:rollback');

		$this->assertStringContainsString('undone   20260101_000000_create_things', $back);
		$this->assertStringContainsString('1 migration undone.', $back);
		$this->assertNull($this->things());

		$this->assertStringContainsString('Nothing to undo.', $this->run_task('migration:rollback', ['steps' => '3']));
	}

	public function test_seed_and_fresh(): void
	{
		$this->run_task('migration:run');

		$seed = $this->run_task('migration:seed');

		$this->assertStringContainsString('seeded   things', $seed);
		$this->assertStringContainsString('1 seed run.', $seed);
		$this->assertSame(2, $this->things());

		$this->assertSame(4, $this->run_task('migration:seed', ['name' => 'things']) ? $this->things() : null);

		// A fresh start: the rows are gone, the table is there again
		$fresh = $this->run_task('migration:fresh');

		$this->assertStringContainsString('Tables dropped, 1 migration applied.', $fresh);
		$this->assertSame(0, $this->things());

		// And with the seeds
		$fresh = $this->run_task('migration:fresh', ['seed' => null]);

		$this->assertStringContainsString('seeded   things', $fresh);
		$this->assertSame(2, $this->things());
	}

	/**
	 * When the user says no, nothing is dropped.
	 */
	public function test_fresh_asks_first(): void
	{
		$this->run_task('migration:run');
		$this->run_task('migration:seed');

		// Asked, and no answer can be read: that is a no
		$this->setEnvironment(['Minion_CLI::$interactive' => true]);

		$answer = $this->run_task('migration:fresh');

		$this->assertStringContainsString('Drop all tables of the "'.self::DB.'" database', $answer);
		$this->assertStringContainsString('Nothing was changed.', $answer);
		$this->assertSame(2, $this->things());
	}

	public function test_create_writes_a_file_in_the_application(): void
	{
		$created = $this->run_task('migration:create', ['name' => 'kohana_task_test']);
		$files   = glob(APPPATH.'migrations/*_kohana_task_test.php');

		$this->assertCount(1, $files);
		$this->assertStringContainsString('Created', $created);
		$this->assertStringContainsString(basename($files[0]), $created);
		$this->assertInstanceOf(Migration::class, Kohana::load($files[0]));
	}

	/**
	 * A name that cannot be a file name is refused before anything is written.
	 */
	public function test_create_refuses_a_bad_name(): void
	{
		try
		{
			$this->run_task('migration:create', ['name' => '../Evil Name']);
			$this->fail('A bad name must be refused');
		}
		catch (Minion_Exception_InvalidOptions $e)
		{
			$this->assertStringContainsString('--help', $e->getMessage());
			$this->assertSame($e->getMessage(), $e->format_for_cli());
		}

		$this->assertCount(1, glob(APPPATH.'migrations/*.php'), 'Only the migration of the test is there');
	}
}
