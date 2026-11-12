<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the error pages: what is shown while developing, what is hidden
 * in production, and how deprecations are handled.
 *
 * @package    Kohana
 * @category   Tests
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026  Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.errorpage')]
class Kohana_ErrorPageTest extends Unittest_TestCase
{
	/**
	 * A secret value. Put together from two parts, so that it is not in
	 * the source code that the error page shows.
	 */
	protected const string SECRET = 'hunter2'.'-secret';

	/**
	 * Environment to put back after each test
	 */
	protected int $_environment;

	/**
	 * Error reporting level to put back after each test
	 */
	protected int $_error_reporting;

	// phpcs:ignore
	public function setUp(): void
	{
		parent::setUp();

		$this->_environment = Kohana::$environment;
		Kohana::$notices = [];

		// PHPUnit lowers the error reporting level while a test runs; the
		// framework's error handler is tested here as it runs in a request
		$this->_error_reporting = error_reporting(E_ALL);
	}

	// phpcs:ignore
	public function tearDown(): void
	{
		Kohana::$environment = $this->_environment;
		Kohana::$notices = [];
		error_reporting($this->_error_reporting);

		parent::tearDown();
	}

	/**
	 * An error with a cause, and a secret in its message path.
	 */
	protected function _error(): Throwable
	{
		try
		{
			$this->_fail(self::SECRET);
		}
		catch (Throwable $cause)
		{
			return new Kohana_Exception('Outer :what', [':what' => 'failure'], 7, $cause);
		}
	}

	/**
	 * Throws: the argument must be masked on the error page, by its name.
	 */
	protected function _fail(string $password): never
	{
		throw new InvalidArgumentException('Inner failure');
	}

	/**
	 * The description holds the error, its place and the chain of causes.
	 */
	public function test_describe_follows_the_causes(): void
	{
		$error = Kohana_Exception::describe($this->_error());

		$this->assertSame('Kohana_Exception', $error['class']);
		$this->assertSame('Outer failure', $error['message']);
		$this->assertSame(__FILE__, $error['file']);
		$this->assertFalse($error['fatal']);
		$this->assertNotEmpty($error['trace']);

		$this->assertSame('InvalidArgumentException', $error['previous']['class']);
		$this->assertSame('Inner failure', $error['previous']['message']);
		$this->assertNull($error['previous']['previous']);
	}

	/**
	 * A PHP error turned into an exception points at the line of the error,
	 * and the framework's own handler is not a step of the trace.
	 */
	public function test_php_errors_point_at_their_line(): void
	{
		try
		{
			$line = __LINE__ + 1;
			Kohana::error_handler(E_WARNING, 'Something odd', __FILE__, $line);
		}
		catch (ErrorException $e)
		{
			$error = Kohana_Exception::describe($e);
		}

		$this->assertSame(__FILE__, $error['file']);
		$this->assertSame($line, $error['line']);
		$this->assertSame('Warning', $error['code']);

		foreach ($error['trace'] as $step)
		{
			$this->assertStringNotContainsString('error_handler', $step['function']);
		}
	}

	/**
	 * An error inside a view is reported as it is: the view's own file and line.
	 */
	public function test_view_errors_keep_their_file_and_line(): void
	{
		$file = Kohana::find_file('tests/test_data/views', 'test_error_view');
		$level = ob_get_level();

		try
		{
			// A view outside the "views" directories: its file is set directly
			$view = View::factory();
			(new ReflectionProperty(View::class, '_file'))->setValue($view, $file);
			$view->render();
			$this->fail('The view should have thrown');
		}
		catch (RuntimeException $e)
		{
			$this->assertSame('broken view', $e->getMessage());
			$this->assertSame($file, $e->getFile());
			$this->assertSame(4, $e->getLine());
		}

		// The output of the broken view was thrown away
		$this->assertSame($level, ob_get_level());
	}

	/**
	 * The reference is 8 characters, and the same every time for one error.
	 */
	public function test_reference_is_stable(): void
	{
		$e = $this->_error();
		$reference = Kohana_Exception::reference($e);

		$this->assertMatchesRegularExpression('/^[0-9A-F]{8}$/', $reference);
		$this->assertSame($reference, Kohana_Exception::reference($e));
		$this->assertNotSame($reference, Kohana_Exception::reference($this->_error()));
	}

