<?php
/**
 * Response wrapper. Created as the result of any [Request] execution
 * or utility method (i.e. Redirect). Implements standard HTTP
 * response format.
 *
 * @package    Kohana
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 * @since      3.1.0
 */
class Kohana_Response implements HTTP_Response {

	/**
	 * Factory method to create a new [Response]. Pass properties
	 * in using an associative array.
	 *
	 *      // Create a new response
	 *      $response = Response::factory();
	 *
	 *      // Create a new response with headers
	 *      $response = Response::factory(array('status' => 200));
	 *
	 * @param   array<string, mixed>    $config Setup the response object
	 * @return  Response
	 */
	public static function factory(array $config = []): Response
	{
		return new Response($config);
	}

	/**
	 * @var  array<int, string>  HTTP status codes and messages
	 */
	public static array $messages = [
		// Informational 1xx
		100 => 'Continue',
		101 => 'Switching Protocols',
		102 => 'Processing',
		103 => 'Early Hints',

		// Success 2xx
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		207 => 'Multi-Status',
		208 => 'Already Reported',
		226 => 'IM Used',

		// Redirection 3xx
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found', // 1.1
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		// 306 is deprecated but reserved
		307 => 'Temporary Redirect',
		308 => 'Permanent Redirect',

		// Client Error 4xx
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		418 => 'I\'m a teapot',
		421 => 'Misdirected Request',
		422 => 'Unprocessable Entity',
		423 => 'Locked',
		424 => 'Failed Dependency',
		425 => 'Too Early',
		426 => 'Upgrade Required',
		428 => 'Precondition Required',
		429 => 'Too Many Requests',
		431 => 'Request Header Fields Too Large',
		451 => 'Unavailable For Legal Reasons',

		// Server Error 5xx
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		506 => 'Variant Also Negotiates',
		507 => 'Insufficient Storage',
		508 => 'Loop Detected',
		509 => 'Bandwidth Limit Exceeded',
		510 => 'Not Extended',
		511 => 'Network Authentication Required'
	];

	/**
	 * @var  int     The response http status
	 */
	protected int $_status = 200;

	/**
	 * @var  HTTP_Header  Headers returned in the response
	 */
	protected HTTP_Header $_header;

	/**
	 * @var  mixed       The response body: a text, or data that is still to be formatted
	 */
	protected mixed $_body = '';

	/**
	 * @var  array<string, mixed>       Cookies to be returned in the response
	 */
	protected array $_cookies = [];

	/**
	 * @var  string      The response protocol
	 */
	protected ?string $_protocol = null;

	/**
	 * Sets up the response object
	 *
	 * @param   array<string, mixed> $config Setup the response object
	 * @return  void
	 */
	public function __construct(array $config = [])
	{
		$this->_header = new HTTP_Header;

		foreach ($config as $key => $value)
		{
			if (property_exists($this, $key))
			{
				if ($key == '_header')
				{
					/** @var array<string, mixed>|HTTP_Header $value */
					$this->headers($value);
				}
				else
				{
					$this->$key = $value;
				}
			}
		}
	}

	/**
	 * Outputs the body when cast to string
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		// A body that is not text (e.g. an array waiting to be formatted) has no string form
		return is_scalar($this->_body) ? (string) $this->_body : '';
	}

	/**
	 * Gets or sets the body of the response
	 *
	 * @param mixed $content   Content of body
	 *
	 * @return  ($content is null ? mixed : static)  the body is text, or data that is still to be formatted (see Controller_REST)
	 */
	public function body(mixed $content = null): mixed
	{
		if ($content === null)
		{
			return $this->_body;
		}

		// Cast scalar types or objects (with __toString method) to string
		if (is_scalar($content) || (is_object($content) && method_exists($content, '__toString')))
		{
			$content = (string)$content;
		}

		$this->_body = $content;

		return $this;
	}

	/**
	 * Gets or sets the HTTP protocol. The standard protocol to use
	 * is `HTTP/1.1`.
	 *
	 * @param   string   $protocol Protocol to set to the request/response
	 * @return  ($protocol is null ? string : static)
	 */
	public function protocol(?string $protocol = null): string|static
	{
		if ($protocol)
		{
			$this->_protocol = strtoupper($protocol);
			return $this;
		}

		if ($this->_protocol === null)
		{
			$this->_protocol = HTTP::$protocol;
		}

		return $this->_protocol;
	}

