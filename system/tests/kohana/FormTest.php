<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests Kohana Form helper
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
#[Group('kohana.core.form')]
class Kohana_FormTest extends Unittest_TestCase {

	/**
	 * Defaults for this test
	 * @var array
	 */
	// @codingStandardsIgnoreStart
	protected array $environmentDefault = [
		'Kohana::$base_url' => '/',
		'HTTP_HOST' => 'koseven.dev',
		'Kohana::$index_file' => '',
	];
	// @codingStandardsIgnoreEnd

	/**
	 * Provides test data for test_open()
	 *
	 * @return array
	 */
	public static function provider_open()
	{
		return [
			[
				  ['', null],
				  ['action' => '']
			],
			[
				  [null, null],
				  ['action' => '']
			],
			[
				  ['foo', null],
				  ['action' => '/foo']
			],
			[
				  ['foo', ['method' => 'get']],
				  ['action' => '/foo', 'method' => 'get']
			],
			[
				['//www.example.com/', null],
				['action' => '//www.example.com/']
			],
		];
	}

	/**
	 * Tests Form::open()
	 *
	 * @param boolean $input  Input for Form::open
	 * @param boolean $expected Output for Form::open
	 */
	#[DataProvider('provider_open')]
	public function test_open($input, $expected)
	{
		list($action, $attributes) = $input;

		$tag = Form::open($action, $attributes);

		$matcher = [
			'tag' => 'form',
			// Default attributes
			'attributes' => [
				'method' => 'post',
				'accept-charset' => 'utf-8',
			],
		];

		$matcher['attributes'] = $expected + $matcher['attributes'];

		$this->assertTag($matcher, $tag);
	}

	/**
	 * Tests Form::close()
	 */
	public function test_close()
	{
		$this->assertSame('</form>', Form::close());
	}

	/**
	 * Provides test data for test_input()
	 *
	 * @return array
	 */
	public static function provider_input()
	{
		return [
			// $value, $result
			['input',    'foo', 'bar', null],
			['input',    'foo',  null, null],
			['hidden',   'foo', 'bar', null],
			['password', 'foo', 'bar', null],
		];
	}

	/**
	 * Tests Form::input()
	 *
	 * @param boolean $input  Input for Form::input
	 * @param boolean $expected Output for Form::input
	 */
	#[DataProvider('provider_input')]
	public function test_input($type, $name, $value, $attributes)
	{
		$matcher = [
			'tag' => 'input',
			'attributes' => ['name' => $name, 'type' => $type]
		];

		// Form::input creates a text input
		if ($type === 'input')
		{
			$matcher['attributes']['type'] = 'text';
		}

		// null just means no value
		if ($value !== null)
		{
			$matcher['attributes']['value'] = $value;
		}

		// Add on any attributes
		if (is_array($attributes))
		{
			$matcher['attributes'] = $attributes + $matcher['attributes'];
		}

		$tag = Form::$type($name, $value, $attributes);

		$this->assertTag($matcher, $tag, $tag);
	}

	/**
	 * Provides test data for test_file()
	 *
	 * @return array
	 */
	public static function provider_file()
	{
		return [
			// $value, $result
			['foo', null, '<input type="file" name="foo" />'],
		];
	}

	/**
	 * Tests Form::file()
	 *
	 * @param boolean $input  Input for Form::file
	 * @param boolean $expected Output for Form::file
	 */
	#[DataProvider('provider_file')]
	public function test_file($name, $attributes, $expected)
	{
		$this->assertSame($expected, Form::file($name, $attributes));
	}

	/**
	 * Provides test data for test_check()
	 *
	 * @return array
	 */
	public static function provider_check()
	{
		return [
			// $value, $result
			['checkbox', 'foo', null, false, null],
			['checkbox', 'foo', null, true, null],
			['checkbox', 'foo', 'bar', true, null],

			['radio', 'foo', null, false, null],
			['radio', 'foo', null, true, null],
			['radio', 'foo', 'bar', true, null],
		];
	}

	/**
	 * Tests Form::check()
	 *
	 * @param boolean $input  Input for Form::check
	 * @param boolean $expected Output for Form::check
	 */
	#[DataProvider('provider_check')]
	public function test_check($type, $name, $value, $checked, $attributes)
	{
		$matcher = ['tag' => 'input', 'attributes' => ['name' => $name, 'type' => $type]];

		if ($value !== null)
		{
			$matcher['attributes']['value'] = $value;
		}

		if (is_array($attributes))
		{
			$matcher['attributes'] = $attributes + $matcher['attributes'];
		}

		if ($checked === true)
		{
			$matcher['attributes']['checked'] = 'checked';
		}

		$tag = Form::$type($name, $value, $checked, $attributes);
		$this->assertTag($matcher, $tag, $tag);
	}

	/**
	 * Provides test data for test_text()
	 *
	 * @return array
	 */
	public static function provider_text()
	{
		return [
			// $value, $result
			['textarea', 'foo', 'bar', null],
			['textarea', 'foo', 'bar', ['rows' => 20, 'cols' => 20]],
			['button', 'foo', 'bar', null],
			['label', 'foo', 'bar', null],
			['label', 'foo', null, null],
		];
	}

