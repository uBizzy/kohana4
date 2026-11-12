<?php

use PHPUnit\Framework\Attributes\Group;

include_once(Kohana::find_file('tests/cache', 'CacheBasicMethods'));

/**
 * Tests the Memcached cache driver against a real server, named by the
 * environment variable KOHANA_TEST_MEMCACHED_HOST (and _PORT).
 *
 * @package    Kohana/Cache
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.cache')]
class Kohana_MemcachedTest extends Kohana_CacheBasicMethodsTest {

	public function setUp(): void
	{
		if ( ! extension_loaded('memcached'))
		{
			$this->markTestSkipped('Memcached PHP Extension is not available');
		}

		if ( ! getenv('KOHANA_TEST_MEMCACHED_HOST'))
		{
			$this->markTestSkipped('No Memcached server: set KOHANA_TEST_MEMCACHED_HOST');
		}

		Kohana::$config->load('cache')->set('memcached', [
			'driver'         => 'memcached',
			'default_expire' => 3600,
			'servers'        => [
				[
					'host' => getenv('KOHANA_TEST_MEMCACHED_HOST'),
					'port' => (int) (getenv('KOHANA_TEST_MEMCACHED_PORT') ?: 11211),
				],
			],
		]);

		parent::setUp();

		$this->cache(Cache::instance('memcached'));
	}

}
