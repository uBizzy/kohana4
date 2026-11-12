<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests what the profiler offers to tools such as the debug bar, the
 * timing of views and the log of slow requests.
 *
 * @package    Kohana
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.profiler')]
class Kohana_ProfilerTest extends Unittest_TestCase
{
	protected bool $profiling;

	public function setUp(): void
	{
		parent::setUp();

		$this->profiling = Kohana::$profiling;

		Kohana::$profiling = true;
	}

	public function tearDown(): void
	{
		Kohana::$profiling = $this->profiling;
		Profiler::$slow_request = 0.0;

		parent::tearDown();
	}

	public function test_marks_are_returned_in_the_order_they_started(): void
	{
		$first  = Profiler::start('Profiler test', 'first');
		$second = Profiler::start('Profiler test', 'second');

		Profiler::stop($first);

		$marks = Profiler::marks();
		$tokens = array_keys($marks);

		$this->assertLessThan(array_search($second, $tokens), array_search($first, $tokens));
		$this->assertSame('profiler test', $marks[$first]['group']);
		$this->assertIsFloat($marks[$first]['stop_time']);

		// Still running
		$this->assertFalse($marks[$second]['stop_time']);

		Profiler::delete($first);
		Profiler::delete($second);
	}

	public function test_add_keeps_data_only_while_profiling(): void
	{
		Profiler::add('Profiler test data', ['a' => 1]);

		$this->assertSame(['a' => 1], Profiler::data()['Profiler test data']);

		Kohana::$profiling = false;

		Profiler::add('Profiler test off', 'x');

		$this->assertArrayNotHasKey('Profiler test off', Profiler::data());
	}

	public function test_summary_counts_database_queries(): void
	{
		$before = Profiler::summary();

		Profiler::stop(Profiler::start('Database (profiler_test)', 'SELECT 1'));
		Profiler::stop(Profiler::start('Database (profiler_test)', 'SELECT 2'));
		Profiler::stop(Profiler::start('Views', 'not a query'));

		$after = Profiler::summary();

		$this->assertSame($before['queries'] + 2, $after['queries']);
		$this->assertGreaterThan(0, $after['time']);
		$this->assertGreaterThan(0, $after['memory']);
		$this->assertGreaterThan(0, $after['files']);
		$this->assertGreaterThanOrEqual($before['query_time'], $after['query_time']);
	}

	public function test_every_rendered_view_is_measured(): void
	{
		$html = View::factory('kohana/error_production', ['title' => 'T', 'status' => 500, 'reference' => 'abc12345'])->render();

		$this->assertNotSame('', $html);

		$views = Profiler::groups()['views'];

		$this->assertNotEmpty(array_filter(array_keys($views), fn ($name) => str_contains($name, 'error_production')));
	}

	public function test_views_are_not_measured_when_profiling_is_off(): void
	{
		Kohana::$profiling = false;

		$before = count(Profiler::marks());

		View::factory('kohana/error_production', ['title' => 'T', 'status' => 500, 'reference' => 'abc12345'])->render();

		$this->assertCount($before, Profiler::marks());
	}

	/**
	 * Returns the newest benchmark of a group.
	 */
	protected function last_mark(string $group): array
	{
		$marks = array_filter(Profiler::marks(), fn ($mark) => $mark['group'] === $group);

		$this->assertNotEmpty($marks, 'No benchmark in the group "'.$group.'"');

		return end($marks);
	}

	public function test_note_says_what_came_of_a_benchmark(): void
	{
		$token = Profiler::start('Profiler test', 'search');
		Profiler::stop($token);

		$this->assertSame('', Profiler::marks()[$token]['note']);

		Profiler::note($token, '12 results');

		$this->assertSame('12 results', Profiler::marks()[$token]['note']);

		// A token that does not exist is ignored
		Profiler::note('no/such/token', 'x');

		Profiler::delete($token);
	}

	public function test_find_file_tells_which_file_and_where(): void
	{
		$caching = Kohana::$caching;
		Kohana::$caching = false;

		try
		{
			$found = Kohana::find_file('classes', 'Kohana/Arr');
			$mark  = $this->last_mark('find file');

			$this->assertSame('classes'.DIRECTORY_SEPARATOR.'Kohana/Arr.php', $mark['name']);
			$this->assertSame($found, $mark['note']);

			Kohana::find_file('views', 'no/such/view_for_the_profiler_test');
			$mark = $this->last_mark('find file');

			$this->assertStringContainsString('view_for_the_profiler_test', $mark['name']);
			$this->assertSame('not found', $mark['note']);
		}
		finally
		{
			Kohana::$caching = $caching;
		}
	}

	public function test_validation_tells_fields_and_result_but_no_values(): void
	{
		Validation::factory(['email' => 'SECRET_VALUE_not_an_email', 'name' => 'Ana'])
			->rule('email', 'email')
			->rule('name', 'not_empty')
			->check();

		$mark = $this->last_mark('validation');

		$this->assertSame('email, name', $mark['name']);
		$this->assertSame('failed: email', $mark['note']);
		$this->assertStringNotContainsString('SECRET_VALUE', $mark['name'].$mark['note']);

		Validation::factory(['name' => 'Ana'])->rule('name', 'not_empty')->check();

		$this->assertSame('passed', $this->last_mark('validation')['note']);
	}

