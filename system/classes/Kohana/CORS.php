<?php
/**
 * Cross-Origin Resource Sharing (CORS).
 *
 * A browser does not let the JavaScript of one site read answers from
 * another site, unless the other site says it may, in the response
 * headers. This class adds those headers, following the `cors` config
 * file. Requests from sites that are not listed there get no such
 * headers, and the browser keeps refusing them.
 *
 *     public function before(): void
 *     {
 *         parent::before();
 *
 *         CORS::apply($this->request, $this->response);
 *     }
 *
 * Before some requests (a PUT, a DELETE, a JSON POST) the browser first
 * asks for permission with an OPTIONS request, the "preflight".
 * [CORS::apply] answers it and returns true: there is then nothing else
 * to do for that request. [Controller_REST] does all of this by itself.
 *
 * [!!] CORS is not access control. It only tells browsers which sites may
 * read the answers; any other program can still call the URL.
 *
 * @package    Kohana
 * @category   Security
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_CORS {

	/**
	 * Adds the CORS headers to a response, when the request comes from an
	 * allowed site.
	 *
	 * @param   Request     $request   the request being answered
	 * @param   Response    $response  the response to add the headers to
	 * @param   array{origins?: array<int, string>, methods?: array<int, string>, headers?: array<int, string>, expose?: array<int, string>, credentials?: bool, max_age?: int}|null  $config    settings; null uses the `cors` config file
	 * @return  bool        true if the request was a preflight, which is now fully answered
	 */
	public static function apply(Request $request, Response $response, ?array $config = null): bool
	{
		if ($config === null)
		{
			$group = Kohana::$config->load('cors');

			/** @var array{origins?: array<int, string>, methods?: array<int, string>, headers?: array<int, string>, expose?: array<int, string>, credentials?: bool, max_age?: int} $config */
			$config = ($group instanceof Config_Group) ? $group->as_array() : [];
		}

		$origin = $request->headers('origin');

		// The answer depends on who asks: caches must keep one copy per origin
		$response->headers('vary', 'Origin');

		if ( ! is_string($origin) OR ! CORS::allowed($origin, (array) ($config['origins'] ?? [])))
		{
			// Not a cross-origin request, or a site that is not allowed
			return false;
		}

		// Name the site that asked. (The wildcard "*" is not sent: browsers
		// refuse it for requests with cookies, and naming the site always works.)
		$response->headers('access-control-allow-origin', $origin);

		if ( ! empty($config['credentials']))
		{
			// The browser may send cookies and show the answer to the script
			$response->headers('access-control-allow-credentials', 'true');
		}

		if ( ! empty($config['expose']))
		{
			// Response headers the script may read, besides the basic ones
			$response->headers('access-control-expose-headers', implode(', ', (array) $config['expose']));
		}

		if ($request->method() !== HTTP_Request::OPTIONS OR $request->headers('access-control-request-method') === null)
		{
			// A normal request: the headers above are all it needs
			return false;
		}

		// A preflight: say what is allowed, and for how long the browser may remember it
		$response->headers('access-control-allow-methods', implode(', ', (array) ($config['methods'] ?? [])));
		$response->headers('access-control-allow-headers', implode(', ', (array) ($config['headers'] ?? [])));
		$response->headers('access-control-max-age', (string) (int) ($config['max_age'] ?? 0));

		// "No Content": the answer is in the headers
		$response->status(204)->body('');

		return true;
	}

	/**
	 * Tells whether a site is in a list of allowed origins.
	 *
	 * An origin is a scheme, a host and an optional port, written exactly
	 * as the browser sends it: "https://app.example.com". The list can
	 * contain "*" to allow every site.
	 *
	 * @param   string  $origin   origin sent by the browser
	 * @param   array<int, string>   $origins  allowed origins
	 * @return  bool
	 */
	public static function allowed(string $origin, array $origins): bool
	{
		return in_array('*', $origins, true) OR in_array($origin, $origins, true);
	}

}
