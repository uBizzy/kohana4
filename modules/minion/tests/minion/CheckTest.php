<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the task that checks whether the application can run here.
 *
 * @package    Kohana/Minion
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.minion')]
class Minion_CheckTest extends Unittest_TestCase
{
	/**
	 * @var string  A cookie salt that is long enough
	 */
	const SALT = 'a-salt-of-the-check-tests-that-is-long-enough';

	/**
	 * @var resource  Takes what the task writes
	 */
	protected $output;

	public function setUp(): void
	{
		parent::setUp();

		$this->output = fopen('php://memory', 'w+');

		Minion_CLI::$stdout = $this->output;
		Minion_CLI::$stderr = $this->output;

		$this->setEnvironment([
			'Cookie::$salt'        => self::SALT,
			'Kohana::$environment' => Kohana::DEVELOPMENT,
		]);
	}

	public function tearDown(): void
	{
		Minion_CLI::$stdout = null;
		Minion_CLI::$stderr = null;

		if (isset(Database::$instances['default']))
		{
			Database::$instances['default']->disconnect();
			unset(Database::$instances['default']);
		}

		parent::tearDown();
	}

	/**
	 * Returns the task, for an application with these modules enabled.
	 */
	protected function task(string ...$modules): Minion_CheckTest_Task
	{
		$task = Minion_Task::factory(['task' => 'check']);

		$this->assertInstanceOf(Task_Check::class, $task);

		return Minion_CheckTest_Task::make($modules);
	}

	/**
	 * Everything the task wrote.
	 */
	protected function written(): string
	{
		rewind($this->output);

		return stream_get_contents($this->output);
	}

	/**
	 * Makes the default database an SQLite one.
	 */
	protected function database(string $dsn = 'sqlite::memory:'): void
	{
		$this->setEnvironment(['database.default' => ['type' => 'PDO']]);

		Database::instance('default', [
			'type'         => 'PDO',
			'connection'   => ['dsn' => $dsn, 'password' => 'a-secret-password'],
			'identifier'   => '"',
			'table_prefix' => '',
		]);
	}

	public function test_basic_checks_pass_and_are_written(): void
	{
		$task = $this->task();
		$task->execute();

		$this->assertSame(['ok'], array_unique(array_column($task->results(), 0)));
		$this->assertSame(
			['PHP version', 'Extension intl', 'Extension mbstring', 'Cache directory', 'Logs directory', 'Cookie salt'],
			array_column($task->results(), 1)
		);

		$output = $this->written();

		$this->assertStringContainsString('PHP version', $output);
		$this->assertStringContainsString(PHP_VERSION, $output);
		$this->assertStringContainsString('No check failed (6 checks).', $output);

		// The salt itself is never written, only its length
		$this->assertStringNotContainsString(self::SALT, $output);
		$this->assertStringContainsString(strlen(self::SALT).' characters', $output);
	}

	/**
	 * A failed check makes the command end with an error, after all
	 * checks were written.
	 */
	public function test_missing_cookie_salt_fails_the_task(): void
	{
		Cookie::$salt = null;

		$task = $this->task();

		try
		{
			$task->execute();
			$this->fail('A failed check must end the task with an error');
		}
		catch (Minion_Exception_Failed $e)
		{
			$this->assertSame('1 of 6 checks failed.', $e->getMessage());
			$this->assertSame('1 of 6 checks failed.', $e->format_for_cli());
		}

		$this->assertSame(['fail', 'Cookie salt', 'Cookie::$salt is not set in the bootstrap'], $task->result('Cookie salt'));
		$this->assertStringContainsString('Logs directory', $this->written());
	}

	public function test_short_cookie_salt_is_a_warning(): void
	{
		Cookie::$salt = 'short';

		$task = $this->task();
		$task->execute();

		$this->assertSame(['warn', 'Cookie salt', '5 characters, use 32 or more'], $task->result('Cookie salt'));
		$this->assertStringContainsString('1 warning.', $this->written());
	}

	public function test_a_wrong_theme_value_is_a_warning(): void
	{
		$config = Kohana::$config->load('theme');
		$before = $config->as_array();

		try
		{
			$config->exchangeArray(['mode' => 'pink'] + $before);

			$task = $this->task();
			$task->checks();

			$this->assertSame(['warn', 'Theme', '"mode" is not valid in config/theme.php: the default is used'], $task->result('Theme'));
		}
		finally
		{
			$config->exchangeArray($before);
		}
	}