	/**
	 * Tests Form::textarea()
	 *
	 * @param boolean $input  Input for Form::textarea
	 * @param boolean $expected Output for Form::textarea
	 */
	#[DataProvider('provider_text')]
	public function test_text($type, $name, $body, $attributes)
	{
		$matcher = [
			'tag' => $type,
			'attributes' => [],
			'content' => $body,
		];

		if ($type !== 'label')
		{
			$matcher['attributes'] = ['name' => $name];
		}
		else
		{
			$matcher['attributes'] = ['for' => $name];
		}


		if (is_array($attributes))
		{
			$matcher['attributes'] = $attributes + $matcher['attributes'];
		}

		$tag = Form::$type($name, $body, $attributes);

		$this->assertTag($matcher, $tag, $tag);
	}


	/**
	 * Provides test data for test_select()
	 *
	 * @return array
	 */
	public static function provider_select()
	{
		return [
			// $value, $result
			['foo', null, null, "<select name=\"foo\"></select>"],
			['foo', ['bar' => 'bar'], null, "<select name=\"foo\">\n<option value=\"bar\">bar</option>\n</select>"],
			['foo', ['bar' => 'bar'], 'bar', "<select name=\"foo\">\n<option value=\"bar\" selected=\"selected\">bar</option>\n</select>"],
			['foo', ['bar' => ['foo' => 'bar']], null, "<select name=\"foo\">\n<optgroup label=\"bar\">\n<option value=\"foo\">bar</option>\n</optgroup>\n</select>"],
			['foo', ['bar' => ['foo' => 'bar']], 'foo', "<select name=\"foo\">\n<optgroup label=\"bar\">\n<option value=\"foo\" selected=\"selected\">bar</option>\n</optgroup>\n</select>"],
			// #2286
			['foo', ['bar' => 'bar', 'unit' => 'test', 'foo' => 'foo'], ['bar', 'foo'], "<select name=\"foo\" multiple=\"multiple\">\n<option value=\"bar\" selected=\"selected\">bar</option>\n<option value=\"unit\">test</option>\n<option value=\"foo\" selected=\"selected\">foo</option>\n</select>"],
		];
	}

	/**
	 * Tests Form::select()
	 *
	 * @param boolean $input  Input for Form::select
	 * @param boolean $expected Output for Form::select
	 */
	#[DataProvider('provider_select')]
	public function test_select($name, $options, $selected, $expected)
	{
		// Much more efficient just to assertSame() rather than assertTag() on each element
		$this->assertSame($expected, Form::select($name, $options, $selected));
	}

	/**
	 * Provides test data for test_submit()
	 *
	 * @return array
	 */
	public static function provider_submit()
	{
		return [
			// $value, $result
			['foo', 'Foobar!', '<input type="submit" name="foo" value="Foobar!" />'],
		];
	}

	/**
	 * Tests Form::submit()
	 *
	 * @param boolean $input  Input for Form::submit
	 * @param boolean $expected Output for Form::submit
	 */
	#[DataProvider('provider_submit')]
	public function test_submit($name, $value, $expected)
	{
		$matcher = [
			'tag' => 'input',
			'attributes' => ['name' => $name, 'type' => 'submit', 'value' => $value]
		];

		$this->assertTag($matcher, Form::submit($name, $value));
	}


	/**
	 * Provides test data for test_image()
	 *
	 * @return array
	 */
	public static function provider_image()
	{
		return [
			// $value, $result
			['foo', 'bar', ['src' => 'media/img/login.png'], '<input type="image" name="foo" value="bar" src="/media/img/login.png" />'],
		];
	}

	/**
	 * Tests Form::image()
	 *
	 * @param boolean $name         Input for Form::image
	 * @param boolean $value        Input for Form::image
	 * @param boolean $attributes  Input for Form::image
	 * @param boolean $expected    Output for Form::image
	 */
	#[DataProvider('provider_image')]
	public function test_image($name, $value, $attributes, $expected)
	{
		$this->assertSame($expected, Form::image($name, $value, $attributes));
	}

	/**
	 * Provides test data for test_label()
	 *
	 * @return array
	 */
	public static function provider_label()
	{
		return [
			// $value, $result
			// Single for provided
			['email', null, null, '<label for="email">Email</label>'],
			['email_address', null, null, '<label for="email_address">Email Address</label>'],
			['email-address', null, null, '<label for="email-address">Email Address</label>'],
			// For and text values provided
			['name', 'First name', null, '<label for="name">First name</label>'],
			// with attributes
			['lastname', 'Last name', ['class' => 'text'], '<label class="text" for="lastname">Last name</label>'],
			['lastname', 'Last name', ['class' => 'text', 'id'=>'txt_lastname'], '<label id="txt_lastname" class="text" for="lastname">Last name</label>'],
		];
	}

	/**
	 * Tests Form::label()
	 *
	 * @param boolean $for         Input for Form::label
	 * @param boolean $text        Input for Form::label
	 * @param boolean $attributes  Input for Form::label
	 * @param boolean $expected    Output for Form::label
	 */
	#[Test]
	#[DataProvider('provider_label')]
	function test_label($for, $text, $attributes, $expected)
	{
		$this->assertSame($expected, Form::label($for, $text, $attributes));
	}

}
