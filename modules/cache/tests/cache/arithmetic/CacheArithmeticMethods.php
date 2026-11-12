<?php

use PHPUnit\Framework\Attributes\DataProvider;

include_once(Kohana::find_file('tests/cache', 'CacheBasicMethods'));

/**
 * @package    Kohana/Cache/Memcache
 * @category   Test
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_CacheArithmeticMethodsTest extends Kohana_CacheBasicMethodsTest {

	public function tearDown(): void
	{
		parent::tearDown();

		// Cleanup
		$cache = $this->cache();

		if ($cache instanceof Cache)
		{
			$cache->delete_all();
		}
	}

	/**
	 * Provider for test_increment
	 *
	 * @return  array
	 */
	public static function provider_increment()
	{
		return [
			[
				0,
				[
					'id'    => 'increment_test_1',
					'step'  => 1
				],
				1
			],
			[
				1,
				[
					'id'    => 'increment_test_2',
					'step'  => 1
				],
				2
			],
			[
				5,
				[
					'id'    => 'increment_test_3',
					'step'  => 5
				],
				10
			],
			[
				null,
				[
					'id'    => 'increment_test_4',
					'step'  => 1
				],
				false
			],
		];
	}

	/**
	 * Test for [Cache_Arithmetic::increment()]
	 *
	 * @param   integer  start state
	 * @param   array    increment arguments
	 * @return  void
	 */
	#[DataProvider('provider_increment')]
	public function test_increment(
		$start_state,
		array $inc_args,
		$expected)
	{
		$cache = $this->cache();

		if ($start_state !== null)
		{
			$cache->set($inc_args['id'], (int)$start_state, 0);
		}

		$this->assertSame(
			$expected,
			$cache->increment(
				$inc_args['id'],
				$inc_args['step']
			)
		);
	}

	/**
	 * Provider for test_decrement
	 *
	 * @return  array
	 */
	public static function provider_decrement()
	{
		return [
			[
				10,
				[
					'id'    => 'decrement_test_1',
					'step'  => 1
				],
				9
			],
			[
				10,
				[
					'id'    => 'decrement_test_2',
					'step'  => 2
				],
				8
			],
			[
				50,
				[
					'id'    => 'decrement_test_3',
					'step'  => 5
				],
				45
			],
			[
				null,
				[
					'id'    => 'decrement_test_4',
					'step'  => 1
				],
				false
			],
		];	}

	/**
	 * Test for [Cache_Arithmetic::decrement()]
	 *
	 * @param   integer  start state
	 * @param   array    decrement arguments
	 * @return  void
	 */
	#[DataProvider('provider_decrement')]
	public function test_decrement(
		$start_state,
		array $dec_args,
		$expected)
	{
		$cache = $this->cache();

		if ($start_state !== null)
		{
			$cache->set($dec_args['id'], $start_state, 0);
		}

		$this->assertSame(
			$expected,
			$cache->decrement(
				$dec_args['id'],
				$dec_args['step']
			)
		);
	}

} // End Kohana_CacheArithmeticMethodsTest
