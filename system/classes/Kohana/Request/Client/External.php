<?php
/**
 * [Request_Client_External] provides a wrapper for all external request
 * processing. This class should be extended by all drivers handling external
 * requests.
 *
 * Supported out of the box:
 *  - Streams (default)
 *  - Curl (default if loaded)
 *
 * @package        Kohana\Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license        https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 *
 */
abstract class Kohana_Request_Client_External extends Request_Client {

	/**
	 * Use:
	 *  - Request_Client_Curl (when the curl extension is loaded)
	 *  - Request_Client_Stream (otherwise)
	 *
	 * @var    string    Defines the external client to use by default
	 */
	public static ?string $client = null;

	/**
	 * Request options
	 *
	 * @var     array<int|string, mixed>
	 * @link    http://www.php.net/manual/function.curl-setopt
	 * @link    http://www.php.net/manual/http.request.options
	 */
	protected array $_options = [];

	/**
	 * @var  array<int, string>  URL schemes that external requests may use, in lowercase.
	 *              Without this limit, a URL such as "file:///etc/passwd"
	 *              would be read like any other, which is dangerous as soon
	 *              as a URL comes from user input. A single request can use
	 *              another list:
	 *
	 *                  Request::factory($url, ['allowed_schemes' => ['http', 'https', 'ftp']]);
	 */
	public static array $allowed_schemes = ['http', 'https'];

	/**
	 * @var  int  Seconds to wait for the connection to the remote server
	 */
	public static int $connect_timeout = 10;

	/**
	 * @var  int  Seconds a whole request may take. Without a limit, a remote
	 *            server that stops answering blocks this PHP process forever.
	 *            The transport options of a request override both timeouts.
	 */
	public static int $timeout = 30;

	/**
	 * @var  array<int, string>|null  Schemes allowed for this client; null uses Request_Client_External::$allowed_schemes
	 */
	protected ?array $_allowed_schemes = null;

	/**
	 * What was asked for with timeout(), auth(), proxy(), upload() and the like.
	 *
	 * @var  array{
	 *     timeout?: int,
	 *     connect_timeout?: int,
	 *     authorization?: string,
	 *     proxy?: array{0: string, 1: string|null},
	 *     verify?: string|bool,
	 *     certificate?: array{0: string, 1: string|null, 2: string|null},
	 *     upload?: array<string, array{0: string, 1: string, 2: string}>,
	 *     body_from?: string,
	 *     save_to?: string,
	 *     retry?: array{0: int, 1: float},
	 *     only_public?: true,
	 * }
	 */
	protected array $_settings = [];

	/**
	 * Sends the HTTP message [Request] to a remote server and processes
	 * the response. This one needs to be implemented by all Request Drivers.
	 *
	 * @param Request  $request  Request to send
	 * @param Response $response Response to send
	 *
	 * @return  Response
	 */
	abstract protected function _send_message(Request $request, Response $response): Response;

	/**
	 * Factory method to create a new Request_Client_External object based on
	 * the client name passed, or defaulting to Request_Client_External::$client
	 * by default.
	 *
	 * Request_Client_External::$client can be set in the application bootstrap.
	 *
	 * @param array<int|string, mixed>  $options Request options to pass to the client
	 * @param string $client  External client to use (to override default one)
	 *
	 * @throws  Request_Exception
	 *
	 * @return  Request_Client_External
	 */
	public static function factory(array $options = [], ?string $client = null): Request_Client_External
	{
		// If no client given determine which one to use (prefer the faster and mature ones)
		//@codeCoverageIgnoreStart
		if ($client === null)
		{
			if (static::$client === null)
			{
				if (extension_loaded('curl'))
				{
					static::$client = 'Request_Client_Curl';
				}
				else
				{
					static::$client = 'Request_Client_Stream';
				}
			}

			$client = static::$client;
		}
		//@codeCoverageIgnoreEnd

		$client = new $client($options);

		// Check if client extends Request_Client_External
		if ( ! $client instanceof Request_Client_External)
		{
			throw new Request_Exception(':client is not a valid external Request Client.', [
				'client' => get_class($client)
			]);
		}

		// The transport options (cURL options, stream context options) are
		// the "options" entry. Entries that are not settings of the client
		// itself (follow, cache, allowed_schemes, ...) are transport options too.
		$transport = (array) ($options['options'] ?? []);

		foreach ($options as $key => $value)
		{
			if ($key !== 'options' AND ! method_exists($client, (string) $key))
			{
				$transport[$key] = $value;
			}
		}

		/** @var array<string, mixed> $transport */
		$client->options($transport);

		return $client;
	}

