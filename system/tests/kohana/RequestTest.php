<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for request class
 *
 * @package    Kohana
 * @category   Tests
 *
 * @author     BRMatt <matthew@sigswitch.com>
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.request')]
class Kohana_RequestTest extends Unittest_TestCase
{
	/** @var Request|null  The initial request, saved in setUp() and restored in tearDown() */
	protected $_initial_request;

	protected $_inital_request;

	// @codingStandardsIgnoreStart
	public function setUp(): void
	// @codingStandardsIgnoreEnd
	{
		parent::setUp();
		Kohana::$config->load('url')->set('trusted_hosts', ['localhost']);
		$this->_initial_request = Request::$initial;
		Request::$initial = new Request('/');
	}

	// @codingStandardsIgnoreStart
	public function tearDown(): void
	// @codingStandardsIgnoreEnd
	{
		Request::$initial = $this->_initial_request;
		parent::tearDown();
	}

	public function test_initial()
	{
		$this->setEnvironment([
			'Request::$initial' => null,
			'Request::$client_ip' => '0.0.0.0',
			'Request::$user_agent' => '',
			'_SERVER' => [
				'HTTPS' => null,
				'PATH_INFO' => '/',
				'HTTP_REFERER' => 'http://example.com/',
				'HTTP_USER_AGENT' => 'whatever (Mozilla 5.0/compatible)',
				'REMOTE_ADDR' => '127.0.0.1',
				'REQUEST_METHOD' => 'GET',
				'HTTP_X_REQUESTED_WITH' => 'ajax-or-something',
			],
			'_GET' => [],
			'_POST' => [],
		]);

		$request = Request::factory();

		$this->assertEquals(Request::$initial, $request);

		$this->assertEquals(Request::$client_ip, '127.0.0.1');

		$this->assertEquals(Request::$user_agent, 'whatever (Mozilla 5.0/compatible)');

		$this->assertEquals($request->protocol(), 'HTTP/1.1');

		$this->assertEquals($request->referrer(), 'http://example.com/');

		$this->assertEquals($request->requested_with(), 'ajax-or-something');

		$this->assertEquals($request->query(), []);

		$this->assertEquals($request->post(), []);
	}

