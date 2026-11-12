<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for response class
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
#[Group('kohana.core')]
#[Group('kohana.core.response')]
class Kohana_ResponseTest extends Unittest_TestCase
{
	/**
	 * Provider for test_body
	 *
	 * @return array
	 */
	public static function provider_body()
	{
		// An object that can be read as a string, as a View can
		$view = new class {
			public function __toString(): string
			{
				return 'foo';
			}
		};

		return [
			['unit test', 'unit test'],
			[$view, 'foo'],
		];
	}

	/**
	 * Tests that we can set and read a body of a response
	 *
	 * @return null
	 */
	#[DataProvider('provider_body')]
	public function test_body($source, $expected)
	{
		$response = new Response;
		$response->body($source);
		$this->assertSame($response->body(), $expected);

		$response = (string) $response;
		$this->assertSame($response, $expected);
	}

	/**
	 * Provides data for test_body_string_zero()
	 *
	 * @return array
	 */
	public static function provider_body_string_zero()
	{
		return [
			['0', '0'],
			["0", '0'],
			[0, '0']
		];
	}

	/**
	 * Test that Response::body() handles numerics correctly
	 *
	 * @param string $string
	 * @param string $expected
	 * @return void
	 */
	#[DataProvider('provider_body_string_zero')]
	public function test_body_string_zero($string, $expected)
	{
		$response = new Response;
		$response->body($string);

		$this->assertSame($expected, $response->body());
	}

	/**
	 * provider for test_cookie_set()
	 *
	 * @return array
	 */
	public static function provider_cookie_set()
	{
		return [
			[
				'test1',
				'foo',
				[
					'test1' => [
						'value' => 'foo',
						'expiration' => Cookie::$expiration
					],
				]
			],
			[
				[
					'test2' => 'stfu',
					'test3' => [
						'value' => 'snafu',
						'expiration' => 123456789
					]
				],
				null,
				[
					'test2' => [
						'value' => 'stfu',
						'expiration' => Cookie::$expiration
					],
					'test3' => [
						'value' => 'snafu',
						'expiration' => 123456789
					]
				]
			]
		];
	}

	/**
	 * Tests the Response::cookie() method, ensures
	 * correct values are set, including defaults
	 *
	 * @param string $key
	 * @param string $value
	 * @param string $expected
	 * @return void
	 */
	#[DataProvider('provider_cookie_set')]
	public function test_cookie_set($key, $value, $expected)
	{
		// Setup the Response and apply cookie
		$response = new Response;
		$response->cookie($key, $value);

		foreach ($expected as $_key => $_value)
		{
			$cookie = $response->cookie($_key);

			$this->assertSame($_value['value'], $cookie['value']);
			$this->assertSame($_value['expiration'], $cookie['expiration']);
		}
	}

	/**
	 * Tests the Response::cookie() get functionality
	 *
	 * @return void
	 */
	public function test_cookie_get()
	{
		$response = new Response;

		// Test for empty cookies
		$this->assertSame([], $response->cookie());

		// Test for no specific cookie
		$this->assertNull($response->cookie('foobar'));

		$response->cookie('foo', 'bar');
		$cookie = $response->cookie('foo');

		$this->assertSame('bar', $cookie['value']);
		$this->assertSame(Cookie::$expiration, $cookie['expiration']);
	}

	/**
	 * Test the content type is sent when set
	 */
	public function test_content_type_when_set()
	{
		$content_type = 'application/json';
		$response = new Response;
		$response->headers('content-type', $content_type);
		$headers  = $response->send_headers()->headers();
		$this->assertSame($content_type, (string) $headers['content-type']);
	}

	/**
	 * @covers Response::json
	 */
	public function test_json_sets_body_type_and_status()
	{
		$response = Response::factory()->json(['id' => 5, 'name' => 'João', 'path' => 'a/b'], 201);

		$this->assertSame('{"id":5,"name":"João","path":"a\\/b"}', $response->body());
		$this->assertSame('application/json; charset=utf-8', $response->headers('content-type'));
		$this->assertSame(201, $response->status());

		// Without a status, the current one is kept
		$this->assertSame(200, Response::factory()->json([])->status());
		$this->assertSame('[]', Response::factory()->json([])->body());
	}

	/**
	 * Data that cannot be written as JSON is an error, not an empty response.
	 *
	 * @covers Response::json
	 */
	public function test_json_refuses_data_it_cannot_encode()
	{
		$this->expectException(JsonException::class);

		Response::factory()->json(["bad" => "\xB1\x31"]);
	}

	/**
	 * Calls one of the protected steps of send_file(), which itself ends
	 * the request and so cannot be called from a test.
	 */
	protected function step(Response $response, string $method, mixed ...$arguments): mixed
	{
		return (new ReflectionMethod('Response', $method))->invoke($response, ...$arguments);
	}

