<?php
/**
 * Sends requests to other servers with the curl extension. It is the
 * client that is used when curl is loaded, and the one that can do
 * everything: see the methods of [Request_Client_External].
 *
 *     $request = Request::factory('https://api.example.com/reports/7');
 *
 *     $request->client()
 *         ->bearer($token)
 *         ->timeout(5)
 *         ->retry(2);
 *
 *     $response = $request->execute();
 *
 * Several requests are sent at the same time with [Request_Client_Curl::all].
 *
 * Any option of curl can still be given with [Request_Client_External::options].
 *
 * @package        Kohana\Request
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license        https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 *
 */
class Kohana_Request_Client_Curl extends Request_Client_External {

	/**
	 * @var  array<int, string>  Methods that can be sent again without harm
	 */
	public const array REPEATABLE = ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS'];

	/**
	 * @var  array<int, int>  Answers after which retry() tries again: the server, or one before it, is busy or down
	 */
	public const array RETRY_STATUS = [502, 503, 504];

	/**
	 * @var  resource|null  The file that save_to() is writing, while a request runs
	 */
	protected mixed $_file = null;

	/**
	 * @var  resource|null  The file that body_from() is sending, while a request runs
	 */
	protected mixed $_body_file = null;

	/**
	 * Sends several requests at the same time and returns their
	 * responses, under the keys of the requests. The time it takes is
	 * that of the slowest request, not of all of them together.
	 *
	 *     $responses = Request_Client_Curl::all([
	 *         'users'  => Request::factory('https://api.example.com/users'),
	 *         'orders' => Request::factory('https://api.example.com/orders'),
	 *     ], $errors);
	 *
	 *     $users = $responses['users']->body();
	 *
	 * A request that fails has no response: its exception is in $errors,
	 * under the same key. The other requests are not affected.
	 *
	 * The settings of each request's client apply (timeout(), auth(),
	 * save_to(), ...), except retry(); and answers that send the client
	 * elsewhere are returned as they are, not followed.
	 *
	 * @param   array<int|string, Request>             $requests  requests to other servers
	 * @param   array<int|string, Request_Exception>|null  $errors    filled with the requests that failed
	 * @return  array<int|string, Response>
	 */
	public static function all(array $requests, ?array &$errors = null): array
	{
		$errors  = [];
		$running = [];
		$multi   = curl_multi_init();

		foreach ($requests as $key => $request)
		{
			try
			{
				$client = $request->client();

				if ( ! $client instanceof Request_Client_Curl)
					throw new Request_Exception('Requests that are sent together must go to other servers with the curl client: :uri', [':uri' => $request->uri()]);

				$response = Response::factory(['_protocol' => $request->protocol()]);

				$client->_prepare($request);

				$running[$key] = [$client, $request, $response, $client->_handle($request, $response)];

				curl_multi_add_handle($multi, $running[$key][3]);
			}
			catch (Request_Exception $e)
			{
				$errors[$key] = $e;
			}
		}

		do
		{
			$status = curl_multi_exec($multi, $active);

			// Wait until one of the requests has something new
			$active and curl_multi_select($multi, 1.0);
		}
		while ($active AND $status === CURLM_OK);

		// How each request ended: curl tells that for all of them here
		$results = [];

		while ($info = curl_multi_info_read($multi))
		{
			/** @var array{handle: CurlHandle, result: int} $info */
			$results[spl_object_id($info['handle'])] = $info['result'];
		}

		$responses = [];

		foreach ($running as $key => list($client, $request, $response, $curl))
		{
			try
			{
				$responses[$key] = $client->_finish($request, $response, (string) curl_multi_getcontent($curl), $curl, $results[spl_object_id($curl)] ?? CURLE_OK);
			}
			catch (Request_Exception $e)
			{
				$errors[$key] = $e;
			}

			curl_multi_remove_handle($multi, $curl);
		}

		// The errors too come in the order of the requests
		$order = array_flip(array_keys($requests));

		uksort($errors, fn ($a, $b) => $order[$a] <=> $order[$b]);

		return $responses;
	}

