<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests what the curl client hands to curl for the settings of a
 * request, without sending anything: certificates, proxies, the address
 * a request is tied to. LiveTest sends real requests.
 *
 * @package    Kohana
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.request')]
#[Group('kohana.core.request.client')]
class Kohana_Request_Client_CurlOptionsTest extends Unittest_TestCase
{
	public function setUp(): void
	{
		parent::setUp();

		if ( ! extension_loaded('curl'))
		{
			$this->markTestSkipped('The curl extension is not available.');
		}
	}

	/**
	 * Returns a request with the curl client of these tests.
	 */
	protected function request(string $url = 'https://api.example.com/items'): Request
	{
		return Request::factory($url)->client(new Kohana_Request_Client_CurlOptionsTest_Client);
	}

	/**
	 * Returns the options curl would get for a request.
	 */
	protected function options(Request $request): array
	{
		return $request->client()->curl_options($request);
	}

	/**
	 * The certificate of a server is checked unless that was switched
	 * off in so many words.
	 */
	public function test_certificates_are_checked_by_default(): void
	{
		$options = $this->options($this->request());

		$this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
		$this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
		$this->assertArrayNotHasKey(CURLOPT_CAINFO, $options);

		$request = $this->request();
		$request->client()->verify('/path/to/authority.pem');
		$options = $this->options($request);

		$this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
		$this->assertSame('/path/to/authority.pem', $options[CURLOPT_CAINFO]);

		$request = $this->request();
		$request->client()->verify(false);
		$options = $this->options($request);

		$this->assertFalse($options[CURLOPT_SSL_VERIFYPEER]);
		$this->assertSame(0, $options[CURLOPT_SSL_VERIFYHOST]);

		// And on again
		$request->client()->verify();

		$this->assertTrue($this->options($request)[CURLOPT_SSL_VERIFYPEER]);
	}

	public function test_client_certificate_and_proxy(): void
	{
		$request = $this->request();
		$request->client()
			->certificate('/path/client.pem', '/path/client.key', 'key-password')
			->proxy('http://proxy.example.com:8080', 'ana', 'proxy-password');

		$options = $this->options($request);

		$this->assertSame('/path/client.pem', $options[CURLOPT_SSLCERT]);
		$this->assertSame('/path/client.key', $options[CURLOPT_SSLKEY]);
		$this->assertSame('key-password', $options[CURLOPT_KEYPASSWD]);
		$this->assertSame('http://proxy.example.com:8080', $options[CURLOPT_PROXY]);
		$this->assertSame('ana:proxy-password', $options[CURLOPT_PROXYUSERPWD]);

		// The parts that are not given are left to curl
		$request = $this->request();
		$request->client()->certificate('/path/both.pem')->proxy('http://proxy.example.com:8080');

		$options = $this->options($request);

		$this->assertArrayNotHasKey(CURLOPT_SSLKEY, $options);
		$this->assertArrayNotHasKey(CURLOPT_KEYPASSWD, $options);
		$this->assertArrayNotHasKey(CURLOPT_PROXYUSERPWD, $options);
	}

	/**
	 * The time limits of the application apply, then those of the
	 * request; an option given to curl directly comes in between.
	 */
	public function test_time_limits(): void
	{
		$this->setEnvironment(['Request_Client_External::$timeout' => 30, 'Request_Client_External::$connect_timeout' => 10]);

		$request = $this->request();
		$options = $this->options($request);

		$this->assertSame(30, $options[CURLOPT_TIMEOUT]);
		$this->assertSame(10, $options[CURLOPT_CONNECTTIMEOUT]);

		$request->client()->options([CURLOPT_TIMEOUT => 20]);

		$this->assertSame(20, $this->options($request)[CURLOPT_TIMEOUT]);

		$request->client()->timeout(5)->connect_timeout(0);
		$options = $this->options($request);

		$this->assertSame(5, $options[CURLOPT_TIMEOUT]);
		$this->assertSame(1, $options[CURLOPT_CONNECTTIMEOUT], 'No limit at all cannot be asked for');
	}

	/**
	 * Whatever the options say, a request and what follows it stay on
	 * the schemes that are allowed.
	 */
	public function test_schemes_are_limited_in_curl_too(): void
	{
		if ( ! defined('CURLOPT_PROTOCOLS_STR'))
		{
			$this->markTestSkipped('This curl cannot limit protocols by name.');
		}

		$options = $this->options($this->request());

		$this->assertSame('http,https', $options[CURLOPT_PROTOCOLS_STR]);
		$this->assertSame('http,https', $options[CURLOPT_REDIR_PROTOCOLS_STR]);
	}

