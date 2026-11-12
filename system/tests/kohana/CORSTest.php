<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the CORS headers.
 *
 * @package    Kohana
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.cors')]
class Kohana_CORSTest extends Unittest_TestCase
{
	/**
	 * @var array  Settings used by the tests
	 */
	protected array $config = [
		'origins'     => ['https://app.example.com'],
		'methods'     => ['GET', 'POST'],
		'headers'     => ['Content-Type'],
		'expose'      => ['X-Total'],
		'credentials' => false,
		'max_age'     => 600,
	];

	/**
	 * Creates a request with the given method and headers.
	 */
	protected function request(string $method, array $headers): Request
	{
		return Request::factory('api/items')->method($method)->headers($headers);
	}

	public function test_shipped_config_switches_cors_off(): void
	{
		$response = Response::factory();

		$this->assertFalse(CORS::apply($this->request('GET', ['origin' => 'https://app.example.com']), $response));
		$this->assertNull($response->headers('access-control-allow-origin'));
	}

	public function test_allowed_origin_gets_headers(): void
	{
		$response = Response::factory();

		$this->assertFalse(CORS::apply($this->request('GET', ['origin' => 'https://app.example.com']), $response, $this->config));

		$this->assertSame('https://app.example.com', $response->headers('access-control-allow-origin'));
		$this->assertSame('X-Total', $response->headers('access-control-expose-headers'));
		$this->assertSame('Origin', $response->headers('vary'));
		$this->assertNull($response->headers('access-control-allow-credentials'));
		$this->assertSame(200, $response->status());
	}

	/**
	 * Provides origins that must not be allowed by the test settings.
	 *
	 * @return array
	 */
	public static function provider_refused_origins(): array
	{
		return [
			['https://evil.example'],
			['http://app.example.com'],
			['https://app.example.com.evil.example'],
			['https://app.example.com:8443'],
			['null'],
		];
	}

	#[DataProvider('provider_refused_origins')]
	public function test_other_origins_get_no_headers(string $origin): void
	{
		$response = Response::factory();

		$this->assertFalse(CORS::apply($this->request('GET', ['origin' => $origin]), $response, $this->config));
		$this->assertNull($response->headers('access-control-allow-origin'));
	}

	public function test_request_without_origin_is_left_alone(): void
	{
		$response = Response::factory();

		$this->assertFalse(CORS::apply($this->request('GET', []), $response, $this->config));
		$this->assertNull($response->headers('access-control-allow-origin'));
	}

	public function test_preflight_is_answered(): void
	{
		$response = Response::factory();

		$request = $this->request('OPTIONS', [
			'origin'                        => 'https://app.example.com',
			'access-control-request-method' => 'POST',
		]);

		$this->assertTrue(CORS::apply($request, $response, $this->config));

		$this->assertSame(204, $response->status());
		$this->assertSame('GET, POST', $response->headers('access-control-allow-methods'));
		$this->assertSame('Content-Type', $response->headers('access-control-allow-headers'));
		$this->assertSame('600', $response->headers('access-control-max-age'));
	}

	/**
	 * An OPTIONS request that is not a preflight is an ordinary request.
	 */
	public function test_plain_options_request_is_not_a_preflight(): void
	{
		$response = Response::factory();

		$this->assertFalse(CORS::apply($this->request('OPTIONS', ['origin' => 'https://app.example.com']), $response, $this->config));
		$this->assertNull($response->headers('access-control-allow-methods'));
	}

	/**
	 * With the wildcard, the calling site is still named, which also
	 * works for requests with cookies.
	 */
	public function test_wildcard_and_credentials(): void
	{
		$response = Response::factory();

		CORS::apply($this->request('GET', ['origin' => 'https://anything.example']), $response, ['origins' => ['*'], 'credentials' => true] + $this->config);

		$this->assertSame('https://anything.example', $response->headers('access-control-allow-origin'));
		$this->assertSame('true', $response->headers('access-control-allow-credentials'));
	}
}
