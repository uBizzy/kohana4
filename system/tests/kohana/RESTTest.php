<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the REST controller with whole requests: which action runs for
 * which method, how the body of a request is read and how the answer is
 * formatted.
 *
 * @package    Kohana
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.rest')]
class Kohana_RESTTest extends Unittest_TestCase
{
	/**
	 * @var array  The routes as they were before the test
	 */
	protected $routes;

	public function setUp(): void
	{
		parent::setUp();

		$this->routes = (new ReflectionProperty('Route', '_routes'))->getValue();

		// The test route goes first, so that no other route takes the address
		(new ReflectionProperty('Route', '_routes'))->setValue(null, []);

		Route::set('kohana_rest_test', 'kohanaresttest(/<action>)(.<format>)')
			->defaults(['controller' => 'KohanaRestTest', 'action' => 'index']);

		Controller_KohanaRestTest::$ran = [];
		unset($_SERVER['CONTENT_TYPE']);
	}

	public function tearDown(): void
	{
		(new ReflectionProperty('Route', '_routes'))->setValue(null, $this->routes);
		unset($_SERVER['CONTENT_TYPE']);

		parent::tearDown();
	}

	/**
	 * Sends a request to the test controller and returns the answer.
	 */
	protected function send(string $method, string $uri = 'kohanaresttest', string $body = '', array $query = []): Response
	{
		return Request::factory($uri)->method($method)->query($query)->body($body)->execute();
	}

	/**
	 * The method of the request chooses the action, whatever the address says.
	 */
	public function test_method_chooses_the_action(): void
	{
		$expected = ['GET' => 'index', 'POST' => 'create', 'PUT' => 'update', 'DELETE' => 'delete'];

		foreach ($expected as $method => $action)
		{
			$response = $this->send($method);

			$this->assertSame(200, $response->status(), $method);
			$this->assertSame('{"action":"'.$action.'"}', $response->body(), $method);
		}

		// An action named in the address does not win over the method
		$this->assertSame('{"action":"index"}', $this->send('GET', 'kohanaresttest/delete')->body());
		$this->assertSame(['index', 'create', 'update', 'delete', 'index'], Controller_KohanaRestTest::$ran);
	}

	/**
	 * A method the controller does not know is answered with 405 and the
	 * list of methods; no action runs, also not the one of the address.
	 */
	public function test_unknown_method_runs_no_action(): void
	{
		foreach (['kohanaresttest', 'kohanaresttest/delete'] as $uri)
		{
			$response = $this->send('PATCH', $uri);

			$this->assertSame(405, $response->status(), $uri);
			$this->assertSame('GET, PUT, POST, DELETE', (string) $response->headers('allow'), $uri);
		}

		$this->assertSame([], Controller_KohanaRestTest::$ran);
	}

	/**
	 * The question a browser asks before a request from another site (a
	 * "preflight") is answered with headers only: no action runs.
	 */
	public function test_preflight_runs_no_action(): void
	{
		$this->setEnvironment(['cors.origins' => ['https://app.example.com']]);

		$preflight = Request::factory('kohanaresttest/delete')
			->method('OPTIONS')
			->headers('origin', 'https://app.example.com')
			->headers('access-control-request-method', 'DELETE');

		$response = $preflight->execute();

		$this->assertSame(204, $response->status());
		$this->assertSame('', $response->body());
		$this->assertSame('https://app.example.com', (string) $response->headers('access-control-allow-origin'));
		$this->assertSame([], Controller_KohanaRestTest::$ran);

		// From a site that is not allowed it is a method like any unknown one
		$other = Request::factory('kohanaresttest/delete')
			->method('OPTIONS')
			->headers('origin', 'https://evil.example.com')
			->headers('access-control-request-method', 'DELETE')
			->execute();

		$this->assertSame(405, $other->status());
		$this->assertNull($other->headers('access-control-allow-origin'));
		$this->assertSame([], Controller_KohanaRestTest::$ran);
	}

	public function test_answer_is_json_by_default_and_never_cached_after_a_change(): void
	{
		$get = $this->send('GET');

		$this->assertSame('application/json', (string) $get->headers('content-type'));
		$this->assertNull($get->headers('cache-control'));

		$post = $this->send('POST');

		$this->assertSame('no-cache, no-store, max-age=0, must-revalidate', (string) $post->headers('cache-control'));
	}

	/**
	 * Values of the answer are written as data: text of a visitor cannot
	 * become markup in XML.
	 */
	public function test_xml_format(): void
	{
		$response = $this->send('GET', 'kohanaresttest/index.xml', '', ['echo' => '<b>&"']);

		$this->assertSame('application/xml', (string) $response->headers('content-type'));

		$xml = simplexml_load_string($response->body());

		$this->assertSame('index', (string) $xml->action);
		$this->assertSame('<b>&"', (string) $xml->echo);
		$this->assertSame(['one', 'two'], array_map('strval', iterator_to_array($xml->list->item, false)));
		$this->assertStringNotContainsString('<b>', $response->body());
	}

