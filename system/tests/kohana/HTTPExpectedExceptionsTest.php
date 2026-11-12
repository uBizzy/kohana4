<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the HTTP exceptions that are answers with a header of their
 * own: 401 (who are you?), 405 (not with this method) and 305 (use this
 * proxy). Each refuses to become a response without that header.
 *
 * @package    Kohana
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.http')]
class Kohana_HTTPExpectedExceptionsTest extends Unittest_TestCase
{
	public function test_401_asks_for_a_login(): void
	{
		$exception = HTTP_Exception::factory(401);

		$this->assertInstanceOf(HTTP_Exception_401::class, $exception);
		$this->assertNull($exception->authenticate());
		$this->assertSame($exception, $exception->authenticate('Basic realm="Admin"'));
		$this->assertSame('Basic realm="Admin"', (string) $exception->authenticate());

		$response = $exception->get_response();

		$this->assertSame(401, $response->status());
		$this->assertSame('Basic realm="Admin"', (string) $response->headers('www-authenticate'));
	}

	public function test_401_without_a_challenge_is_a_mistake(): void
	{
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage("'www-authenticate' header must be specified");

		HTTP_Exception::factory(401)->get_response();
	}

	public function test_405_lists_the_methods(): void
	{
		$exception = HTTP_Exception::factory(405);

		$this->assertSame($exception, $exception->allowed(['GET', 'POST']));
		$this->assertSame('GET,POST', (string) $exception->get_response()->headers('allow'));
		$this->assertSame('GET, HEAD', (string) HTTP_Exception::factory(405)->allowed('GET, HEAD')->get_response()->headers('allow'));
		$this->assertSame(405, $exception->get_response()->status());
	}

	public function test_405_without_methods_is_a_mistake(): void
	{
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('A list of allowed methods must be specified');

		HTTP_Exception::factory(405)->get_response();
	}

	public function test_305_names_the_proxy(): void
	{
		$exception = HTTP_Exception::factory(305);

		$this->assertNull($exception->location());
		$this->assertSame($exception, $exception->location('http://proxy.example.com:8080/'));
		$this->assertSame('http://proxy.example.com:8080/', (string) $exception->location());

		$response = $exception->get_response();

		$this->assertSame(305, $response->status());
		$this->assertSame('http://proxy.example.com:8080/', (string) $response->headers('location'));
	}

	public function test_305_needs_a_complete_address(): void
	{
		try
		{
			HTTP_Exception::factory(305)->get_response();
			$this->fail('A 305 without a proxy must fail');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString("'location' must be specified", $e->getMessage());
		}

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('An absolute URI to the proxy server must be specified');

		HTTP_Exception::factory(305)->location('/proxy')->get_response();
	}

	/**
	 * The writer for the log of the system: messages and the trace of an
	 * exception are handed over without an error.
	 */
	public function test_syslog_writer_takes_messages(): void
	{
		$writer = new Log_Syslog('KohanaTests');

		$message = ['time' => time(), 'level' => LOG_DEBUG, 'body' => 'A test message of the Kohana test suite', 'additional' => []];

		$writer->write([$message, ['additional' => ['exception' => new Exception('with a trace')]] + $message]);

		unset($writer);

		$this->assertTrue(true);
	}
}
