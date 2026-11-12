<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Kohana Exception Class
 *
 * @package    Kohana
 * @category   Tests
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.exception')]
class Kohana_ExceptionTest extends Unittest_TestCase
{
	/**
	 * Provides test data for test_constructor()
	 *
	 * @return array
	 */
	public static function provider_constructor()
	{
		return [
			[[''], '', 0],
			[[':a'], ':a', 0],

			[[':a', null], ':a', 0],
			[[':a', []], ':a', 0],
			[[':a', [':a' => 'b']], 'b', 0],
			[[':a :b', [':a' => 'c', ':b' => 'd']], 'c d', 0],

			[[':a', null, 5], ':a', 5],
			// #3358
			[[':a', null, '3F000'], ':a', '3F000'],
			// #3404
			[[':a', null, '42S22'], ':a', '42S22'],
			// #3927
			[[':a', null, 'b'], ':a', 'b'],
			// #4039
			[[':a', null, '25P01'], ':a', '25P01'],
		];
	}

	/**
	 * Tests Kohana_Kohana_Exception::__construct()
	 *
	 * @covers Kohana_Kohana_Exception::__construct
	 * @param array             $arguments          Arguments
	 * @param string            $expected_message   Value from getMessage()
	 * @param integer|string    $expected_code      Value from getCode()
	 */
	#[DataProvider('provider_constructor')]
	public function test_constructor($arguments, $expected_message, $expected_code)
	{
		switch (count($arguments))
		{
			case 1:
				$exception = new Kohana_Exception(reset($arguments));
			break;
			case 2:
				$exception = new Kohana_Exception(reset($arguments), next($arguments));
			break;
			default:
				$exception = new Kohana_Exception(reset($arguments), next($arguments), next($arguments));
		}

		$this->assertSame($expected_code, $exception->getCode());
		$this->assertSame($expected_message, $exception->getMessage());
	}

	/**
	 * Provides test data for test_text()
	 *
	 * @return array
	 */
	public static function provider_text()
	{
		return [
			[new Kohana_Exception('foobar'), Unittest_Helpers::dir_separator('Kohana_Exception [ 0 ]: foobar ~ SYSPATH/tests/kohana/ExceptionTest.php [ '.__LINE__.' ]')],
		];
	}

	/**
	 * Tests Kohana_Exception::text()
	 *
	 * @covers Kohana_Exception::text
	 * @param object $exception exception to test
	 * @param string $expected  expected output
	 */
	#[DataProvider('provider_text')]
	public function test_text($exception, $expected)
	{
		$this->assertEquals($expected, Kohana_Exception::text($exception));
	}
}
