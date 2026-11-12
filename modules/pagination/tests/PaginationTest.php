<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Pagination: the numbers it works out, the page number it takes
 * from the address, and the links it makes.
 *
 * @package    Kohana/Pagination
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.pagination')]
class Kohana_PaginationTest extends Unittest_TestCase
{
	/**
	 * @var array  The routes as they were before the test
	 */
	protected $routes;

	public function setUp(): void
	{
		parent::setUp();

		// Only the route of these tests: no other one takes the address
		$this->routes = (new ReflectionProperty('Route', '_routes'))->getValue();
		(new ReflectionProperty('Route', '_routes'))->setValue(null, []);

		Route::set('kohana_pagination_test', '(<controller>(/<action>(/<id>)))')
			->defaults(['controller' => 'Welcome', 'action' => 'index']);

		$this->setEnvironment([
			'Kohana::$base_url'   => '/',
			'Kohana::$index_file' => false,
			'Request::$initial'   => null,
			'Request::$current'   => null,
		]);
	}

	public function tearDown(): void
	{
		(new ReflectionProperty('Route', '_routes'))->setValue(null, $this->routes);

		parent::tearDown();
	}

	/**
	 * Returns a request that was executed, as the one a controller works
	 * with: it knows its route, controller and action.
	 */
	protected function request(string $uri, array $query = []): Request
	{
		$request = Request::factory($uri)->query($query);
		$request->execute();

		return $request;
	}

	/**
	 * Returns the pagination of a request for "paged/list", with
	 * these values in the query string.
	 */
	protected function pagination(array $config = [], array $query = []): Pagination
	{
		return Pagination::factory($config, $this->request('paged/list', $query));
	}

	public static function provider_numbers(): array
	{
		return [
			// total, per page, page asked => pages, page, first item, last item, offset, previous, next, first, last
			'the first of three'    => [25, 10, 1, [3, 1, 1, 10, 0, false, 2, false, 3]],
			'the middle one'        => [25, 10, 2, [3, 2, 11, 20, 10, 1, 3, 1, 3]],
			'the last, not full'    => [25, 10, 3, [3, 3, 21, 25, 20, 2, false, 1, false]],
			'beyond the last'       => [25, 10, 99, [3, 3, 21, 25, 20, 2, false, 1, false]],
			'before the first'      => [25, 10, -4, [3, 1, 1, 10, 0, false, 2, false, 3]],
			'exactly one page'      => [10, 10, 1, [1, 1, 1, 10, 0, false, false, false, false]],
			'no items'              => [0, 10, 1, [0, 1, 0, 0, 0, false, false, false, false]],
			'no items per page'     => [5, 0, 2, [5, 2, 2, 2, 1, 1, 3, 1, 5]],
			'a negative total'      => [-5, 10, 1, [0, 1, 0, 0, 0, false, false, false, false]],
		];
	}

	#[DataProvider('provider_numbers')]
	public function test_numbers(int $total, int $per_page, int $page, array $expected): void
	{
		$pagination = $this->pagination(['total_items' => $total, 'items_per_page' => $per_page], ['page' => (string) $page]);

		$this->assertSame($expected, [
			$pagination->total_pages,
			$pagination->current_page,
			$pagination->current_first_item,
			$pagination->current_last_item,
			$pagination->offset,
			$pagination->previous_page,
			$pagination->next_page,
			$pagination->first_page,
			$pagination->last_page,
		]);
	}

	/**
	 * The page number is typed by a visitor: whatever it is, the result
	 * is a page that exists.
	 */
	public function test_page_from_the_address_is_always_a_valid_number(): void
	{
		$inputs = ['abc', '', '2abc', ['3'], '1.9', '0', '-1', '99999999999999999999999', '1e3', "2\n", '<script>', null];

		foreach ($inputs as $input)
		{
			$pagination = $this->pagination(['total_items' => 50], ($input === null) ? [] : ['page' => $input]);

			$this->assertIsInt($pagination->current_page);
			$this->assertGreaterThanOrEqual(1, $pagination->current_page);
			$this->assertLessThanOrEqual(5, $pagination->current_page);
			$this->assertGreaterThanOrEqual(0, $pagination->offset);
		}

		// What is not a number is the first page
		$this->assertSame(1, $this->pagination(['total_items' => 50], ['page' => 'abc'])->current_page);
		$this->assertSame(1, $this->pagination(['total_items' => 50], ['page' => ['3']])->current_page);
	}

	public function test_page_can_be_given_or_come_from_the_route(): void
	{
		$given = $this->pagination(['total_items' => 50, 'current_page' => ['source' => 'query_string', 'key' => 'page', 'page' => 4]], ['page' => '2']);

		$this->assertSame(4, $given->current_page);

		// Another key of the query string
		$key = $this->pagination(['total_items' => 50, 'current_page' => ['source' => 'query_string', 'key' => 'p']], ['p' => '3', 'page' => '2']);

		$this->assertSame(3, $key->current_page);

		// From the route: "paged/list/5" with the default route gives the parameter "id"
		$request = $this->request('paged/list/5');
		$route   = Pagination::factory(['total_items' => 50, 'current_page' => ['source' => 'route', 'key' => 'id']], $request);

		$this->assertSame(5, $route->current_page);
		$this->assertSame('/Paged/list/2', $route->url(2));
		$this->assertSame('/Paged/list', $route->url(1));
	}

