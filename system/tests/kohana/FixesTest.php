<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for bugs fixed in 4.0, so they do not come back.
 *
 * @group kohana
 * @group kohana.core
 * @group kohana.core.fixes
 *
 * @package    Kohana
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.fixes')]
class Kohana_FixesTest extends Unittest_TestCase
{
	/**
	 * Fragment::delete() really removes the fragment.
	 */
	public function test_fragment_delete_removes_the_fragment(): void
	{
		Kohana::cache('Fragment::cache(+fixes_test)', 'cached text');

		$this->assertTrue(Fragment::has('fixes_test', 60, false));

		Fragment::delete('fixes_test', false);

		$this->assertFalse(Fragment::has('fixes_test', 60, false));
	}

	/**
	 * A protected action cannot be reached from the address.
	 */
	public function test_non_public_action_is_not_found(): void
	{
		$request = Request::factory('fixestest/hidden');
		$request->action('hidden');

		$controller = new Controller_FixesTest($request, new Response);

		$this->expectException(HTTP_Exception_404::class);

		$controller->execute();
	}

	/**
	 * A public action of the same controller still runs.
	 */
	public function test_public_action_runs(): void
	{
		$request = Request::factory('fixestest/shown');
		$request->action('shown');

		$controller = new Controller_FixesTest($request, new Response);

		$this->assertSame('shown', $controller->execute()->body());
	}

	/**
	 * "matches" also runs on an empty field when written with its class.
	 */
	public function test_matches_runs_on_empty_field_in_every_spelling(): void
	{
		foreach (['matches', 'Valid::matches', ['Valid', 'matches']] as $rule)
		{
			$validation = Validation::factory(['password' => 'secret', 'confirm' => ''])
				->rule('confirm', $rule, [':validation', 'confirm', 'password']);

			$this->assertFalse($validation->check(), 'Rule: '.json_encode($rule));
		}
	}

	/**
	 * A very high word limit returns the whole text, not an empty string.
	 */
	public function test_limit_words_with_huge_limit(): void
	{
		$this->assertSame('one two three', Text::limit_words('one two three', 100000));

		// Cutting after many words works too, white space is kept as it was
		$text = '  '.str_repeat("word \n", 20000);

		$this->assertSame('  '.rtrim(str_repeat("word \n", 12000)).'…', Text::limit_words($text, 12000));
	}

	/**
	 * Session data too big for a cookie is reported, not lost in silence.
	 */
	public function test_cookie_session_over_the_limit_is_reported(): void
	{
		$session = (new ReflectionClass('Session_Cookie'))->newInstanceWithoutConstructor();

		$data = new ReflectionProperty('Session', '_data');
		$data->setValue($session, ['big' => str_repeat('x', Session_Cookie::MAX_SIZE)]);

		$write = new ReflectionMethod('Session_Cookie', '_write');

		$salt = Cookie::$salt;
		Cookie::$salt = 'fixes test salt';

		try
		{
			$write->invoke($session);

			$this->fail('No exception was thrown');
		}
		catch (Session_Exception $e)
		{
			$this->assertStringContainsString('too big for a cookie', $e->getMessage());
		}
		finally
		{
			Cookie::$salt = $salt;
		}
	}

	/**
	 * A short write, as on a full disk, is an error.
	 */
	public function test_file_write_checks_the_result(): void
	{
		// Nothing can be written to a file opened for reading
		$handle = fopen(__FILE__, 'rb');

		$write = new ReflectionMethod('File', '_write');

		try
		{
			@$write->invoke(null, $handle, 'data', __FILE__);

			$this->fail('No exception was thrown');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('Unable to write', $e->getMessage());
		}
		finally
		{
			fclose($handle);
		}
	}

	/**
	 * PHP gives false for a byte that is not UTF-8 and for a number that
	 * is no character, and the UTF8 helper turns that into an exception.
	 * (The static analysis believes this cannot happen: see phpstan.neon.)
	 */
	public function test_utf8_reports_what_is_not_a_character(): void
	{
		$this->assertFalse(mb_ord("\xFF", 'UTF-8'));
		$this->assertFalse(@mb_chr(0xD800, 'UTF-8'));

		foreach ([fn () => UTF8::ord("\xFF"), fn () => UTF8::from_unicode([0xD800])] as $call)
		{
			try
			{
				$call();

				$this->fail('No exception was thrown');
			}
			catch (UTF8_Exception $e)
			{
				$this->assertStringContainsString('Invalid', $e->getMessage());
			}
		}

		// What is valid still works
		$this->assertSame(0x20AC, UTF8::ord('€'));
		$this->assertSame('€', UTF8::from_unicode([0x20AC]));
	}