	/**
	 * Processes the request, executing the controller action that handles this
	 * request, determined by the [Route].
	 *
	 * 1. Before the controller action is called, the [Controller::before] method
	 * will be called.
	 * 2. Next the controller action will be called.
	 * 3. After the controller action is called, the [Controller::after] method
	 * will be called.
	 *
	 * By default, the output from the controller is captured and returned, and
	 * no headers are sent.
	 *
	 *     $request->execute();
	 *
	 * @param Request  $request  A request object
	 * @param Response $response A response object
	 *
	 * @throws Exception
	 * @return Response
	 */
	public function execute_request(Request $request, Response $response): Response
	{
		//@codeCoverageIgnoreStart
		if (Kohana::$profiling)
		{
			// Set the benchmark name
			$benchmark = '"' . $request->uri() . '"';

			if ($request !== Request::$initial AND Request::$current)
			{
				// Add the parent request uri
				$benchmark .= ' « "' . Request::$current->uri() . '"';
			}

			// Start benchmarking
			$benchmark = Profiler::start('Requests', $benchmark);
		}
		//@codeCoverageIgnoreEnd

		// Check the address and finish the headers and the body
		$this->_prepare($request);

		// Store the current active request and replace current with new request
		$previous = Request::$current;
		Request::$current = $request;

		try
		{
			$response = $this->_send_message($request, $response);
		}
		catch (Exception $e)
		{
			// Restore the previous request
			Request::$current = $previous;

			//@codeCoverageIgnoreStart
			if (isset($benchmark))
			{
				// Delete the benchmark, it is invalid
				Profiler::delete($benchmark);
			}
			//@codeCoverageIgnoreEnd

			// Re-throw the exception
			throw $e;
		}

		// Restore the previous request
		Request::$current = $previous;

		//@codeCoverageIgnoreStart
		if (isset($benchmark))
		{
			// Stop the benchmark
			Profiler::stop($benchmark);

			Profiler::note($benchmark, $request->method().', status '.$response->status().', '.strlen(is_string($response->body()) ? $response->body() : '').' bytes');
		}
		//@codeCoverageIgnoreEnd

		// Return the response
		return $response;
	}

	/**
	 * Gets or sets the URL schemes this client may request.
	 *
	 *     $client->allowed_schemes(['http', 'https', 'ftp']);
	 *
	 * @param   array<int, string>|null  $schemes  list of schemes; null to read the current list
	 * @return  ($schemes is null ? array<int, string> : static)
	 */
	public function allowed_schemes(?array $schemes = null): array|static
	{
		if ($schemes === null)
			return $this->_allowed_schemes ?? Request_Client_External::$allowed_schemes;

		$this->_allowed_schemes = array_map('strtolower', $schemes);

		return $this;
	}

	/**
	 * Makes sure the URL of a request uses one of the allowed schemes.
	 *
	 * @param   Request  $request  request about to be sent
	 * @return  void
	 * @throws  Request_Exception  if the scheme is not allowed
	 */
	protected function _check_scheme(Request $request): void
	{
		$scheme = strtolower( (string) parse_url($request->uri(), PHP_URL_SCHEME));

		// A URL without a scheme ("//example.com/page") is sent as http
		if ($scheme !== '' AND ! in_array($scheme, $this->allowed_schemes(), true))
		{
			throw new Request_Exception(
				'The URL scheme ":scheme" is not allowed for external requests. Allowed: :allowed',
				[':scheme' => $scheme, ':allowed' => implode(', ', $this->allowed_schemes())]
			);
		}
	}