	/**
	 * While developing, the page shows the error, its cause and the reference.
	 */
	public function test_development_page(): void
	{
		Kohana::$environment = Kohana::DEVELOPMENT;

		$e = $this->_error();
		$response = Kohana_Exception::response($e, 'html');
		$body = $response->body();

		$this->assertSame(500, $response->status());
		$this->assertStringContainsString('<!DOCTYPE html>', $body);
		$this->assertStringContainsString('Outer failure', $body);
		$this->assertStringContainsString('Inner failure', $body);
		$this->assertStringContainsString(Kohana_Exception::reference($e), $body);
		$this->assertStringContainsString('nonce="'.Security::nonce().'"', $body);

		// No inline event handlers and no style attributes (strict CSP)
		$this->assertStringNotContainsString('onclick', $body);
		$this->assertStringNotContainsString(' style="', $body);
	}

	/**
	 * Values with a secret name are never printed.
	 */
	public function test_secrets_are_masked(): void
	{
		Kohana::$environment = Kohana::DEVELOPMENT;

		$body = Kohana_Exception::response($this->_error(), 'html')->body();

		$this->assertStringNotContainsString(self::SECRET, $body);

		$this->assertTrue(Debug::is_sensitive('db_password'));
		$this->assertTrue(Debug::is_sensitive('HTTP_AUTHORIZATION'));
		$this->assertFalse(Debug::is_sensitive('username'));
		$this->assertFalse(Debug::is_sensitive(3));

		$dump = Debug::dump(['user' => 'ana', 'password' => self::SECRET, 'nested' => ['api_key' => 'abc-123-key']]);
		$this->assertStringContainsString('ana', $dump);
		$this->assertStringNotContainsString(self::SECRET, $dump);
		$this->assertStringNotContainsString('abc-123-key', $dump);
	}

	/**
	 * With an editor set, files get a link that opens them at the line.
	 */
	public function test_editor_links(): void
	{
		Kohana::$environment = Kohana::DEVELOPMENT;

		// No editor: no link
		$this->assertNull(Debug::editor_link(__FILE__, 5));
		$this->assertStringNotContainsString('<a class="k-open"', Kohana_Exception::response($this->_error(), 'html')->body());

		Debug::$editor = 'vscode://file/:file::line';
		Debug::$editor_paths = [DOCROOT => '/home/me/project/'];

		try
		{
			$this->assertSame('vscode://file/'.rawurlencode('/home/me/project/index.php').':12', Debug::editor_link(DOCROOT.'index.php', 12));
			$this->assertStringContainsString('<a class="k-open" href="vscode://file/', Kohana_Exception::response($this->_error(), 'html')->body());

			// Never in production
			Kohana::$environment = Kohana::PRODUCTION;
			$this->assertNull(Debug::editor_link(__FILE__, 5));
		}
		finally
		{
			Debug::$editor = null;
			Debug::$editor_paths = [];
		}
	}

	/**
	 * Formats of the production response.
	 */
	public static function provider_formats(): array
	{
		return [['html'], ['json'], ['text']];
	}

	/**
	 * In production nothing about the error, the code or the framework
	 * is sent: only a general message and the reference.
	 */
	#[DataProvider('provider_formats')]
	public function test_production_says_nothing(string $format): void
	{
		Kohana::$environment = Kohana::PRODUCTION;

		$e = $this->_error();
		$response = Kohana_Exception::response($e, $format);
		$body = $response->body();

		$this->assertSame(500, $response->status());
		$this->assertStringContainsString(Kohana_Exception::reference($e), $body);

		foreach (['Outer', 'Inner', 'Exception', 'ErrorPageTest', 'hunter2', 'kohana', 'Kohana', 'koseven', 'php', 'PHP', DOCROOT, SYSPATH] as $leak)
		{
			$this->assertStringNotContainsString($leak, $body, $format.' leaks "'.$leak.'"');
		}

		if ($format === 'json')
		{
			$data = json_decode($body, true);
			$this->assertSame(['status', 'message', 'reference'], array_keys($data['error']));
			$this->assertSame(500, $data['error']['status']);
		}
	}