	public static function provider_addresses(): array
	{
		return [
			// address => on the public internet?
			'a public address'            => ['93.184.216.34', true],
			'a public IPv6 address'       => ['2606:2800:220:1:248:1893:25c8:1946', true],
			'this machine'                => ['127.0.0.1', false],
			'this machine, another way'   => ['127.8.8.8', false],
			'this machine, IPv6'          => ['::1', false],
			'private 10.x'                => ['10.1.2.3', false],
			'private 172.16.x'            => ['172.16.0.1', false],
			'private 192.168.x'           => ['192.168.1.1', false],
			'link-local (cloud metadata)' => ['169.254.169.254', false],
			'no address at all'           => ['0.0.0.0', false],
			'private IPv6'                => ['fd00::1', false],
			'link-local IPv6'             => ['fe80::1', false],
			'IPv4 inside IPv6, private'   => ['::ffff:10.0.0.1', false],
			'IPv4 inside IPv6, local'     => ['::FFFF:127.0.0.1', false],
			'IPv4 inside IPv6, public'    => ['::ffff:93.184.216.34', true],
			'not an address'              => ['example.com', false],
			'nothing'                     => ['', false],
		];
	}

	#[DataProvider('provider_addresses')]
	public function test_is_public_address(string $address, bool $expected): void
	{
		$this->assertSame($expected, Request_Client_External::is_public_address($address));
	}

	/**
	 * A name is looked up once, and the request is tied to the address
	 * that was checked: a second look-up cannot lead somewhere else.
	 */
	public function test_request_is_tied_to_the_checked_address(): void
	{
		$request = $this->request('https://api.example.com/items');
		$request->client()->only_public_addresses();
		$request->client()->addresses = ['93.184.216.34'];

		$options = $this->options($request);

		$this->assertSame(['api.example.com:443:93.184.216.34'], $options[CURLOPT_RESOLVE]);
		$this->assertFalse($options[CURLOPT_FOLLOWLOCATION], 'Where an answer sends the client is checked by the framework');

		// The port of the address, or the one of plain http
		$request = $this->request('http://api.example.com:8080/items');
		$request->client()->only_public_addresses();
		$request->client()->addresses = ['93.184.216.34'];

		$this->assertSame(['api.example.com:8080:93.184.216.34'], $this->options($request)[CURLOPT_RESOLVE]);

		// Without the check nothing is tied
		$this->assertArrayNotHasKey(CURLOPT_RESOLVE, $this->options($this->request()));
	}

	/**
	 * A public name that leads to an address inside the network is
	 * refused, also when only one of its addresses does; so is a name
	 * that leads nowhere.
	 */
	public function test_name_that_leads_inside_is_refused(): void
	{
		foreach ([['10.0.0.5'], ['93.184.216.34', '127.0.0.1'], ['93.184.216.34', '::1']] as $addresses)
		{
			$request = $this->request('https://inside.example.com/');
			$request->client()->only_public_addresses();
			$request->client()->addresses = $addresses;

			try
			{
				$this->options($request);
				$this->fail('A name with the addresses '.implode(', ', $addresses).' must be refused');
			}
			catch (Request_Exception $e)
			{
				$this->assertStringContainsString('inside.example.com is not a public one', $e->getMessage());
			}
		}

		$request = $this->request('https://nowhere.example.com/');
		$request->client()->only_public_addresses();
		$request->client()->addresses = [];

		$this->expectException(Request_Exception::class);
		$this->expectExceptionMessage('The address of nowhere.example.com was not found');

		$this->options($request);
	}

	/**
	 * A request that follows from another one (a redirect) keeps the
	 * settings of the first, but not its login and files.
	 */
	public function test_settings_go_along_with_a_redirect(): void
	{
		$first = $this->request();
		$first->client()
			->timeout(5)
			->only_public_addresses()
			->verify('/path/to/authority.pem')
			->bearer('the-token')
			->upload('file', __FILE__)
			->allowed_schemes(['https']);

		$next = new Kohana_Request_Client_CurlOptionsTest_Client;
		$next->timeout(9);

		$first->client()->assign_client_properties($next);

		$this->assertSame(
			['timeout' => 5, 'only_public' => true, 'verify' => '/path/to/authority.pem'],
			$next->settings()
		);
		$this->assertSame(['https'], $next->allowed_schemes());
	}

	/**
	 * A key that is no option of curl is named, not passed on.
	 */
	public function test_unknown_option_is_reported(): void
	{
		$request = $this->request();
		$request->client()->options([987654321 => 'value']);

		$this->expectException(Request_Exception::class);
		$this->expectExceptionMessage('CURL options are invalid');

		$request->client()->handle($request);
	}
}

/**
 * The curl client, with its options open to the test and the look-up of
 * names replaced by a list the test gives.
 */
class Kohana_Request_Client_CurlOptionsTest_Client extends Request_Client_Curl
{
	/**
	 * @var array<int, string>|null  What every name resolves to; the real look-up when null
	 */
	public ?array $addresses = null;

	public function curl_options(Request $request): array
	{
		return $this->_curl_options($request, new Response);
	}

	public function handle(Request $request): CurlHandle
	{
		return $this->_handle($request, new Response);
	}

	protected function _resolve(string $host): array
	{
		return $this->addresses ?? parent::_resolve($host);
	}
}
