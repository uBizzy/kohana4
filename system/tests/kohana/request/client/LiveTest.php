<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Sends real HTTP requests with the Curl and Stream clients to a small
 * local server (PHP's built-in web server, started for this test).
 *
 * @package    Kohana
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.request')]
class Kohana_Request_Client_LiveTest extends Unittest_TestCase
{
	/**
	 * @var resource|null  The server process
	 */
	protected static $server = null;

	/**
	 * @var string  Base URL of the server
	 */
	protected static string $url = 'http://127.0.0.1:8765/';

	/**
	 * @var array<string, string|false>  The "no proxy" settings before the tests
	 */
	protected static array $no_proxy = [];

	/**
	 * Starts the local server and waits until it accepts connections.
	 */
	public static function setUpBeforeClass(): void
	{
		// On a machine that reaches the web through a proxy, the server of
		// this test is still asked directly
		foreach (['no_proxy', 'NO_PROXY'] as $name)
		{
			static::$no_proxy[$name] = getenv($name);
			putenv($name.'=127.0.0.1,localhost');
		}

		$command = escapeshellarg(PHP_BINARY).' -S 127.0.0.1:8765 -t '.escapeshellarg(SYSPATH.'tests/test_data/http_echo');

		static::$server = proc_open('exec '.$command, [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes) ?: null;

		for ($i = 0; $i < 50; $i++)
		{
			if ($socket = @fsockopen('127.0.0.1', 8765, $errno, $error, 0.1))
			{
				fclose($socket);
				return;
			}

			usleep(100000);
		}
	}

	public static function tearDownAfterClass(): void
	{
		if (static::$server)
		{
			proc_terminate(static::$server);
			proc_close(static::$server);
		}

		foreach (static::$no_proxy as $name => $value)
		{
			putenv(($value === false) ? $name : $name.'='.$value);
		}
	}

	public function setUp(): void
	{
		parent::setUp();

		if ( ! @fsockopen('127.0.0.1', 8765, $errno, $error, 0.2))
		{
			$this->markTestSkipped('The local test server could not be started.');
		}
	}

	/**
	 * Provides the client classes that can run here.
	 *
	 * @return array
	 */
	public static function provider_clients(): array
	{
		$clients = ['stream' => ['Request_Client_Stream']];

		if (extension_loaded('curl'))
		{
			$clients['curl'] = ['Request_Client_Curl'];
		}

		return $clients;
	}

	/**
	 * Creates an external request that uses the given client class.
	 */
	protected function request(string $client, string $uri = '', array $options = []): Request
	{
		$request = Request::factory(static::$url.$uri);

		return $request->client(Request_Client_External::factory($options, $client));
	}

	#[DataProvider('provider_clients')]
	public function test_get(string $client): void
	{
		$response = $this->request($client)->query(['a' => '1', 'b' => 'two words'])->headers('X-Custom', 'hello')->execute();

		$this->assertSame(200, $response->status());
		$this->assertSame('yes', $response->headers('x-echo'));

		$echo = json_decode($response->body(), true);

		$this->assertSame('GET', $echo['method']);
		$this->assertSame(['a' => '1', 'b' => 'two words'], $echo['query']);
		$this->assertSame('hello', $echo['custom']);
	}

	#[DataProvider('provider_clients')]
	public function test_post_form_data(string $client): void
	{
		$response = $this->request($client)->method('POST')->post(['name' => 'Ana', 'city' => 'Lisboa & Porto'])->execute();

		$echo = json_decode($response->body(), true);

		$this->assertSame('POST', $echo['method']);
		$this->assertSame('name=Ana&city=Lisboa+%26+Porto', $echo['body']);
		$this->assertStringStartsWith('application/x-www-form-urlencoded', $echo['content_type']);
	}

	/**
	 * A body with its own content type (JSON here) is sent as it is.
	 */
	#[DataProvider('provider_clients')]
	public function test_put_json_body(string $client): void
	{
		$response = $this->request($client)->method('PUT')
			->headers('content-type', 'application/json')
			->body('{"id":5}')
			->execute();

		$echo = json_decode($response->body(), true);

		$this->assertSame('PUT', $echo['method']);
		$this->assertSame('{"id":5}', $echo['body']);
		$this->assertSame('application/json', $echo['content_type']);
	}

	/**
	 * A server that answers too slowly is given up on.
	 */
	#[DataProvider('provider_clients')]
	public function test_slow_server_times_out(string $client): void
	{
		$this->setEnvironment(['Request_Client_External::$timeout' => 1]);

		$start = microtime(true);

		try
		{
			$this->request($client, '?wait=4')->execute();
			$this->fail('The request did not time out');
		}
		catch (Request_Exception $e)
		{
			$this->assertLessThan(3, microtime(true) - $start);
		}
	}

	/**
	 * Returns the description the test server gives of the request it got.
	 */
	protected function echo(Response $response): array
	{
		return json_decode($response->body(), true);
	}

	/**
	 * A path for a file of a test, removed when the test ends.
	 */
	protected function temporary(string $name): string
	{
		$path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kohana_live_'.bin2hex(random_bytes(6)).'_'.$name;

		$this->files[] = $path;

		return $path;
	}

	/**
	 * @var array<int, string>  Files to remove after the test
	 */
	protected array $files = [];

	public function tearDown(): void
	{
		foreach ($this->files as $file)
		{
			is_file($file) and unlink($file);
		}

		array_map('unlink', glob(sys_get_temp_dir().'/kohana_http_echo_*') ?: []);

		parent::tearDown();
	}

	/**
	 * A login is sent as a header, by name and password or by token.
	 */
	#[DataProvider('provider_clients')]
	public function test_logins(string $client): void
	{
		$request = $this->request($client);
		$request->client()->auth('ana', 'correct horse');

		$this->assertSame('Basic '.base64_encode('ana:correct horse'), $this->echo($request->execute())['authorization']);

		$request = $this->request($client);
		$request->client()->bearer('the-token');

		$this->assertSame('Bearer the-token', $this->echo($request->execute())['authorization']);

		// A header set on the request itself is not replaced
		$request = $this->request($client)->headers('authorization', 'Custom mine');
		$request->client()->bearer('the-token');

		$this->assertSame('Custom mine', $this->echo($request->execute())['authorization']);
	}

	/**
	 * A time limit of one request, apart from the one of the application.
	 */
	#[DataProvider('provider_clients')]
	public function test_time_limit_of_one_request(string $client): void
	{
		$request = $this->request($client, '?wait=4');
		$request->client()->timeout(1)->connect_timeout(1);

		$start = microtime(true);

		try
		{
			$request->execute();
			$this->fail('The request did not time out');
		}
		catch (Request_Exception $e)
		{
			$this->assertLessThan(3, microtime(true) - $start);
		}
	}

	/**
	 * A login goes along to another page of the same server, and stays
	 * behind when the answer sends the client to another server.
	 */
	public function test_login_is_not_sent_to_another_server(): void
	{
		$same = $this->request('Request_Client_Curl', '?redirect='.urlencode(static::$url.'?arrived=1'));
		$same->client()->bearer('the-token')->follow(true);

		$echo = $this->echo($same->execute());

		$this->assertSame(['arrived' => '1'], $echo['query']);
		$this->assertSame('Bearer the-token', $echo['authorization']);

		// "localhost" is another name, and so another server, than 127.0.0.1
		$other = $this->request('Request_Client_Curl', '?redirect='.urlencode('http://localhost:8765/?arrived=2'));
		$other->client()->bearer('the-token')->follow(true);

		$echo = $this->echo($other->execute());

		$this->assertSame(['arrived' => '2'], $echo['query']);
		$this->assertNull($echo['authorization']);
	}

	/**
	 * A file is sent as a form upload, with the other values of the form.
	 */
	public function test_upload(): void
	{
		$file = $this->temporary('report.txt');
		file_put_contents($file, 'the content of the report');

		$request = $this->request('Request_Client_Curl')
			->method('POST')
			->post(['title' => 'Lisboa & Porto', 'user' => ['name' => 'Ana', 'tags' => ['a', 'b']]]);

		$request->client()
			->upload('report', $file)
			->upload('copy', $file, 'other name.txt', 'text/x-test');

		$echo = $this->echo($request->execute());

		$this->assertSame('POST', $echo['method']);
		$this->assertStringStartsWith('multipart/form-data; boundary=', $echo['content_type']);
		$this->assertSame(['title' => 'Lisboa & Porto', 'user' => ['name' => 'Ana', 'tags' => ['a', 'b']]], $echo['post']);
		$this->assertSame(['name' => basename($file), 'type' => 'text/plain', 'size' => 25, 'content' => 'the content of the report'], $echo['files']['report']);
		$this->assertSame('other name.txt', $echo['files']['copy']['name']);
		$this->assertSame('text/x-test', $echo['files']['copy']['type']);
	}

	public function test_upload_of_a_missing_file_fails_at_once(): void
	{
		$this->expectException(Request_Exception::class);
		$this->expectExceptionMessage('The file to upload cannot be read');

		$this->request('Request_Client_Curl')->client()->upload('report', sys_get_temp_dir().'/kohana-no-such-file');
	}

	/**
	 * A file is sent as the body of a request, as APIs take files with PUT.
	 */
	public function test_file_as_the_body(): void
	{
		$file = $this->temporary('body.bin');
		file_put_contents($file, str_repeat('0123456789', 30000));

		foreach (['PUT', 'POST'] as $method)
		{
			$request = $this->request('Request_Client_Curl')->method($method)->headers('content-type', 'application/octet-stream');
			$request->client()->body_from($file);

			$echo = $this->echo($request->execute());

			$this->assertSame($method, $echo['method']);
			$this->assertSame(300000, strlen($echo['body']), $method);
			$this->assertSame(md5_file($file), md5($echo['body']), $method);
			$this->assertSame('application/octet-stream', $echo['content_type']);
		}

		$this->expectException(Request_Exception::class);
		$this->expectExceptionMessage('The file to send cannot be read');

		$this->request('Request_Client_Curl')->client()->body_from(sys_get_temp_dir().'/kohana-no-such-file');
	}

	/**
	 * HEAD asks for the headers only: the answer comes at once, without a body.
	 */
	#[DataProvider('provider_clients')]
	public function test_head_request(string $client): void
	{
		$request = $this->request($client, '?bytes=5000')->method('HEAD');
		$request->client()->timeout(5);

		$start    = microtime(true);
		$response = $request->execute();

		$this->assertLessThan(3, microtime(true) - $start, 'The client must not wait for a body');
		$this->assertSame(200, $response->status());
		$this->assertSame('yes', $response->headers('x-echo'));
		$this->assertSame('', (string) $response->body());
	}

	/**
	 * A download goes straight to a file; a failed one leaves no file.
	 */
	public function test_save_to_a_file(): void
	{
		$file = $this->temporary('download.bin');

		$request = $this->request('Request_Client_Curl', '?bytes=300000');
		$request->client()->save_to($file);

		$response = $request->execute();

		$this->assertSame(200, $response->status());
		$this->assertSame('', $response->body());
		$this->assertSame('yes', $response->headers('x-echo'));
		$this->assertSame(300000, filesize($file));

		// An error page is not the file that was asked for
		$missing = $this->temporary('missing.bin');

		$request = $this->request('Request_Client_Curl', '?status=404');
		$request->client()->save_to($missing);

		$this->assertSame(404, $request->execute()->status());
		$this->assertFileDoesNotExist($missing);

		// Nor is there one when the server cannot be reached
		$request = Request::factory('http://127.0.0.1:8766/')->client(Request_Client_External::factory([], 'Request_Client_Curl'));
		$request->client()->save_to($missing)->timeout(2);

		try
		{
			$request->execute();
			$this->fail('A server that is not there must fail');
		}
		catch (Request_Exception $e)
		{
			$this->assertFileDoesNotExist($missing);
		}
	}

	/**
	 * A busy server is asked again; a request that could do its work
	 * twice is not.
	 */
	public function test_retry(): void
	{
		$id = bin2hex(random_bytes(6));

		// Busy twice, then fine: three attempts
		$request = $this->request('Request_Client_Curl', '?fail=2&id=a'.$id);
		$request->client()->retry(3, 0.01);

		$response = $request->execute();

		$this->assertSame(200, $response->status());
		$this->assertSame('3', $response->headers('x-attempt'));
		$this->assertSame('GET', $this->echo($response)['method']);

		// Busy longer than there are attempts: the last answer is returned
		$request = $this->request('Request_Client_Curl', '?fail=5&id=b'.$id);
		$request->client()->retry(1, 0.01);

		$response = $request->execute();

		$this->assertSame(503, $response->status());
		$this->assertSame('2', $response->headers('x-attempt'));

		// A POST is sent once
		$request = $this->request('Request_Client_Curl', '?fail=2&id=c'.$id)->method('POST');
		$request->client()->retry(3, 0.01);

		$response = $request->execute();

		$this->assertSame(503, $response->status());
		$this->assertSame('1', $response->headers('x-attempt'));

		// Without retry() there is one attempt
		$this->assertSame('1', $this->request('Request_Client_Curl', '?fail=2&id=d'.$id)->execute()->headers('x-attempt'));
	}

	/**
	 * Requests sent together take the time of the slowest one, and one
	 * that fails does not stop the others.
	 */
	public function test_several_requests_at_once(): void
	{
		// A server answers one request at a time: each request gets its own,
		// so that the time shows whether they were sent together
		$servers = [];
		$curl    = fn (string $url) => Request::factory($url)->client(Request_Client_External::factory([], 'Request_Client_Curl'));

		foreach ([8767, 8768] as $port)
		{
			$command   = escapeshellarg(PHP_BINARY).' -S 127.0.0.1:'.$port.' -t '.escapeshellarg(SYSPATH.'tests/test_data/http_echo');
			$servers[] = proc_open('exec '.$command, [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);

			for ($i = 0; $i < 50 AND ! ($socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1)); $i++)
			{
				usleep(100000);
			}

			$socket and fclose($socket);
		}

		$requests = [
			'first'  => $this->request('Request_Client_Curl', '?wait=1&n=1'),
			'second' => $curl('http://127.0.0.1:8767/?wait=1&n=2')->method('POST')->post(['a' => 'b']),
			'third'  => $curl('http://127.0.0.1:8768/?wait=1&n=3'),
			'gone'   => Request::factory('http://127.0.0.1:8766/')->client(Request_Client_External::factory([], 'Request_Client_Curl')),
			'stream' => $this->request('Request_Client_Stream'),
		];

		$requests['third']->client()->bearer('the-token');

		$start = microtime(true);

		try
		{
			$responses = Request_Client_Curl::all($requests, $errors);
		}
		finally
		{
			foreach ($servers as $server)
			{
				proc_terminate($server);
				proc_close($server);
			}
		}

		$this->assertLessThan(2.5, microtime(true) - $start, 'Three requests of one second each');
		$this->assertSame(['first', 'second', 'third'], array_keys($responses));
		$this->assertSame(['gone', 'stream'], array_keys($errors));

		$this->assertSame('1', $this->echo($responses['first'])['query']['n']);
		$this->assertSame(['a' => 'b'], $this->echo($responses['second'])['post']);
		$this->assertSame('Bearer the-token', $this->echo($responses['third'])['authorization']);
		$this->assertSame('yes', $responses['first']->headers('x-echo'));

		$this->assertStringContainsString('Error fetching remote', $errors['gone']->getMessage());
		$this->assertStringContainsString('must go to other servers with the curl client', $errors['stream']->getMessage());

		$this->assertSame([], Request_Client_Curl::all([], $errors));
		$this->assertSame([], $errors);
	}

	/**
	 * With only public addresses allowed, this machine is refused: by
	 * its address, and by a name that leads to it.
	 */
	public function test_only_public_addresses(): void
	{
		foreach ([static::$url, 'http://localhost:8765/', 'http://[::1]:8765/', 'http://10.1.2.3/', 'http://169.254.169.254/latest/meta-data/'] as $url)
		{
			$request = Request::factory($url)->client(Request_Client_External::factory([], 'Request_Client_Curl'));
			$request->client()->only_public_addresses()->timeout(2);

			try
			{
				$request->execute();
				$this->fail($url.' must be refused');
			}
			catch (Request_Exception $e)
			{
				$this->assertStringContainsString('is not a public one', $e->getMessage(), $url);
			}
		}

		// Switched off again, the request goes through
		$request = $this->request('Request_Client_Curl');
		$request->client()->only_public_addresses()->only_public_addresses(false);

		$this->assertSame(200, $request->execute()->status());
	}

	/**
	 * What only curl can do says so with the other client.
	 */
	public function test_stream_client_says_what_it_cannot_do(): void
	{
		$calls = [
			'proxy()'                 => fn (Request_Client_External $client) => $client->proxy('http://proxy.example.com:8080'),
			'verify()'                => fn (Request_Client_External $client) => $client->verify(false),
			'certificate()'           => fn (Request_Client_External $client) => $client->certificate(__FILE__),
			'upload()'                => fn (Request_Client_External $client) => $client->upload('file', __FILE__),
			'body_from()'             => fn (Request_Client_External $client) => $client->body_from(__FILE__),
			'save_to()'               => fn (Request_Client_External $client) => $client->save_to($this->temporary('never')),
			'retry()'                 => fn (Request_Client_External $client) => $client->retry(2),
			'only_public_addresses()' => fn (Request_Client_External $client) => $client->only_public_addresses(),
		];

		foreach ($calls as $method => $call)
		{
			$request = $this->request('Request_Client_Stream');
			$call($request->client());

			try
			{
				$request->execute();
				$this->fail($method.' must not pass in silence');
			}
			catch (Request_Exception $e)
			{
				$this->assertSame('Request_Client_Stream cannot do '.$method.': it needs the curl extension', $e->getMessage());
			}
		}
	}
}