	/**
	 * Sends the HTTP message [Request] to a remote server and processes
	 * the response.
	 *
	 * @param Request  $request  request to send
	 * @param Response $response response to send
	 *
	 * @return  Response
	 * @throws  Request_Exception
	 */
	public function _send_message(Request $request, Response $response): Response
	{
		list($times, $pause) = $this->_settings['retry'] ?? [0, 0.0];

		for ($attempt = 0; ; $attempt++)
		{
			$curl = $this->_handle($request, $response);
			$body = curl_exec($curl);

			if ($attempt >= $times OR ! $this->_try_again($request, $curl))
				break;

			// Forget what the failed attempt left behind
			$this->_close_file(false);
			$response->headers()->exchangeArray([]);

			usleep( (int) ($pause * 1000000));
		}

		return $this->_finish($request, $response, is_string($body) ? $body : '', $curl, curl_errno($curl));
	}

	/**
	 * Tells whether a request that just ran should be sent again: it can
	 * be repeated without harm, and the server could not be reached or
	 * said it is busy or down.
	 *
	 * @param   Request     $request  the request
	 * @param   CurlHandle  $curl     its curl handle, after it ran
	 * @return  bool
	 */
	protected function _try_again(Request $request, CurlHandle $curl): bool
	{
		if ( ! in_array($request->method(), static::REPEATABLE, true))
			return false;

		return curl_errno($curl) !== 0 OR in_array(curl_getinfo($curl, CURLINFO_HTTP_CODE), static::RETRY_STATUS, true);
	}

	/**
	 * Returns a curl handle that is ready to send the request.
	 *
	 * @param   Request   $request   request to send
	 * @param   Response  $response  response that takes the headers of the answer
	 * @return  CurlHandle
	 * @throws  Request_Exception  when an option is not one of curl
	 */
	protected function _handle(Request $request, Response $response): CurlHandle
	{
		$options = $this->_curl_options($request, $response);

		// An unknown key would only give a warning: say which one it is
		/** @var array<string, int> $constants */
		$constants = get_defined_constants(true)['curl'];
		$known     = array_flip($constants);

		foreach (array_keys($options) as $option)
		{
			if ( ! isset($known[$option]))
				throw new Request_Exception('CURL options are invalid :$option_key in :options', [':$option_key' => $option]);
		}

		$uri = $request->uri();

		if ($query = $request->query())
		{
			$uri .= '?'.http_build_query($query, '', '&');
		}

		$curl = curl_init($uri);

		try
		{
			curl_setopt_array($curl, $options);
		}
		catch (Throwable $e)
		{
			$this->_close_file(false);

			throw new Request_Exception($e->getMessage());
		}

		return $curl;
	}

	/**
	 * Returns the options of curl for a request: what the request says
	 * (method, body, headers, cookies), then what was asked of the client
	 * with its methods, then the options given with options(), then the
	 * defaults. The first that names an option decides it.
	 *
	 * @param   Request   $request   request to send
	 * @param   Response  $response  response that takes the headers of the answer
	 * @return  array<int, mixed>
	 */
	protected function _curl_options(Request $request, Response $response): array
	{
		$options = $this->_set_curl_request_method($request, []);

		if (isset($this->_settings['upload']))
		{
			// Form values and files, sent as "multipart/form-data"
			$options[CURLOPT_POSTFIELDS] = $this->_form($request);
		}
		elseif (isset($this->_settings['body_from']))
		{
			// The body is read from a file while it is sent
			$file = @fopen($this->_settings['body_from'], 'rb');

			if ($file === false)
				throw new Request_Exception('The file to send cannot be read: :file', [':file' => Debug::path($this->_settings['body_from'])]);

			$this->_body_file = $file;

			$options[CURLOPT_UPLOAD]     = true;
			$options[CURLOPT_INFILE]     = $file;
			$options[CURLOPT_INFILESIZE] = (int) filesize($this->_settings['body_from']);

			// "Upload" means PUT to curl: the method of the request is said again
			unset($options[CURLOPT_POST]);
			$options[CURLOPT_CUSTOMREQUEST] = $request->method();
		}
		elseif ($body = $request->body())
		{
			// A body is legal with any method, PUT included, and need not be a file
			$options[CURLOPT_POSTFIELDS] = $body;
		}

		$headers = [];

		foreach ($request->headers() as $name => $value)
		{
			$headers[] = $name.': '.(is_scalar($value) ? $value : '');
		}

		if ($headers)
		{
			$options[CURLOPT_HTTPHEADER] = $headers;
		}

		if ($cookies = $request->cookie())
		{
			$options[CURLOPT_COOKIE] = http_build_query($cookies, '', '; ');
		}

		// The headers of the answer go to the response as they arrive
		$options[CURLOPT_HEADERFUNCTION] = [$response->headers(), 'parse_header_string'];
		$options[CURLOPT_HEADER] = false;

		$options += $this->_setting_options($request) + $this->_options + [
			CURLOPT_CONNECTTIMEOUT => Request_Client_External::$connect_timeout,
			CURLOPT_TIMEOUT        => Request_Client_External::$timeout,
			CURLOPT_RETURNTRANSFER => true,
		];

		/** @var array<int, mixed> $options  the keys are options of curl */
		return $options;
	}