	public function test_missing_directory_fails(): void
	{
		$this->setEnvironment(['Kohana::$cache_dir' => APPPATH.'no-such-directory']);

		$task = $this->task();
		$task->checks();

		$this->assertSame('fail', $task->result('Cache directory')[0]);
		$this->assertStringContainsString('does not exist', $task->result('Cache directory')[2]);
	}

	public function test_missing_extension_fails(): void
	{
		$task = $this->task();
		$task->extension('no_such_extension', 'the test');

		$this->assertSame(['fail', 'Extension no_such_extension', 'missing, needed for the test'], $task->result('Extension no_such_extension'));
	}

	public function test_database_is_connected_and_queried(): void
	{
		if ( ! extension_loaded('pdo_sqlite'))
		{
			$this->markTestSkipped('The pdo_sqlite extension is not available.');
		}

		$this->database();

		$task = $this->task('database', 'migration');
		$task->checks();

		$this->assertSame(['ok', 'Extension pdo', 'for the database'], $task->result('Extension pdo'));
		$this->assertSame(['ok', 'Database connection', '"default" sqlite'], $task->result('Database connection'));
		$this->assertSame(['ok', 'Migrations', 'all applied'], $task->result('Migrations'));
	}

	/**
	 * A migration that was not applied is a warning: the application may
	 * still work, and the deploy is not stopped.
	 */
	public function test_pending_migration_is_a_warning(): void
	{
		if ( ! extension_loaded('pdo_sqlite'))
		{
			$this->markTestSkipped('The pdo_sqlite extension is not available.');
		}

		$directory = APPPATH.'migrations';
		$existed   = is_dir($directory);
		$file      = $directory.DIRECTORY_SEPARATOR.'20260101_000000_kohana_check_test.php';

		$existed or mkdir($directory);
		file_put_contents($file, '<?php return new class extends Migration { public function up(Database $db): void {} };');

		try
		{
			$this->database();

			$task = $this->task('database', 'migration');
			$task->execute();

			$this->assertSame('warn', $task->result('Migrations')[0]);
			$this->assertStringStartsWith('1 pending, run "', $task->result('Migrations')[2]);
			$this->assertStringContainsString('1 warning.', $this->written());
		}
		finally
		{
			unlink($file);
			$existed or rmdir($directory);
		}
	}

	/**
	 * A database that cannot be reached is a failed check with the
	 * message of the database, and never its password.
	 */
	public function test_unreachable_database_fails_without_secrets(): void
	{
		if ( ! extension_loaded('pdo_sqlite'))
		{
			$this->markTestSkipped('The pdo_sqlite extension is not available.');
		}

		$this->database('sqlite:/no/such/directory/data.sqlite');

		$task = $this->task('database');

		try
		{
			$task->execute();
			$this->fail('A database that cannot be reached must fail the task');
		}
		catch (Minion_Exception_Failed $e)
		{
			$this->assertSame('fail', $task->result('Database connection')[0]);
		}

		$this->assertStringContainsString('"default": ', $task->result('Database connection')[2]);
		$this->assertStringNotContainsString('a-secret-password', $this->written());
	}

	public function test_cache_is_written_read_and_deleted(): void
	{
		$this->setEnvironment([
			'Cache::$default' => 'file',
			'cache.file'      => ['driver' => 'file', 'cache_dir' => APPPATH.'cache', 'default_expire' => 3600],
		]);

		$task = $this->task('cache');
		$task->checks();

		$this->assertSame('ok', $task->result('Cache')[0], $task->result('Cache')[2]);
		$this->assertStringContainsString('written, read and deleted', $task->result('Cache')[2]);
	}

	public function test_cache_that_is_not_configured_fails(): void
	{
		$this->setEnvironment(['Cache::$default' => 'no_such_group']);

		$task = $this->task('cache');
		$task->checks();

		$this->assertSame('fail', $task->result('Cache')[0]);
		$this->assertStringContainsString('no_such_group', $task->result('Cache')[2]);
	}