	/**
	 * Provides server environments and the client IP that must be detected.
	 * In every case the only trusted proxies are 10.0.0.1 and 10.1.0.0/16.
	 *
	 * @return array of $server, $expected, $message
	 */
	public static function provider_client_ips()
	{
		return [
			[
				['REMOTE_ADDR' => '203.0.113.9'],
				'203.0.113.9',
				'A direct connection is the client',
			],
			[
				['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4', 'HTTP_CLIENT_IP' => '1.2.3.4'],
				'203.0.113.9',
				'Headers from an untrusted address must be ignored (they can be forged)',
			],
			[
				['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'],
				'198.51.100.7',
				'A trusted proxy passes the client on in X-Forwarded-For',
			],
			[
				['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.7, 10.1.2.3'],
				'198.51.100.7',
				'The list is read from the right, skipping trusted proxies: 6.6.6.6 was written by the client',
			],
			[
				['REMOTE_ADDR' => '10.1.200.4', 'HTTP_X_REAL_IP' => '198.51.100.8'],
				'198.51.100.8',
				'A proxy inside a trusted network (CIDR) is trusted',
			],
			[
				['REMOTE_ADDR' => '10.0.0.1', 'HTTP_CF_CONNECTING_IP' => '2001:db8::7', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'],
				'2001:db8::7',
				'Headers are tried in the configured order, and IPv6 is accepted',
			],
			[
				['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => 'not an ip <script>'],
				'10.0.0.1',
				'A header that does not hold an address is not used',
			],
			[
				['REMOTE_ADDR' => '10.0.0.1', 'HTTP_CLIENT_IP' => '198.51.100.9'],
				'198.51.100.9',
				'Client-IP is used when nothing better is present',
			],
			[
				[],
				'0.0.0.0',
				'Without REMOTE_ADDR (command line) there is no client',
			],
		];
	}

	/**
	 * Tests client IP detection
	 *
	 * @covers Request::factory
	 * @covers Request::is_trusted_proxy
	 */
	#[DataProvider('provider_client_ips')]
	public function test_client_ips(array $server, $expected, $message)
	{
		$this->setEnvironment([
			'Request::$initial' => null,
			'Request::$client_ip' => '0.0.0.0',
			'Request::$trusted_proxies' => ['10.0.0.1', '10.1.0.0/16'],
			'_SERVER' => $server + ['HTTPS' => null, 'PATH_INFO' => '/'],
		]);

		Request::factory();

		$this->assertSame($expected, Request::$client_ip, $message);
	}

	/**
	 * Tests that the allow_external flag prevents an external request.
	 *
	 * @return null
	 */
	public function test_disable_external_tests()
	{
		$this->setEnvironment(
			[
				'Request::$initial' => null,
			]
		);

		$request = new Request('http://www.google.com/', [], false);

		$this->assertEquals(false, $request->is_external());
	}

	/**
	 * Provides the data for test_create()
	 * @return  array
	 */
	public static function provider_create()
	{
		return [
			['foo/bar', 'Request_Client_Internal'],
			['http://google.com', 'Request_Client_External'],
		];
	}

	/**
	 * Ensures the create class is created with the correct client
	 */
	#[DataProvider('provider_create')]
	public function test_create($uri, $client_class)
	{
		$request = Request::factory($uri);

		$this->assertInstanceOf($client_class, $request->client());
	}

	/**
	 * Ensure that parameters can be read
	 */
	public function test_param()
	{
		$route = new Route('(<controller>(/<action>(/<id>)))');

		$uri = 'kohana_requesttest_dummy/foobar/some_id';
		$request = Request::factory($uri, null, true, [$route]);

		// We need to execute the request before it has matched a route
		$response = $request->execute();
		$controller = new Controller_Kohana_RequestTest_Dummy($request, $response);

		$this->assertSame(200, $response->status());
		$this->assertSame($controller->get_expected_response(), $response->body());
		$this->assertArrayHasKey('id', $request->param());
		$this->assertArrayNotHasKey('foo', $request->param());
		$this->assertEquals($request->uri(), $uri);

		// Ensure the params do not contain contamination from controller, action, route, uri etc etc
		$params = $request->param();

		// Test for illegal components
		$this->assertArrayNotHasKey('controller', $params);
		$this->assertArrayNotHasKey('action', $params);
		$this->assertArrayNotHasKey('directory', $params);
		$this->assertArrayNotHasKey('uri', $params);
		$this->assertArrayNotHasKey('route', $params);

		$route = new Route('(<uri>)', ['uri' => '.+']);
		$route->defaults(['controller' => 'kohana_requesttest_dummy', 'action' => 'foobar']);
		$request = Request::factory('kohana_requesttest_dummy', null, true, [$route]);

		// We need to execute the request before it has matched a route
		$response = $request->execute();
		$controller = new Controller_Kohana_RequestTest_Dummy($request, $response);

		$this->assertSame(200, $response->status());
		$this->assertSame($controller->get_expected_response(), $response->body());
		$this->assertSame('kohana_requesttest_dummy', $request->param('uri'));
	}

	/**
	 * Tests Request::method()
	 */
	public function test_method()
	{
		$request = Request::factory('foo/bar');

		$this->assertEquals($request->method(), 'GET');
		$this->assertEquals(($request->method('post') === $request), true);
		$this->assertEquals(($request->method() === 'POST'), true);
	}

	/**
	 * @covers Request::json
	 */
	public function test_json_reads_the_body()
	{
		$request = Request::factory('foo/bar')->body('{"user":{"name":"Ana","tags":["a","b"]},"n":0}');

		$this->assertSame(['user' => ['name' => 'Ana', 'tags' => ['a', 'b']], 'n' => 0], $request->json());
		$this->assertSame('Ana', $request->json('user.name'));
		$this->assertSame('b', $request->json('user.tags.1'));
		$this->assertSame(0, $request->json('n', 5));
		$this->assertSame('default', $request->json('user.email', 'default'));
		$this->assertNull($request->json('missing'));
	}

	/**
	 * @covers Request::json
	 */
	public function test_json_of_an_empty_body_is_empty()
	{
		$request = Request::factory('foo/bar');

		$this->assertSame([], $request->json());
		$this->assertSame('default', $request->json('anything', 'default'));
	}

	/**
	 * A body that is not JSON is the client's error: 400 Bad Request.
	 *
	 * @covers Request::json
	 */
	public function test_json_of_an_invalid_body_is_a_bad_request()
	{
		$this->expectException(HTTP_Exception_400::class);

		Request::factory('foo/bar')->body('{"user":')->json();
	}

	/**
	 * Tests Request::route()
	 */
	public function test_route()
	{
		$request = Request::factory(''); // This should always match something, no matter what changes people make

		// We need to execute the request before it has matched a route
		try
		{
			$request->execute();
		}
		catch (Exception $e) {}

		$this->assertInstanceOf('Route', $request->route());
	}

	/**
	 * Tests Request::route()
	 */
	public function test_route_is_not_set_before_execute()
	{
		$request = Request::factory(''); // This should always match something, no matter what changes people make

		// The route should be null since the request has not been executed yet
		$this->assertEquals($request->route(), null);
	}


	/**
	 * Provides test data for Request::url()
	 * @return array
	 */
	public static function provider_url()
	{
		return [
			[
				'foo/bar',
				'http',
				'http://localhost/kohana/foo/bar'
			],
			[
				'foo',
				'http',
				'http://localhost/kohana/foo'
			],
			[
				'http://www.google.com',
				'http',
				'http://www.google.com'
			],
			[
				'0',
				'http',
				'http://localhost/kohana/0'
			]
		];
	}

	/**
	 * Tests Request::url()
	 *
	 * @covers Request::url
	 * @param string $uri the uri to use
	 * @param string $protocol the protocol to use
	 * @param array $expected The string we expect
	 */
	#[DataProvider('provider_url')]
	public function test_url($uri, $protocol, $expected)
	{
		if ( ! isset($_SERVER['argc']))
		{
			$_SERVER['argc'] = 1;
		}

		$this->setEnvironment([
			'Kohana::$base_url'  => '/kohana/',
			'_SERVER'            => ['HTTP_HOST' => 'localhost', 'argc' => $_SERVER['argc']],
			'Kohana::$index_file' => false,
		]);

		// issue #3967: inject the route so that we don't conflict with the application's default route
		$route = new Route('(<controller>(/<action>))');
		$route->defaults([
			'controller' => 'welcome',
			'action'     => 'index',
		]);

		$this->assertEquals(Request::factory($uri, [], true, [$route])->url($protocol), $expected);
	}

	/**
	 * Data provider for test_set_protocol() test
	 *
	 * @return array
	 */
	public static function provider_set_protocol()
	{
		return [
			[
				'http/1.1',
				'HTTP/1.1',
			],
			[
				'ftp',
				'FTP',
			],
			[
				'hTTp/1.0',
				'HTTP/1.0',
			],
		];
	}

	/**
	 * Tests the protocol() method
	 *
	 * @return null
	 */
	#[DataProvider('provider_set_protocol')]
	public function test_set_protocol($protocol, $expected)
	{
		$request = Request::factory();

		// Set the supplied protocol
		$result = $request->protocol($protocol);

		// Test the set value
		$this->assertSame($expected, $request->protocol());

		// Test the return value
		$this->assertTrue($request instanceof $result);
	}

	/**
	 * Provides data for test_post_max_size_exceeded()
	 *
	 * @return  array
	 */
	public static function provider_post_max_size_exceeded()
	{
		// Get the post max size
		$post_max_size = Num::bytes(ini_get('post_max_size'));

		return [
			[
				$post_max_size+200000,
				true
			],
			[
				$post_max_size-20,
				false
			],
			[
				$post_max_size,
				false
			]
		];
	}

	/**
	 * Tests the post_max_size_exceeded() method
	 *
	 * @param   int      content_length
	 * @param   bool     expected
	 * @return  void
	 */
	#[DataProvider('provider_post_max_size_exceeded')]
	public function test_post_max_size_exceeded($content_length, $expected)
	{
		// Ensure the request method is set to POST
		Request::$initial->method(HTTP_Request::POST);

		// Set the content length
		$_SERVER['CONTENT_LENGTH'] = $content_length;

		// Test the post_max_size_exceeded() method
		$this->assertSame(Request::post_max_size_exceeded(), $expected);
	}

	/**
	 * Provides data for test_uri_only_trimed_on_internal()
	 *
	 * @return  array
	 */
	public static function provider_uri_only_trimed_on_internal()
	{
		// issue #3967: inject the route so that we don't conflict with the application's default route
		$route = new Route('(<controller>(/<action>))');
		$route->defaults([
			'controller' => 'welcome',
			'action'     => 'index',
		]);

		$old_request = Request::$initial;
		Request::$initial = new Request(true, [], true, [$route]);

		$result = [
			[
				new Request('http://www.google.com'),
				'http://www.google.com'
			],
			[
				new Request('http://www.google.com/'),
				'http://www.google.com/'
			],
			[
				new Request('foo/bar/'),
				'foo/bar'
			],
			[
				new Request('foo/bar'),
				'foo/bar'
			],
			[
				new Request('/0'),
				'0'
			],
			[
				new Request('0'),
				'0'
			],
			[
				new Request('/'),
				'/'
			],
			[
				new Request(''),
				'/'
			]
		];

		Request::$initial = $old_request;
		return $result;
	}

	/**
	 * Tests that the uri supplied to Request is only trimed
	 * for internal requests.
	 *
	 * @return void
	 */
	#[DataProvider('provider_uri_only_trimed_on_internal')]
	public function test_uri_only_trimed_on_internal(Request $request, $expected)
	{
		$this->assertSame($request->uri(), $expected);
	}

	/**
	 * Data provider for test_options_set_to_external_client()
	 *
	 * @return  array
	 */
	public static function provider_options_set_to_external_client()
	{
		$provider = [
			[
				[
					CURLOPT_PROXYPORT   => 8080,
					CURLOPT_PROXYTYPE   => CURLPROXY_HTTP,
					CURLOPT_VERBOSE     => true
				],
				[
					CURLOPT_PROXYPORT   => 8080,
					CURLOPT_PROXYTYPE   => CURLPROXY_HTTP,
					CURLOPT_VERBOSE     => true
				]
			]
		];

		return $provider;
	}

	/**
	 * Test for Request_Client_External::options() to ensure options
	 * can be set to the external client (for cURL and PECL_HTTP)
	 *
	 * @param   array    settings
	 * @param   array    expected
	 * @return void
	 */
	#[DataProvider('provider_options_set_to_external_client')]
	public function test_options_set_to_external_client($settings, $expected)
	{
		$request_client = Request_Client_External::factory([], 'Request_Client_Curl');

		// Test for empty array
		$this->assertSame([], $request_client->options());

		// Test that set works as expected
		$this->assertSame($request_client->options($settings), $request_client);

		// Test that each setting is present and returned
		foreach ($expected as $key => $value)
		{
			$this->assertSame($request_client->options($key), $value);
		}
	}

	/**
	 * Provides data for test_headers_get()
	 *
	 * @return  array
	 */
	public static function provider_headers_get()
	{
		$x_powered_by = 'Kohana Unit Test';
		$content_type = 'application/x-www-form-urlencoded';
		$request = new Request('foo/bar', [], true, []);

		return [
			[
				$request->headers([
						'x-powered-by' => $x_powered_by,
						'content-type' => $content_type
					]
				),
				[
					'x-powered-by' => $x_powered_by,
					'content-type' => $content_type
				]
			]
		];
	}

	/**
	 * Tests getting headers from the Request object
	 *
	 * @param   Request  request to test
	 * @param   array    headers to test against
	 * @return  void
	 */
	#[DataProvider('provider_headers_get')]
	public function test_headers_get($request, $headers)
	{
		foreach ($headers as $key => $expected_value)
		{
			$this->assertSame( (string) $request->headers($key), $expected_value);
		}
	}

	/**
	 * Provides data for test_headers_set
	 *
	 * @return  array
	 */
	public static function provider_headers_set()
	{
		return [
			[
				[
					'content-type'  => 'application/x-www-form-urlencoded',
					'x-test-header' => 'foo'
				],
				"Content-Type: application/x-www-form-urlencoded\r\nX-Test-Header: foo\r\n\r\n"
			],
			[
				[
					'content-type'  => 'application/json',
					'x-powered-by'  => 'kohana'
				],
				"Content-Type: application/json\r\nX-Powered-By: kohana\r\n\r\n"
			]
		];
	}

	/**
	 * Tests the setting of headers to the request object
	 *
	 * @param   array      header(s) to set to the request object
	 * @param   string     expected http header
	 * @return  void
	 */
	#[DataProvider('provider_headers_set')]
	public function test_headers_set($headers, $expected)
	{
		$request = new Request(true, [], true, []);
		$request->headers($headers);
		$this->assertSame($expected, (string) $request->headers());
	}

	/**
	 * Provides test data for test_query_parameter_parsing()
	 *
	 * @return  array
	 */
	public static function provider_query_parameter_parsing()
	{
		return [
			[
				'foo/bar',
				[
					'foo'   => 'bar',
					'sna'   => 'fu'
				],
				[
					'foo'   => 'bar',
					'sna'   => 'fu'
				],
			],
			[
				'foo/bar?john=wayne&peggy=sue',
				[
					'foo'   => 'bar',
					'sna'   => 'fu'
				],
				[
					'john'  => 'wayne',
					'peggy' => 'sue',
					'foo'   => 'bar',
					'sna'   => 'fu'
				],
			],
			[
				'http://host.tld/foo/bar?john=wayne&peggy=sue',
				[
					'foo'   => 'bar',
					'sna'   => 'fu'
				],
				[
					'john'  => 'wayne',
					'peggy' => 'sue',
					'foo'   => 'bar',
					'sna'   => 'fu'
				],
			],
		];
	}

	/**
	 * Tests that query parameters are parsed correctly
	 *
	 * @param   string    url
	 * @param   array     query
	 * @param   array    expected
	 * @return  void
	 */
	#[DataProvider('provider_query_parameter_parsing')]
	public function test_query_parameter_parsing($url, $query, $expected)
	{
		Request::$initial = null;

		$request = new Request($url);

		foreach ($query as $key => $value)
		{
			$request->query($key, $value);
		}

		$this->assertSame($expected, $request->query());
	}

	/**
	 * Tests that query parameters are parsed correctly
	 *
	 * @param   string    url
	 * @param   array     query
	 * @param   array    expected
	 * @return  void
	 */
	#[DataProvider('provider_query_parameter_parsing')]
	public function test_query_parameter_parsing_in_subrequest($url, $query, $expected)
	{
		Request::$initial = new Request(true);

		$request = new Request($url);

		foreach ($query as $key => $value)
		{
			$request->query($key, $value);
		}

		$this->assertSame($expected, $request->query());
	}

	/**
	 * Provides data for test_client
	 *
	 * @return  array
	 */
	public static function provider_client()
	{
		$internal_client = new Request_Client_Internal;
		$external_client = new Request_Client_Stream;

		return [
			[
				new Request('http://koseven.dev/'),
				$internal_client,
				$internal_client
			],
			[
				new Request('foo/bar'),
				$external_client,
				$external_client
			]
		];
	}

	/**
	 * Tests the getter/setter for request client
	 *
	 * @param   Request $request
	 * @param   Request_Client $client
	 * @param   Request_Client $expected
	 * @return  void
	 */
	#[DataProvider('provider_client')]
	public function test_client(Request $request, Request_Client $client, Request_Client $expected)
	{
		$request->client($client);
		$this->assertSame($expected, $request->client());
	}

	/**
	 * Tests that the Request constructor passes client params on to the
	 * Request_Client once created.
	 */
	public function test_passes_client_params()
	{
		$request = Request::factory('http://example.com/', [
			'follow' => true,
			'strict_redirect' => false
		]);

		$client = $request->client();

		$this->assertEquals($client->follow(), true);
		$this->assertEquals($client->strict_redirect(), false);
	}

	/**
	 * Tests correctness request content-length header after calling render
	 */
	public function test_content_length_after_render()
	{
		$request = Request::factory('https://example.org/post')
			->client(new Kohana_RequestTest_Header_Spying_Request_Client_External)
			->method(Request::POST)
			->post(['aaa' => 'bbb']);

		$request->render();

		$request->execute();

		$headers = $request->client()->get_received_request_headers();

		$this->assertEquals(strlen($request->body()), $headers['content-length']);
	}

	/**
	 * Tests correctness request content-length header after calling render
	 * and changing post
	 */
	public function test_content_length_after_changing_post()
	{
		$request = Request::factory('https://example.org/post')
			->client(new Kohana_RequestTest_Header_Spying_Request_Client_External)
			->method(Request::POST)
			->post(['aaa' => 'bbb']);

		$request->render();

		$request->post(['one' => 'one', 'two' => 'two', 'three' => 'three']);

		$request->execute();

		$headers = $request->client()->get_received_request_headers();

		$this->assertEquals(strlen($request->body()), $headers['content-length']);
	}

	public static function provider_trusted_proxies(): array
	{
		return [
			// address of the caller, list of trusted proxies => trusted?
			'the address itself'           => ['10.0.0.1', ['10.0.0.1'], true],
			'another address'              => ['10.0.0.2', ['10.0.0.1'], false],
			'inside a /16'                 => ['10.1.200.7', ['10.1.0.0/16'], true],
			'outside a /16'                => ['10.2.0.1', ['10.1.0.0/16'], false],
			'inside a /25, upper half'     => ['10.1.2.200', ['10.1.2.128/25'], true],
			'outside a /25, lower half'    => ['10.1.2.100', ['10.1.2.128/25'], false],
			'last address of a /22'        => ['192.168.7.255', ['192.168.4.0/22'], true],
			'first address after a /22'    => ['192.168.8.0', ['192.168.4.0/22'], false],
			'a /32 is one address'         => ['10.0.0.1', ['10.0.0.1/32'], true],
			'a /32 is only that address'   => ['10.0.0.2', ['10.0.0.1/32'], false],
			'a /0 is every address'        => ['203.0.113.9', ['0.0.0.0/0'], true],
			'more bits than there are'     => ['10.0.0.1', ['10.0.0.1/99'], true],
			'IPv6 inside a /33'            => ['2001:db8:7fff::1', ['2001:db8::/33'], true],
			'IPv6 outside a /33'           => ['2001:db8:8000::1', ['2001:db8::/33'], false],
			'IPv6 written another way'     => ['::1', ['0:0:0:0:0:0:0:1/128'], true],
			'IPv4 against an IPv6 network' => ['10.0.0.1', ['::/0'], false],
			'IPv6 against an IPv4 network' => ['::1', ['0.0.0.0/0'], false],
			'a network that is no number'  => ['10.0.0.1', ['10.0.0.0/abc'], false],
			'a negative number of bits'    => ['10.0.0.1', ['10.0.0.0/-8'], false],
			'a network that is no address' => ['10.0.0.1', ['proxy.example.com/8'], false],
			'a name, listed as it is'      => ['proxy.example.com', ['proxy.example.com'], true],
			'a name against a network'     => ['proxy.example.com', ['10.0.0.0/8'], false],
			'no address'                   => [null, ['0.0.0.0/0'], false],
			'an empty address'             => ['', ['0.0.0.0/0', ''], false],
			'nobody is trusted'            => ['10.0.0.1', [], false],
		];
	}

	/**
	 * Which callers may say who the visitor is: an exact address, or a
	 * network, down to the single bit.
	 *
	 * @covers Request::is_trusted_proxy
	 */
	#[DataProvider('provider_trusted_proxies')]
	public function test_is_trusted_proxy(?string $ip, array $trusted, bool $expected): void
	{
		$this->setEnvironment(['Request::$trusted_proxies' => $trusted]);

		$this->assertSame($expected, Request::is_trusted_proxy($ip));
	}

	/**
	 * The same check against any list: an entry that is not text is
	 * skipped, an empty list or no address fits nothing.
	 */
	public function test_ip_in_list(): void
	{
		$this->assertTrue(Request::ip_in_list('203.0.113.7', ['198.51.100.1', '203.0.113.7']));
		$this->assertTrue(Request::ip_in_list('10.200.3.4', [null, ['10.200.3.4'], '10.0.0.0/8']));
		$this->assertTrue(Request::ip_in_list('2001:db8:1::7', ['2001:db8::/32']));
		$this->assertFalse(Request::ip_in_list('203.0.113.70', ['203.0.113.7']));
		$this->assertFalse(Request::ip_in_list('203.0.113.7', []));
		$this->assertFalse(Request::ip_in_list('203.0.113.7', [true, 203, ['203.0.113.7']]));
		$this->assertFalse(Request::ip_in_list('203.0.113.7', ['::/0']), 'IPv4 and IPv6 never fit each other');
		$this->assertFalse(Request::ip_in_list(null, ['0.0.0.0/0']));
		$this->assertFalse(Request::ip_in_list('', ['0.0.0.0/0']));
	}

	public static function provider_detected_uris(): array
	{
		return [
			// what the web server says => the address inside the application
			'PATH_INFO wins'             => [['PATH_INFO' => '/items/list', 'REQUEST_URI' => '/other'], '/items/list'],
			'REQUEST_URI without query'  => [['REQUEST_URI' => '/shop/index.php/items/list?page=2'], '/items/list'],
			'REQUEST_URI is decoded'     => [['REQUEST_URI' => '/shop/items/my%20list'], 'items/my list'],
			'REQUEST_URI outside base'   => [['REQUEST_URI' => '/blog/post'], '/blog/post'],
			'REQUEST_URI that is a list' => [['REQUEST_URI' => ['/items']], ''],
			'PHP_SELF'                   => [['PHP_SELF' => '/shop/index.php/items/list'], '/items/list'],
			'PHP_SELF that is a list'    => [['PHP_SELF' => ['/items']], ''],
			'REDIRECT_URL'               => [['REDIRECT_URL' => '/shop/items/list'], 'items/list'],
		];
	}

	/**
	 * Takes the four values the address is read from out of $_SERVER,
	 * puts the given ones in, and returns the ones that were there.
	 */
	protected function server_address(array $server): array
	{
		$keys   = ['PATH_INFO', 'REQUEST_URI', 'PHP_SELF', 'REDIRECT_URL'];
		$before = array_intersect_key($_SERVER, array_flip($keys));

		foreach ($keys as $key)
		{
			unset($_SERVER[$key]);
		}

		$_SERVER = $server + $_SERVER;

		return $before;
	}

	/**
	 * @covers Request::detect_uri
	 */
	#[DataProvider('provider_detected_uris')]
	public function test_detect_uri(array $server, string $expected): void
	{
		$this->setEnvironment(['Kohana::$base_url' => '/shop/', 'Kohana::$index_file' => 'index.php']);

		$before = $this->server_address($server);

		try
		{
			$this->assertSame($expected, Request::detect_uri());
		}
		finally
		{
			$this->server_address($before);
		}
	}

	/**
	 * @covers Request::detect_uri
	 */
	public function test_detect_uri_needs_something_to_go_on(): void
	{
		$before = $this->server_address([]);

		try
		{
			$this->expectException(Kohana_Exception::class);
			$this->expectExceptionMessage('Unable to detect the URI');

			Request::detect_uri();
		}
		finally
		{
			$this->server_address($before);
		}
	}

	/**
	 * @covers Request::cookie
	 * @covers Request::post
	 * @covers Request::headers
	 * @covers Request::render
	 */
	public function test_cookies_post_headers_and_the_request_as_text(): void
	{
		$this->setEnvironment(['Kohana::$expose' => true]);

		$request = Request::factory('items/list');

		$this->assertSame([], $request->cookie());
		$this->assertNull($request->cookie('theme'));
		$this->assertSame($request, $request->cookie('theme', 'dark'));
		$this->assertSame('dark', $request->cookie('theme'));
		$this->assertSame($request, $request->cookie(['a' => '1', 'b' => '2']));
		$this->assertSame(['a' => '1', 'b' => '2'], $request->cookie());

		$this->assertSame($request, $request->post('user', ['name' => 'Ana']));
		$this->assertSame('Ana', $request->post('user.name'));
		$this->assertNull($request->post('user.email'));
		$this->assertSame(['user' => ['name' => 'Ana']], $request->post());

		$headers = new HTTP_Header(['x-test' => 'yes']);

		$this->assertSame($request, $request->headers($headers));
		$this->assertSame($headers, $request->headers());

		$text = (string) $request->method('POST');

		$this->assertStringStartsWith('POST items/list HTTP/1.1', $text);
		$this->assertStringContainsStringIgnoringCase('cookie: a=1; b=2', $text);
		$this->assertStringContainsStringIgnoringCase('x-test: yes', $text);
		$this->assertStringContainsString(Kohana::version(), $text);
		$this->assertStringContainsString('user%5Bname%5D=Ana', $text);
	}

	/**
	 * @covers Request::user_agent
	 */
	public function test_user_agent_of_the_visitor(): void
	{
		$this->setEnvironment(['Request::$user_agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0']);

		$this->assertSame('Firefox', Request::user_agent('browser'));
		$this->assertSame('130.0', Request::user_agent('version'));
		$this->assertSame(['browser' => 'Firefox', 'platform' => 'Linux'], Request::user_agent(['browser', 'platform']));
	}

	/**
	 * An address that no route knows is a 404 answer, not an error.
	 *
	 * @covers Request::execute
	 */
	public function test_address_without_route_is_a_404(): void
	{
		$routes = new ReflectionProperty('Route', '_routes');
		$before = $routes->getValue();

		$routes->setValue(null, []);
		Route::set('kohana_only_route', 'only/this');

		try
		{
			foreach ([true, false] as $profiling)
			{
				$this->setEnvironment(['Kohana::$profiling' => $profiling]);

				$this->assertSame(404, Request::factory('something/else')->execute()->status());
			}
		}
		finally
		{
			$routes->setValue(null, $before);
		}
	}

} // End Kohana_RequestTest

/**
 * A dummy Request_Client_External implementation, that spies on the headers
 * of the request
 */
class Kohana_RequestTest_Header_Spying_Request_Client_External extends Request_Client_External
{
	private $headers;

	protected function _send_message(Request $request, Response $response) : Response
	{
		$this->headers = $request->headers();

		return $response;
	}

	public function get_received_request_headers()
	{
		return $this->headers;
	}
}

class Controller_Kohana_RequestTest_Dummy extends Controller
{
	// hard coded dummy response
	protected $dummy_response = "this is a dummy response";

	public function action_foobar()
	{
		$this->response->body($this->dummy_response);
	}

	public function get_expected_response()
	{
		return $this->dummy_response;
	}

} // End Kohana_RequestTest