	/**
	 * The links keep the other values of the query string, and those
	 * come out encoded.
	 */
	public function test_links_keep_the_query_string(): void
	{
		// The links name the controller as the request knows it ("Paged"),
		// which always leads back to the same class

		$pagination = $this->pagination(['total_items' => 50], ['page' => '2', 'q' => 'a b&c', 'x' => '"><script>']);

		$this->assertSame('/Paged/list?page=3&q=a+b%26c&x=%22%3E%3Cscript%3E', $pagination->url(3));

		// The first page has no number, unless asked for
		$this->assertSame('/Paged/list?q=a+b%26c&x=%22%3E%3Cscript%3E', $pagination->url(1));
		$this->assertSame('/Paged/list?q=a+b%26c&x=%22%3E%3Cscript%3E', $pagination->url(-3));

		$pagination->setup(['first_page_in_url' => true]);

		$this->assertStringStartsWith('/Paged/list?page=1&', $pagination->url(1));

		$this->assertSame('', $this->pagination()->query());
		$this->assertSame('?a=1', $this->pagination()->query(['a' => 1]));
		$this->assertSame('', $this->pagination()->query(['a' => null]));
	}

	public function test_valid_page(): void
	{
		$pagination = $this->pagination(['total_items' => 25]);

		$this->assertTrue($pagination->valid_page(1));
		$this->assertTrue($pagination->valid_page('3'));
		$this->assertFalse($pagination->valid_page(4));
		$this->assertFalse($pagination->valid_page(0));
		$this->assertFalse($pagination->valid_page('-1'));
		$this->assertFalse($pagination->valid_page('1.5'));
		$this->assertFalse($pagination->valid_page('abc'));
		$this->assertFalse($pagination->valid_page(null));
	}

	/**
	 * Both views that come with the module render, and what a visitor
	 * put in the query string cannot become markup.
	 */
	public function test_render(): void
	{
		$pagination = $this->pagination(['total_items' => 25], ['page' => '2', 'x' => '"><script>alert(1)</script>']);

		foreach (['pagination/basic', 'pagination/floating'] as $view)
		{
			$html = $pagination->render($view);

			$this->assertStringContainsString('<strong>2</strong>', $html, $view);
			$this->assertStringContainsString('rel="next"', $html, $view);
			$this->assertStringContainsString('page=3', $html, $view);
			$this->assertStringNotContainsString('<script>', $html, $view);
			$this->assertStringNotContainsString('"><', $html, $view);
		}

		// The view of the config, as text, and a view object
		$this->assertSame($pagination->render('pagination/basic'), (string) $pagination);
		$this->assertSame($pagination->render('pagination/basic'), $pagination->render(View::factory('pagination/basic')));
	}

	public function test_one_page_is_hidden_unless_asked(): void
	{
		$pagination = $this->pagination(['total_items' => 5]);

		$this->assertSame('', $pagination->render());

		$pagination->setup(['auto_hide' => false]);

		$this->assertStringContainsString('<strong>1</strong>', $pagination->render());
	}

	public function test_settings_can_be_changed_afterwards(): void
	{
		$pagination = $this->pagination(['total_items' => 25], ['page' => '3']);

		$this->assertSame(3, $pagination->current_page);

		// Fewer items: the page that no longer exists becomes the last one
		$pagination->total_items = 15;

		$this->assertSame(2, $pagination->total_pages);
		$this->assertSame(2, $pagination->current_page);
		$this->assertNull($pagination->no_such_property);

		// A group of config/pagination.php
		$this->setEnvironment(['pagination.small' => ['items_per_page' => 5]]);
		$pagination->setup(['group' => 'small']);

		$this->assertSame(5, $pagination->items_per_page);
		$this->assertSame(3, $pagination->total_pages);
	}

	public function test_request_route_and_parameters(): void
	{
		$pagination = $this->pagination(['total_items' => 25]);
		$request    = $this->request('paged/other');
		$route      = new Route('archive(/<year>)');

		$this->assertSame('list', $pagination->request()->action());
		$this->assertSame($pagination, $pagination->request($request));
		$this->assertSame($request, $pagination->request());

		$this->assertSame($pagination, $pagination->route($route));
		$this->assertSame($route, $pagination->route());

		$this->assertSame($pagination, $pagination->route_params(['year' => '2026']));
		$this->assertSame(['year' => '2026'], $pagination->route_params());
		$this->assertSame('/archive/2026?page=2', $pagination->url(2));
	}

	public function test_request_is_needed(): void
	{
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('Pagination needs a request');

		Pagination::factory();
	}
}

/**
 * The controller of the requests of these tests: its actions do nothing.
 */
class Controller_Paged extends Controller
{
	public function action_list(): void
	{
	}

	public function action_other(): void
	{
	}
}