	/**
	 * @covers Response::send_file
	 */
	public function test_download_of_a_file(): void
	{
		$file = tempnam(sys_get_temp_dir(), 'kohana_download_');
		file_put_contents($file, 'twelve bytes');

		try
		{
			list($handle, $size, $name, $mime) = $this->step(new Response, '_open_download', $file, null, null);

			$this->assertSame('twelve bytes', stream_get_contents($handle));
			$this->assertSame(12, $size);
			$this->assertSame(basename($file), $name);
			$this->assertSame('application/octet-stream', $mime, 'An unknown extension is sent as plain bytes');

			// The download name decides the type, unless a type is given
			$this->assertSame('application/pdf', $this->step(new Response, '_open_download', $file, 'report.pdf', null)[3]);
			$this->assertSame('text/plain', $this->step(new Response, '_open_download', $file, 'report.pdf', 'text/plain')[3]);
		}
		finally
		{
			unlink($file);
		}
	}

	/**
	 * @covers Response::send_file
	 */
	public function test_download_of_the_body_or_an_open_file(): void
	{
		$response = new Response;
		$response->body('the body');

		list($handle, $size, $name, $mime) = $this->step($response, '_open_download', true, 'notes.txt', null);

		rewind($handle);

		$this->assertSame('the body', stream_get_contents($handle));
		$this->assertSame(8, $size);
		$this->assertSame('notes.txt', $name);
		$this->assertSame('text/plain', $mime);

		$open = tmpfile();
		fwrite($open, 'from a handle');

		$this->assertSame(13, $this->step(new Response, '_open_download', $open, 'data.bin', null)[1]);
	}

	public static function provider_downloads_that_fail(): array
	{
		return [
			'a file that does not exist' => [sys_get_temp_dir().'/kohana-no-such-file', null, 'Could not read file'],
			'a directory'                => [sys_get_temp_dir(), null, 'Could not read file'],
			'something that is no path'  => [['file.txt'], null, 'Could not read file'],
			'the body without a name'    => [true, null, 'Download name must be provided'],
			'the body with an empty name' => [true, '', 'Download name must be provided'],
		];
	}

	/**
	 * @covers Response::send_file
	 */
	#[DataProvider('provider_downloads_that_fail')]
	public function test_download_that_cannot_be_sent_fails(mixed $file, ?string $name, string $message): void
	{
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage($message);

		$this->step(new Response, '_open_download', $file, $name, null);
	}

	/**
	 * @covers Response::send_file
	 */
	public function test_download_of_a_handle_that_cannot_be_rewound_fails(): void
	{
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('Resource must be a file handle');

		$this->step(new Response, '_open_download', fopen('php://output', 'w'), 'data.bin', null);
	}

	/**
	 * The name of a download is put in a header: line breaks and quotes
	 * in it must not start a header of their own.
	 *
	 * @covers Response::send_file
	 */
	public function test_download_headers(): void
	{
		$response = new Response;

		$this->step($response, '_download_headers', "re\"port\r\nSet-Cookie: admin=1\\.txt", 'text/plain', 100, 0, 99, []);

		$this->assertSame('attachment; filename="reportSet-Cookie: admin=1.txt"', (string) $response->headers('content-disposition'));
		$this->assertStringNotContainsString("\n", (string) $response->headers('content-disposition'));
		$this->assertSame('text/plain', (string) $response->headers('content-type'));
		$this->assertSame('100', (string) $response->headers('content-length'));
		$this->assertSame('nosniff', (string) $response->headers('x-content-type-options'));
		$this->assertNull($response->headers('content-range'));
		$this->assertSame(200, $response->status());

		// Shown in the browser, with a name that is not plain ASCII
		$response = new Response;

		$this->step($response, '_download_headers', 'relatório.pdf', 'application/pdf', 100, 0, 99, ['inline' => true]);

		$this->assertSame(
			"inline; filename=\"relatorio.pdf\"; filename*=UTF-8''relat%C3%B3rio.pdf",
			(string) $response->headers('content-disposition')
		);
	}

	/**
	 * @covers Response::send_file
	 */
	public function test_resumable_download_says_which_part_it_sends(): void
	{
		$whole = new Response;
		$this->step($whole, '_download_headers', 'a.bin', 'application/octet-stream', 100, 0, 99, ['resumable' => true]);

		$this->assertSame(200, $whole->status());
		$this->assertSame('bytes 0-99/100', (string) $whole->headers('content-range'));
		$this->assertSame('bytes', (string) $whole->headers('accept-ranges'));

		$part = new Response;
		$this->step($part, '_download_headers', 'a.bin', 'application/octet-stream', 100, 10, 19, ['resumable' => true]);

		$this->assertSame(206, $part->status());
		$this->assertSame('bytes 10-19/100', (string) $part->headers('content-range'));
		$this->assertSame('10', (string) $part->headers('content-length'));
	}

