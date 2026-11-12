<?php
/**
 * Stream driver performs external requests using php
 * sockets.
 *
 * This is the default driver for all external requests.
 *
 * @package        Kohana\Request
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license        https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 *
 */
class Kohana_Request_Client_Stream extends Request_Client_External {

	/**
	 * Sends the HTTP message [Request] to a remote server and processes
	 * the response.
	 *
	 * @param Request  $request  request to send
	 * @param Response $response response to send
	 *
	 * @throws Request_Exception
	 *
	 * @return  Response
	 */
	public function _send_message(Request $request, Response $response): Response
	{
		// The rest of what can be asked of a client needs curl
		$this->_only_supports(['timeout', 'connect_timeout', 'authorization']);

		// Process cookies
		if ($cookies = $request->cookie())
		{
			$request->headers('cookie', http_build_query($cookies, '', '; '));
		}

		// Get the message body
		$body = $request->body();

		// Set the content length and form-urlencoded
		if (is_string($body) AND $body !== '')
		{
			$request->headers('content-length', (string)strlen($body));

			if ( ! $request->headers('content-type'))
			{
				// A body without a declared type is form data
				$request->headers('content-type', 'application/x-www-form-urlencoded');
			}
		}

		[$protocol] = explode('/', $request->protocol());

		// Create the context
		$options = [
			strtolower($protocol) => [
				'method' => $request->method(),
				'header' => (string)$request->headers(),
				'content' => $body,
				// Seconds to wait for the remote server
				'timeout' => $this->_settings['timeout'] ?? Request_Client_External::$timeout,
			]
		];

		// Create the context stream
		$context = stream_context_create($options);

		// Set options
		if (!empty($this->_options))
		{
			stream_context_set_options($context, $this->_options);
		}

		$uri = $request->uri();

		if ($query = $request->query())
		{
			$uri .= '?' . http_build_query($query, '', '&');
		}

		// The HTTP method and body are in the context; the stream itself
		// is always opened for reading (the response). A failure is
		// reported below, so PHP's own warning is silenced.
		$stream = @fopen($uri, 'r', false, $context);

		if ($stream === false)
		{
			throw new Request_Exception(error_get_last()['message'] ?? 'Could not open :uri', [':uri' => $uri]);
		}

		if (is_resource($stream)) {
			$meta_data = stream_get_meta_data($stream);

			// Get the HTTP response code
			// The status line and the headers of the response
			$header_lines = (array) ($meta_data['wrapper_data'] ?? []);

			$http_response = array_shift($header_lines);

			// Fetch respone protocol and status
			preg_match_all('/(\w+\/\d\.\d) (\d{3})/', is_string($http_response) ? $http_response : '', $matches);

			$protocol = $matches[1][0];
			$status = (int)$matches[2][0];

			// Get any existing response headers
			$response_header = $response->headers();

			// Process headers
			foreach ($header_lines as $header_line)
			{
				if (is_string($header_line))
				{
					$response_header->parse_header_string(null, $header_line);
				}
			}

			// Build the response
			$response->status($status)->protocol($protocol)->body(stream_get_contents($stream));

			// Close the stream after use
			fclose($stream);

			return $response;
		} else {
			throw new Request_Exception('Request stream could not be opened: '.$request->method() .' '.$request->uri());
		}
	}

}