	/**
	 * Makes a request ready to be sent: its address is checked, its form
	 * values become the body, and the headers that follow from the body
	 * and from auth() or bearer() are set.
	 *
	 * @param   Request  $request  request to send
	 * @return  void
	 * @throws  Request_Exception  when the address is not allowed
	 */
	protected function _prepare(Request $request): void
	{
		// Refuse URL schemes that are not allowed (file://, php://, gopher://, ...)
		$this->_check_scheme($request);

		if (isset($this->_settings['upload']))
		{
			// Form values and files are sent together by the client itself,
			// which also writes the headers that describe them
			$request->headers()->offsetUnset('content-type');
			$request->headers()->offsetUnset('content-length');
		}
		elseif (isset($this->_settings['body_from']))
		{
			// The body is a file, read while it is sent
			$request->headers('content-length', (string) filesize($this->_settings['body_from']));
		}
		else
		{
			// Resolve the POST fields
			if ($post = $request->post())
			{
				$request
					->body(http_build_query($post, '', '&'))
					->headers('content-type', 'application/x-www-form-urlencoded; charset='.Kohana::$charset);
			}

			$request->headers('content-length', (string) $request->content_length());
		}

		// If Kohana expose, set the user-agent
		if (Kohana::$expose)
		{
			$request->headers('user-agent', Kohana::version());
		}

		// The login is a header of the request: when the answer sends the
		// client elsewhere, it only goes along to the same host
		if (isset($this->_settings['authorization']) AND $request->headers('authorization') === null)
		{
			$request->headers('authorization', (string) $this->_settings['authorization']);
		}
	}

	/**
	 * Sets how long the whole request may take, for this request only.
	 * [Request_Client_External::$timeout] is the default.
	 *
	 *     $request->client()->timeout(5);
	 *
	 * @param   int  $seconds  seconds, at least 1
	 * @return  static
	 */
	public function timeout(int $seconds): static
	{
		$this->_settings['timeout'] = max(1, $seconds);

		return $this;
	}

	/**
	 * Sets how long connecting to the server may take, for this request
	 * only. [Request_Client_External::$connect_timeout] is the default.
	 *
	 * @param   int  $seconds  seconds, at least 1
	 * @return  static
	 */
	public function connect_timeout(int $seconds): static
	{
		$this->_settings['connect_timeout'] = max(1, $seconds);

		return $this;
	}

