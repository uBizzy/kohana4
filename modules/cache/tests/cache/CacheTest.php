<?php

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

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
// Partial mocks of an abstract class are used here as plain objects
#[AllowMockObjectsWithoutExpectations]
class Kohana_CacheTest extends Unittest_TestCase {

	const BAD_GROUP_DEFINITION  = 1010;
	const EXPECT_SELF           = 1001;

	/**
	 * Data provider for test_instance
	 *
	 * @return  array
	 */
	public static function provider_instance()
	{
		$base = [];

		if (Kohana::$config->load('cache.file'))
		{
			$base = [
				// Test default group
				[
					null,
					Cache::instance('file')
				],
				// Test defined group
				[
					'file',
					Cache::instance('file')
				],
			];
		}

		return $base + [[
			// Test bad group definition
			Kohana_CacheTest::BAD_GROUP_DEFINITION,
			'Failed to load Kohana Cache group: 1010'
		]];
	}

	/**
	 * Tests the [Cache::factory()] method behaves as expected
	 *
	 * @return  void
	 */
	#[DataProvider('provider_instance')]
	public function test_instance($group, $expected)
	{
		try
		{
			$cache = Cache::instance($group);
		}
		catch (Cache_Exception $e)
		{
			$this->assertSame($expected, $e->getMessage());
			return;
		}

		$this->assertInstanceOf(get_class($expected), $cache);
		$this->assertSame($expected->config(), $cache->config());
	}

	/**
	 * Tests that `clone($cache)` will be prevented to maintain singleton
	 */
	public function test_cloning_fails()
	{
		$this->expectException(Cache_Exception::class);
		$cache = $this->getMockForAbstractClass('Cache', [], false);

		try
		{
			clone($cache);
		}
		catch (Cache_Exception $e)
		{
			$this->assertSame('Cloning of Kohana_Cache objects is forbidden',
				$e->getMessage());
			throw $e;
		}
	}

	/**
	 * Data provider for test_config
	 *
	 * @return  array
	 */
	public static function provider_config()
	{
		return [
			[
				[
					'server'     => 'otherhost',
					'port'       => 5555,
					'persistent' => true,
				],
				null,
				Kohana_CacheTest::EXPECT_SELF,
				[
					'server'     => 'otherhost',
					'port'       => 5555,
					'persistent' => true,
				],
			],
			[
				'foo',
				'bar',
				Kohana_CacheTest::EXPECT_SELF,
				[
					'foo'        => 'bar'
				]
			],
			[
				'server',
				null,
				null,
				[]
			],
			[
				null,
				null,
				[],
				[]
			]
		];
	}

	/**
	 * Tests the config method behaviour
	 *
	 * @param   mixed    key value to set or get
	 * @param   mixed    value to set to key
	 * @param   mixed    expected result from [Cache::config()]
	 * @param   array    expected config within cache
	 * @return  void
	 */
	#[DataProvider('provider_config')]
	public function test_config($key, $value, $expected_result, array $expected_config)
	{
		$cache = $this->createStub('Cache_File');

		$cache_reflection = new ReflectionClass('Cache_File');
		$config = $cache_reflection->getMethod('config');

		if ($expected_result === Kohana_CacheTest::EXPECT_SELF)
		{
			$expected_result = $cache;
		}

		$this->assertSame($expected_result, $config->invoke($cache, $key, $value));
		$this->assertSame($expected_config, $config->invoke($cache));
	}

	/**
	 * Data provider for test_sanitize_id
	 *
	 * @return  array
	 */
	public static function provider_sanitize_id()
	{
		return [
			[
				'foo',
				// SHA-256 of 'foo'
				'2c26b46b68ffc68ff99b453c1d30413413422d706483bfa0f98a5e886266e7ae'
			],
		];
	}

	/**
	 * Tests the [Cache::_sanitize_id()] method works as expected.
	 * This uses some nasty reflection techniques to access a protected
	 * method.
	 *
	 * @param   string    id
	 * @param   string    expected
	 * @return  void
	 */
	#[DataProvider('provider_sanitize_id')]
	public function test_sanitize_id($id, $expected)
	{
		$cache = $this->createStub('Cache');

		$cache_reflection = new ReflectionClass('Cache');
		$sanitize_id = $cache_reflection->getMethod('_sanitize_id');
		$sanitize_id->setAccessible(true);

		// Get Prefix if set
		if ( ! $prefix = Kohana::$config->load('cache')->get('prefix', false)) {
			$prefix = '';
		}

		$this->assertSame($prefix.$expected, $sanitize_id->invoke($cache, $id));
	}
} // End Kohana_CacheTest