	/**
	 * Asking a REST controller for a format that does not exist gives the
	 * REST exception that names it; it used to be a type error.
	 */
	public function test_unknown_rest_format_is_reported(): void
	{
		$request = Request::factory('fixesrest/index');
		$request->format('nosuchformat');

		$this->expectException(REST_Exception::class);
		$this->expectExceptionMessage('REST_Format_nosuchformat does not exist');

		REST_Format::factory($request, new Response);
	}

	/**
	 * A request without an Accept header accepts everything, as the
	 * method has always said it does.
	 */
	public function test_missing_accept_header_accepts_everything(): void
	{
		$everything = ['*' => ['*' => 1.0]];

		$this->assertSame($everything, HTTP_Header::parse_accept_header(null));
		$this->assertSame($everything, HTTP_Header::parse_accept_header(''));
		$this->assertSame(['text' => ['html' => 1.0]], HTTP_Header::parse_accept_header('text/html'));
	}

	/**
	 * A stream that has no address (a pipe) is dumped like any resource;
	 * it used to return nothing, which is an error.
	 */
	public function test_debug_dump_of_a_stream_without_address(): void
	{
		$process = proc_open('exit 0', [1 => ['pipe', 'w']], $pipes);

		try
		{
			$this->assertStringContainsString('resource', Debug::dump($pipes[1]));
		}
		finally
		{
			fclose($pipes[1]);
			proc_close($process);
		}

		// A stream with an address shows it
		$file = fopen(__FILE__, 'rb');

		$this->assertStringContainsString('FixesTest.php', Debug::dump($file));

		fclose($file);
	}

	/**
	 * A REST request that says it sends JSON, with an empty body, has no
	 * values; it used to stop on an undefined variable.
	 */
	public function test_rest_request_with_empty_json_body(): void
	{
		$type = $_SERVER['CONTENT_TYPE'] ?? null;
		$_SERVER['CONTENT_TYPE'] = 'application/json';

		try
		{
			$request = Request::factory('fixesrest/index');
			$request->method(HTTP_Request::POST);
			$request->body('');

			$controller = (new ReflectionClass('Controller_FixesRest'))->newInstanceWithoutConstructor();
			$controller->request = $request;

			$parse = new ReflectionMethod('Controller_REST', '_parse_params');

			$this->assertSame([], $parse->invoke($controller));

			$request->body('{"name":"Ana"}');

			$this->assertSame(['name' => 'Ana'], $parse->invoke($controller));
		}
		finally
		{
			if ($type === null)
			{
				unset($_SERVER['CONTENT_TYPE']);
			}
			else
			{
				$_SERVER['CONTENT_TYPE'] = $type;
			}
		}
	}

	/**
	 * Debug::dump() shows of an object what the object allows, as
	 * var_dump() does.
	 */
	public function test_debug_dump_obeys_debug_info(): void
	{
		$object = new class {
			public string $name = 'visible';
			protected string $key = 'KEY_THAT_MUST_NOT_SHOW';

			public function __debugInfo(): array
			{
				return ['name' => $this->name];
			}
		};

		$dump = Debug::dump($object);

		$this->assertStringContainsString('visible', $dump);
		$this->assertStringNotContainsString('KEY_THAT_MUST_NOT_SHOW', $dump);
	}

	/**
	 * A method named "test_..." in a class that is not a test case is
	 * never run, and nobody notices: tests for the database drivers once
	 * sat in a helper class at the end of their file. Every such method
	 * must be in a class PHPUnit runs.
	 */
	public function test_no_test_method_sits_in_a_class_that_is_not_run(): void
	{
		$lost = [];

		// PHPUnit has loaded every test file before the first test runs
		foreach (get_declared_classes() as $class)
		{
			$reflection = new ReflectionClass($class);
			$file = (string) $reflection->getFileName();

			if ( ! str_contains($file, DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR) OR str_contains($file, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR))
				continue;

			if ($reflection->isSubclassOf(PHPUnit\Framework\TestCase::class))
				continue;

			foreach ($reflection->getMethods() as $method)
			{
				if (str_starts_with($method->getName(), 'test_') AND $method->getDeclaringClass()->getName() === $class)
				{
					$lost[] = $class.'::'.$method->getName();
				}
			}
		}

		$this->assertSame([], $lost, 'Test methods in classes that PHPUnit does not run');
	}

