<?php
/**
 * Request. Uses the [Route] class to determine what
 * [Controller] to send the request to.
 *
 * @package    Kohana
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Request implements HTTP_Request {

	/**
	 * @var  string  client user agent
	 */
	public static string $user_agent = '';

	/**
	 * @var  string  client IP address
	 */
	public static string $client_ip = '0.0.0.0';

	/**
	 * @var  array<int, string>  trusted proxy server IPs, host names or networks (see Request::is_trusted_proxy)
	 */
	public static array $trusted_proxies = ['127.0.0.1', 'localhost', 'localhost.localdomain'];

	/**
	 * @var  array<int, string>  keys of $_SERVER for the headers a trusted proxy may use to pass
	 *              on the address of the real client, in order of preference.
	 *              They are only read when the request comes from one of the
	 *              [Request::$trusted_proxies]: anyone can send these headers,
	 *              so from any other address they are ignored.
	 */
	public static array $client_ip_headers = [
		'HTTP_CF_CONNECTING_IP',
		'HTTP_X_REAL_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_CLIENT_IP',
	];

	/**
	 * @var  Request  main request instance
	 */
	public static ?Request $initial = null;

	/**
	 * @var  Request  currently executing request instance
	 */
	public static ?Request $current = null;

	/**
	 * Creates a new request object for the given URI. New requests should be
	 * Created using the [Request::factory] method.
	 *
	 *     $request = Request::factory($uri);
	 *
	 * If $cache parameter is set, the response for the request will attempt to
	 * be retrieved from the cache.
	 *
	 * @param   string  $uri              URI of the request
	 * @param   array<string, mixed>   $client_params    An array of params to pass to the request client
	 * @param   bool    $allow_external   Allow external requests? (deprecated in 3.3)
	 * @param   array<string, Route>   $injected_routes  An array of routes to use, for testing
	 * @return  Request
	 * @throws  Request_Exception
	 * @uses    Route::all
	 * @uses    Route::matches
	 */
	public static function factory(string|bool $uri = true, ?array $client_params = [], bool $allow_external = true, array $injected_routes = []): Request
	{
		if (Request::$initial)
		{
			// A sub-request: nothing is taken from the environment
			return new Request( (string) $uri, $client_params, $allow_external, $injected_routes);
		}

		// A value of the environment, when it is text: all of it comes from outside
		$server = fn (string $key): ?string => is_string($_SERVER[$key] ?? null) ? $_SERVER[$key] : null;

		// This is the initial (main) request: describe it from the environment
		if (isset($_SERVER['HTTP_USER_AGENT']))
		{
			// Browser type
			Request::$user_agent = $server('HTTP_USER_AGENT') ?? '';
		}

		Request::$client_ip = Request::_detect_client_ip();

		if ($uri === true)
		{
			// Attempt to guess the proper URI
			$uri = Request::detect_uri();
		}

		// Create the instance singleton
		Request::$initial = $request = new Request( (string) $uri, $client_params, $allow_external, $injected_routes);

		// Store global GET and POST data in the initial request only
		$request->protocol(HTTP::$protocol)
			->query($_GET)
			->post($_POST)
			->secure(Request::_detect_secure())
			->method($server('REQUEST_METHOD') ?? HTTP_Request::GET)
			->cookie(Request::_signed_cookies());

		if (isset($_SERVER['HTTP_REFERER']))
		{
			// There is a referrer for this request
			$request->referrer($server('HTTP_REFERER'));
		}

		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']))
		{
			// Typically used to denote AJAX requests
			$request->requested_with($server('HTTP_X_REQUESTED_WITH'));
		}

		if ($request->method() !== HTTP_Request::GET)
		{
			// Ensure the raw body is saved for future use
			$request->body( (string) file_get_contents('php://input'));
		}

		return $request;
	}

	/**
	 * Tells whether the current request reached the site over HTTPS: either
	 * directly, or through a trusted proxy that says so in the
	 * X-Forwarded-Proto header.
	 *
	 * @return  bool
	 * @uses    Request::is_trusted_proxy
	 */
	protected static function _detect_secure(): bool
	{
		if ( ! empty($_SERVER['HTTPS']) AND filter_var($_SERVER['HTTPS'], FILTER_VALIDATE_BOOLEAN))
			return true;

		// The header can be sent by anyone, so it only counts from a trusted proxy
		return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null) === 'https'
			AND Request::is_trusted_proxy(is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : null);
	}

	/**
	 * Finds the IP address of the client.
	 *
	 * The only address that cannot be faked is REMOTE_ADDR, the machine that
	 * connected to the server. Headers such as X-Forwarded-For are sent by
	 * the client and can contain anything, so they are only read when the
	 * connection comes from one of the [Request::$trusted_proxies]. Then the
	 * first of the [Request::$client_ip_headers] that holds a valid address
	 * is used. A header can list several addresses ("client, proxy1,
	 * proxy2"): they are read from the right, skipping our own proxies, so
	 * the result is the address the outermost trusted proxy really saw.
	 *
	 * @return  string  a valid IPv4 or IPv6 address; "0.0.0.0" if there is none (CLI)
	 * @uses    Request::is_trusted_proxy
	 */
	protected static function _detect_client_ip(): string
	{
		$remote = $_SERVER['REMOTE_ADDR'] ?? null;

		if ( ! is_string($remote) OR filter_var($remote, FILTER_VALIDATE_IP) === false)
		{
			// No usable address, e.g. when running from the command line
			return '0.0.0.0';
		}

		if ( ! Request::is_trusted_proxy($remote))
		{
			// A direct connection: forwarding headers are not to be believed
			return $remote;
		}

		foreach (Request::$client_ip_headers as $header)
		{
			if (empty($_SERVER[$header]) OR ! is_string($_SERVER[$header]))
				continue;

			// Read the list from the right: the last entries were added by proxies
			foreach (array_reverse(explode(',', $_SERVER[$header])) as $ip)
			{
				$ip = trim($ip);

				if (filter_var($ip, FILTER_VALIDATE_IP) === false)
				{
					// Not an address: do not trust the rest of this header
					break;
				}

				if ( ! Request::is_trusted_proxy($ip))
				{
					// The first address that is not one of our proxies is the client
					return $ip;
				}
			}
		}

		// The proxy passed nothing usable on: the proxy itself is the client
		return $remote;
	}

	/**
	 * Tells whether an address is one of the [Request::$trusted_proxies].
	 * An entry of that list is an exact address or host name ("127.0.0.1")
	 * or a network in CIDR notation ("10.0.0.0/8", "2001:db8::/32").
	 *
	 *     Request::$trusted_proxies[] = '10.0.0.0/8';
	 *
	 * @param   string|null  $ip  address to check
	 * @return  bool
	 */
	public static function is_trusted_proxy(?string $ip): bool
	{
		return Request::ip_in_list($ip, Request::$trusted_proxies);
	}

	/**
	 * Tells whether an address is in a list of addresses and networks.
	 * An entry is an exact address or host name ("127.0.0.1") or a
	 * network in CIDR notation ("10.0.0.0/8", "2001:db8::/32").
	 *
	 *     if (Request::ip_in_list(Request::$client_ip, ['203.0.113.7', '10.0.0.0/8']))
	 *
	 * @param   string|null        $ip    address to check
	 * @param   array<int, mixed>  $list  addresses and networks; what is not text is skipped
	 * @return  bool
	 */
	public static function ip_in_list(?string $ip, array $list): bool
	{
		if ($ip === null OR $ip === '')
			return false;

		// The address in binary form; false if it is a host name
		$address = @inet_pton($ip);

		foreach ($list as $entry)
		{
			if ( ! is_string($entry))
				continue;

			if ($entry === $ip)
				return true;

			if ($address === false OR ! str_contains($entry, '/'))
				continue;

			// A network: "address/number of leading bits that must match"
			list($network, $bits) = explode('/', $entry, 2);

			$network = @inet_pton($network);

			// IPv4 and IPv6 addresses have different lengths and never match each other
			if ($network === false OR strlen($network) !== strlen($address) OR ! ctype_digit($bits))
				continue;

			$bits = min( (int) $bits, strlen($address) * 8);

			// Compare the whole bytes first, then the remaining bits of the next byte
			$bytes = intdiv($bits, 8);
			$rest  = $bits % 8;

			if (substr($address, 0, $bytes) !== substr($network, 0, $bytes))
				continue;

			if ($rest === 0)
				return true;

			$mask = 0xFF << (8 - $rest) & 0xFF;

			if ((ord($address[$bytes]) & $mask) === (ord($network[$bytes]) & $mask))
				return true;
		}

		return false;
	}

	/**
	 * Reads the cookies of the current request, keeping only the values
	 * whose signature is valid (see [Cookie::get]).
	 *
	 * @return  array<string, mixed>  cookie name => value, or null for an invalid cookie
	 * @uses    Cookie::get
	 */
	protected static function _signed_cookies(): array
	{
		$cookies = [];

		foreach (array_keys($_COOKIE) as $key)
		{
			$cookies[ (string) $key] = Cookie::get( (string) $key);
		}

		return $cookies;
	}

	/**
	 * Automatically detects the URI of the main request using PATH_INFO,
	 * REQUEST_URI, PHP_SELF or REDIRECT_URL.
	 *
	 *     $uri = Request::detect_uri();
	 *
	 * @return  string  URI of the main request
	 * @throws  Kohana_Exception
	 * @since   3.0.8
	 */
	public static function detect_uri(): string
	{
		if ( ! empty($_SERVER['PATH_INFO']))
		{
			// PATH_INFO does not contain the docroot or index
			$uri = $_SERVER['PATH_INFO'];
		}
		else
		{
			// REQUEST_URI and PHP_SELF include the docroot and index

			if (isset($_SERVER['REQUEST_URI']))
			{
				/**
				 * We use REQUEST_URI as the fallback value. The reason
				 * for this is we might have a malformed URL such as:
				 *
				 *  http://localhost/http://example.com/judge.php
				 *
				 * which parse_url can't handle. So rather than leave empty
				 * handed, we'll use this.
				 */
				$uri = $_SERVER['REQUEST_URI'];

				if (is_string($uri) AND $request_uri = parse_url($uri, PHP_URL_PATH))
				{
					// Valid URL path found, set it.
					$uri = $request_uri;
				}

				// Decode the request URI
				$uri = rawurldecode(is_string($uri) ? $uri : '');
			}
			elseif (isset($_SERVER['PHP_SELF']))
			{
				$uri = $_SERVER['PHP_SELF'];
			}
			elseif (isset($_SERVER['REDIRECT_URL']))
			{
				$uri = $_SERVER['REDIRECT_URL'];
			}
			else
			{
				// The web server gave none of the values the address is read
				// from: its setup does not pass the request on as PHP expects
				throw new Kohana_Exception('Unable to detect the URI using PATH_INFO, REQUEST_URI, PHP_SELF or REDIRECT_URL');
			}

			// The values of the environment are only used when they are text
			$uri = is_string($uri) ? $uri : '';

			// Get the path from the base URL, including the index file
			$base_url = (string) parse_url( (string) Kohana::$base_url, PHP_URL_PATH);

			if ($base_url !== '' AND str_starts_with($uri, $base_url))
			{
				// Remove the base URL from the URI
				$uri = (string) substr($uri, strlen($base_url));
			}

			if (Kohana::$index_file AND str_starts_with($uri, Kohana::$index_file))
			{
				// Remove the index file from the URI
				$uri = (string) substr($uri, strlen(Kohana::$index_file));
			}
		}

		return is_string($uri) ? $uri : '';
	}

	/**
	 * Return the currently executing request. This is changed to the current
	 * request when [Request::execute] is called and restored when the request
	 * is completed.
	 *
	 *     $request = Request::current();
	 *
	 * @return  Request
	 * @since   3.0.5
	 */
	public static function current(): ?Request
	{
		return Request::$current;
	}

	/**
	 * Returns the first request encountered by this framework. This will should
	 * only be set once during the first [Request::factory] invocation.
	 *
	 *     // Get the first request
	 *     $request = Request::initial();
	 *
	 *     // Test whether the current request is the first request
	 *     if (Request::initial() === Request::current())
	 *          // Do something useful
	 *
	 * @return  Request
	 * @since   3.1.0
	 */
	public static function initial(): ?Request
	{
		return Request::$initial;
	}

	/**
	 * Returns information about the initial user agent.
	 *
	 * @param   array<int, string>|string   $value  array or string to return: browser, version, robot, mobile, platform
	 * @return  array<string, mixed>|string|false   requested information, false if nothing is found
	 * @uses    Request::$user_agent
	 * @uses    Text::user_agent
	 */
	public static function user_agent(string|array $value): array|string|false
	{
		return Text::user_agent(Request::$user_agent, $value);
	}

	/**
	 * Determines if a file larger than the post_max_size has been uploaded. PHP
	 * does not handle this situation gracefully on its own, so this method
	 * helps to solve that problem.
	 *
	 * @return  bool
	 * @uses    Num::bytes
	 * @uses    Arr::get
	 */
	public static function post_max_size_exceeded(): bool
	{
		// Make sure the request method is POST
		if (Request::$initial?->method() !== HTTP_Request::POST)
			return false;

		// Get the post_max_size in bytes
		$max_bytes = Num::bytes( (string) ini_get('post_max_size'));

		// Error occurred if method is POST, and content length is too long
		return (Arr::get($_SERVER, 'CONTENT_LENGTH') > $max_bytes);
	}

	/**
	 * Process a request to find a matching route
	 *
	 * @param   Request  $request Request
	 * @param   array<string, Route>   $routes  Route
	 * @return  array<string, mixed>
	 */
	public static function process(Request $request, ?array $routes = null): ?array
	{
		// Load routes
		$routes = (empty($routes)) ? Route::all() : $routes;
		$params = null;

		// Names of the routes that did not match, kept while profiling
		$tried = [];

		if (Kohana::$profiling)
		{
			// One benchmark for the search, with the routes that were tried
			$benchmark = Profiler::start('Routing', $request->uri());
		}

		foreach ($routes as $name => $route)
		{
			// Use external routes for reverse routing only
			if ($route->is_external())
			{
				continue;
			}

			// We found something suitable
			if ($params = $route->matches($request))
			{
				if (isset($benchmark))
				{
					Profiler::stop($benchmark);
					Profiler::note($benchmark, 'matched "'.$name.'"'.($tried ? ' after trying: '.implode(', ', $tried) : ''));
				}

				return [
					'params' => $params,
					'route' => $route,
				];
			}

			if (isset($benchmark))
			{
				$tried[] = (string) $name;
			}
		}

		if (isset($benchmark))
		{
			Profiler::stop($benchmark);
			Profiler::note($benchmark, 'no route matched; tried: '.implode(', ', $tried));
		}

		return null;
	}

	/**
	 * @var  string  the x-requested-with header which most likely
	 *               will be xmlhttprequest
	 */
	protected ?string $_requested_with = null;

	/**
	 * @var  string  method: GET, POST, PUT, DELETE, HEAD, etc
	 */
	protected string $_method = 'GET';

	/**
	 * @var  string  protocol: HTTP/1.1, FTP, CLI, etc
	 */
	protected ?string $_protocol = null;

	/**
	 * @var  bool
	 */
	protected bool $_secure = false;

	/**
	 * @var  string  referring URL
	 */
	protected ?string $_referrer = null;

	/**
	 * @var  Route       route matched for this request
	 */
	protected ?Route $_route = null;

	/**
	 * @var  array<string, Route>       array of routes to manually look at instead of the global namespace
	 */
	protected array $_routes = [];

	/**
	 * @var  HTTP_Header  headers to sent as part of the request
	 */
	protected HTTP_Header $_header;

	/**
	 * @var  string|null  the body
	 */
	protected mixed $_body = null;

	/**
	 * @var  string  controller directory
	 */
	protected string $_directory = '';

	/**
	 * @var  string  controller to be executed
	 */
	protected ?string $_controller = null;

	/**
	 * Requested Format (json, xml, html)
	 * @var string
	 */
	protected ?string $_format = null;

	/**
	 * @var  string  action to be executed in the controller
	 */
	protected ?string $_action = null;

	/**
	 * @var  string  the URI of the request
	 */
	protected string $_uri = '';

	/**
	 * @var  bool  external request
	 */
	protected bool $_external = false;

	/**
	 * @var  array<string, mixed>   parameters from the route
	 */
	protected array $_params = [];

	/**
	 * @var array<int|string, mixed>    query parameters
	 */
	protected array $_get = [];

	/**
	 * @var array<int|string, mixed>    post parameters
	 */
	protected array $_post = [];

	/**
	 * @var array<string, mixed>    cookies to send with the request
	 */
	protected array $_cookies = [];

	/**
	 * @var Request_Client
	 */
	protected Request_Client $_client;

	/**
	 * Creates a new request object for the given URI. New requests should be
	 * Created using the [Request::factory] method.
	 *
	 *     $request = new Request($uri);
	 *
	 * If $cache parameter is set, the response for the request will attempt to
	 * be retrieved from the cache.
	 *
	 * @param   string  $uri              URI of the request
	 * @param   array<string, mixed>   $client_params    Array of params to pass to the request client
	 * @param   bool    $allow_external   Allow external requests? (deprecated in 3.3)
	 * @param   array<string, Route>   $injected_routes  An array of routes to use, for testing
	 * @return  void
	 * @throws  Request_Exception
	 * @uses    Route::all
	 * @uses    Route::matches
	 */
	public function __construct(string $uri, ?array $client_params = [], bool $allow_external = true, array $injected_routes = [])
	{
		$client_params ??= [];

		// Initialise the header
		$this->_header = new HTTP_Header([]);

		// Assign injected routes
		$this->_routes = $injected_routes;

		// Cleanse query parameters from URI (faster that parse_url())
		$split_uri = explode('?', $uri);
		$uri = array_shift($split_uri);

		if ($split_uri)
		{
			parse_str($split_uri[0], $this->_get);
		}

		// Detect protocol (if present)
		// $allow_external = false prevents the default index.php from
		// being able to proxy external pages.
		if ( ! $allow_external OR (strpos($uri, '://') === false AND strncmp($uri, '//', 2)))
		{
			// Remove leading and trailing slashes from the URI
			$this->_uri = trim($uri, '/');

			// Apply the client
			$this->_client = new Request_Client_Internal($client_params);
		}
		else
		{
			// Create a route
			$this->_route = new Route($uri);

			// Store the URI
			$this->_uri = $uri;

			// Set the security setting if required
			if (strpos($uri, 'https://') === 0)
			{
				$this->secure(true);
			}

			// Set external state
			$this->_external = true;

			// Setup the client
			$this->_client = Request_Client_External::factory($client_params);
		}
	}

	/**
	 * Returns the response as the string representation of a request.
	 *
	 *     echo $request;
	 *
	 * @return  string
	 */
	public function __toString(): string
	{
		return $this->render();
	}

	/**
	 * Sets and gets the uri from the request.
	 *
	 * @param   string $uri
	 * @return  ($uri is null ? string : static)
	 */
	public function uri(?string $uri = null): string|static
	{
		if ($uri === null)
		{
			// Act as a getter
			return ($this->_uri === '') ? '/' : $this->_uri;
		}

		// Act as a setter
		$this->_uri = $uri;

		return $this;
	}

	/**
	 * Create a URL string from the current request. This is a shortcut for:
	 *
	 *     echo URL::site($this->request->uri(), $protocol);
	 *
	 * @param   bool|Request|string|null    $protocol  protocol string or Request object
	 * @return  string
	 * @since   3.0.7
	 * @uses    URL::site
	 */
	public function url(string|bool|Request|null $protocol = null): string
	{
		if ($this->is_external())
		{
			// If it's an external request return the URI
			return $this->uri();
		}

		// Create a URI with the current route, convert to a URL and returns
		return URL::site($this->uri(), $protocol);
	}

	/**
	 * Retrieves a value from the route parameters.
	 *
	 *     $id = $request->param('id');
	 *
	 * @param   string   $key      Key of the value
	 * @param   mixed    $default  Default value if the key is not set
	 * @return  ($key is null ? array<string, mixed> : mixed)
	 */
	public function param(?string $key = null, mixed $default = null): mixed
	{
		if ($key === null)
		{
			// Return the full array
			return $this->_params;
		}

		return $this->_params[$key] ?? $default;
	}

	/**
	 * Get / Set requested format
	 *
	 * @param ?string $format e.g JSON, XML, etc...
	 *
	 * @return ($format is null ? string|null : static)
	 */
	public function format(?string $format = null): string|static|null
	{
		if ($format === null)
		{
			return $this->_format;
		}

		$this->_format = $format;

		return $this;
	}

	/**
	 * Sets and gets the referrer from the request.
	 *
	 * @param   string $referrer
	 * @return  ($referrer is null ? string|null : static)
	 */
	public function referrer(?string $referrer = null): string|static|null
	{
		if ($referrer === null)
		{
			// Act as a getter
			return $this->_referrer;
		}

		// Act as a setter
		$this->_referrer = $referrer;

		return $this;
	}

	/**
	 * Sets and gets the route from the request.
	 *
	 * @param   Route|null $route
	 * @return  ($route is null ? Route|null : static)
	 */
	public function route(?Route $route = null): Route|static|null
	{
		if ($route === null)
		{
			// Act as a getter
			return $this->_route;
		}

		// Act as a setter
		$this->_route = $route;

		return $this;
	}

	/**
	 * Sets and gets the directory for the controller.
	 *
	 * @param   string   $directory  Directory to execute the controller from
	 * @return  ($directory is null ? string : static)
	 */
	public function directory(?string $directory = null): string|static
	{
		if ($directory === null)
		{
			// Act as a getter
			return $this->_directory;
		}

		// Act as a setter
		$this->_directory = $directory;

		return $this;
	}

	/**
	 * Sets and gets the controller for the matched route.
	 *
	 * @param   string   $controller  Controller to execute the action
	 * @return  ($controller is null ? string|null : static)
	 */
	public function controller(?string $controller = null): string|static|null
	{
		if ($controller === null)
		{
			// Act as a getter
			return $this->_controller;
		}

		// Act as a setter
		$this->_controller = $controller;

		return $this;
	}

	/**
	 * Sets and gets the action for the controller.
	 *
	 * @param   string   $action  Action to execute the controller from
	 * @return  ($action is null ? string|null : static)
	 */
	public function action(?string $action = null): string|static|null
	{
		if ($action === null)
		{
			// Act as a getter
			return $this->_action;
		}

		// Act as a setter
		$this->_action = $action;

		return $this;
	}

	/**
	 * Gets or sets the [Request_Client] used to execute this request.
	 *
	 *     $client = $request->client();          // getter
	 *     $request->client(new Request_Client_Curl);  // setter, chainable
	 *
	 * @param   ?Request_Client  $client  Client to set, or null to read the current one
	 * @return  ($client is null ? Request_Client : static)  The client when reading, this request when setting
	 */
	public function client(?Request_Client $client = null): Request_Client|static
	{
		// Act as a getter
		if ($client === null)
			return $this->_client;

		// Act as a setter
		$this->_client = $client;

		return $this;
	}

	/**
	 * Gets and sets the requested with property, which should
	 * be relative to the x-requested-with pseudo header.
	 *
	 * @param   string    $requested_with Requested with value
	 * @return  ($requested_with is null ? string|null : static)
	 */
	public function requested_with(?string $requested_with = null): string|static|null
	{
		if ($requested_with === null)
		{
			// Act as a getter
			return $this->_requested_with;
		}

		// Act as a setter
		$this->_requested_with = strtolower($requested_with);

		return $this;
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
	 * @return  Response
	 * @throws  Request_Exception
	 * @throws  HTTP_Exception_404
	 * @uses    [Kohana::$profiling]
	 * @uses    [Profiler]
	 */
	public function execute(): Response
	{
		if ( ! $this->_external)
		{
			$processed = Request::process($this, $this->_routes);

			if ($processed)
			{
				/** @var array{route: Route, params: array<string, string>} $processed  see Request::process() */

				// Store the matching route
				$this->_route = $processed['route'];
				$params = $processed['params'];

				// Is this route external?
				$this->_external = $processed['route']->is_external();

				if (isset($params['directory']))
				{
					// Controllers are in a sub-directory
					$this->_directory = $params['directory'];
				}

				// Requested format e.g XML, JSON, etc..
				if (isset($params['format']))
				{
					$this->_format = $params['format'];
				}

				// Store the controller
				$this->_controller = $params['controller'];

				// Store the action
				$this->_action = $params['action'] ?? Route::$default_action;

				// These are accessible as public vars and can be overloaded
				unset($params['controller'], $params['action'], $params['directory']);

				// Params cannot be changed once matched
				$this->_params = $params;
			}
		}

		if ( ! $this->_route instanceof Route)
		{
			return HTTP_Exception::factory(404, 'Unable to find a route to match the URI: :uri', [
				':uri' => $this->_uri,
			])->request($this)
				->get_response();
		}

		return $this->_client->execute($this);
	}

	/**
	 * Returns whether this request is the initial request Kohana received.
	 * Can be used to test for sub requests.
	 *
	 *     if ( ! $request->is_initial())
	 *         // This is a sub request
	 *
	 * @return  bool
	 */
	public function is_initial(): bool
	{
		return ($this === Request::$initial);
	}

	/**
	 * Readonly access to the [Request::$_external] property.
	 *
	 *     if ( ! $request->is_external())
	 *          // This is an internal request
	 *
	 * @return  bool
	 */
	public function is_external(): bool
	{
		return $this->_external;
	}

	/**
	 * Returns whether this is an ajax request (as used by JS frameworks)
	 *
	 * @return  bool
	 */
	public function is_ajax(): bool
	{
		return ($this->requested_with() === 'xmlhttprequest');
	}

	/**
	 * Gets or sets the HTTP method. Usually GET, POST, PUT or DELETE in
	 * traditional CRUD applications.
	 *
	 * @param   string   $method  Method to use for this request
	 * @return  ($method is null ? string : static)
	 */
	public function method(?string $method = null): string|static
	{
		if ($method === null)
		{
			// Act as a getter
			return $this->_method;
		}

		// Method is always uppercase
		$method = strtoupper($method);

		// Allow overriding method
		$override = $this->headers('X-HTTP-Method-Override');

		// Act as a setter
		$this->_method = (is_string($override) && $override !== '' && defined('HTTP_REQUEST::' . $override)) ? $override : $method;

		return $this;
	}

	/**
	 * Gets or sets the HTTP protocol. If there is no current protocol set,
	 * it will use the default set in HTTP::$protocol
	 *
	 * @param   string   $protocol  Protocol to set to the request
	 * @return  ($protocol is null ? string : static)
	 */
	public function protocol(?string $protocol = null): string|static
	{
		if ($protocol === null)
		{
			if ($this->_protocol)
				return $this->_protocol;
			else
				return $this->_protocol = HTTP::$protocol;
		}

		// Act as a setter
		$this->_protocol = strtoupper($protocol);
		return $this;
	}

	/**
	 * Getter/Setter to the security settings for this request. This
	 * method should be treated as immutable.
	 *
	 * @param   bool $secure is this request secure?
	 * @return  ($secure is null ? bool : static)
	 */
	public function secure(?bool $secure = null): bool|static
	{
		if ($secure === null)
			return $this->_secure;

		// Act as a setter
		$this->_secure = $secure;
		return $this;
	}

	/**
	 * Gets or sets HTTP headers oo the request. All headers
	 * are included immediately after the HTTP protocol definition during
	 * transmission. This method provides a simple array or key/value
	 * interface to the headers.
	 *
	 * @param   array<string, mixed>|HTTP_Header|string|null   $key   Key or array of key/value pairs to set
	 * @param   string  $value Value to set to the supplied key
	 * @return  ($key is null ? HTTP_Header : ($key is array|HTTP_Header ? static : ($value is null ? mixed : static)))
	 */
	public function headers(HTTP_Header|array|string|null $key = null, ?string $value = null): mixed
	{
		if ($key instanceof HTTP_Header)
		{
			// Act a setter, replace all headers
			$this->_header = $key;

			return $this;
		}

		if (is_array($key))
		{
			// Act as a setter, replace all headers
			$this->_header->exchangeArray($key);

			return $this;
		}

		if ($this->_header->count() === 0 AND $this->is_initial())
		{
			// Lazy load the request headers
			$this->_header = HTTP::request_headers();
		}

		if ($key === null)
		{
			// Act as a getter, return all headers
			return $this->_header;
		}
		elseif ($value === null)
		{
			// Act as a getter, single header
			return ($this->_header->offsetExists($key)) ? $this->_header->offsetGet($key) : null;
		}

		// Act as a setter for a single header
		$this->_header[$key] = $value;

		return $this;
	}

	/**
	 * Set and get cookies values for this request.
	 *
	 * @param   array<string, mixed>|string|null    $key    Cookie name, or array of cookie values
	 * @param   string   $value  Value to set to cookie
	 * @return  ($key is null ? array<string, mixed> : ($key is array ? static : ($value is null ? mixed : static)))  the value of a cookie, all cookies, or this object after setting
	 */
	public function cookie(array|string|null $key = null, ?string $value = null): mixed
	{
		if (is_array($key))
		{
			// Act as a setter, replace all cookies
			$this->_cookies = $key;
			return $this;
		}
		elseif ($key === null)
		{
			// Act as a getter, all cookies
			return $this->_cookies;
		}
		elseif ($value === null)
		{
			// Act as a getting, single cookie
			return $this->_cookies[$key] ?? null;
		}

		// Act as a setter for a single cookie
		$this->_cookies[$key] = $value;

		return $this;
	}

	/**
	 * Gets or sets the HTTP body of the request. The body is
	 * included after the header, separated by a single empty new line.
	 *
	 * @param   string  $content Content to set to the object
	 * @return  ($content is null ? mixed : static)
	 */
	public function body(mixed $content = null): mixed
	{
		if ($content === null)
		{
			// Act as a getter
			return $this->_body;
		}

		// Act as a setter
		$this->_body = $content;

		return $this;
	}

	/**
	 * Reads the body of the request as JSON.
	 *
	 *     // The whole decoded body, as an array
	 *     $data = $request->json();
	 *
	 *     // One value, with a default; nested values are reached with a path
	 *     $name = $request->json('user.name', 'unknown');
	 *
	 * An empty body gives an empty array. A body that is not valid JSON is
	 * the client's mistake, so it is answered with a "400 Bad Request".
	 *
	 * @param   string|null  $key      path of the value to return; null for everything
	 * @param   mixed        $default  value returned when the path does not exist
	 * @return  mixed
	 * @throws  HTTP_Exception_400  if the body is not valid JSON
	 * @uses    Arr::path
	 */
	public function json(?string $key = null, mixed $default = null): mixed
	{
		$body = $this->body();

		if ( ! is_string($body) OR trim($body) === '')
		{
			// Nothing was sent
			$data = [];
		}
		else
		{
			try
			{
				$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
			}
			catch (JsonException $e)
			{
				throw HTTP_Exception::factory(400, 'The request body is not valid JSON: :error', [
					':error' => $e->getMessage(),
				], $e)->request($this);
			}
		}

		if ($key === null)
			return $data;

		return Arr::path($data, $key, $default);
	}

	/**
	 * Returns the length of the body for use with
	 * content header
	 *
	 * @return  int
	 */
	public function content_length(): int
	{
		if ( ! is_string($this->body()) )
			return 0;

		return strlen($this->body());
	}

	/**
	 * Renders the HTTP_Interaction to a string, producing
	 *
	 *  - Protocol
	 *  - Headers
	 *  - Body
	 *
	 *  If there are variables set to the `Kohana_Request::$_post`
	 *  they will override any values set to body.
	 *
	 * @return  string
	 */
	public function render(): string
	{
		if ( ! $post = $this->post())
		{
			$body = $this->body();
		}
		else
		{
			$body = http_build_query($post, '', '&');
			$this->body($body)
				->headers('content-type', 'application/x-www-form-urlencoded; charset='.Kohana::$charset);
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
			$cookie_string = [];

			// Parse each
			foreach ($this->_cookies as $key => $value)
			{
				$cookie_string[] = $key.'='.(is_scalar($value) ? $value : '');
			}

			// Create the cookie string
			$this->_header['cookie'] = implode('; ', $cookie_string);
		}

		$output = $this->method().' '.$this->uri().' '.$this->protocol()."\r\n";
		$output .= (string) $this->_header;
		$output .= is_scalar($body) ? $body : '';

		return $output;
	}

	/**
	 * Gets or sets HTTP query string.
	 *
	 * @param   array<int|string, mixed>|string|null   $key    Key or key value pairs to set
	 * @param   string  $value  Value to set to a key
	 * @return  ($key is null ? array<int|string, mixed> : ($key is array ? static : ($value is null ? mixed : static)))
	 * @uses    Arr::path
	 */
	public function query(array|string|null $key = null, mixed $value = null): mixed
	{
		if (is_array($key))
		{
			// Act as a setter, replace all query strings
			$this->_get = $key;

			return $this;
		}

		if ($key === null)
		{
			// Act as a getter, all query strings
			return $this->_get;
		}
		elseif ($value === null)
		{
			// Act as a getter, single query string
			return Arr::path($this->_get, $key);
		}

		// Act as a setter, single query string
		$this->_get[$key] = $value;

		return $this;
	}

	/**
	 * Gets or sets HTTP POST parameters to the request.
	 *
	 * @param   array<int|string, mixed>|string|null  $key    Key or key value pairs to set
	 * @param   string $value  Value to set to a key
	 * @return  ($key is null ? array<int|string, mixed> : ($key is array ? static : ($value is null ? mixed : static)))
	 * @uses    Arr::path
	 */
	public function post(array|string|null $key = null, mixed $value = null): mixed
	{
		if (is_array($key))
		{
			// Act as a setter, replace all fields
			$this->_post = $key;

			return $this;
		}

		if ($key === null)
		{
			// Act as a getter, all fields
			return $this->_post;
		}
		elseif ($value === null)
		{
			// Act as a getter, single field
			return Arr::path($this->_post, $key);
		}

		// Act as a setter, single field
		$this->_post[$key] = $value;

		return $this;
	}

}