	/**
	 * In production a "not found" says only "Not Found".
	 */
	public function test_production_http_errors(): void
	{
		Kohana::$environment = Kohana::PRODUCTION;

		$e = HTTP_Exception::factory(404, 'The page :page is not here, see :file', [':page' => 'secret/page', ':file' => __FILE__]);
		$response = Kohana_Exception::response($e, 'html');

		$this->assertSame(404, $response->status());
		$this->assertStringContainsString('Not Found', $response->body());
		$this->assertStringNotContainsString('secret/page', $response->body());
		$this->assertStringNotContainsString('ErrorPageTest', $response->body());

		// "Try again later" is for errors of the server: a page that is
		// not there will not be there later either
		$this->assertStringNotContainsString('try again later', $response->body());
		$this->assertStringContainsString(
			'try again later',
			Kohana_Exception::response(new RuntimeException('Broken'), 'html')->body()
		);
	}

	/**
	 * While developing, JSON and plain text carry the whole error.
	 */
	public function test_development_json_and_text(): void
	{
		Kohana::$environment = Kohana::DEVELOPMENT;

		$e = $this->_error();

		$json = Kohana_Exception::response($e, 'json');
		$data = json_decode($json->body(), true);

		$this->assertStringContainsString('application/json', (string) $json->headers('content-type'));
		$this->assertSame('Outer failure', $data['error']['message']);
		$this->assertSame('Inner failure', $data['error']['previous']['message']);
		$this->assertSame(Kohana_Exception::reference($e), $data['error']['reference']);
		$this->assertStringNotContainsString(self::SECRET, $json->body());

		$text = Kohana_Exception::response($e, 'text');

		$this->assertStringContainsString('text/plain', (string) $text->headers('content-type'));
		$this->assertStringContainsString('Outer failure', $text->body());
		$this->assertStringContainsString('Inner failure', $text->body());
		$this->assertStringNotContainsString('<', $text->body());
		$this->assertStringNotContainsString(self::SECRET, $text->body());
	}

	/**
	 * A deprecation does not stop the request: it is recorded once.
	 */
	public function test_deprecations_are_recorded_not_thrown(): void
	{
		Kohana::$environment = Kohana::DEVELOPMENT;

		$this->assertTrue(Kohana::error_handler(E_USER_DEPRECATED, 'old_function() is deprecated', __FILE__, 10));
		$this->assertTrue(Kohana::error_handler(E_USER_DEPRECATED, 'old_function() is deprecated', __FILE__, 10));

		$this->assertCount(1, Kohana::$notices);

		$notice = reset(Kohana::$notices);
		$this->assertSame('old_function() is deprecated', $notice['message']);
		$this->assertSame(__FILE__, $notice['file']);
		$this->assertSame(10, $notice['line']);

		$panel = View::factory('kohana/notices', ['notices' => Kohana::$notices])->render();
		$this->assertStringContainsString('old_function() is deprecated', $panel);
		$this->assertStringContainsString('id="kohana_notices"', $panel);
	}

	/**
	 * In production a deprecation is only logged.
	 */
	public function test_deprecations_are_not_collected_in_production(): void
	{
		Kohana::$environment = Kohana::PRODUCTION;

		$this->assertTrue(Kohana::error_handler(E_USER_DEPRECATED, 'other_function() is deprecated', __FILE__, 11));
		$this->assertSame([], Kohana::$notices);
	}

	/**
	 * Any other PHP error still stops the request.
	 */
	public function test_other_errors_are_thrown(): void
	{
		$this->expectException(ErrorException::class);

		Kohana::error_handler(E_USER_WARNING, 'A real problem', __FILE__, 12);
	}

	/**
	 * The profiler uses the shared styles and has no style attributes.
	 */
	public function test_profiler_view(): void
	{
		$token = Profiler::start('errorpage', 'test');
		Profiler::stop($token);

		$html = View::factory('profiler/stats')->render();

		$this->assertStringContainsString('class="kohana k-debug"', $html);
		$this->assertStringContainsString('data-k-left="', $html);
		$this->assertStringNotContainsString(' style="', $html);
	}
}