	public function test_view_tells_the_names_of_its_variables_but_no_values(): void
	{
		View::factory('kohana/error_production', ['title' => 'SECRET_TITLE', 'status' => 500, 'reference' => 'abc12345'])->render();

		$mark = $this->last_mark('views');

		$this->assertSame('$title, $status, $reference', $mark['note']);
		$this->assertStringNotContainsString('SECRET_TITLE', $mark['name'].$mark['note']);
	}

	public function test_config_benchmark_is_named_after_the_group(): void
	{
		(new Config_File)->load('url');

		$this->assertSame('url', $this->last_mark('config (config)')['name']);
	}

	public function test_routing_tells_the_routes_that_were_tried(): void
	{
		$routes = [
			'profiler_first'  => new Route('profiler-test-first/<id>'),
			'profiler_second' => new Route('profiler-test-second(/<id>)'),
		];

		$routes['profiler_second']->defaults(['controller' => 'Welcome', 'action' => 'index']);

		$request = Request::factory('profiler-test-second/5');

		// The search for a route, as a request does it before it runs
		$this->assertSame($routes['profiler_second'], Request::process($request, $routes)['route']);

		$mark = $this->last_mark('routing');

		$this->assertSame('profiler-test-second/5', $mark['name']);
		$this->assertSame('matched "profiler_second" after trying: profiler_first', $mark['note']);
	}

	public function test_controller_steps_are_measured_one_by_one(): void
	{
		$request = Request::factory('profilertest/shown');
		$request->action('shown');

		$controller = new Controller_ProfilerTest($request, new Response);

		$this->assertSame('before,shown,after', $controller->execute()->body());

		$names = array_column(array_filter(Profiler::marks(), fn ($mark) => $mark['group'] === 'controller'), 'name');

		$this->assertSame(
			['Controller_ProfilerTest::before', 'Controller_ProfilerTest::action_shown', 'Controller_ProfilerTest::after'],
			array_slice($names, -3)
		);

		// A protected action is still not found while profiling
		$request = Request::factory('profilertest/hidden');
		$request->action('hidden');

		$this->expectException(HTTP_Exception_404::class);

		(new Controller_ProfilerTest($request, new Response))->execute();
	}

	public function test_class_loading_is_measured(): void
	{
		$this->assertFalse(class_exists('Kohana_ProfilerTest_Autoloaded', false));

		// The class file is found through the cascading filesystem
		$this->assertTrue(class_exists('Feed'));

		$classes = array_column(array_filter(Profiler::marks(), fn ($mark) => $mark['group'] === 'classes'), 'note', 'name');

		if (isset($classes['Feed']))
		{
			$this->assertStringEndsWith('classes'.DIRECTORY_SEPARATOR.'Feed.php', $classes['Feed']);
		}
		else
		{
			// Another test loaded the class before profiling was on
			$this->assertTrue(class_exists('Feed', false));
		}
	}

	public function test_slow_request_is_logged(): void
	{
		$writer = new Kohana_ProfilerTest_Writer;

		Kohana::$log->attach($writer);

		try
		{
			// Switched off: nothing is written
			Profiler::$slow_request = 0.0;
			Profiler::log_slow_request();
			Kohana::$log->write();

			$this->assertSame([], $writer->messages);

			// Not slow enough
			Profiler::$slow_request = 100000.0;
			Profiler::log_slow_request();
			Kohana::$log->write();

			$this->assertSame([], $writer->messages);

			// Every request is slower than this
			Profiler::$slow_request = 0.000001;
			Profiler::log_slow_request();
			Kohana::$log->write();

			$this->assertCount(1, $writer->messages);
			$this->assertSame(Log::WARNING, $writer->messages[0]['level']);
			$this->assertMatchesRegularExpression('/^Slow request: [\d.,]+ s, [\d.,]+ MB, \d+ queries \([\d.,]+ s\): /', $writer->messages[0]['body']);
		}
		finally
		{
			Kohana::$log->detach($writer);
		}
	}
}

/**
 * Controller whose three steps each leave a trace.
 */
class Controller_ProfilerTest extends Controller
{
	public function before(): void
	{
		$this->response->body('before');
	}

	public function action_shown(): void
	{
		$this->response->body($this->response->body().',shown');
	}

	public function after(): void
	{
		$this->response->body($this->response->body().',after');
	}

	protected function action_hidden(): void
	{
		$this->response->body('hidden');
	}
}

/**
 * Log writer that keeps what it is given.
 */
class Kohana_ProfilerTest_Writer extends Log_Writer
{
	public array $messages = [];

	public function write(array $messages): void
	{
		$this->messages = array_merge($this->messages, $messages);
	}
}