	public static function provider_byte_ranges(): array
	{
		return [
			// Range header, size of the file => first and last byte to send
			'no header'                  => [null, 100, [0, 99]],
			'the first ten'              => ['bytes=0-9', 100, [0, 9]],
			'from the middle'            => ['bytes=50-', 100, [50, 99]],
			'a part'                     => ['bytes=10-19', 100, [10, 19]],
			'the last ten'               => ['bytes=-10', 100, [90, 99]],
			'more than there is'         => ['bytes=-500', 100, [0, 99]],
			'a bit more than there is'   => ['bytes=-150', 100, [0, 99]],
			'an end beyond the file'     => ['bytes=10-5000', 100, [10, 99]],
			'a start beyond the file'    => ['bytes=5000-', 100, [0, 99]],
			'only the first of several'  => ['bytes=0-9,20-29', 100, [0, 9]],
			'nonsense'                   => ['bytes=abc', 100, [0, 99]],
			'not text'                   => [['bytes=0-9'], 100, [0, 99]],
			'an empty file'              => ['bytes=0-9', 0, [0, -1]],
		];
	}

	/**
	 * The range comes from the client: whatever it says, the bytes to
	 * send are inside the file.
	 *
	 * @covers Response::send_file
	 */
	#[DataProvider('provider_byte_ranges')]
	public function test_byte_range(mixed $header, int $size, array $expected): void
	{
		$before = $_SERVER['HTTP_RANGE'] ?? null;

		if ($header === null)
		{
			unset($_SERVER['HTTP_RANGE']);
		}
		else
		{
			$_SERVER['HTTP_RANGE'] = $header;
		}

		try
		{
			$this->assertSame($expected, $this->step(new Response, '_calculate_byte_range', $size));
		}
		finally
		{
			if ($before === null)
			{
				unset($_SERVER['HTTP_RANGE']);
			}
			else
			{
				$_SERVER['HTTP_RANGE'] = $before;
			}
		}
	}

	/**
	 * @covers Response::cookie
	 * @covers Response::delete_cookie
	 * @covers Response::delete_cookies
	 */
	public function test_cookies_of_a_response(): void
	{
		$this->setEnvironment(['Cookie::$expiration' => 60]);

		$response = new Response;

		$this->assertSame([], $response->cookie());
		$this->assertSame($response, $response->cookie('theme', 'dark'));
		$this->assertSame(['value' => 'dark', 'expiration' => 60], $response->cookie('theme'));

		$response->cookie(['a' => '1', 'b' => ['value' => '2'], 'c' => ['value' => '3', 'expiration' => 5]]);

		$this->assertSame(['value' => '2', 'expiration' => 60], $response->cookie('b'));
		$this->assertSame(['value' => '3', 'expiration' => 5], $response->cookie('c'));
		$this->assertNull($response->cookie('none'));

		$response->delete_cookie('a');

		$this->assertSame(['theme', 'b', 'c'], array_keys($response->cookie()));
		$this->assertSame([], $response->delete_cookies()->cookie());
	}

	/**
	 * @covers Response::csv
	 * @covers Response::xml
	 */
	public function test_csv_and_xml_responses(): void
	{
		$rows = [['name' => 'Ana', 'total' => 12.5], ['name' => 'Bea', 'total' => 7]];

		$response = (new Response)->csv($rows);

		$this->assertSame("name,total\r\nAna,12.5\r\nBea,7\r\n", $response->body());
		$this->assertSame('text/csv; charset=utf-8', (string) $response->headers('content-type'));
		$this->assertNull($response->headers('content-disposition'));
		$this->assertSame(200, $response->status());

		// Offered as a file: the name cannot break out of its header
		$response = (new Response)->csv($rows, "orders\"\r\nSet-Cookie: a=b.csv", 201);

		$this->assertSame('attachment; filename="ordersSet-Cookie: a=b.csv"', (string) $response->headers('content-disposition'));
		$this->assertSame(201, $response->status());

		$response = (new Response)->xml(['name' => 'Ana', 'tags' => ['a', '<b>']], 404);

		$this->assertSame('application/xml; charset=utf-8', (string) $response->headers('content-type'));
		$this->assertSame(404, $response->status());
		$this->assertSame('<b>', (string) simplexml_load_string($response->body())->tags->item[1]);
		$this->assertStringNotContainsString('<b>', $response->body());
	}

	/**
	 * @covers Response::render
	 * @covers Response::generate_etag
	 */
	public function test_render_and_etag(): void
	{
		$this->setEnvironment(['Kohana::$expose' => true]);

		$response = new Response;
		$response->status(404)->body('not here')->cookie('seen', ['value' => 'yes', 'expiration' => 0]);

		$text = $response->render();

		$this->assertStringStartsWith("HTTP/1.1 404 Not Found\r\n", $text);
		$this->assertStringEndsWith("\r\n\r\nnot here", $text);
		$this->assertStringContainsStringIgnoringCase('content-length: 8', $text);
		$this->assertStringContainsStringIgnoringCase('content-type: text/html; charset=utf-8', $text);
		$this->assertStringContainsStringIgnoringCase('set-cookie: seen=yes; expires=', $text);
		$this->assertStringContainsString(Kohana::version(), $text);

		// The same answer has the same etag; another body another one
		$etag = $response->generate_etag();

		$this->assertMatchesRegularExpression('/^"[0-9a-f]{64}"$/', $etag);
		$this->assertSame($etag, $response->generate_etag());
		$this->assertNotSame($etag, $response->body('changed')->generate_etag());

		$this->expectException(Request_Exception::class);

		(new Response)->generate_etag();
	}
}
