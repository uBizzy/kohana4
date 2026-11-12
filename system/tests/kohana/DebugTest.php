<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Kohana Core
 *
 * @TODO Use a virtual filesystem (see phpunit doc on mocking fs) for find_file etc.
 *
 * @package    Kohana
 * @category   Tests
 *
 * @author     Jeremy Bush <contractfrombelow@gmail.com>
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.debug')]
class Kohana_DebugTest extends Unittest_TestCase
{

	/**
	 * Provides test data for test_debug()
	 *
	 * @return array
	 */
	public static function provider_vars()
	{
		return [
			// $thing, $expected
			[['foobar'], "<pre class=\"debug\"><small>array</small><span>(1)</span> <span>(\n    0 => <small>string</small><span>(6)</span> \"foobar\"\n)</span></pre>"],
		];
	}

	/**
	 * Tests Debug::vars()
	 *
	 * @covers Debug::vars
	 * @param boolean $thing    The thing to debug
	 * @param boolean $expected Output for Debug::vars
	 */
	#[DataProvider('provider_vars')]
	public function test_var($thing, $expected)
	{
		$this->assertEquals($expected, Debug::vars($thing));
	}

	/**
	 * Provides test data for testDebugPath()
	 *
	 * @return array
	 */
	public static function provider_debug_path()
	{
		return [
			[
				SYSPATH.'classes'.DIRECTORY_SEPARATOR.'kohana'.EXT,
				'SYSPATH'.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'kohana.php'
			],
			[
				MODPATH.Unittest_Helpers::dir_separator('unittest/classes/kohana/unittest/runner').EXT,
				Unittest_Helpers::dir_separator('MODPATH/unittest/classes/kohana/unittest/runner').EXT
			],
		];
	}

	/**
	 * Tests Debug::path()
	 *
	 * @covers Debug::path
	 * @param boolean $path     Input for Debug::path
	 * @param boolean $expected Output for Debug::path
	 */
	#[DataProvider('provider_debug_path')]
	public function test_debug_path($path, $expected)
	{
		$this->assertEquals($expected, Debug::path($path));
	}

	/**
	 * Provides test data for test_dump()
	 *
	 * @return array
	 */
	public static function provider_dump()
	{
		return [
			['foobar', 128, 10, '<small>string</small><span>(6)</span> "foobar"'],
			['foobar', 2, 10, '<small>string</small><span>(6)</span> "fo&nbsp;&hellip;"'],
			[null, 128, 10, '<small>NULL</small>'],
			[true, 128, 10, '<small>bool</small> TRUE'],
			[['foobar'], 128, 10, "<small>array</small><span>(1)</span> <span>(\n    0 => <small>string</small><span>(6)</span> \"foobar\"\n)</span>"],
			[new StdClass, 128, 10, "<small>object</small> <span>stdClass(0)</span> <code>{\n}</code>"],
			["fo\x6F\xFF\x00bar\x8F\xC2\xB110", 128, 10, '<small>string</small><span>(10)</span> "foobar±10"'],
			[['level1' => ['level2' => ['level3' => ['level4' => ['value' => 'something']]]]], 128, 4,
'<small>array</small><span>(1)</span> <span>(
    "level1" => <small>array</small><span>(1)</span> <span>(
        "level2" => <small>array</small><span>(1)</span> <span>(
            "level3" => <small>array</small><span>(1)</span> <span>(
                "level4" => <small>array</small><span>(1)</span> (
                    ...
                )
            )</span>
        )</span>
    )</span>
)</span>'],
		];
	}

	/**
	 * Tests Debug::dump()
	 *
	 * @covers Debug::dump
	 * @covers Debug::_dump
	 * @param object $exception exception to test
	 * @param string $expected  expected output
	 */
	#[DataProvider('provider_dump')]
	public function test_dump($input, $length, $limit, $expected)
	{
		// Replace New Line Seperator to pass test on windows systems
		$expected = str_replace("\r\n","\n", $expected);
		$this->assertEquals($expected, Debug::dump($input, $length, $limit));
	}

	/**
	 * An object can have numbers as property names (a list turned into an
	 * object, or the answer of __debugInfo()): the dump must show them and
	 * not fail, or the error page fails while it shows an error.
	 */
	public function test_dump_object_with_numbered_properties(): void
	{
		$dump = Debug::dump((object) ['first', 'second']);

		$this->assertStringContainsString('<small>public</small> 0 => ', $dump);
		$this->assertStringContainsString('"second"', $dump);

		$object = new class {
			/**
			 * @return  array<int, string>
			 */
			public function __debugInfo(): array
			{
				return ['only this'];
			}
		};

		$this->assertStringContainsString('"only this"', Debug::dump($object));
	}
}
