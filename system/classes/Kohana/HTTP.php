<?php
/**
 * This class only contains static functions which can do the following:
 *
 *  - HTTP Redirection
 *  - E-Tag Cache comparison
 *  - HTTP Header Parsing
 *  - URL-Formatting
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license        https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 * @since          3.1.0
 *
 * @package        Kohana\HTTP
 */
abstract class Kohana_HTTP {

	/**
	 * The default protocol to use if it cannot be detected
	 *
	 * @var string
	 */
	public static string $protocol = 'HTTP/1.1';

	/**
	 * Headers that are added to every response the application sends, to
	 * switch on the protections browsers offer. A header the application
	 * sets itself on the response is never overwritten.
	 *
	 * The defaults:
	 *
	 * - `x-content-type-options: nosniff`: the browser must not guess a
	 *   different type for a file than the one sent (stops an uploaded
	 *   text file from being run as a script).
	 * - `referrer-policy`: other sites are told only the site a visitor
	 *   comes from, not the full address of the page.
	 * - `x-frame-options: SAMEORIGIN`: the pages cannot be shown inside a
	 *   frame of another site ("clickjacking"). Remove this entry if the
	 *   application is meant to be embedded elsewhere.
	 *
	 * Two more are worth adding in bootstrap.php:
	 *
	 *     // Browsers must use HTTPS for the next year (only on an HTTPS site!)
	 *     HTTP::$security_headers['strict-transport-security'] = 'max-age=31536000';
	 *
	 *     // Only run scripts from this site, or inline ones carrying the nonce
	 *     HTTP::$security_headers['content-security-policy'] = "default-src 'self'; script-src 'self' 'nonce-{nonce}'";
	 *
	 * `{nonce}` is replaced by [Security::nonce], a random value that
	 * changes on every request. Set the array to `[]` to send none.
	 *
	 * @var array<string, string>  header name (lowercase) => value
	 */
	public static array $security_headers = [
		'x-content-type-options' => 'nosniff',
		'referrer-policy'        => 'strict-origin-when-cross-origin',
		'x-frame-options'        => 'SAMEORIGIN',
	];

	/**
	 * Issues a HTTP redirect.
	 *
	 * This method never returns: it always throws, either the redirect itself
	 * (an HTTP_Exception_3xx carrying the location) or an error for a bad code.
	 *
	 * @param ?string $uri  URI to redirect to (null is treated as '')
	 * @param int         $code HTTP Status code to use for the redirect (RFC 7231)
	 *
	 * @throws HTTP_Exception
	 */
	public static function redirect(?string $uri = '', int $code = 302): never
	{
		// Check if Redirection code is valid according to RFC 7231
		if ($code < 300 || $code > 308)
		{
			throw new HTTP_Exception('Invalid redirect code \':code\'', [':code' => $code]);
		}

		/** @var HTTP_Exception_Redirect $e  codes 300 to 308 are redirects */
		$e = HTTP_Exception::factory($code);

		throw $e->location($uri ?? '');
	}

	/**
	 * Checks the browser cache to see the response needs to be returned,
	 * execution will halt and a 304 Not Modified will be sent if the
	 * browser cache is up to date.
	 *
	 * @param Request  $request  Request
	 * @param Response $response Response
	 * @param string   $etag     Resource ETag
	 *
	 * @throws Request_Exception
	 * @throws HTTP_Exception_304
	 *
	 * @return Response
	 */
	public static function check_cache(Request $request, Response $response, ?string $etag = null): Response
	{
		// Generate an etag if necessary
		if ($etag === null)
		{
			$etag = $response->generate_etag();
		}

		// Set the ETag header
		$response->headers('etag', $etag);

		// Add the Cache-Control header if it is not already set
		// This allows etags to be used with max-age, etc
		$cache_control = $response->headers('cache-control');

		if (is_string($cache_control) AND $cache_control !== '')
		{
			$response->headers('cache-control', $cache_control . ', must-revalidate');
		}
		else
		{
			$response->headers('cache-control', 'must-revalidate');
		}

		// Check if we have a matching etag
		if ($request->headers('if-none-match') === $etag)
		{
			/** @var HTTP_Exception_304 $e */
			$e = HTTP_Exception::factory(304);

			// No need to send data again
			$e->headers('etag', $etag);

			throw $e;
		}

		return $response;
	}

	/**
	 * Parses a HTTP header string into an associative array
	 *
	 * @param string $header_string Header string to parse
	 *
	 * @return  HTTP_Header
	 */
	public static function parse_header_string(string $header_string): HTTP_Header
	{
		// Otherwise we use the slower PHP parsing
		$headers = [];

		// Match all HTTP headers
		if (preg_match_all('/(\w[^\s:]*):[ ]*([^\r\n]*(?:\r\n[ \t][^\r\n]*)*)/', $header_string, $matches))
		{
			// Parse each matched header
			foreach ($matches[0] as $key => $value)
			{
				// If the header has not already been set
				if (!isset($headers[$matches[1][$key]]))
				{
					// Apply the header directly
					$headers[$matches[1][$key]] = $matches[2][$key];
				} // Otherwise there is an existing entry
				else
				{
					// If the entry is an array
					if (is_array($headers[$matches[1][$key]]))
					{
						// Apply the new entry to the array
						$headers[$matches[1][$key]][] = $matches[2][$key];
					} // Otherwise create a new array with the entries
					else
					{
						$headers[$matches[1][$key]] = [$headers[$matches[1][$key]], $matches[2][$key],];
					}
				}
			}
		}

		// Return the headers
		return new HTTP_Header($headers);
	}

	/**
	 * Parses the the HTTP request headers and returns an array containing
	 * key value pairs. This method is slow, but provides an accurate
	 * representation of the HTTP request.
	 *
	 * @return  HTTP_Header
	 */
	public static function request_headers(): HTTP_Header
	{
		// If running on apache server
		if (function_exists('apache_request_headers'))
		{
			// Return the much faster method
			// Ignore Code Coverage....apache_request_headers will *never* be present with PHPUnit
			return new HTTP_Header(apache_request_headers()); // @codeCoverageIgnore
		}

		// Setup the output
		$headers = [];

		// Parse the content type
		if (!empty($_SERVER['CONTENT_TYPE']))
		{
			$headers['content-type'] = $_SERVER['CONTENT_TYPE'];
		}

		// Parse the content length
		if (!empty($_SERVER['CONTENT_LENGTH']))
		{
			$headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
		}

		foreach ($_SERVER as $key => $value)
		{
			// If there is no HTTP header here, skip
			if ( ! str_starts_with($key, 'HTTP_'))
			{
				continue;
			}

			// This is a dirty hack to ensure HTTP_X_FOO_BAR becomes X-FOO-BAR
			$headers[str_replace('_', '-', substr($key, 5))] = $value;
		}

		return new HTTP_Header($headers);
	}

	/**
	 * Processes an array of key value pairs and encodes
	 * the values to meet RFC 3986
	 *
	 * @param array<string, mixed> $params Params
	 *
	 * @return  string
	 */
	public static function www_form_urlencode(array $params): string
	{
		$encoded = [];

		foreach ($params as $key => $value)
		{
			$encoded[] = $key . '=' . rawurlencode(is_scalar($value) ? (string) $value : '');
		}

		return implode('&', $encoded);
	}

}
