<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;

/**
 * @package    Kohana/Cache
 * @category   Test
 *
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_RedisTest extends Kohana_CacheBasicMethodsTest {

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
	 * @throws Cache_Exception
	 * @throws Kohana_Exception
	 */
	public function setUp(): void
	{
		// Check if Redis extension is loaded
		if ( ! extension_loaded('redis'))
		{
			$this->markTestSkipped('Redis PHP Extension is not available');
		}

		// A test server is named by KOHANA_TEST_REDIS_HOST (and _PORT,
		// _PASSWORD); without it, a "redis" group of config/cache.php is used
		if (getenv('KOHANA_TEST_REDIS_HOST'))
		{
			$server = [
				'host'       => getenv('KOHANA_TEST_REDIS_HOST'),
				'port'       => (int) (getenv('KOHANA_TEST_REDIS_PORT') ?: 6379),
				'persistent' => false,
				'prefix'     => 'prefix',
			];

			if (getenv('KOHANA_TEST_REDIS_PASSWORD'))
			{
				$server['password'] = getenv('KOHANA_TEST_REDIS_PASSWORD');
			}

			Kohana::$config->load('cache')->set('redis', [
				'driver'         => 'redis',
				'default_expire' => 3600,
				'cache_prefix'   => 'redis1_',
				'tag_prefix'     => 'tag_',
				'servers'        => ['local' => $server],
			]);
		}
		elseif ( ! ($config = Kohana::$config->load('cache')->get('redis', false)) OR empty($config['servers']))
		{
			$this->markTestSkipped('No Redis server: set KOHANA_TEST_REDIS_HOST or configure "redis" in config/cache.php');
		}

		parent::setUp();

		$this->cache(Cache::instance('redis'));
	}

	/**
	 * Test without proper configuration
	 * @throws Cache_Exception
	 */
	public function test_invalid_configuration()
	{
		$this->expectException(Cache_Exception::class);
		new Cache_Redis([]);
	}

	/**
	 * Always returns true
	 */
	public function test_delete_all()
	{
		$this->assertTrue($this->cache()->delete_all());
	}

	/**
	 * Just a proxy for set_get to allow other tests to depend on this one
	 *
	 * @param array $data
	 * @param mixed $expected
	 */
	#[DataProvider('provider_set_get')]
	public function test_set_get(array $data, $expected)
	{
		parent::test_set_get($data, $expected);
	}

	/**
	 * Test setting and getting values by tag
	 *
	 * @param array $data
	 * @param mixed $expected
	 *
	 * @throws Kohana_Exception
	 */
	#[Depends('test_set_get')]
	#[Depends('test_delete_all')]
	#[DataProvider('provider_set_get')]
	public function test_set_get_with_tags(array $data, $expected)
	{
		// Init
		$cache = $this->cache();
		$cache->delete_all();
		extract($data);

		// Insert Value with tags
		$this->assertTrue($cache->set_with_tags($id, $value, $ttl, $tags));

		// Let the cache expire
		if ($wait !== false)
		{
			sleep($wait);
		}

		// Only find by one tag
		if (is_array($tags)) {
			$tags = $tags[0];
		}

		// Get Prefix if set
		if ( ! $prefix = Kohana::$config->load('cache')->get('prefix', false)) {
			$prefix = '';
		}

		// An expired entry is not found: the provider gives a default for it
		$expect = $cache->find($tags)[$prefix.hash('sha256', $id)] ?? $default;
		$this->assertEquals($expected, $expect);
	}

	/**
	 * Test Redis::delete_tag
	 *
	 * @param array $data
	 * @param mixed $expected
	 */
	#[Depends('test_set_get_with_tags')]
	#[DataProvider('provider_set_get')]
	public function test_delete_tag(array $data, $expected)
	{
		// Init
		$cache = $this->cache();
		$cache->delete_all();
		extract($data);

		// Set value to delete later
		$cache->set_with_tags($id, $value, $ttl, $tags);

		// Get/Delete only by one tag
		if (is_array($tags)) {
			$tags = $tags[0];
		}

		// Test delete and try to get afterwards
		$this->assertTrue($cache->delete_tag($tags));
		$this->assertFalse($cache->delete_tag($tags));
		// Nothing is found by the tag any more
		$this->assertSame([], $cache->find($tags));
	}

	/**
	 * Test set and get items with arrays
	 */
	public function test_set_get_array()
	{
		// Initialize
		$cache = $this->cache();
		$providers = $this->provider_set_get();
		$ids = $values = $expected = [];
		$longestWait = 0;

		// Loop through all providers to build array for setting / getting
		foreach ($providers as $provider) {
			$id = $provider[0]['id'];
			$value =  $provider[0]['value'];
			$ids[] = $id;
			$values[] = $value;
			$expected[$id] = $value;
			$longestWait = $provider[0]['wait'] > $longestWait ? $provider[0]['wait'] : $longestWait;
		}

		// Check setting multiple ids
		$this->assertTrue($cache->set($ids, $values));

		// Wait to test lifetime
		sleep($longestWait);

		// Get items with multiple ids
		$items = $cache->get($ids);
		$this->assertNotNull($items);

		// Check values
		foreach ($items as $id => $value) {
			$this->assertEquals($expected[$id], $value);
		}
	}

} // End Kohana_RedisTest