	/**
	 * Sets or gets the HTTP status from this response.
	 *
	 *      // Set the HTTP status to 404 Not Found
	 *      $response = Response::factory()
	 *              ->status(404);
	 *
	 *      // Get the current status
	 *      $status = $response->status();
	 *
	 * @param   int  $status Status to set to this response
	 *
	 * @return  ($status is null ? int|false : static)	acting as setter \ acting as getter \ false on invalid status code
	 */
	public function status(?int $status = null): int|static|false
	{
		if ($status === null)
		{
			return $this->_status;
		}
		if (array_key_exists($status, Response::$messages))
		{
			$this->_status = $status;
			return $this;
		}
		return false;
	}

	/**
	 * Gets and sets headers to the [Response], allowing chaining
	 * of response methods. If chaining isn't required, direct
	 * access to the property should be used instead.
	 *
	 *       // Get a header
	 *       $accept = $response->headers('Content-Type');
	 *
	 *       // Set a header
	 *       $response->headers('Content-Type', 'text/html');
	 *
	 *       // Get all headers
	 *       $headers = $response->headers();
	 *
	 *       // Set multiple headers
	 *       $response->headers(array('Content-Type' => 'text/html', 'Cache-Control' => 'no-cache'));
	 *
	 * @param array<string, mixed>|HTTP_Header|string|null $key
	 * @param string $value
	 * @return ($key is null ? HTTP_Header : ($key is array|HTTP_Header ? static : ($value is null ? mixed : static)))
	 */
	public function headers(HTTP_Header|array|string|null $key = null, ?string $value = null): mixed
	{
		if ($key === null)
		{
			return $this->_header;
		}
		elseif ($key instanceof HTTP_Header)
		{
			// Replace all headers by the given set
			$this->_header = $key;
			return $this;
		}
		elseif (is_array($key))
		{
			$this->_header->exchangeArray($key);
			return $this;
		}
		elseif ($value === null)
		{
			return Arr::get($this->_header, $key);
		}
		else
		{
			$this->_header[$key] = $value;
			return $this;
		}
	}