	/**
	 * Logs in with a name and a password ("Basic" authentication). Only
	 * use it over https: the password is sent with every request.
	 *
	 *     $request->client()->auth('ana', 'correct horse');
	 *
	 * @param   string  $username  user name
	 * @param   string  $password  password
	 * @return  static
	 */
	public function auth(string $username, #[SensitiveParameter] string $password): static
	{
		$this->_settings['authorization'] = 'Basic '.base64_encode($username.':'.$password);

		return $this;
	}

	/**
	 * Logs in with a token ("Bearer" authentication), as most APIs ask.
	 *
	 *     $request->client()->bearer($token);
	 *
	 * @param   string  $token  the token
	 * @return  static
	 */
	public function bearer(#[SensitiveParameter] string $token): static
	{
		$this->_settings['authorization'] = 'Bearer '.$token;

		return $this;
	}

	/**
	 * Sends the request through a proxy. Needs curl.
	 *
	 *     $request->client()->proxy('http://proxy.example.com:8080', 'ana', 'secret');
	 *
	 * @param   string       $address   address of the proxy, with its port
	 * @param   string|null  $username  user name, when the proxy asks for one
	 * @param   string|null  $password  password
	 * @return  static
	 */
	public function proxy(string $address, ?string $username = null, #[SensitiveParameter] ?string $password = null): static
	{
		$this->_settings['proxy'] = [$address, ($username === null) ? null : $username.':'.$password];

		return $this;
	}

	/**
	 * Says which certificates of servers are trusted. Without a call the
	 * certificates the system trusts are used, and a server with another
	 * one is refused. Needs curl.
	 *
	 *     // A server with a certificate of your own authority
	 *     $request->client()->verify(APPPATH.'certificates/authority.pem');
	 *
	 *     // No check at all: anybody can pose as the server. For tests only.
	 *     $request->client()->verify(false);
	 *
	 * @param   string|bool  $authority  file with the certificates to trust, true for
	 *                                   those of the system, false for no check
	 * @return  static
	 */
	public function verify(string|bool $authority = true): static
	{
		$this->_settings['verify'] = $authority;

		return $this;
	}

	/**
	 * Shows a certificate of your own to the server, for servers that
	 * know their clients by certificate. Needs curl.
	 *
	 *     $request->client()->certificate(APPPATH.'certificates/client.pem', APPPATH.'certificates/client.key');
	 *
	 * @param   string       $certificate  file with the certificate
	 * @param   string|null  $key          file with its private key, when it is not in the same file
	 * @param   string|null  $password     password of the key
	 * @return  static
	 */
	public function certificate(string $certificate, ?string $key = null, #[SensitiveParameter] ?string $password = null): static
	{
		$this->_settings['certificate'] = [$certificate, $key, $password];

		return $this;
	}

	/**
	 * Sends a file as a form upload, together with the values of
	 * [Request::post]. Call it once for each file. Needs curl.
	 *
	 *     $request = Request::factory('https://example.com/photos')->method('POST')->post('title', 'Lisboa');
	 *     $request->client()->upload('photo', '/path/to/photo.jpg');
	 *
	 * @param   string       $field  name of the form field
	 * @param   string       $file   path of the file to send
	 * @param   string|null  $name   name to send it under, the name of the file by default
	 * @param   string|null  $type   type of its content, found from the file by default
	 * @return  static
	 * @throws  Request_Exception  when the file cannot be read
	 */
	public function upload(string $field, string $file, ?string $name = null, ?string $type = null): static
	{
		if ( ! is_file($file) OR ! is_readable($file))
			throw new Request_Exception('The file to upload cannot be read: :file', [':file' => Debug::path($file)]);

		$this->_settings['upload'][$field] = [$file, $name ?? basename($file), $type ?? (File::mime($file) ?: 'application/octet-stream')];

		return $this;
	}

	/**
	 * Sends a file as the body of the request, read from the disk while
	 * it is sent: a large file never has to fit in memory. This is how
	 * APIs take files with PUT; a form upload is [Request_Client_External::upload].
	 * Needs curl.
	 *
	 *     $request = Request::factory('https://example.com/files/report.pdf')->method('PUT');
	 *     $request->client()->body_from('/path/to/report.pdf');
	 *
	 * @param   string  $file  path of the file to send
	 * @return  static
	 * @throws  Request_Exception  when the file cannot be read
	 */
	public function body_from(string $file): static
	{
		if ( ! is_file($file) OR ! is_readable($file))
			throw new Request_Exception('The file to send cannot be read: :file', [':file' => Debug::path($file)]);

		$this->_settings['body_from'] = $file;

		return $this;
	}

	/**
	 * Writes the answer to a file as it arrives, so a large download
	 * never has to fit in memory. The body of the response stays empty.
	 * When the request fails or is answered with an error status (400 and
	 * up), no file is left. Needs curl.
	 *
	 *     $request->client()->save_to(APPPATH.'cache/report.pdf');
	 *
	 * @param   string  $path  where to write the file
	 * @return  static
	 */
	public function save_to(string $path): static
	{
		$this->_settings['save_to'] = $path;

		return $this;
	}

	/**
	 * Tries again when the server cannot be reached or answers with 502,
	 * 503 or 504. Only requests that can be repeated without harm are
	 * tried again (GET, HEAD, PUT, DELETE, OPTIONS): a POST never is,
	 * because it could do its work twice. Needs curl.
	 *
	 *     // Up to three more attempts, half a second apart
	 *     $request->client()->retry(3, 0.5);
	 *
	 * @param   int    $times  how many more attempts, at most
	 * @param   float  $pause  seconds to wait between attempts
	 * @return  static
	 */
	public function retry(int $times, float $pause = 1.0): static
	{
		$this->_settings['retry'] = [max(0, $times), max(0.0, $pause)];

		return $this;
	}

	/**
	 * Refuses addresses inside your own network: this machine, private
	 * networks, link-local addresses. Use it whenever the address comes
	 * from a visitor, who could otherwise make the server ask its own
	 * neighbours ("server-side request forgery"). Needs curl.
	 *
	 *     $request = Request::factory($url_from_a_form);
	 *     $request->client()->only_public_addresses();
	 *
	 * The name is looked up once and the request goes to the address that
	 * was checked. An answer that sends the client elsewhere is checked
	 * the same way.
	 *
	 * @param   bool  $only  false switches the check off again
	 * @return  static
	 */
	public function only_public_addresses(bool $only = true): static
	{
		if ($only)
		{
			$this->_settings['only_public'] = true;
		}
		else
		{
			unset($this->_settings['only_public']);
		}

		return $this;
	}

	/**
	 * Returns what was asked for with the methods above.
	 *
	 * @return  array<string, mixed>
	 */
	public function settings(): array
	{
		return $this->_settings;
	}

	/**
	 * Gives the settings of this client to the client of a request that
	 * follows from this one (a redirect). Logins and files are not passed
	 * on: the login travels as a header, which is only kept for the same
	 * host, and a file is sent once.
	 *
	 * @param   Request_Client  $client  client of the request that follows
	 * @return  void
	 */
	public function assign_client_properties(Request_Client $client): void
	{
		parent::assign_client_properties($client);

		if ($client instanceof Request_Client_External)
		{
			$passed = $this->_settings;

			unset($passed['authorization'], $passed['upload'], $passed['body_from']);

			$client->_settings = $passed + $client->_settings;
			$client->_allowed_schemes ??= $this->_allowed_schemes;
		}
	}

	/**
	 * Returns the address the request must go to when only public
	 * addresses are allowed: the name of the server is looked up, and every
	 * address it has must be a public one.
	 *
	 * @param   Request  $request  request to send
	 * @return  string|null  the address to connect to, null when nothing is to be checked
	 * @throws  Request_Exception  when the server is, or may be, inside your own network
	 */
	protected function _public_address(Request $request): ?string
	{
		if (empty($this->_settings['only_public']))
			return null;

		$host = trim( (string) parse_url($request->uri(), PHP_URL_HOST), '[]');

		// A name has one or more addresses; an address is its own
		$addresses = (filter_var($host, FILTER_VALIDATE_IP) !== false) ? [$host] : $this->_resolve($host);

		foreach ($addresses as $address)
		{
			if ( ! static::is_public_address($address))
				throw new Request_Exception('The address :host is not a public one: the request is refused', [':host' => $host]);
		}

		if ( ! $addresses)
			throw new Request_Exception('The address of :host was not found', [':host' => $host]);

		return $addresses[0];
	}

	/**
	 * Looks up the addresses of a server name, IPv4 and IPv6.
	 *
	 * @param   string  $host  name of the server
	 * @return  array<int, string>
	 */
	protected function _resolve(string $host): array
	{
		$addresses = gethostbynamel($host) ?: [];

		foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record)
		{
			if (is_string($record['ipv6'] ?? null))
			{
				$addresses[] = $record['ipv6'];
			}
		}

		return $addresses;
	}

	/**
	 * Tells whether an address is on the public internet: not this
	 * machine, not a private or link-local network, not a reserved range.
	 *
	 *     Request_Client_External::is_public_address('10.0.0.5');   // false
	 *
	 * @param   string  $address  IPv4 or IPv6 address
	 * @return  bool
	 */
	public static function is_public_address(string $address): bool
	{
		// An IPv4 address written as IPv6 ("::ffff:10.0.0.5") is that IPv4 address
		if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $address, $match))
		{
			$address = $match[1];
		}

		return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
	}

	/**
	 * Stops with a clear message when something was asked for that only
	 * the curl client can do. Called by the other clients.
	 *
	 * @param   array<int, string>  $supported  names of the settings this client does support
	 * @return  void
	 * @throws  Request_Exception  when another setting is in use
	 */
	protected function _only_supports(array $supported): void
	{
		$names = [
			'proxy'       => 'proxy()',
			'verify'      => 'verify()',
			'certificate' => 'certificate()',
			'upload'      => 'upload()',
			'body_from'   => 'body_from()',
			'save_to'     => 'save_to()',
			'retry'       => 'retry()',
			'only_public' => 'only_public_addresses()',
		];

		foreach (array_diff_key($this->_settings, array_flip($supported)) as $setting => $value)
		{
			throw new Request_Exception(':client cannot do :method: it needs the curl extension', [
				':client' => get_class($this),
				':method' => $names[$setting] ?? $setting,
			]);
		}
	}

	/**
	 * Set and get options for this request.
	 *
	 * @param array<string, mixed>|int|string|null $key    Option name, or array of options
	 * @param mixed $value  Option value
	 *
	 * @return  mixed
	 */
	public function options(array|string|int|null $key = null, mixed $value = null): mixed
	{
		if ($key === null)
		{
			return $this->_options;
		}

		if (is_array($key))
		{
			$this->_options = $key;
		}
		elseif ($value === null)
		{
			return Arr::get($this->_options, $key);
		}
		else
		{
			$this->_options[$key] = $value;
		}

		return $this;
	}

}