	/**
	 * The files PHP runs directly are outside the class folders, so no
	 * other test loads them: make sure they are valid PHP. (The install
	 * page once shipped with a syntax error.)
	 */
	public function test_entry_files_are_valid_php(): void
	{
		// In the tests DOCROOT is the directory of the project
		$files = array_merge(glob(DOCROOT.'public'.DIRECTORY_SEPARATOR.'*.php'), [DOCROOT.'minion']);

		$this->assertNotEmpty($files);

		foreach ($files as $file)
		{
			try
			{
				token_get_all( (string) file_get_contents($file), TOKEN_PARSE);
			}
			catch (ParseError $e)
			{
				$this->fail(Debug::path($file).' line '.$e->getLine().': '.$e->getMessage());
			}
		}

		// Every file was parsed without an error
		$this->assertTrue(true);
	}

	/**
	 * The data files know current formats, cards and words.
	 */
	public function test_updated_data_files(): void
	{
		$this->assertSame('image/webp', File::mime_by_ext('webp'));
		$this->assertSame('image/avif', File::mime_by_ext('avif'));
		$this->assertSame('font/woff2', File::mime_by_ext('woff2'));

		// A Mastercard of the 2221-2720 range, issued since 2017
		$this->assertTrue(Valid::credit_card('2223000048400011', 'mastercard'));

		$this->assertSame('analyses', Inflector::plural('analysis'));
		$this->assertSame('software', Inflector::plural('software'));
	}

	/**
	 * Kohana::sanitize() cleans the public properties of an object. It
	 * used to write to the object as if it were an array, which stopped
	 * the request with an error.
	 *
	 * @return  void
	 */
	public function test_sanitize_cleans_the_properties_of_an_object(): void
	{
		$object = new stdClass;
		$object->text = "one\r\ntwo";
		$object->list = ["three\rfour"];

		$clean = Kohana::sanitize($object);

		$this->assertSame("one\ntwo", $clean->text);
		$this->assertSame(["three\nfour"], $clean->list);

		// An object that is used as an array is still cleaned as one
		$clean = Kohana::sanitize(new ArrayObject(['text' => "five\rsix"]));

		$this->assertSame("five\nsix", $clean['text']);
	}

	/**
	 * A feed with an encoding that does not exist is refused with a clear
	 * message, not with an error about a property of "false".
	 *
	 * @return  void
	 */
	public function test_feed_with_an_unknown_encoding_is_refused(): void
	{
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('The feed could not be made');

		// libxml warns about the encoding before it gives up
		@Feed::create(['title' => 'News'], [], 'no-such-encoding');
	}

	/**
	 * A validation rule that cannot be called is reported by its name.
	 *
	 * @return  void
	 */
	public function test_validation_names_a_rule_that_cannot_be_called(): void
	{
		$validation = Validation::factory(['name' => 'Ann'])
			->rule('name', 'Kohana_FixesTest::no_such_rule');

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('Kohana_FixesTest::no_such_rule');

		$validation->check();
	}

	/**
	 * Rules written as "Class::method" and as [object, method] still run.
	 *
	 * @return  void
	 */
	public function test_validation_runs_static_and_object_rules(): void
	{
		$validation = Validation::factory(['a' => 'abc', 'b' => 'abc'])
			->rule('a', 'Valid::alpha')
			->rule('b', [$this, 'rule_that_fails']);

		$this->assertFalse($validation->check());
		$this->assertSame(['b'], array_keys($validation->errors()));
	}

	/**
	 * A rule for the test above.
	 *
	 * @return  bool
	 */
	public function rule_that_fails(): bool
	{
		return false;
	}

	/**
	 * Profiler::stats() of no benchmarks gives zeros, it used to divide
	 * by zero.
	 *
	 * @return  void
	 */
	public function test_profiler_stats_of_nothing(): void
	{
		$stats = Profiler::stats([]);

		$this->assertSame(0, $stats['total']['time']);
		$this->assertSame(0, $stats['average']['time']);
		$this->assertNull($stats['min']['time']);
	}

	/**
	 * Arr::extract() accepts a number as a path, as it does a text.
	 *
	 * @return  void
	 */
	public function test_arr_extract_with_a_numeric_path(): void
	{
		$this->assertSame([1 => 'b', 'x' => null], Arr::extract(['a', 'b'], [1, 'x']));
	}

	/**
	 * on() before any join() says so. It used to stop with an error
	 * about a method of "null".
	 *
	 * @return  void
	 */
	public function test_on_without_a_join_is_refused(): void
	{
		if ( ! class_exists('DB'))
		{
			$this->markTestSkipped('The database module is not loaded');
		}

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('on() must follow a join()');

		DB::select()->from('users')->on('a', '=', 'b');
	}

	/**
	 * Without a request (a task on the command line) no POST can be too
	 * large: the answer is false, not an error.
	 *
	 * @return  void
	 */
	public function test_post_max_size_without_a_request(): void
	{
		$initial = Request::$initial;
		Request::$initial = null;

		try
		{
			$this->assertFalse(Request::post_max_size_exceeded());
		}
		finally
		{
			Request::$initial = $initial;
		}
	}

