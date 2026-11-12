<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;

/**
 * @package    Kohana/Cache
 * @category   Test
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_CacheBasicMethodsTest extends Unittest_TestCase {

	/**
	 * @var     Cache driver for this test
	 */
	protected $_cache_driver;

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
	}

	/**
	 * Accessor method to `$_cache_driver`.
	 *
	 * @return  Cache
	 * @return  self
	 */
	public function cache(?Cache $cache = null)
	{
		if ($cache === null)
			return $this->_cache_driver;

		$this->_cache_driver = $cache;
		return $this;
	}

	/**
	 * Data provider for test_set_get()
	 *
	 * @return  array
	 */
	public static function provider_set_get()
	{
		$object = new StdClass;
		$object->foo = 'foo';
		$object->bar = 'bar';

		$html_text = <<<TESTTEXT
<!doctype html>  
<head> 
</head> 

<body>
</body>
</html>
TESTTEXT;

		return [
			[
				[
					'id'      => 'string',    // Key to set to cache
					'value'   => 'foobar',    // Value to set to key
					'ttl'     => 0,           // Time to live
					'wait'    => false,       // Test wait time to let cache expire
					'type'    => 'string',    // Type test
					'tags'    => ['tag_str'],   // Tag for item
					'default' => null         // Default value get should return
				],
				'foobar'
			],
			[
				[
					'id'      => 'integer',
					'value'   => 101010,
					'ttl'     => 0,
					'wait'    => false,
					'type'    => 'int',
					'tags'    => ['tag_int'],
					'default' => null
				],
				101010
			],
			[
				[
					'id'      => 'float',
					'value'   => 10.00,
					'ttl'     => 0,
					'wait'    => false,
					'type'    => 'float',
					'tags'    => ['tag_float'],
					'default' => null
				],
				10.00
			],
			[
				[
					'id'      => 'array',
					'value'   => [
						'key'   => 'foo',
						'value' => 'bar'
					],
					'ttl'     => 0,
					'wait'    => false,
					'type'    => 'array',
					'tags'    => ['tag_arr'],
					'default' => null
				],
				[
					'key'   => 'foo',
					'value' => 'bar'
				]
			],
			[
				[
					'id'      => 'boolean',
					'value'   => true,
					'ttl'     => 0,
					'wait'    => false,
					'type'    => 'bool',
					'tags'    => ['tag_bool'],
					'default' => null
				],
				true
			],
			[
				[
					'id'      => 'null',
					'value'   => null,
					'ttl'     => 0,
					'wait'    => false,
					'type'    => 'null',
					'tags'    => ['tag_null'],
					'default' => null,
				],
				null
			],
			[
				[
					'id'      => 'object',
					'value'   => $object,
					'ttl'     => null,
					'wait'    => false,
					'type'    => 'object',
					'tags'    => ['tag_obj'],
					'default' => null
				],
				$object
			],
			[
				[
					'id'      => 'bar\\ with / troublesome key',
					'value'   => 'foo bar snafu',
					'ttl'     => 0,
					'wait'    => false,
					'type'    => 'string',
					'tags'    => ['tag_mult', 'tag_mult2'],
					'default' => null
				],
				'foo bar snafu'
			],
			[
				[
					'id'      => 'test ttl 0 means never expire',
					'value'   => 'cache value that should last',
					'ttl'     => 0,
					'wait'    => 1,
					'type'    => 'string',
					'tags'    => ['tag_ttl'],
					'default' => null
				],
				'cache value that should last'
			],
			[
				[
					'id'      => 'bar',
					'value'   => 'foo',
					'ttl'     => 3,
					'wait'    => 5,
					'type'    => 'null',
					'tags'    => ['tag_bar'],
					'default' => null
				],
				null
			],
			[
				[
					'id'      => 'snafu',
					'value'   => 'fubar',
					'ttl'     => 3,
					'wait'    => 5,
					'type'    => 'string',
					'tags'    => ['tag_snafu'],
					'default' => 'something completely different!'
				],
				'something completely different!'
			],
			[
				[
					'id'      => 'new line test with HTML',
					'value'   => $html_text,
					'ttl'     => 10,
					'wait'    => false,
					'type'    => 'string',
					'tags'    => ['tag_html', 'tag_code'],
					'default' => null,
				],
				$html_text
			],
			[
				[
					'id'      => 'test with 60*5',
					'value'   => 'blabla',
					'ttl'     => 60*5,
					'wait'    => false,
					'type'    => 'string',
					'tags'    => ['tag_ttl'],
					'default' => null,
				],
				'blabla'
			],
			[
				[
					'id'      => 'test with 60*50',
					'value'   => 'bla bla',
					'ttl'     => 60*50,
					'wait'    => false,
					'type'    => 'string',
					'tags'    => ['tag_ttl'],
					'default' => null,
				],
				'bla bla'
			]
		];
	}

	/**
	 * Tests the [Cache::set()] method, testing;
	 *
	 *  - The value is cached
	 *  - The lifetime is respected
	 *  - The returned value type is as expected
	 *  - The default not-found value is respected
	 *
	 * @param   array    data
	 * @param   mixed    expected
	 * @return  void
	 */
	#[DataProvider('provider_set_get')]
	public function test_set_get(array $data, $expected)
	{
		$cache = $this->cache();
		extract($data);

		$this->assertTrue($cache->set($id, $value, $ttl));

		if ($wait !== false)
		{
			// Lets let the cache expire
			sleep($wait);
		}

		$result = $cache->get($id, $default);
		$this->assertEquals($expected, $result);

		$assertIsType = 'assertIs'.ucfirst($type);
		$assertIsType = $assertIsType === 'assertIsNull' ? 'assertNull' : $assertIsType;
		$this->$assertIsType($result);

		unset($id, $value, $ttl, $wait, $type, $default);
	}

	/**
	 * Tests the [Cache::delete()] method, testing;
	 *
	 *  - The a cached value is deleted from cache
	 *  - The cache returns a true value upon deletion
	 *  - The cache returns a false value if no value exists to delete
	 *
	 * @param   array   $data
	 * @param   mixed   $expected
	 * @throws  Cache_Exception
	 * @return  void
	 */
	#[Depends('test_set_get')]
	#[DataProvider('provider_set_get')]
	public function test_delete(array $data, $expected)
	{
		// Init
		$cache = $this->cache();
		$cache->delete_all();
		extract($data);

		// Test deletion of real cached value
		$cache->set($id, $value, 3600);

		// Test delete returns true and check the value is gone
		$this->assertTrue($cache->delete($id));
		$this->assertNull($cache->get($id));

		// Test non-existent cache value returns false if no error
		$this->assertFalse($cache->delete($id));
	}

	/**
	 * Tests [Cache::delete_all()] works as specified
	 *
	 * @return  void
	 * @uses    Kohana_CacheBasicMethodsTest::provider_set_get()
	 */
	public function test_delete_all()
	{
		// Init
		$cache = $this->cache();
		$data = $this->provider_set_get();

		foreach ($data as $key => $values)
		{
			extract($values[0]);
			if ( ! $cache->set($id, $value))
			{
				$this->fail('Unable to set: '.$key.' => '.$value.' to cache');
			}
			unset($id, $value, $ttl, $wait, $type, $default);
		}

		// Test delete_all is successful
		$this->assertTrue($cache->delete_all());

		foreach ($data as $key => $values)
		{
			// Verify data has been purged
			$this->assertSame('Cache Deleted!', $cache->get($values[0]['id'],
				'Cache Deleted!'));
		}
	}

} // End Kohana_CacheBasicMethodsTest
