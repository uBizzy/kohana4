<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 *
 * @package    Kohana/Userguide
 * 
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.userguide')]
class Kohana_KodocTest extends Unittest_TestCase
{
	public static function provider_parse_basic()
	{
		return [
			[
<<<'COMMENT'
/**
 * Description
 */
COMMENT
,
				["<p>Description</p>", []],
			],
			[
<<<'COMMENT'
/**
 * Description spanning
 * multiple lines
 */
COMMENT
,
				["<p>Description spanning\nmultiple lines</p>", []],
			],
			[
<<<'COMMENT'
/**
 * Description including
 *
 *     a code block
 */
COMMENT
,
				["<p>Description including</p>\n<pre><code>a code block</code></pre>", []],
			],
			[
<<<'COMMENT'
	/**
	 * Indented
	 */
COMMENT
,
				["<p>Indented</p>", []],
			],
			[
<<<'COMMENT'
/**
 * @tag Content
 */
COMMENT
,
				['', ['tag' => ['Content']]],
			],
			[
<<<'COMMENT'
/**
 * @tag Multiple
 * @tag Tags
 */
COMMENT
,
				['', ['tag' => ['Multiple', 'Tags']]],
			],
			[
<<<'COMMENT'
/**
 * Description with tag
 * @tag Content
 */
COMMENT
,
				[
					"<p>Description with tag</p>",
					['tag' => ['Content']],
				],
			],
			[
<<<'COMMENT'
/**
 * @trailingspace
 */
COMMENT
,
				['', ['trailingspace' => ['']]],
			],
			[
<<<'COMMENT'
/**
 * @tag Content that spans
 * multiple lines
 */
COMMENT
,
				[
					'',
					['tag' => ["Content that spans\nmultiple lines"]],
				],
			],
			[
<<<'COMMENT'
/**
 * @tag Content that spans
 *    multiple lines indented
 */
COMMENT
,
				[
					'',
					['tag' => ["Content that spans\n   multiple lines indented"]],
				],
			],
		];
	}

	/**
	 * @covers  Kohana_Kodoc::parse
	 *
	 * @param   string  $comment    Argument to the method
	 * @param   array   $expected   Expected result
	 */
	#[DataProvider('provider_parse_basic')]
	public function test_parse_basic($comment, $expected)
	{
		$this->assertSame($expected, Kodoc::parse($comment));
	}