	/**
	 * Headers cannot be sent without their response.
	 *
	 * @return  void
	 */
	public function test_send_headers_needs_a_response(): void
	{
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('without the response');

		(new HTTP_Header)->send_headers();
	}

	/**
	 * Pagination outside of a request says what is missing.
	 *
	 * @return  void
	 */
	public function test_pagination_without_a_request_is_refused(): void
	{
		if ( ! class_exists('Pagination'))
		{
			$this->markTestSkipped('The pagination module is not loaded');
		}

		$current = Request::$current;
		Request::$current = null;

		try
		{
			$this->expectException(Kohana_Exception::class);
			$this->expectExceptionMessage('Pagination needs a request');

			new Pagination;
		}
		finally
		{
			Request::$current = $current;
		}
	}

	/**
	 * An image that cannot be opened ends in an Image_Exception with
	 * the Imagick driver too: its destructor used to fail on the image
	 * that was never made.
	 *
	 * @return  void
	 */
	public function test_imagick_driver_survives_a_file_that_is_missing(): void
	{
		if ( ! class_exists('Image') OR ! extension_loaded('imagick'))
		{
			$this->markTestSkipped('The image module or the imagick extension is not loaded');
		}

		try
		{
			Image::factory('/no/such/file.png', 'Imagick');

			$this->fail('A missing file was accepted');
		}
		catch (Kohana_Exception $e)
		{
			// Destructors of the failed object run now
			gc_collect_cycles();

			$this->assertStringContainsString('file.png', $e->getMessage());
		}
	}
	/**
	 * "Accept-Language: *" accepts every language. The quality of a
	 * language that is not named used to be returned as a list, which
	 * stopped the request with a type error.
	 *
	 * @return  void
	 */
	public function test_accept_language_wildcard_gives_a_quality(): void
	{
		$header = new HTTP_Header(['accept-language' => '*;q=0.5']);

		$this->assertSame(0.5, $header->accepts_language_at_quality('pt-br'));
		$this->assertSame(0.0, $header->accepts_language_at_quality('pt-br', true));
	}

	/**
	 * Stopping a benchmark that does not exist changes nothing. It used
	 * to add half a benchmark, which the profiler views could not show.
	 *
	 * @return  void
	 */
	public function test_profiler_stop_ignores_an_unknown_token(): void
	{
		$before = Profiler::marks();

		Profiler::stop('no/such/token');

		$this->assertSame(array_keys($before), array_keys(Profiler::marks()));
	}

	/**
	 * The file driver of Auth has no password for a user name that is
	 * not a text (a list sent by a form, for example).
	 *
	 * @return  void
	 */
	public function test_auth_file_has_no_password_for_a_list(): void
	{
		if ( ! class_exists('Auth_File'))
		{
			$this->markTestSkipped('The auth module is not loaded');
		}

		$auth = new Auth_File(['users' => ['ann' => 'hash'], 'session_type' => 'native', 'session_key' => 'auth_user']);

		$this->assertSame('hash', $auth->password('ann'));
		$this->assertFalse($auth->password(['ann']));
		$this->assertFalse($auth->password('bob'));
	}

	/**
	 * before() may send the request to another action: the action is read
	 * after it ran, with and without profiling.
	 */
	public function test_before_can_choose_another_action(): void
	{
		foreach ([true, false] as $profiling)
		{
			$this->setEnvironment(['Kohana::$profiling' => $profiling]);

			$request    = Request::factory('fixesbefore/first');
			$controller = new Controller_FixesBefore($request, new Response);

			$request->action('first');

			$this->assertSame('second', $controller->execute()->body(), $profiling ? 'with profiling' : 'without profiling');
		}

		// An action that before() chose and that does not exist is a 404
		$request    = Request::factory('fixesbefore/missing');
		$controller = new Controller_FixesBefore($request, new Response);

		$request->action('missing');

		$this->expectException(HTTP_Exception_404::class);

		$controller->execute();
	}
}

/**
 * A controller whose before() sends "first" to "second".
 */
class Controller_FixesBefore extends Controller
{
	public function before(): void
	{
		if ($this->request->action() === 'first')
		{
			$this->request->action('second');
		}
	}

	public function action_first(): void
	{
		$this->response->body('first');
	}

	public function action_second(): void
	{
		$this->response->body('second');
	}
}

/**
 * A REST controller, to reach the reading of the request body.
 */
class Controller_FixesRest extends Controller_REST
{
}

/**
 * Controller with one public and one protected action.
 */
class Controller_FixesTest extends Controller
{
	public function action_shown(): void
	{
		$this->response->body('shown');
	}

	protected function action_hidden(): void
	{
		$this->response->body('hidden');
	}
}
