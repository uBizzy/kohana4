<?php

use PHPUnit\Framework\Attributes\Group;

include_once(Kohana::find_file('tests/cache', 'CacheBasicMethods'));

/**
 * @package    Kohana/Cache
 * @category   Test
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.cache')]
class Kohana_SqliteTest extends Kohana_CacheBasicMethodsTest {

	/**
	 * This method MUST be implemented by each driver to setup the `Cache`
	 * instance for each test.
	 *
	 * This method should do the following tasks for each driver test:
	 *
	 *  - Test the Cache instance driver is available, skip test otherwise
	 *  - Setup the Cache instance
	 *  - Call the parent setup method, `parent::setUp()`
	 *
	 * @return  void
	 */
	public function setUp(): void
	{
		parent::setUp();

		if ( ! extension_loaded('pdo_sqlite'))
		{
			$this->markTestSkipped('SQLite PDO PHP Extension is not available');
		}

		if ( ! Kohana::$config->load('cache.sqlite'))
		{
			Kohana::$config->load('cache')
				->set(
					'sqlite',
					[
						'driver'             => 'sqlite',
						'default_expire'     => 3600,
						'database'           => ':memory:',
						'schema'             => 'CREATE TABLE caches(id VARCHAR(127) PRIMARY KEY, tags VARCHAR(255), expiration INTEGER, cache TEXT)',
					]
				);
		}

		$this->cache(Cache::instance('sqlite'));
	}

	/**
	 * A row without content was not written by this class: find() passes
	 * over it instead of failing.
	 */
	public function test_find_passes_over_rows_without_content(): void
	{
		$cache = $this->cache();
		$cache->delete_all();
		$cache->set_with_tags('good', 'value', 60, ['odd']);

		$db = (new ReflectionProperty('Cache_Sqlite', '_db'))->getValue($cache);
		$db->exec("INSERT INTO caches (id, tags, expiration, cache) VALUES ('nothing', '<odd>', ".(time() + 60).", NULL)");

		// The keys are the ids as they are stored
		$this->assertSame(['value'], array_values($cache->find('odd')));

		$cache->delete_all();
	}

	/**
	 * Entries are found and deleted by tag. "_" and "%" in the name of
	 * a tag are part of the name, not "any character".
	 */
	public function test_tags(): void
	{
		$cache = $this->cache();
		$cache->delete_all();

		$cache->set_with_tags('one', 'first', 60, ['user_1', 'news']);
		$cache->set_with_tags('two', 'second', 60, ['userA1']);
		$cache->set_with_tags('three', 'third', 60, ['user%']);
		$cache->set_with_tags('four', 'fourth', 60);

		$this->assertSame(['first'], array_values($cache->find('user_1')));
		$this->assertSame(['first'], array_values($cache->find('news')));
		$this->assertSame(['third'], array_values($cache->find('user%')));
		$this->assertSame([], $cache->find('nothing'));

		$this->assertTrue($cache->delete_tag('user_1'));
		$this->assertFalse($cache->delete_tag('user_1'));

		$this->assertNull($cache->get('one'));
		$this->assertSame('second', $cache->get('two'), 'A tag that only looks alike is left alone');
		$this->assertSame('third', $cache->get('three'));
		$this->assertSame('fourth', $cache->get('four'));

		$this->assertTrue($cache->delete_tag('user%'));
		$this->assertSame('second', $cache->get('two'));

		$cache->delete_all();
	}

	public function test_garbage_collect_removes_what_expired(): void
	{
		$cache = $this->cache();
		$cache->delete_all();

		$cache->set('keep', 'value', 60);
		$cache->set('old', 'value', 60);

		// Make the second one expire, as time would
		$db = (new ReflectionProperty('Cache_Sqlite', '_db'))->getValue($cache);
		$db->exec('UPDATE caches SET expiration = '.(time() - 10)." WHERE id <> (SELECT id FROM caches ORDER BY rowid LIMIT 1)");

		$cache->garbage_collect();

		$this->assertSame(1, (int) $db->query('SELECT COUNT(*) FROM caches')->fetchColumn());
		$this->assertSame('value', $cache->get('keep'));
		$this->assertNull($cache->get('old'));

		$cache->delete_all();
	}

	/**
	 * A cache without its table says what is wrong, for every operation.
	 */
	public function test_broken_database_is_reported(): void
	{
		$class = new ReflectionClass('Cache_Sqlite');
		$cache = $class->newInstanceWithoutConstructor();

		$class->getProperty('_config')->setValue($cache, ['default_expire' => 60]);
		$class->getProperty('_db')->setValue($cache, new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]));

		$calls = [
			fn () => $cache->get('a'),
			fn () => $cache->set('a', 1),
			fn () => $cache->delete('a'),
			fn () => $cache->delete_all(),
			fn () => $cache->delete_tag('a'),
			fn () => $cache->find('a'),
			fn () => $cache->garbage_collect(),
		];

		foreach ($calls as $number => $call)
		{
			try
			{
				$call();
				$this->fail('Call '.$number.' must fail without the table');
			}
			catch (Cache_Exception | PDOException $e)
			{
				$this->assertStringContainsString('caches', $e->getMessage());
			}
		}
	}

	public function test_settings_are_needed(): void
	{
		$make = function (array $config): void
		{
			$class = new ReflectionClass('Cache_Sqlite');
			$cache = $class->newInstanceWithoutConstructor();

			$class->getConstructor()->invoke($cache, $config);
		};

		try
		{
			$make(['driver' => 'sqlite']);
			$this->fail('A cache without a database must fail');
		}
		catch (Cache_Exception $e)
		{
			$this->assertStringContainsString('Database path not available', $e->getMessage());
		}

		try
		{
			$make(['driver' => 'sqlite', 'database' => ':memory:']);
			$this->fail('A cache without a schema must fail');
		}
		catch (Cache_Exception $e)
		{
			$this->assertStringContainsString('Database schema not found', $e->getMessage());
		}

		$this->expectException(Cache_Exception::class);
		$this->expectExceptionMessage('Failed to create new SQLite caches table');

		$make(['driver' => 'sqlite', 'database' => ':memory:', 'schema' => 'CREATE NONSENSE']);
	}

} // End Kohana_SqliteTest