	/**
	 * A key of the data cannot be read as markup either: what cannot be
	 * part of a name becomes "_".
	 */
	public function test_xml_names_are_made_safe(): void
	{
		$xml = REST_Format_XML::encode([
			'first name'      => 'Ana',
			'9lives'          => 'yes',
			'a><b'            => 'x',
			''                => 'empty',
			'list'            => [['id' => 1], ['id' => 2]],
			'nothing'         => null,
			'ampersand'       => 'Tom & Jerry',
		]);

		$read = simplexml_load_string($xml);

		$this->assertSame('Ana', (string) $read->first_name);
		$this->assertSame('yes', (string) $read->_9lives);
		$this->assertSame('x', (string) $read->a__b);
		$this->assertSame('empty', (string) $read->item);
		$this->assertSame('2', (string) $read->list->item[1]->id);
		$this->assertSame('', (string) $read->nothing);
		$this->assertSame('Tom & Jerry', (string) $read->ampersand);
		$this->assertStringContainsString('<root/>', REST_Format_XML::encode([]));
	}

	/**
	 * CSV: the names of the values are the first line, rows need not
	 * have the same values, and quotes, commas and line breaks stay
	 * inside their cell.
	 */
	public function test_csv_format(): void
	{
		$csv = REST_Format_CSV::encode([
			['name' => 'Ana', 'city' => 'Lisboa, Portugal', 'note' => 'says "olá"'],
			['name' => 'Bea', 'note' => "two\nlines", 'age' => 30],
			['name' => 'Cleo', 'tags' => ['a', 'b'], 'active' => true, 'gone' => false, 'age' => null],
		]);

		$this->assertSame(
			"name,city,note,age,tags,active,gone\r\n"
			."Ana,\"Lisboa, Portugal\",\"says \"\"olá\"\"\",,,,\r\n"
			."Bea,,\"two\nlines\",30,,,\r\n"
			."Cleo,,,,\"[\"\"a\"\",\"\"b\"\"]\",1,0\r\n",
			$csv
		);

		// Read back, it is the same table
		$lines = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), explode("\r\n", trim(str_replace("two\nlines", 'two lines', $csv))));

		$this->assertSame(['Bea', '', 'two lines', '30', '', '', ''], $lines[2]);

		// One row alone, a list without names, and nothing at all
		$this->assertSame("id,name\r\n5,Ana\r\n", REST_Format_CSV::encode(['id' => 5, 'name' => 'Ana']));
		$this->assertSame("1,2\r\n3,4\r\n", REST_Format_CSV::encode([[1, 2], [3, 4]]));
		$this->assertSame('', REST_Format_CSV::encode([]));
		$this->assertSame("a\r\n1\r\n2\r\n", REST_Format_CSV::encode(new ArrayIterator([new ArrayIterator(['a' => 1]), (object) ['a' => 2]])));
	}

	/**
	 * Text that a spreadsheet program would run as a formula is marked
	 * as text; numbers stay numbers.
	 */
	public function test_csv_does_not_carry_formulas(): void
	{
		$csv = REST_Format_CSV::encode([[
			'=HYPERLINK("http://evil.example.com","click")',
			'+1+1',
			'-2+3',
			'@SUM(A1:A9)',
			"\t=1+1",
			'-5',
			'+3.2',
			-7,
			'plain = text',
			'=cmd|\' /C calc\'!A0',
		]]);

		$cells = str_getcsv(trim($csv), ',', '"', '');

		$this->assertSame([
			'\'=HYPERLINK("http://evil.example.com","click")',
			"'+1+1",
			"'-2+3",
			"'@SUM(A1:A9)",
			"'\t=1+1",
			'-5',
			'+3.2',
			'-7',
			'plain = text',
			"'=cmd|' /C calc'!A0",
		], $cells);

		// The name of a column is a cell like any other
		$this->assertStringStartsWith("'=evil", REST_Format_CSV::encode([['=evil' => 1]]));
	}

	public function test_csv_settings(): void
	{
		$this->setEnvironment(['REST_Format_CSV::$delimiter' => ';', 'REST_Format_CSV::$bom' => true]);

		$this->assertSame("\xEF\xBB\xBFname;price\r\nCafé;\"1;50\"\r\n", REST_Format_CSV::encode([['name' => 'Café', 'price' => '1;50']]));
	}

	public function test_csv_from_the_address(): void
	{
		$response = $this->send('GET', 'kohanaresttest/index.csv', '', ['echo' => 'a,b']);

		$this->assertSame('text/csv', (string) $response->headers('content-type'));
		$this->assertSame("action,echo,list\r\nindex,\"a,b\",\"[\"\"one\"\",\"\"two\"\"]\"\r\n", $response->body());
	}

	public static function provider_accept_headers(): array
	{
		return [
			// Accept header of the request => content type of the answer
			'none'                         => [null, 'application/json'],
			'anything'                     => ['*/*', 'application/json'],
			'JSON'                         => ['application/json', 'application/json'],
			'XML'                          => ['application/xml', 'application/xml'],
			'XML, the older name'          => ['text/xml', 'application/xml'],
			'CSV'                          => ['text/csv', 'text/csv'],
			'CSV before JSON'              => ['application/json;q=0.5, text/csv', 'text/csv'],
			'JSON before CSV'              => ['text/csv;q=0.5, application/json', 'application/json'],
			'what a script library sends'  => ['application/json, text/plain, */*', 'application/json'],
			'what a browser sends'         => ['text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'application/json'],
			'something that is not there'  => ['application/pdf', 'application/json'],
			'nonsense'                     => ['<script>', 'application/json'],
		];
	}

	/**
	 * Without a format in the address, the client can ask for one.
	 */
	#[DataProvider('provider_accept_headers')]
	public function test_format_from_the_accept_header(?string $accept, string $expected): void
	{
		$request = Request::factory('kohanaresttest');

		if ($accept !== null)
		{
			$request->headers('accept', $accept);
		}

		$response = $request->execute();

		$this->assertSame(200, $response->status());
		$this->assertSame($expected, (string) $response->headers('content-type'));
		$this->assertSame('Origin, Accept', (string) $response->headers('vary'), 'Caches are told that the answer depends on the header');
	}

	/**
	 * The address decides when it names a format: the header is not looked at.
	 */
	public function test_format_of_the_address_wins(): void
	{
		$response = Request::factory('kohanaresttest/index.xml')->headers('accept', 'text/csv')->execute();

		$this->assertSame('application/xml', (string) $response->headers('content-type'));
		$this->assertSame('Origin', (string) $response->headers('vary'));
	}

	public function test_json_format_escapes_nothing_into_markup(): void
	{
		$response = $this->send('GET', 'kohanaresttest/index.json', '', ['echo' => '<b>&"']);

		$this->assertSame(['action' => 'index', 'echo' => '<b>&"', 'list' => ['one', 'two']], json_decode($response->body(), true));
	}

	/**
	 * A format without formatter is an error of the request, and no class
	 * is made from what the visitor typed.
	 */
	public function test_unknown_format_fails(): void
	{
		$this->assertSame(500, $this->send('GET', 'kohanaresttest/index.exe')->status());

		// The formatters of the framework are found, in small letters too
		$this->assertSame(200, $this->send('GET', 'kohanaresttest/index.JSON')->status());
	}

	/**
	 * The body of a request is read as JSON or as a form, as its content
	 * type says.
	 */
	public function test_request_body_is_read(): void
	{
		$_SERVER['CONTENT_TYPE'] = 'application/json; charset=utf-8';

		$this->assertSame('{"action":"create","name":"Ana"}', $this->send('POST', 'kohanaresttest', '{"name":"Ana"}')->body());

		$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

		$this->assertSame('{"action":"update","name":"Bea"}', $this->send('PUT', 'kohanaresttest', 'name=Bea')->body());
	}

	/**
	 * JSON that cannot be read is a mistake of the client (400), not an
	 * error of the server.
	 */
	public function test_broken_json_body_is_a_bad_request(): void
	{
		$_SERVER['CONTENT_TYPE'] = 'application/json';

		$this->assertSame(400, $this->send('POST', 'kohanaresttest', '{"name":')->status());
	}

	/**
	 * For clients that cannot read a status: the status goes into the
	 * body and the answer itself is 200.
	 */
	public function test_suppress_response_codes(): void
	{
		$response = $this->send('GET', 'kohanaresttest', '', ['fail' => '1', 'suppressResponseCodes' => '1']);

		$this->assertSame(200, $response->status());
		$this->assertSame(['action' => 'index', 'responseCode' => 404], json_decode($response->body(), true));

		$this->assertSame(404, $this->send('GET', 'kohanaresttest', '', ['fail' => '1'])->status());
	}
}

/**
 * A REST controller that answers with the name of the action that ran
 * and the values it was sent.
 */
class Controller_KohanaRestTest extends Controller_REST
{
	/**
	 * @var array<int, string>  The actions that ran, in order
	 */
	public static array $ran = [];

	protected function answer(string $action): void
	{
		static::$ran[] = $action;

		$params = $this->_parse_params();
		$data   = ['action' => $action];

		foreach (['name', 'echo'] as $key)
		{
			if (isset($params[$key]))
			{
				$data[$key] = $params[$key];
			}
		}

		if (isset($params['echo']))
		{
			$data['list'] = ['one', 'two'];
		}

		if (isset($params['fail']))
		{
			$this->response->status(404);
		}

		$this->response->body($data);
	}

	public function action_index(): void
	{
		$this->answer('index');
	}

	public function action_create(): void
	{
		$this->answer('create');
	}

	public function action_update(): void
	{
		$this->answer('update');
	}

	public function action_delete(): void
	{
		$this->answer('delete');
	}
}