	/**
	 * Returns the options of curl for what was asked of this client with
	 * timeout(), proxy(), verify(), certificate(), save_to() and
	 * only_public_addresses().
	 *
	 * @param   Request  $request  request to send
	 * @return  array<int, mixed>
	 * @throws  Request_Exception  when the address is refused or the file cannot be written
	 */
	protected function _setting_options(Request $request): array
	{
		$settings = $this->_settings;
		$options  = [];

		// Whatever else is set, the request and what follows it stay on the allowed schemes
		if (defined('CURLOPT_PROTOCOLS_STR'))
		{
			$options[CURLOPT_PROTOCOLS_STR]       = implode(',', $this->allowed_schemes());
			$options[CURLOPT_REDIR_PROTOCOLS_STR] = implode(',', $this->allowed_schemes());
		}

		if (isset($settings['timeout']))
		{
			$options[CURLOPT_TIMEOUT] = $settings['timeout'];
		}

		if (isset($settings['connect_timeout']))
		{
			$options[CURLOPT_CONNECTTIMEOUT] = $settings['connect_timeout'];
		}

		if (isset($settings['proxy']))
		{
			$proxy = $settings['proxy'];

			$options[CURLOPT_PROXY] = $proxy[0];

			if ($proxy[1] !== null)
			{
				$options[CURLOPT_PROXYUSERPWD] = $proxy[1];
			}
		}

		$options += $this->_certificate_options();

		if (($address = $this->_public_address($request)) !== null)
		{
			$scheme = strtolower( (string) parse_url($request->uri(), PHP_URL_SCHEME));
			$port   = parse_url($request->uri(), PHP_URL_PORT) ?: (($scheme === 'https') ? 443 : 80);
			$host   = trim( (string) parse_url($request->uri(), PHP_URL_HOST), '[]');

			// Connect to the address that was checked: a second look-up of
			// the name could give another one. Where the answer sends the
			// client is checked by the framework, not followed by curl.
			$options[CURLOPT_RESOLVE] = [$host.':'.$port.':'.$address];
			$options[CURLOPT_FOLLOWLOCATION] = false;
		}

		if (isset($settings['save_to']))
		{
			$file = @fopen($settings['save_to'], 'wb');

			if ($file === false)
				throw new Request_Exception('The file to save the answer in cannot be written: :file', [':file' => Debug::path($settings['save_to'])]);

			$this->_file = $file;

			$options[CURLOPT_RETURNTRANSFER] = false;
			$options[CURLOPT_FILE] = $file;
		}

		return $options;
	}