	public static function provider_parse_tags()
	{
		$route_api = Route::get('docs/api');

		return [
			[
<<<'COMMENT'
/**
 * @access public
 */
COMMENT
,
				['', []],
			],
			[
<<<'COMMENT'
/**
 * @copyright Some plain text
 */
COMMENT
,
				['', ['copyright' => ['Some plain text']]],
			],
			[
<<<'COMMENT'
/**
 * @copyright (c) 2008-2017 Kohana Team
 */
COMMENT
,
				['', ['copyright' => ['&copy; 2008-2017 Kohana Team']]],
			],
			[
<<<'COMMENT'
/**
 * @license Kohana
 */
COMMENT
,
				['', ['license' => ['Kohana']]],
			],
			[
<<<'COMMENT'
/**
 * @license http://example.com/license
 */
COMMENT
,
				['', ['license' => ['<a href="http://example.com/license">http://example.com/license</a>']]],
			],
			[
<<<'COMMENT'
/**
 * @link http://koseven.dev
 */
COMMENT
,
				['', ['link' => ['<a href="http://koseven.dev">http://koseven.dev</a>']]],
			],
			[
<<<'COMMENT'
/**
 * @link http://koseven.dev Description
 */
COMMENT
,
				['', ['link' => ['<a href="http://koseven.dev">Description</a>']]],
			],
			[
<<<'COMMENT'
/**
 * @see MyClass
 */
COMMENT
,
				[
					'',
					[
						'see' => [
							'<a href="'.URL::site(
								$route_api->uri(['class' => 'MyClass'])
							).'">MyClass</a>',
						],
					],
				],
			],
			[
<<<'COMMENT'
/**
 * @see MyClass::method()
 */
COMMENT
,
				[
					'',
					[
						'see' => [
							'<a href="'.URL::site(
								$route_api->uri(['class' => 'MyClass']).'#method'
							).'">MyClass::method()</a>',
						],
					],
				],
			],
			[
<<<'COMMENT'
/**
 * @throws Exception
 */
COMMENT
,
				[
					'',
					[
						'throws' => [
							'<a href="'.URL::site(
								$route_api->uri(['class' => 'Exception'])
							).'">Exception</a>',
						],
					],
				],
			],
			[
<<<'COMMENT'
/**
 * @throws Exception During failure
 */
COMMENT
,
				[
					'',
					[
						'throws' => [
							'<a href="'.URL::site(
								$route_api->uri(['class' => 'Exception'])
							).'">Exception</a> During failure',
						],
					],
				],
			],
			[
<<<'COMMENT'
/**
 * @uses MyClass
 */
COMMENT
,
				[
					'',
					[
						'uses' => [
							'<a href="'.URL::site(
								$route_api->uri(['class' => 'MyClass'])
							).'">MyClass</a>',
						],
					],
				],
			],
			[
<<<'COMMENT'
/**
 * @uses MyClass::method()
 */
COMMENT
,
				[
					'',
					[
						'uses' => [
							'<a href="'.URL::site(
								$route_api->uri(['class' => 'MyClass']).'#method'
							).'">MyClass::method()</a>',
						],
					],
				],
			],
		];
	}

	/**
	 * @covers  Kohana_Kodoc::format_tag
	 * @covers  Kohana_Kodoc::parse
	 *
	 * @param   string  $comment    Argument to the method
	 * @param   array   $expected   Expected result
	 */
	#[DataProvider('provider_parse_tags')]
	public function test_parse_tags($comment, $expected)
	{
		$this->assertSame($expected, Kodoc::parse($comment));
	}

	/**
	 * Provides test data for test_transparent_classes
	 * @return array
	 */
	public static function provider_transparent_classes()
	{
		return [
			// Kohana_Core is a special case
			['Kohana','Kohana_Core',null],
			['Controller_Template','Kohana_Controller_Template',null],
			['Controller_Template','Kohana_Controller_Template',
				['Kohana_Controller_Template'=>'Kohana_Controller_Template',
					'Controller_Template'=>'Controller_Template']
			],
			[false,'Kohana_Controller_Template',
				['Kohana_Controller_Template'=>'Kohana_Controller_Template']],
			[false,'Controller_Template',null],
		];
	}

	public function test_a_type_with_a_space_inside_its_brackets_is_one_type(): void
	{
		// @param array<int|string, mixed> $array array to extract paths from
		$method = new Kodoc_Method('Kohana_Arr', 'extract');

		$this->assertSame('Array to extract paths from', $method->params[0]->description);
		$this->assertSame('List of path', $method->params[1]->description);
		$this->assertSame('Default value', $method->params[2]->description);
		$this->assertStringNotContainsString('&gt;', (string) $method->params[0]->description);

		// @var array<string, string> ...: the type is kept whole
		$property = new Kodoc_Property('Kohana_Upload', 'forbidden_extensions');

		$this->assertStringStartsWith('<p>File extensions', (string) $property->description);
	}

	/**
	 * Tests Kodoc::is_transparent
	 *
	 * Checks that a selection of transparent and non-transparent classes give expected results
	 *
	 * @param mixed $expected
	 * @param string $class
	 * @param array $classes
	 */
	#[Group('kohana.userguide.3529-configurable-transparent-classes')]
	#[DataProvider('provider_transparent_classes')]
	public function test_transparent_classes($expected, $class, $classes)
	{
		$result = Kodoc::is_transparent($class, $classes);
		$this->assertSame($expected,$result);
	}
}
