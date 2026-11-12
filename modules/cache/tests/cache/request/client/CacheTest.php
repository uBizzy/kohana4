<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for request client cache logic
 *
 * @package    Kohana
 * @category   Tests
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.request')]
#[Group('kohana.request.client')]
#[Group('kohana.request.client.cache')]
class Kohana_Request_Client_CacheTest extends Unittest_TestCase {

	/**
	 * Sets up a test route for caching
	 *
	 * @return void
	 */
	public function setUp(): void
	{
		Route::set('welcome', 'welcome/index')
			->defaults([
				'controller' => 'welcome',
				'action'     => 'index'
			]);

		parent::setUp();
	}

	/**
	 * Tests the Client does not attempt to load cache if no Cache library
	 * is present
	 *
	 * @return void
	 */
	public function test_cache_not_called_with_no_cache()
	{
		$request       = new Request('welcome/index');
		$response      = new Response;

		$client_mock   = $this->createMock('Request_Client_Internal');

		$request->client($client_mock);
		$client_mock->expects($this->exactly(0))
			->method('execute_request');
		$client_mock->expects($this->once())
			->method('execute')
			->willReturn($response);

		$this->assertSame($response, $request->execute());
	}

	/**
	 * Tests that the client attempts to load a cached response from the
	 * cache library, but fails.
	 *
	 * @return void
	 */
	public function test_cache_miss()
	{
		$route = new Route('welcome/index');
		$route->defaults([
			'controller' => 'Kohana_Request_CacheTest_Dummy',
			'action'     => 'index',
		]);

		$request       = new Request('welcome/index', [], true, [$route]);
		$cache_mock    = $this->_get_cache_mock();

		$request->client()->cache(HTTP_Cache::factory($cache_mock));

		$cache_mock->expects($this->once())
			->method('get')
			->with($request->client()->cache()->create_cache_key($request))
			->willReturn(false);

		$response = $request->client()->execute($request);

		$this->assertSame(HTTP_Cache::CACHE_STATUS_MISS,
			$response->headers(HTTP_Cache::CACHE_STATUS_KEY));
	}

	/**
	 * Tests the client saves a response if the correct headers are set
	 *
	 * @return void
	 */
	public function test_cache_save()
	{
		$lifetime      = 800;
		$request       = new Request('welcome/index');
		$cache_mock    = $this->_get_cache_mock();
		$response      = Response::factory();

		$request->client()->cache(new HTTP_Cache([
			'cache' => $cache_mock
			]
		));

		$response->headers('cache-control', 'max-age='.$lifetime);

		$key = $request->client()->cache()->create_cache_key($request);

		// Two values are stored, in this order: the hit counter, then the response
		$calls = [];

		$cache_mock->expects($this->exactly(2))
			->method('set')
			->willReturnCallback(function ($id, $data, $life = null) use (&$calls) {
				$calls[] = [$id, $data, $life];

				return true;
			});

		$this->assertTrue(
			$request->client()->cache()
				->cache_response($key, $request, $response)
		);

		$this->assertStringEndsWith($key, $calls[0][0]);
		$this->assertSame(0, $calls[0][1]);
		$this->assertSame($key, $calls[1][0]);
		$this->assertSame($lifetime, $calls[1][2]);

		$this->assertSame(HTTP_Cache::CACHE_STATUS_SAVED,
			$response->headers(HTTP_Cache::CACHE_STATUS_KEY));
	}

	/**
	 * Tests the client handles a cache HIT event correctly
	 *
	 * @return void
	 */
	public function test_cache_hit()
	{
		$lifetime      = 800;
		$request       = new Request('welcome/index');
		$cache_mock    = $this->_get_cache_mock();

		$request->client()->cache(new HTTP_Cache([
			'cache' => $cache_mock
			]
		));

		$response = Response::factory();

		$response->headers([
			'cache-control'                  => 'max-age='.$lifetime,
			HTTP_Cache::CACHE_STATUS_KEY =>
				HTTP_Cache::CACHE_STATUS_HIT
		]);

		$key = $request->client()->cache()->create_cache_key($request);

		$cache_mock->expects($this->exactly(2))
			->method('get')
			->with($this->stringContains($key))
			->willReturn($response);

		$request->client()->cache()->cache_response($key, $request);

		$this->assertSame(HTTP_Cache::CACHE_STATUS_HIT,
			$response->headers(HTTP_Cache::CACHE_STATUS_KEY));
	}


	/**
	 * Data provider for test_set_cache
	 *
	 * @return array
	 */
	public static function provider_set_cache()
	{
		return [
			[
				new HTTP_Header(['cache-control' => 'no-cache']),
				['no-cache' => null],
				false,
			],
			[
				new HTTP_Header(['cache-control' => 'no-store']),
				['no-store' => null],
				false,
			],
			[
				new HTTP_Header(['cache-control' => 'max-age=100']),
				['max-age' => '100'],
				true
			],
			[
				new HTTP_Header(['cache-control' => 'private']),
				['private' => null],
				false
			],
			[
				new HTTP_Header(['cache-control' => 'private, max-age=100']),
				['private' => null, 'max-age' => '100'],
				false
			],
			[
				new HTTP_Header(['cache-control' => 'private, s-maxage=100']),
				['private' => null, 's-maxage' => '100'],
				true
			],
			[
				new HTTP_Header([
					'expires' => date('m/d/Y', strtotime('-1 day')),
				]),
				[],
				false
			],
			[
				new HTTP_Header([
					'expires' => date('m/d/Y', strtotime('+1 day')),
				]),
				[],
				true
			],
			[
				new HTTP_Header([]),
				[],
				true
			],
		];
	}

	/**
	 * Tests the set_cache() method
	 *
	 * @return null
	 */
	#[DataProvider('provider_set_cache')]
	public function test_set_cache($headers, $cache_control, $expected)
	{
		/**
		 * Set up a mock response object to test with
		 */
		$response = $this->createStub('Response');

		$response->method('headers')
			->willReturn($headers);

		$request = new Request_Client_Internal;
		$request->cache(new HTTP_Cache);
		$this->assertEquals($request->cache()->set_cache($response), $expected);
	}

	/**
	 * Returns a mock object for Cache
	 *
	 * @return Cache
	 */
	protected function _get_cache_mock()
	{
		return $this->createMock('Cache_File');
	}
} // End Kohana_Request_Client_CacheTest

class Controller_Kohana_Request_CacheTest_Dummy extends Controller
{
	public function action_index()
	{

	}
}