	/**
	 * Returns the options of curl for verify() and certificate(). The
	 * certificate of the server is checked unless verify(false) was called.
	 *
	 * @return  array<int, mixed>
	 */
	protected function _certificate_options(): array
	{
		$verify  = $this->_settings['verify'] ?? true;
		$options = [
			CURLOPT_SSL_VERIFYPEER => ($verify !== false),
			CURLOPT_SSL_VERIFYHOST => ($verify !== false) ? 2 : 0,
		];

		if (is_string($verify))
		{
			$options[CURLOPT_CAINFO] = $verify;
		}

		if (isset($this->_settings['certificate']))
		{
			$certificate = $this->_settings['certificate'];

			$options[CURLOPT_SSLCERT] = $certificate[0];

			if ($certificate[1] !== null)
			{
				$options[CURLOPT_SSLKEY] = $certificate[1];
			}

			if ($certificate[2] !== null)
			{
				$options[CURLOPT_KEYPASSWD] = $certificate[2];
			}
		}

		return $options;
	}

	/**
	 * Returns the form to send when files are uploaded: the values of
	 * [Request::post] and the files of upload(), by field name.
	 *
	 * @param   Request  $request  request to send
	 * @return  array<string, string|CURLFile>
	 */
	protected function _form(Request $request): array
	{
		$form = [];

		// curl takes a flat list: "user[name]" stands for $post['user']['name']
		foreach (explode('&', http_build_query($request->post(), '', '&')) as $pair)
		{
			if ($pair !== '')
			{
				list($name, $value) = explode('=', $pair, 2) + [1 => ''];

				$form[urldecode($name)] = urldecode($value);
			}
		}

		foreach ($this->_settings['upload'] ?? [] as $field => list($file, $name, $type))
		{
			$form[$field] = new CURLFile($file, $type, $name);
		}

		return $form;
	}

	/**
	 * Finishes a request that ran: the file of save_to() is closed, a
	 * failure becomes an exception, and the answer goes into the response.
	 *
	 * @param   Request     $request   the request
	 * @param   Response    $response  the response to fill
	 * @param   string      $body      body of the answer (empty when it was saved to a file)
	 * @param   CurlHandle  $curl      the curl handle, after it ran
	 * @param   int         $failure   the error number of curl, 0 when the request went well
	 * @return  Response
	 * @throws  Request_Exception  when the request failed
	 */
	protected function _finish(Request $request, Response $response, string $body, CurlHandle $curl, int $failure): Response
	{
		$code  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$error = ($failure === CURLE_OK) ? null : (curl_error($curl) ?: curl_strerror($failure));

		// A failed request or an error page is not the file that was asked for
		$this->_close_file($error === null AND $code < 400);

		if ($error !== null)
		{
			throw new Request_Exception(
				'Error fetching remote :url [ status :code ] :error',
				[':url' => $request->url(), ':code' => $code, ':error' => $error]
			);
		}

		return $response->status($code)->body($body);
	}

	/**
	 * Closes the files of a request that ran: the one body_from() was
	 * sending and the one save_to() was writing, when there are any.
	 *
	 * @param   bool  $keep  false removes the file of save_to()
	 * @return  void
	 */
	protected function _close_file(bool $keep): void
	{
		if (is_resource($this->_body_file))
		{
			fclose($this->_body_file);

			$this->_body_file = null;
		}

		if ( ! is_resource($this->_file))
			return;

		fclose($this->_file);

		$this->_file = null;

		if ( ! $keep AND isset($this->_settings['save_to']))
		{
			@unlink($this->_settings['save_to']);
		}
	}

	/**
	 * Sets the appropriate curl request options. Uses the responding option
	 * for POST or CURLOPT_CUSTOMREQUEST otherwise
	 *
	 * @param Request $request
	 * @param array<int, mixed>   $options
	 * @return array<int, mixed>
	 */
	public function _set_curl_request_method(Request $request, array $options): array
	{
		if ($request->method() === Request::POST)
		{
			$options[CURLOPT_POST] = true;
		}
		else
		{
			$options[CURLOPT_CUSTOMREQUEST] = $request->method();
		}

		if ($request->method() === Request::HEAD)
		{
			// The answer to HEAD has headers and no body: without this,
			// curl waits for the body the headers speak of
			$options[CURLOPT_NOBODY] = true;
		}

		return $options;
	}

}
