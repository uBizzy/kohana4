<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the config reader and writer that keep settings in a database
 * table, on SQLite.
 *
 * @package    Kohana/Database
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.database')]
class Kohana_ConfigDatabaseTest extends Unittest_TestCase
{
	/**
	 * @var string  Name of the database connection of these tests
	 */
	const DB = 'kohana_config_test';

	/**
	 * @var Database
	 */
	protected $db;

	public function setUp(): void
	{
		parent::setUp();

		if ( ! extension_loaded('pdo_sqlite'))
		{
			$this->markTestSkipped('The pdo_sqlite extension is not available.');
		}

		$this->db = Database::instance(self::DB, [
			'type'         => 'PDO',
			'connection'   => ['dsn' => 'sqlite::memory:'],
			'identifier'   => '"',
			'table_prefix' => '',
		]);

		$this->db->query(null, 'CREATE TABLE settings (group_name VARCHAR(100) NOT NULL, config_key VARCHAR(100) NOT NULL, config_value TEXT, PRIMARY KEY (group_name, config_key))');
	}

	public function tearDown(): void
	{
		if (isset(Database::$instances[self::DB]))
		{
			Database::$instances[self::DB]->disconnect();
			unset(Database::$instances[self::DB]);
		}

		parent::tearDown();
	}

	protected function writer(): Config_Database_Writer
	{
		return new Config_Database_Writer(['instance' => self::DB, 'table_name' => 'settings']);
	}

	public function test_values_are_written_and_read(): void
	{
		$writer = $this->writer();

		$this->assertFalse($writer->load('site'), 'A group without rows is not there');

		$this->assertTrue($writer->write('site', 'name', 'My Site'));
		$this->assertTrue($writer->write('site', 'languages', ['en', 'pt']));
		$this->assertTrue($writer->write('site', 'open', false));
		$this->assertTrue($writer->write('other', 'name', 'Other'));

		$reader = new Config_Database_Reader(['instance' => self::DB, 'table_name' => 'settings']);

		$this->assertSame(['languages' => ['en', 'pt'], 'name' => 'My Site', 'open' => false], Arr::only($reader->load('site'), ['languages', 'name', 'open']));
		$this->assertSame(['name' => 'Other'], $reader->load('other'));
	}

	/**
	 * A key is changed, not added twice: when the writer has read the
	 * group, and when it has not.
	 */
	public function test_a_key_is_overwritten(): void
	{
		$this->writer()->write('site', 'name', 'First');

		// A writer that does not know the key is there
		$this->writer()->write('site', 'name', 'Second');

		$this->assertSame(['name' => 'Second'], $this->writer()->load('site'));

		// A writer that has read the group
		$writer = $this->writer();
		$writer->load('site');
		$writer->write('site', 'name', 'Third');

		$this->assertSame(['name' => 'Third'], $this->writer()->load('site'));
		$this->assertSame(1, $this->db->count_records('settings'));
	}

	/**
	 * The settings of the database cannot come from the database.
	 */
	public function test_database_group_is_never_read_from_the_table(): void
	{
		$this->writer()->write('database', 'default', ['type' => 'PDO']);

		$this->assertFalse($this->writer()->load('database'));
	}

	/**
	 * Whoever can write to the table cannot make the application create
	 * objects: what is read is data only.
	 */
	public function test_no_object_is_made_from_stored_text(): void
	{
		DB::insert('settings', ['group_name', 'config_key', 'config_value'])
			->values(['site', 'planted', serialize(new ArrayObject(['a' => 1]))])
			->values(['site', 'broken', 'not serialized at all'])
			->execute($this->db);

		$config = @$this->writer()->load('site');

		$this->assertNotInstanceOf(ArrayObject::class, $config['planted']);
		$this->assertFalse($config['broken']);
	}

	public function test_default_connection_and_table(): void
	{
		$reader = new Config_Database_Reader;

		$this->assertSame(Database::$default, (new ReflectionProperty($reader, '_db_instance'))->getValue($reader));
		$this->assertSame('config', (new ReflectionProperty($reader, '_table_name'))->getValue($reader));
	}

	/**
	 * Attached to Kohana::$config, the table is one more place settings
	 * come from.
	 */
	public function test_as_a_source_of_the_config(): void
	{
		$this->writer()->write('kohana_config_db_test', 'answer', 42);

		$config = new Config;
		$config->attach($this->writer());

		$this->assertSame(42, $config->load('kohana_config_db_test')->get('answer'));

		// Setting a value writes it to the table
		$config->load('kohana_config_db_test')->set('answer', 43);

		$this->assertSame(['answer' => 43], $this->writer()->load('kohana_config_db_test'));
	}
}