	/**
	 * Makes this a JSON response: encodes the data as the body and sets
	 * the content type.
	 *
	 *     $this->response->json(['id' => 5, 'name' => 'Ana']);
	 *
	 *     // With a status code
	 *     $this->response->json(['error' => 'Not found'], 404);
	 *
	 * @param   mixed     $data    data to encode
	 * @param   int|null  $status  HTTP status code; null leaves the current one
	 * @return  static
	 * @throws  JsonException  if the data cannot be encoded
	 */
	public function json(mixed $data, ?int $status = null): static
	{
		if ($status !== null)
		{
			$this->status($status);
		}

		// JSON is always UTF-8; unescaped unicode keeps the output readable and small
		$this->headers('content-type', 'application/json; charset=utf-8');

		return $this->body(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
	}

	/**
	 * Makes this a CSV response: a table that spreadsheet programs open.
	 * See [REST_Format_CSV] for how the rows are written.
	 *
	 *     $this->response->csv($rows);
	 *
	 *     // Offered as a file to save
	 *     $this->response->csv($rows, 'orders-2026.csv');
	 *
	 * @param   iterable<mixed>  $rows      a list of rows, or one row
	 * @param   string|null      $filename  name of the file to offer; null shows the text
	 * @param   int|null         $status    HTTP status code; null leaves the current one
	 * @return  static
	 */
	public function csv(iterable $rows, ?string $filename = null, ?int $status = null): static
	{
		if ($status !== null)
		{
			$this->status($status);
		}

		$this->headers('content-type', 'text/csv; charset=utf-8');

		if ($filename !== null)
		{
			$this->headers('content-disposition', $this->_disposition($filename, false));
		}

		return $this->body(REST_Format_CSV::encode($rows));
	}

	/**
	 * Makes this an XML response. See [REST_Format_XML] for how the data
	 * is written.
	 *
	 *     // <root><id>5</id><name>Ana</name></root>
	 *     $this->response->xml(['id' => 5, 'name' => 'Ana']);
	 *
	 * @param   iterable<mixed>  $data    the data, lists and values inside each other
	 * @param   int|null         $status  HTTP status code; null leaves the current one
	 * @return  static
	 * @throws  REST_Exception  when the data cannot be written as XML
	 */
	public function xml(iterable $data, ?int $status = null): static
	{
		if ($status !== null)
		{
			$this->status($status);
		}

		$this->headers('content-type', 'application/xml; charset='.Kohana::$charset);

		return $this->body(REST_Format_XML::encode($data));
	}

	/**
	 * Returns the value of the "Content-Disposition" header for a file
	 * name: whether the browser shows the content or saves it, and under
	 * which name.
	 *
	 * @param   string  $name    name of the file
	 * @param   bool    $inline  show it in the browser instead of saving it
	 * @return  string
	 */
	protected function _disposition(string $name, bool $inline): string
	{
		// Inside the quoted name, quotes, backslashes and control characters
		// would break the header: remove them
		$name = (string) preg_replace('/[\x00-\x1F\x7F"\\\\]/', '', $name);

		// Plain ASCII version for old clients...
		$disposition = ($inline ? 'inline' : 'attachment').'; filename="'.UTF8::strip_non_ascii(UTF8::transliterate_to_ascii($name)).'"';

		if ( ! UTF8::is_ascii($name))
		{
			// ...and the real name, encoded as RFC 5987 defines, for the others
			$disposition .= "; filename*=UTF-8''".rawurlencode($name);
		}

		return $disposition;
	}

	/**
	 * Returns the length of the body for use with
	 * content header
	 *
	 * @return  int
	 */
	public function content_length(): int
	{
		$body = $this->body();

		return is_string($body) ? strlen($body) : 0;
	}

	/**
	 * Set and get cookies values for this response.
	 *
	 *     // Get the cookies set to the response
	 *     $cookies = $response->cookie();
	 *
	 *     // Set a cookie to the response
	 *     $response->cookie('session', array(
	 *          'value' => $value,
	 *          'expiration' => 12352234
	 *     ));
	 *
	 * @param   array<string, mixed>|string|null   $key    cookie name, or array of cookie values
	 * @param   mixed   $value  value to set to cookie: a text, or an array with "value" and "expiration"
	 * @return  ($key is null ? array<string, mixed> : ($key is array ? static : ($value is null ? mixed : static)))  the value of a cookie, all cookies, or this object after setting
	 */
	public function cookie(array|string|null $key = null, mixed $value = null): mixed
	{
		// Handle the get cookie calls
		if ($key === null)
			return $this->_cookies;
		elseif ( ! is_array($key) AND ! $value)
			return Arr::get($this->_cookies, $key);

		// Handle the set cookie calls
		if (is_array($key))
		{
			reset($key);
			foreach ($key as $_key => $_value)
			{
				$this->cookie($_key, $_value);
			}
		}
		else
		{
			if ( ! is_array($value))
			{
				$value = [
					'value' => $value,
					'expiration' => Cookie::$expiration
				];
			}
			elseif ( ! isset($value['expiration']))
			{
				$value['expiration'] = Cookie::$expiration;
			}

			$this->_cookies[$key] = $value;
		}

		return $this;
	}

	/**
	 * Deletes a cookie set to the response
	 *
	 * @param   string  $name
	 * @return  static
	 */
	public function delete_cookie(string $name): static
	{
		unset($this->_cookies[$name]);
		return $this;
	}

	/**
	 * Deletes all cookies from this response
	 *
	 * @return  static
	 */
	public function delete_cookies(): static
	{
		$this->_cookies = [];
		return $this;
	}

	/**
	 * Sends the response status and all set headers.
	 *
	 * @param   bool     $replace    replace existing headers
	 * @param   (callable)|null    $callback   function to handle header output
	 * @return  mixed
	 */
	public function send_headers(bool $replace = false, ?callable $callback = null): mixed
	{
		return $this->_header->send_headers($this, $replace, $callback);
	}

	/**
	 * Send file download as the response. All execution will be halted when
	 * this method is called! Use true for the filename to send the current
	 * response as the file content. The third parameter allows the following
	 * options to be set:
	 *
	 * Type      | Option    | Description                        | Default Value
	 * ----------|-----------|------------------------------------|--------------
	 * `boolean` | inline    | Display inline instead of download | `false`
	 * `string`  | mime_type | Manual mime type                   | Automatic
	 * `boolean` | delete    | Delete the file after sending      | `false`
	 *
	 * Download a file that already exists:
	 *
	 *     $request->send_file('media/packages/kohana.zip');
	 *
	 * Download a generated file:
	 *
	 *     $csv = tmpfile();
	 *     fputcsv($csv, ['label1', 'label2']);
	 *     $request->send_file($csv, $filename);
	 *
	 * Download generated content as a file:
	 *
	 *     $request->response($content);
	 *     $request->send_file(true, $filename);
	 *
	 * [!!] No further processing can be done after this method is called!
	 *
	 * @param   string|resource|true  $filename  filename with path, file stream, or true for the current response
	 * @param   string|null  $download  downloaded file name
	 * @param   array<string, mixed>|null   $options   additional options
	 * @return  never
	 * @throws  Kohana_Exception
	 * @uses    Response::_open_download
	 * @uses    Response::_download_headers
	 * @uses    Response::_stream
	 */
	public function send_file(mixed $filename, ?string $download = null, ?array $options = null): never
	{
		// Find out what to send: an open handle, its size, name and mime type
		list($file, $size, $download, $mime) = $this->_open_download($filename, $download, is_string($options['mime_type'] ?? null) ? $options['mime_type'] : null);

		// The part of the file to send. A range the client asks for is only
		// followed for a resumable download: only then the answer says which
		// part it is, and without that a part would pass for the whole file.
		list($start, $end) = empty($options['resumable'])
			? [0, max(0, $size - 1)]
			: $this->_calculate_byte_range($size);

		$this->_download_headers($download, $mime, $size, $start, $end, (array) $options);

		// Send all headers now
		$this->send_headers();

		$this->_stream($file, $start, $end);

		// Only a file given by its path can be deleted
		if ( ! empty($options['delete']) AND is_string($filename))
		{
			try
			{
				// Attempt to remove the file
				unlink($filename);
			}
			catch (Throwable $e)
			{
				// Do NOT display the exception, it would corrupt the download: log it
				if (is_object(Kohana::$log))
				{
					Kohana::$log->add(Log::ERROR, Kohana_Exception::text($e));
					Kohana::$log->write();
				}
			}
		}

		// Stop execution
		exit;
	}

	/**
	 * Opens the content that [Response::send_file] has to send.
	 *
	 * @param   mixed        $filename  file path, open file stream, or true for the response body
	 * @param   string|null  $download  name the browser should save the file under
	 * @param   string|null  $mime      mime type; null to guess it from the name
	 * @return  array{0: resource, 1: int, 2: string, 3: string}  list of: open file handle, size in bytes, download name, mime type
	 * @throws  Kohana_Exception
	 * @uses    File::mime_by_ext
	 */
	protected function _open_download(mixed $filename, ?string $download, ?string $mime): array
	{
		if ($filename === true OR is_resource($filename))
		{
			// There is no file name to fall back on
			if (empty($download))
				throw new Kohana_Exception('Download name must be provided for streaming files');
		}

		if ($filename === true)
		{
			// Send the response body: put it in a temporary file, so it can be read in ranges
			$file = tmpfile();
			fwrite($file, (string) $this);
		}
		elseif (is_resource($filename))
		{
			if (get_resource_type($filename) !== 'stream' OR stream_get_meta_data($filename)['seekable'] === false)
				throw new Kohana_Exception('Resource must be a file handle');

			$file = $filename;
		}
		else
		{
			// Get the complete file path
			$path = is_string($filename) ? realpath($filename) : false;

			// Use the file name as the download name by default
			$download = $download ?: basename( (string) $path);

			$file = ($path !== false AND is_file($path)) ? fopen($path, 'rb') : false;
		}

		if ( ! is_resource($file))
		{
			throw new Kohana_Exception('Could not read file to send: :file', [
				':file' => $download,
			]);
		}

		// Guess the mime type from the extension of the download name
		$mime = $mime ?: File::mime_by_ext(pathinfo( (string) $download, PATHINFO_EXTENSION));

		$stat = fstat($file);

		return [$file, $stat ? $stat['size'] : 0, (string) $download, $mime ?: 'application/octet-stream'];
	}

	/**
	 * Sets the headers of a file download.
	 *
	 * @param   string  $download  name the browser should save the file under
	 * @param   string  $mime      mime type of the content
	 * @param   int     $size      size of the whole file in bytes
	 * @param   int     $start     first byte that is sent
	 * @param   int     $end       last byte that is sent
	 * @param   array<string, mixed>   $options   options given to send_file(): inline, resumable
	 * @return  void
	 */
	protected function _download_headers(string $download, string $mime, int $size, int $start, int $end, array $options): void
	{
		$disposition = $this->_disposition($download, ! empty($options['inline']));

		if ( ! empty($options['resumable']))
		{
			if ($start > 0 OR $end < ($size - 1))
			{
				// Partial Content
				$this->_status = 206;
			}

			// Range of bytes being sent
			$this->_header['content-range'] = 'bytes '.$start.'-'.$end.'/'.$size;
			$this->_header['accept-ranges'] = 'bytes';
		}

		// Set the headers for a download
		$this->_header['content-disposition'] = $disposition;
		$this->_header['content-type']        = $mime;
		$this->_header['content-length']      = (string) (($end - $start) + 1);

		// The browser must not guess a different type from the content
		$this->_header['x-content-type-options'] = 'nosniff';
	}

	/**
	 * Sends a range of bytes of an open file to the browser, then closes the file.
	 *
	 * @param   resource  $file   open, seekable file handle
	 * @param   int       $start  first byte to send
	 * @param   int       $end    last byte to send
	 * @return  void
	 */
	protected function _stream(mixed $file, int $start, int $end): void
	{
		while (ob_get_level())
		{
			// Flush all output buffers
			ob_end_flush();
		}

		// Manually stop execution if the client goes away
		ignore_user_abort(true);

		// Send data in 16kb blocks
		$block = 1024 * 16;

		fseek($file, $start);

		while ( ! feof($file) AND ($pos = ftell($file)) <= $end)
		{
			if (connection_aborted())
				break;

			if ($pos + $block > $end)
			{
				// Do not read past the end of the range
				$block = $end - $pos + 1;
			}

			// Output a block of the file
			echo fread($file, max(1, $block));

			// Send the data now
			flush();
		}

		// Close the file
		fclose($file);
	}

	/**
	 * Renders the HTTP_Interaction to a string, producing
	 *
	 *  - Protocol
	 *  - Headers
	 *  - Body
	 *
	 * @return  string
	 */
	public function render(): string
	{
		if ( ! $this->_header->offsetExists('content-type'))
		{
			// Add the default Content-Type header if required
			$this->_header['content-type'] = Kohana::$content_type.'; charset='.Kohana::$charset;
		}

		// Set the content length
		$this->headers('content-length', (string) $this->content_length());

		// If Kohana expose, set the user-agent
		if (Kohana::$expose)
		{
			$this->headers('user-agent', Kohana::version());
		}

		// Prepare cookies
		if ($this->_cookies)
		{
			$cookies = [];

			// Parse each cookie into a "name=value; expires=date" string
			foreach ($this->_cookies as $key => $value)
			{
				/** @var array{value: string, expiration: int} $value */
				$cookies[] = $key.'='.$value['value'].'; expires='.date('l, d M Y H:i:s T', $value['expiration']);
			}

			// Create the cookie string
			$this->_header['set-cookie'] = $cookies;
		}

		$output = $this->protocol().' '.$this->_status.' '.Response::$messages[$this->_status]."\r\n";
		$output .= (string) $this->_header;
		$output .= (string) $this;

		return $output;
	}

	/**
	 * Generate ETag
	 * Generates an ETag from the response ready to be returned
	 *
	 * @throws Request_Exception
	 * @return String Generated ETag
	 */
	public function generate_etag(): string
	{
		if ($this->_body === '')
		{
			throw new Request_Exception('No response yet associated with request - cannot auto generate resource ETag');
		}

		// Generate a unique hash for the response
		return '"'.hash('sha256', $this->render()).'"';
	}

	/**
	 * Parse the byte ranges from the HTTP_RANGE header used for
	 * resumable downloads.
	 *
	 * @link   http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.35
	 * @return array<int, string>|false
	 */
	protected function _parse_byte_range(): array|false
	{
		if ( ! isset($_SERVER['HTTP_RANGE']))
		{
			return false;
		}

		preg_match_all('/(-?[0-9]++(?:-(?![0-9]++))?)(?:-?([0-9]++))?/', is_string($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : '', $matches, PREG_SET_ORDER);

		// No usable range in the header counts as no range
		return $matches[0] ?? false;
	}

	/**
	 * Calculates the byte range to use with send_file. If HTTP_RANGE doesn't
	 * exist then the complete byte range is returned
	 *
	 * @param  int $size
	 * @return array<int, int>
	 */
	protected function _calculate_byte_range(int $size): array
	{
		// Defaults to start with when the HTTP_RANGE header doesn't exist.
		$start = 0;
		$end = $size - 1;

		if ($range = $this->_parse_byte_range())
		{
			// We have a byte range from HTTP_RANGE
			$start = $range[1];

			if ($start[0] === '-')
			{
				// A negative value means we start from the end, so -500 would be the
				// last 500 bytes.
				// When more is asked than there is, that is the whole file.
				$start = max(0, $size - abs( (int) $start));
			}

			if (isset($range[2]))
			{
				// Set the end range
				$end = $range[2];
			}
		}

		// Normalize values.
		$start = abs(intval($start));

		// Keep the the end value in bounds and normalize it.
		$end = min(abs(intval($end)), $size - 1);

		// Keep the start in bounds.
		$start = ($end < $start) ? 0 : max($start, 0);

		return [$start, $end];
	}

}
