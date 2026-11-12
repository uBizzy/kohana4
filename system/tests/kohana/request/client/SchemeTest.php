<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the safety settings of external requests: allowed URL schemes,
 * default timeouts, and how request options reach the transport.
 *
 * @package    Kohana
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.request')]
class Kohana_Request_Client_SchemeTest extends Unittest_TestCase
{
	/**
	 * Provides URLs whose scheme is not allowed by default.
	 *
	 * @return array
	 */
	public static function provider_refused_urls(): array
	{
		return [
			['file:///etc/passwd'],
			['FILE:///etc/passwd'],
			['php://filter/resource=/etc/passwd'],
			['gopher://127.0.0.1:6379/_FLUSHALL'],
			['ftp://example.com/file.txt'],
		];
	}

	#[DataProvider('provider_refused_urls')]
	public function test_other_schemes_are_refused_by_default(string $url): void
	{
		foreach (['Request_Client_Curl', 'Request_Client_Stream'] as $client)
		{
			try
			{
				Request::factory($url)->client(Request_Client_External::factory([], $client))->execute();
				$this->fail($client.' sent a request to '.$url);
			}
			catch (Request_Exception $e)
			{
				$this->assertStringContainsString('is not allowed', $e->getMessage());
			}
		}
	}

	/**
	 * A request can be given its own list of schemes, and so can the application.
	 */
	public function test_allowed_schemes_can_be_changed(): void
	{
		$client = Request::factory('http://example.com/', ['allowed_schemes' => ['HTTP', 'ftp']])->client();

		$this->assertSame(['http', 'ftp'], $client->allowed_schemes());

		$this->setEnvironment(['Request_Client_External::$allowed_schemes' => ['https']]);

		$this->assertSame(['https'], Request::factory('http://example.com/')->client()->allowed_schemes());
	}

	/**
	 * Settings of the client are not mistaken for transport options, and
	 * transport options can be given in the "options" entry or directly.
	 */
	public function test_client_settings_are_separated_from_transport_options(): void
	{
		$client = Request::factory('http://example.com/', [
			'follow'         => true,
			'options'        => [CURLOPT_TIMEOUT => 5],
			CURLOPT_USERAGENT => 'test',
		])->client();

		$this->assertTrue($client->follow());
		$this->assertSame([CURLOPT_TIMEOUT => 5, CURLOPT_USERAGENT => 'test'], $client->options());
	}

	public function test_timeouts_have_defaults(): void
	{
		$this->assertGreaterThan(0, Request_Client_External::$connect_timeout);
		$this->assertGreaterThan(0, Request_Client_External::$timeout);
	}
}
