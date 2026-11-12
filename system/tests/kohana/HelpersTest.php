<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the small helper methods added in 4.0.
 *
 * @group kohana
 * @group kohana.core
 * @group kohana.core.helpers
 *
 * @package    Kohana
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.helpers')]
class Kohana_HelpersTest extends Unittest_TestCase
{
	public function test_arr_only_and_except(): void
	{
		$post = ['username' => 'ana', 'email' => 'ana@example.com', 'is_admin' => '1'];

		$this->assertSame(['username' => 'ana', 'email' => 'ana@example.com'], Arr::only($post, ['username', 'email', 'missing']));
		$this->assertSame(['username' => 'ana', 'email' => 'ana@example.com'], Arr::except($post, ['is_admin', 'missing']));
	}

	public function test_arr_has(): void
	{
		$array = ['db' => ['host' => null, 'port' => 3306]];

		$this->assertTrue(Arr::has($array, 'db.port'));
		$this->assertTrue(Arr::has($array, 'db.host'), 'A null value still exists');
		$this->assertFalse(Arr::has($array, 'db.user'));
		$this->assertFalse(Arr::has($array, 'cache.host'));
	}

	public function test_arr_delete_path(): void
	{
		$array = ['theme' => ['colors' => ['link' => 'blue', 'text' => 'black']], 'name' => 'x'];

		$this->assertTrue(Arr::delete_path($array, 'theme.colors.link'));
		$this->assertSame(['theme' => ['colors' => ['text' => 'black']], 'name' => 'x'], $array);

		// Nothing to remove: the array stays as it is
		$this->assertFalse(Arr::delete_path($array, 'theme.fonts.title'));
		$this->assertFalse(Arr::delete_path($array, 'name.first'));
		$this->assertTrue(Arr::delete_path($array, ['name']));
		$this->assertSame(['theme' => ['colors' => ['text' => 'black']]], $array);
	}

	public function test_form_typed_inputs(): void
	{
		$types = [
			'email' => 'email', 'url_field' => 'url', 'tel' => 'tel', 'search' => 'search', 'number' => 'number',
			'range' => 'range', 'date' => 'date', 'time' => 'time', 'color' => 'color',
		];

		foreach ($types as $method => $type)
		{
			$html = Form::$method('field', 'v', ['type' => 'text', 'class' => 'c']);

			$this->assertStringContainsString('type="'.$type.'"', $html);
			$this->assertStringContainsString('name="field"', $html);
			$this->assertStringContainsString('value="v"', $html);
			$this->assertStringContainsString('class="c"', $html);
		}
	}

	public function test_profiler_measure(): void
	{
		$profiling = Kohana::$profiling;

		try
		{
			Kohana::$profiling = true;

			$this->assertSame(42, Profiler::measure('helpers test', 'answer', fn () => 42));

			try
			{
				Profiler::measure('helpers test', 'broken', fn () => throw new RuntimeException('boom'));
			}
			catch (RuntimeException $e)
			{
				// Expected: the benchmark must be stopped all the same
			}

			$tokens = Profiler::groups()['helpers test'];

			$this->assertSame(['answer', 'broken'], array_keys($tokens));

			foreach ($tokens as $list)
			{
				// A benchmark that was never stopped has no usable total
				$this->assertGreaterThanOrEqual(0, Profiler::total($list[0])[0]);
				$this->assertNotFalse((new ReflectionProperty('Profiler', '_marks'))->getValue()[$list[0]]['stop_time']);
			}

			// With profiling off the function simply runs
			Kohana::$profiling = false;

			$this->assertSame('plain', Profiler::measure('helpers test', 'off', fn () => 'plain'));
			$this->assertArrayNotHasKey('off', Profiler::groups()['helpers test']);
		}
		finally
		{
			Kohana::$profiling = $profiling;
		}
	}

	public function test_route_has(): void
	{
		Route::set('helpers_test_route', 'helpers-test(/<id>)');

		$this->assertTrue(Route::has('helpers_test_route'));
		$this->assertFalse(Route::has('helpers_test_no_such_route'));
	}

	public function test_i18n_has(): void
	{
		$cache = new ReflectionProperty('I18n', '_cache');
		$before = $cache->getValue();

		// "Same" is translated to the very same text
		$cache->setValue(null, ['xx' => ['Hello' => 'Hola', 'Same' => 'Same']] + $before);

		try
		{
			$this->assertTrue(I18n::has('Hello', 'xx'));
			$this->assertTrue(I18n::has('Same', 'xx'));
			$this->assertFalse(I18n::has('Goodbye', 'xx'));
		}
		finally
		{
			$cache->setValue(null, $before);
		}
	}

	public function test_fragment_discard_does_not_cache(): void
	{
		Fragment::delete('helpers_test', false);

		ob_start();

		$this->assertFalse(Fragment::load('helpers_test', 60, false));
		echo 'not kept';
		Fragment::discard();

		// The content is displayed all the same
		$this->assertSame('not kept', ob_get_clean());
		$this->assertFalse(Fragment::has('helpers_test', 60, false));
	}

	public function test_security_check_and_rotate(): void
	{
		// A token name of our own, so other tests keep their token
		$name = Security::$token_name;
		Security::$token_name = 'helpers_test_token';

		try
		{
			$token = Security::token(true);

			$this->assertFalse(Security::check_and_rotate('wrong'));
			$this->assertSame($token, Security::token(), 'A wrong token changes nothing');

			$this->assertTrue(Security::check_and_rotate($token));
			$this->assertNotSame($token, Security::token());

			// The used token is refused the second time
			$this->assertFalse(Security::check_and_rotate($token));
		}
		finally
		{
			Session::instance()->delete(Security::$token_name);
			Security::$token_name = $name;
		}
	}

	public function test_json_encode_and_decode(): void
	{
		$this->assertSame('{"url":"a/b","name":"Zoë"}', Json::encode(['url' => 'a/b', 'name' => 'Zoë']));
		$this->assertStringContainsString("\n", Json::encode(['a' => 1], JSON_PRETTY_PRINT));

		$this->assertSame(['a' => ['b' => 1]], Json::decode('{"a":{"b":1}}'));
		$this->assertEquals((object) ['a' => 1], Json::decode('{"a":1}', false));
		$this->assertNull(Json::decode('null'));
	}

	public function test_json_decode_throws_on_invalid_json(): void
	{
		$this->expectException(JsonException::class);

		Json::decode('{not json');
	}

	public function test_json_encode_throws_on_invalid_value(): void
	{
		$this->expectException(JsonException::class);

		// Not valid UTF-8
		Json::encode("\xB1\x31");
	}
}