	public function test_encryption_key_is_checked_but_not_written(): void
	{
		$this->setEnvironment(['encrypt.default' => ['type' => 'openssl', 'key' => null]]);

		$task = $this->task('encrypt');
		$task->checks();

		$this->assertSame('warn', $task->result('Encryption key')[0]);
		$this->assertSame('ok', $task->result('Extension openssl')[0]);

		$this->setEnvironment(['encrypt.default' => ['type' => 'openssl', 'key' => 'a-secret-encryption-key']]);

		$task = $this->task('encrypt');
		$task->execute();

		$this->assertSame('ok', $task->result('Encryption key')[0]);
		$this->assertStringNotContainsString('a-secret-encryption-key', $this->written());
	}

	public function test_image_driver_is_checked(): void
	{
		$this->setEnvironment(['image.default_driver' => null]);

		$task = $this->task('image');
		$task->checks();

		$this->assertSame('warn', $task->result('Image driver')[0]);

		$this->setEnvironment(['image.default_driver' => 'No_Such_Extension']);

		$task = $this->task('image');
		$task->checks();

		$this->assertSame('fail', $task->result('Extension no_such_extension')[0]);
	}

	/**
	 * Development settings are only looked for in production, and are
	 * warnings: they do not fail the task.
	 */
	public function test_production_warns_about_development_settings(): void
	{
		$task = $this->task('userguide');
		$task->checks();

		$this->assertNull($task->result('Production'));

		$this->setEnvironment([
			'Kohana::$environment' => Kohana::PRODUCTION,
			'Kohana::$profiling'   => true,
			'Kohana::$caching'     => false,
		]);

		// A module of your own that is for development names itself in config/check.php
		$this->setEnvironment(['check.development_modules' => ['userguide', 'unittest', 'codebench', 'my_tool']]);

		$task = $this->task('userguide', 'my_tool', 'pagination');
		$task->execute();

		$details = array_column(array_filter($task->results(), fn ($result) => $result[1] === 'Production'), 2);

		$this->assertContains('the userguide module is enabled', $details);
		$this->assertContains('the my_tool module is enabled', $details);
		$this->assertNotContains('the pagination module is enabled', $details);
		$this->assertContains('profiling is on: set "profile" to false in Kohana::init()', $details);
		$this->assertContains('internal caching is off: set "caching" to true in Kohana::init()', $details);
		$this->assertSame(is_file(PUBPATH.'install.php'), in_array('delete install.php from the public directory', $details, true));
		$this->assertNotContains('fail', array_column($task->results(), 0));
	}

	public function test_clean_production_passes(): void
	{
		$this->setEnvironment([
			'Kohana::$environment' => Kohana::PRODUCTION,
			'Kohana::$profiling'   => false,
			'Kohana::$caching'     => true,
		]);

		$task = $this->task();
		$task->install_file = false;
		$task->checks();

		$this->assertSame(['ok', 'Production', 'no development setting found'], $task->result('Production'));
	}
}

/**
 * The task, for an application whose modules the test chooses, with its
 * results open to the test.
 */
class Minion_CheckTest_Task extends Task_Check
{
	/**
	 * @var array<int, string>  Names of the modules that count as enabled
	 */
	public array $modules = [];

	/**
	 * @var bool|null  Is install.php there? Looked up when null
	 */
	public ?bool $install_file = null;

	/**
	 * The constructor of a task is not public: tasks are made by a factory.
	 *
	 * @param array<int, string> $modules
	 */
	public static function make(array $modules): Minion_CheckTest_Task
	{
		$task = new Minion_CheckTest_Task;
		$task->modules = $modules;

		return $task;
	}

	protected function _enabled(string $module): bool
	{
		return in_array($module, $this->modules, true);
	}

	protected function _install_file(): bool
	{
		return $this->install_file ?? parent::_install_file();
	}

	public function checks(): void
	{
		$this->_checks();
	}

	public function extension(string $extension, string $for): void
	{
		$this->_check_extension($extension, $for);
	}

	public function results(): array
	{
		return $this->_results;
	}

	/**
	 * The first result with this name, or null.
	 */
	public function result(string $name): ?array
	{
		foreach ($this->_results as $result)
		{
			if ($result[1] === $name)
				return $result;
		}

		return null;
	}
}
