<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests Num
 *
 * @package    Kohana
 * @category   Tests
 *
 * @author     BRMatt <matthew@sigswitch.com>
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.num')]
class Kohana_NumTest extends Unittest_TestCase
{
	protected $default_locale;

	/**
	 * SetUp test enviroment
	 */
	// @codingStandardsIgnoreStart
	public function setUp(): void
	// @codingStandardsIgnoreEnd
	{
		parent::setUp();

		$this->default_locale = setlocale(LC_ALL, 0);
		setlocale(LC_ALL, 'en_US.utf8');
	}

	/**
	 * Tear down environment
	 */
	// @codingStandardsIgnoreStart
	public function tearDown(): void
	// @codingStandardsIgnoreEnd
	{
		parent::tearDown();

		setlocale(LC_ALL, $this->default_locale);
	}

	/**
	 * Provides test data for test_bytes()
	 *
	 * @return array
	 */
	public static function provider_bytes()
	{
		return [
			[204800.0, '200K'],
			[5242880.0, '5MiB'],
			[1000.0, 1000],
			[2684354560.0, '2.5GB'],
		];
	}

	/**
	 * Tests Num::bytes()
	 *
	 * @covers Num::bytes
	 * @param integer Expected Value
	 * @param string  Input value
	 */
	#[DataProvider('provider_bytes')]
	public function test_bytes($expected, $size)
	{
		$this->assertSame($expected, Num::bytes($size));
	}

	/**
	 * Provides test data for test_ordinal()
	 * @return array
	 */
	public static function provider_ordinal()
	{
		return [
			[0, 'th'],
			[1, 'st'],
			[21, 'st'],
			[112, 'th'],
			[23, 'rd'],
			[42, 'nd'],
		];
	}

	/**
	 *
	 * @param integer $number
	 * @param <type> $expected
	 */
	#[DataProvider('provider_ordinal')]
	public function test_ordinal($number, $expected)
	{
		$this->assertSame($expected, Num::ordinal($number));
	}

	/**
	 * Provides test data for test_format()
	 * @return array
	 */
	public static function provider_format()
	{
		return [
			// English
			[10000, 2, false, '10,000.00'],
			[10000, 2, true, '10,000.00'],

			// Additional dp's should be removed
			[123.456, 2, false, '123.46'],
			[123.456, 2, true, '123.46'],
		];
	}

	/**
	 * @TODO test locales
	 * @param integer $number
	 * @param integer $places
	 * @param boolean $monetary
	 * @param string $expected
	 */
	#[DataProvider('provider_format')]
	public function test_format($number, $places, $monetary, $expected)
	{
		$this->assertSame($expected, Num::format($number, $places, $monetary));
	}

	/**
	 * Provides data for test_round()
	 * @return array
	 */
	public static function provider_round()
	{
		return [
			[5.5, 0, [
				6.0,
				5.0,
				6.0,
				5.0,
			]],
			[42.5, 0, [
				43.0,
				42.0,
				42.0,
				43.0,
			]],
			[10.4, 0, [
				10.0,
				10.0,
				10.0,
				10.0,
			]],
			[10.8, 0, [
				11.0,
				11.0,
				11.0,
				11.0,
			]],
			[-5.5, 0, [
				-6.0,
				-5.0,
				-6.0,
				-5.0,
			]],
			[-10.5, 0, [
				-11.0,
				-10.0,
				-10.0,
				-11.0,
			]],
			[26.12375, 4, [
				26.1238,
				26.1237,
				26.1238,
				26.1237,
			]],
			[26.12325, 4, [
				26.1233,
				26.1232,
				26.1232,
				26.1233,
			]],
		];
	}

	/**
	 * @param number $input
	 * @param integer $precision
	 * @param integer $mode
	 * @param number $expected
	 */
	#[Test]
	#[DataProvider('provider_round')]
	function test_round($input, $precision, $expected)
	{
		foreach ([Num::ROUND_HALF_UP, Num::ROUND_HALF_DOWN, Num::ROUND_HALF_EVEN, Num::ROUND_HALF_ODD] as $i => $mode)
		{
			$this->assertSame($expected[$i], Num::round($input, $precision, $mode, false));
		}
	}
}
