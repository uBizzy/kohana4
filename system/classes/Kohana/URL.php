<?php
/**
 * URL helper class.
 *
 * [!!] You need to setup the list of trusted hosts in the `url.php` config file, before starting using this helper class.
 *
 * @package    Kohana
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_URL {

	/**
	 * Gets the base URL to the application.
	 * To specify a protocol, provide the protocol as a string or request object.
	 * If a protocol is used, a complete URL will be generated using the
	 * `$_SERVER['HTTP_HOST']` variable, which will be validated against RFC 952
	 * and RFC 2181, as well as against the list of trusted hosts you have set
	 * in the `url.php` config file.
	 *
	 *     // Absolute URL path with no host or protocol
	 *     echo URL::base();
	 *
	 *     // Absolute URL path with host, https protocol and index.php if set
	 *     echo URL::base('https', true);
	 *
	 *     // Absolute URL path with host, https protocol and subdomain part
	 *     // prepended or replaced with given value
	 *     echo URL::base('https', false, 'subdomain');
	 *
	 *     // Absolute URL path with host and protocol from $request
	 *     echo URL::base($request);
	 *
	 * @param   bool|Request|string|null    $protocol  Protocol string, [Request], or boolean
	 * @param   bool  $index     Add index file to URL?
	 * @param   string   $subdomain Subdomain string
	 * @return  string
	 * @uses    Kohana::$index_file
	 * @uses    Request::protocol()
	 */
	public static function base(string|bool|Request|null $protocol = null, bool $index = false, ?string $subdomain = null): string
	{
		// Start with the configured base URL
		$base_url = (string) Kohana::$base_url;

		if ($protocol === true)
		{
			// Use the initial request to get the protocol
			$protocol = Request::$initial;
		}

		if ($protocol instanceof Request)
		{
			if ( ! $protocol->secure())
			{
				// Use the current protocol
				list($protocol) = explode('/', strtolower($protocol->protocol()));
			}
			else
			{
				$protocol = 'https';
			}
		}

		if ( ! $protocol)
		{
			// Use the configured default protocol
			$protocol = parse_url($base_url, PHP_URL_SCHEME);
		}

		if ($index === true AND ! empty(Kohana::$index_file))
		{
			// Add the index file to the URL
			$base_url .= Kohana::$index_file.'/';
		}

		if (is_string($protocol))
		{
			if ($port = parse_url($base_url, PHP_URL_PORT))
			{
				// Found a port, make it usable for the URL
				$port = ':'.$port;
			}

			if ($host = parse_url($base_url, PHP_URL_HOST))
			{
				// Remove everything but the path from the URL
				$base_url = (string) parse_url($base_url, PHP_URL_PATH);
			}
			else
			{
				// Attempt to use HTTP_HOST and fallback to SERVER_NAME
				$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';

				// The host name is sent by the client: only text is used
				$host = is_string($host) ? $host : '';
			}

			// If subdomain passed, then prepend to host or replace existing subdomain
			if (null !== $subdomain)
			{
				if ( ! str_contains($host, '.'))
				{
					$host = $subdomain.'.'.$host;
				}
				else
				{
					// Get the domain part of host eg. example.com, then prepend subdomain
					$host = $subdomain.'.'.implode('.', array_slice(explode('.', $host), -2));
				}
			}

			// make $host lowercase
			$host = strtolower($host);

			// check that host does not contain forbidden characters (see RFC 952 and RFC 2181)
			// use preg_replace() instead of preg_match() to prevent DoS attacks with long host names
			// (When the expression itself fails the result is null, which is
			// not '' either: the host is refused, never accepted by mistake.)
			if ($host && '' !== preg_replace('/(?:^\[)?[a-zA-Z0-9-:\]_]+\.?/', '', $host)) {
				throw new Kohana_Exception(
					'Invalid host :host',
					[':host' => $host]
				);
			}

			// Validate $host, see if it matches trusted hosts
			if ( ! static::is_trusted_host($host))
			{
				throw new Kohana_Exception(
					'Untrusted host :host. If you trust :host, add it to the trusted hosts in the `url` config file.',
					[':host' => $host]
				);
			}

			// Add the protocol and domain to the base URL
			$base_url = $protocol.'://'.$host.$port.$base_url;
		}

		return $base_url;
	}

	/**
	 * Fetches an absolute site URL based on a URI segment.
	 *
	 *     echo URL::site('foo/bar');
	 *
	 * @param   string  $uri        Site URI to convert
	 * @param   bool|Request|string|null   $protocol   Protocol string or [Request] class to use protocol from
	 * @param   bool $index		Include the index_page in the URL
	 * @param   string  $subdomain  Subdomain string
	 * @return  string
	 * @uses    URL::base
	 */
	public static function site(string $uri = '', string|bool|Request|null $protocol = null, bool $index = true, ?string $subdomain = null): string
	{
		// Chop off possible scheme, host, port, user and pass parts
		$path = (string) preg_replace('~^[-a-z0-9+.]++://[^/]++/?~', '', trim($uri, '/'));

		if ( ! UTF8::is_ascii($path))
		{
			// Encode all non-ASCII characters, as per RFC 1738
			$path = (string) preg_replace_callback('~([^/#]+)~', static::_rawurlencode_callback(...), $path);
		}

		// Concat the URL
		return URL::base($protocol, $index, $subdomain).$path;
	}

	/**
	 * Callback used for encoding all non-ASCII characters, as per RFC 1738
	 * Used by URL::site()
	 *
	 * @param  array $matches  Array of matches from preg_replace_callback()
	 * @return string          Encoded string
	 */
	/**
	 * Encodes one segment of a URI path.
	 *
	 * @param   array<int|string, string>  $matches  regex matches; [0] is the segment
	 * @return  string
	 */
	protected static function _rawurlencode_callback(array $matches): string
	{
		return rawurlencode($matches[0]);
	}

	/**
	 * Merges the current GET parameters with an array of new or overloaded
	 * parameters and returns the resulting query string.
	 *
	 *     // Returns "?sort=title&limit=10" combined with any existing GET values
	 *     $query = URL::query(array('sort' => 'title', 'limit' => 10));
	 *
	 * Typically you would use this when you are sorting query results,
	 * or something similar.
	 *
	 * [!!] Parameters with a null value are left out.
	 *
	 * @param   array<string, mixed>    $params   Array of GET parameters
	 * @param   bool  $use_get  Include current request GET parameters
	 * @return  string
	 */
	public static function query(?array $params = null, bool $use_get = true): string
	{
		if ($use_get)
		{
			if ($params === null)
			{
				// Use only the current parameters
				$params = $_GET;
			}
			else
			{
				// Merge the current and new parameters
				$params = Arr::merge($_GET, $params);
			}
		}

		if (empty($params))
		{
			// No query parameters
			return '';
		}

		// Note: http_build_query returns an empty string for a params array with only null values
		$query = http_build_query($params, '', '&');

		// Don't prepend '?' to an empty string
		return ($query === '') ? '' : ('?'.$query);
	}

	/**
	 * Convert a phrase to a URL-safe title.
	 *
	 *     echo URL::title('My Blog Post'); // "my-blog-post"
	 *
	 * @param   string   $title       Phrase to convert
	 * @param   string   $separator   Word separator (any single character)
	 * @param   bool  $ascii_only  Transliterate to ASCII?
	 * @return  string
	 * @uses    UTF8::transliterate_to_ascii
	 */
	public static function title(string $title, string $separator = '-', bool $ascii_only = false): string
	{
		// The separator is used inside regular expressions delimited by "!"
		$quoted = preg_quote($separator, '!');

		if ($ascii_only)
		{
			// Transliterate any script to plain ASCII letters (intl extension)
			$title = (string) transliterator_transliterate('Any-Latin;Latin-ASCII', $title);

			// Remove all characters that are not the separator, a-z, 0-9, or whitespace
			$title = (string) preg_replace('![^'.$quoted.'a-z0-9\s]+!', '', strtolower($title));
		}
		else
		{
			// Remove all characters that are not the separator, letters, numbers, or whitespace
			$title = (string) preg_replace('![^'.$quoted.'\pL\pN\s]+!u', '', UTF8::strtolower($title));
		}

		// Replace all separator characters and whitespace by a single separator
		$title = (string) preg_replace('!['.$quoted.'\s]+!u', $separator, $title);

		// Trim separators from the beginning and end
		return trim($title, $separator);
	}

	/**
	 * Test if given $host should be trusted.
	 *
	 * Tests against given $trusted_hosts
	 * or looks for key `trusted_hosts` in `url` config
	 *
	 * The port is not part of the name: "localhost" in the list trusts
	 * "localhost:8000" as well. An entry with a port trusts that port only.
	 *
	 * @param string $host
	 * @param array<int, string> $trusted_hosts
	 * @return bool true if $host is trustworthy
	 */
	public static function is_trusted_host(string $host, ?array $trusted_hosts = null): bool
	{

		// If list of trusted hosts is not directly provided read from config
		if (empty($trusted_hosts))
		{
			$trusted_hosts = (array) Kohana::$config->load('url.trusted_hosts');
		}

		// The host without its port: "example.com:8080", "[::1]:8080"
		$name = (string) preg_replace('/^(\[[^\]]*\]|[^:]*):\d+$/D', '$1', $host);

		// loop through the $trusted_hosts array for a match
		foreach ($trusted_hosts as $trusted_host)
		{

			// make sure we fully match the trusted hosts
			if ( ! is_string($trusted_host))
				continue;

			$pattern = '#^'.$trusted_host.'$#uD';

			// return true if there is match, with or without the port
			if (preg_match($pattern, $host) OR preg_match($pattern, $name)) {
				return true;
			}

		}

		// return false as nothing is matched
		return false;

	}
}
