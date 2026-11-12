<?php
/**
 * Interface Kohana_REST_Format
 * This interface needs to be extended from every REST-Formatter
 *
 * The following formatter come shipped with Koseven:
 *  - JSON
 *  - XML
 *  - HTML
 *
 * @package        Kohana\REST
 *
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license        https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_REST_Format {

	/**
	 * Default Output Format
	 *
	 * @var string
	 */
	public static string $default_format = 'json';

	/**
	 * @var  array<string, string>  The formats a client can ask for with the "Accept"
	 *                              header of its request: names of formats by content type
	 */
	public static array $accept = [
		'application/json' => 'json',
		'application/xml'  => 'xml',
		'text/xml'         => 'xml',
		'text/csv'         => 'csv',
	];

	/**
	 * Holds an instance of the request class
	 *
	 * @var Request
	 */
	protected Request $_request;

	/**
	 * Holds an instance of the response class
	 *
	 * @var Response
	 */
	protected Response $_response;

	/**
	 * Holds the response body
	 *
	 * @var mixed
	 */
	protected mixed $_body = null;

	/**
	 * Factory Method for REST Formatter
	 *
	 * @param Request  $request  Request Class
	 * @param Response $response Response Class
	 *
	 * @throws REST_Exception
	 *
	 * @return REST_Format
	 */
	public static function factory(Request $request, Response $response): REST_Format
	{
		// The format comes from the address ("items.xml"); without one,
		// from what the client says it wants; without that, the default
		if ($request->format() === null)
		{
			$request->format(static::_accepted($request, $response) ?? static::$default_format);
		}

		// The format is part of the address a visitor typed: only letters
		// and digits can be the name of a formatter
		$format = (string) $request->format();
		$format = ctype_alnum($format) ? $format : '';

		// The formatters that come with the framework are named in capitals
		// (REST_Format_JSON); one of your own may be named as the format is.
		// File names tell capitals apart, so "json" alone finds nothing.
		$formatter = class_exists('REST_Format_'.strtoupper($format))
			? 'REST_Format_'.strtoupper($format)
			: 'REST_Format_'.$format;

		// Check if formatter Exists
		if ($format === '' OR ! class_exists($formatter))
		{
			// $formatter is the name of the class here, not an object
			throw new REST_Exception('Formatter :formatter does not exist.', [
				':formatter' => $formatter
			]);
		}

		$formatter = new $formatter($request, $response);

		// Check if client extends Request_Client_External
		if ( ! $formatter instanceof REST_Format)
		{
			throw new REST_Exception(':formatter is not a valid REST formatter.', [
				':formatter' => get_class($formatter)
			]);
		}

		// Set response content type by format used
		$response->headers('Content-Type', (string) File::mime_by_ext(strtolower($format)));

		return $formatter;
	}

	/**
	 * Returns the format a client asks for in the "Accept" header of its
	 * request, when it names one this class can write.
	 *
	 * A browser asks for HTML first and "anything" last: that is no
	 * request for a format, so a page opened in a browser gets the
	 * default. A client that names a type and nothing a browser would
	 * ("Accept: text/csv") gets that type.
	 *
	 * @param   Request   $request   the request
	 * @param   Response  $response  the response, which is told that it depends on the header
	 * @return  string|null  name of the format, null when the client names none
	 */
	protected static function _accepted(Request $request, Response $response): ?string
	{
		// Caches must keep one copy for each kind of answer
		$vary = $response->headers('vary');

		$response->headers('vary', is_string($vary) ? $vary.', Accept' : 'Accept');

		$preferred = $request->headers()->preferred_accept(array_merge(array_keys(static::$accept), ['text/html']), true);

		return is_string($preferred) ? (static::$accept[$preferred] ?? null) : null;
	}

	/**
	 * Kohana_REST_Format constructor.
	 *
	 * @param Request $request
	 */
	public function __construct(Request $request, Response $response)
	{
		$this->_request = $request;
		$this->_response = $response;

		// Make sure body is an array
		$body = $response->body();
		if (is_string($body))
		{
			$body = [
			  'body' => $body
			];
		}

		$this->_body = $body;
	}

	/**
	 * Function for formatting the body
	 *
	 * @return string
	 */
	abstract public function format(): string;

}