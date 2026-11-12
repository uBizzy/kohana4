<?php
/**
 * Routes are used to determine the controller and action for a requested URI.
 * Every route generates a regular expression which is used to match a URI
 * and a route. Routes may also contain keys which can be used to set the
 * controller, action, and parameters.
 *
 * Each <key> will be translated to a regular expression using a default
 * regular expression pattern. You can override the default pattern by providing
 * a pattern for the key:
 *
 *     // This route will only match when <id> is a digit
 *     Route::set('user', 'user/<action>/<id>', array('id' => '\d+'));
 *
 *     // This route will match when <path> is anything
 *     Route::set('file', '<path>', array('path' => '.*'));
 *
 * It is also possible to create optional segments by using parentheses in
 * the URI definition:
 *
 *     // This is the standard default route, and no keys are required
 *     Route::set('default', '(<controller>(/<action>(/<id>)))');
 *
 *     // This route only requires the <file> key
 *     Route::set('file', '(<path>/)<file>(.<format>)', array('path' => '.*', 'format' => '\w+'));
 *
 * Routes also provide a way to generate URIs (called "reverse routing"), which
 * makes them an extremely powerful and flexible way to generate internal links.
 *
 * @package    Kohana
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Route {

	// Matches a URI group and captures the contents
	const string REGEX_GROUP   = '\(((?:(?>[^()]+)|(?R))*)\)';

	// Defines the pattern of a <segment>
	const string REGEX_KEY     = '<([a-zA-Z0-9_]++)>';

	// What can be part of a <segment> value
	const string REGEX_SEGMENT = '[^/.,;?\n]++';

	// What must be escaped in the route regex
	const string REGEX_ESCAPE  = '[.\\+*?[^\\]${}=!|]';

	/**
	 * @var  string  default protocol for all routes
	 *
	 * @example  'http://'
	 */
	public static string $default_protocol = 'http://';

	/**
	 * @var  array<int, string|false>   list of valid localhost entries
	 */
	public static array $localhosts = [false, '', 'local', 'localhost'];

	/**
	 * @var  string  default action for all routes
	 */
	public static string $default_action = 'index';

	/**
	 * @var  bool Indicates whether routes are cached
	 */
	public static bool $cache = false;

	/**
	 * @var  array<string, Route>
	 */
	protected static array $_routes = [];

	/**
	 * Stores a named route and returns it. The "action" will always be set to
	 * "index" if it is not defined.
	 *
	 *     Route::set('default', '(<controller>(/<action>(/<id>)))')
	 *         ->defaults(array(
	 *             'controller' => 'welcome',
	 *         ));
	 *
	 * @param   string  $name           route name
	 * @param   string  $uri            URI pattern
	 * @param   array<string, string>   $regex          regex patterns for route keys
	 * @return  Route
	 */
	public static function set(string $name, ?string $uri = null, ?array $regex = null): Route
	{
		return Route::$_routes[$name] = new Route($uri, $regex);
	}

	/**
	 * Retrieves a named route.
	 *
	 *     $route = Route::get('default');
	 *
	 * @param   string  $name   route name
	 * @return  Route
	 * @throws  Kohana_Exception
	 */
	public static function get(string $name): Route
	{
		if ( ! isset(Route::$_routes[$name]))
		{
			throw new Kohana_Exception('The requested route does not exist: :route',
				[':route' => $name]);
		}

		return Route::$_routes[$name];
	}

	/**
	 * Retrieves all named routes.
	 *
	 *     $routes = Route::all();
	 *
	 * @return  array<string, Route>  routes by name
	 */
	public static function all(): array
	{
		return Route::$_routes;
	}

	/**
	 * Get the name of a route.
	 *
	 *     $name = Route::name($route)
	 *
	 * @param   Route   $route  instance
	 * @return  string
	 */
	public static function name(Route $route): string|false
	{
		return array_search($route, Route::$_routes);
	}

	/**
	 * Saves or loads the route cache. If your routes will remain the same for
	 * a long period of time, use this to reload the routes from the cache
	 * rather than redefining them on every page load.
	 *
	 *     if ( ! Route::cache())
	 *     {
	 *         // Set routes here
	 *         Route::cache(true);
	 *     }
	 *
	 * @param   bool $save   cache the current routes
	 * @param   bool $append append, rather than replace, cached routes when loading
	 * @return  bool|null  true or false when loading routes, null when saving
	 * @uses    Kohana::cache
	 */
	public static function cache(?bool $save = false, bool $append = false): ?bool
	{
		if ($save === true)
		{
			try
			{
				// Cache all defined routes
				Kohana::cache('Route::cache()', Route::$_routes);
			}
			catch (Throwable $e)
			{
				// We most likely have a lambda in a route, which cannot be cached
				throw new Kohana_Exception('One or more routes could not be cached (:message)', [
						':message' => $e->getMessage(),
					], 0, $e);
			}
		}
		else
		{
			if (($routes = Kohana::cache('Route::cache()')) AND is_array($routes))
			{
				/** @var array<string, Route> $routes */
				if ($append)
				{
					// Append cached routes
					Route::$_routes += $routes;
				}
				else
				{
					// Replace existing routes
					Route::$_routes = $routes;
				}

				// Routes were cached
				return Route::$cache = true;
			}
			else
			{
				// Routes were not cached
				return Route::$cache = false;
			}
		}

		// Saving the routes has no result
		return null;
	}

	/**
	 * Create a URL from a route name. This is a shortcut for:
	 *
	 *     echo URL::site(Route::get($name)->uri($params), $protocol);
	 *
	 * @param   string  $name       route name
	 * @param   array<string, mixed>   $params     URI parameters
	 * @param   string|bool|Request|null   $protocol   protocol string or boolean, adds protocol and domain
	 * @return  string
	 * @since   3.0.7
	 * @uses    URL::site
	 */
	public static function url(string $name, ?array $params = null, mixed $protocol = null): string
	{
		$route = Route::get($name);

		// Create a URI with the route and convert it to a URL
		if ($route->is_external())
			return $route->uri($params);
		else
			return URL::site($route->uri($params), $protocol);
	}

	/**
	 * Returns the compiled regular expression for the route. This translates
	 * keys and optional groups to a proper PCRE regular expression.
	 *
	 *     $compiled = Route::compile(
	 *        '<controller>(/<action>(/<id>))',
	 *         array(
	 *           'controller' => '[a-z]+',
	 *           'id' => '\d+',
	 *         )
	 *     );
	 *
	 * @param   array<string, string>  $regex
	 * @return  string
	 * @uses    Route::REGEX_ESCAPE
	 * @uses    Route::REGEX_SEGMENT
	 */
	public static function compile(string $uri, ?array $regex = null): string
	{
		// The URI should be considered literal except for keys and optional parts
		// Escape everything preg_quote would escape except for : ( ) < >
		$expression = (string) preg_replace('#'.Route::REGEX_ESCAPE.'#', '\\\\$0', $uri);

		if (str_contains($expression, '('))
		{
			// Make optional parts of the URI non-capturing and optional
			$expression = str_replace(['(', ')'], ['(?:', ')?'], $expression);
		}

		// Insert default regex for keys
		$expression = str_replace(['<', '>'], ['(?P<', '>'.Route::REGEX_SEGMENT.')'], $expression);

		if ($regex)
		{
			$search = $replace = [];
			foreach ($regex as $key => $value)
			{
				$search[]  = "<$key>".Route::REGEX_SEGMENT;
				$replace[] = "<$key>$value";
			}

			// Replace the default regex with the user-specified regex
			$expression = str_replace($search, $replace, $expression);
		}

		return '#^'.$expression.'$#uD';
	}

	/**
	 * @var  array<int, callable>  route filters
	 */
	protected array $_filters = [];

	/**
	 * @var  string  route URI
	 */
	protected string $_uri = '';

	/**
	 * @var  array<string, string>
	 */
	protected array $_regex = [];

	/**
	 * @var  array<string, mixed>
	 */
	protected array $_defaults = ['action' => 'index', 'host' => false];

	/**
	 * @var  string
	 */
	protected ?string $_route_regex = null;

	/**
	 * Creates a new route. Sets the URI and regular expressions for keys.
	 * Routes should always be created with [Route::set] or they will not
	 * be properly stored.
	 *
	 *     $route = new Route($uri, $regex);
	 *
	 * The $uri parameter should be a string for basic regex matching.
	 *
	 *
	 * @param   string  $uri    route URI pattern
	 * @param   array<string, string>   $regex  key patterns
	 * @return  void
	 * @uses    Route::_compile
	 */
	public function __construct(?string $uri = null, ?array $regex = null)
	{
		if ($uri === null)
		{
			// Assume the route is from cache
			return;
		}

		if ( ! empty($uri))
		{
			$this->_uri = $uri;
		}

		if ( ! empty($regex))
		{
			$this->_regex = $regex;
		}

		// Store the compiled regex locally
		$this->_route_regex = Route::compile($uri, $regex);
	}

	/**
	 * Provides default values for keys when they are not present. The default
	 * action will always be "index" unless it is overloaded here.
	 *
	 *     $route->defaults(array(
	 *         'controller' => 'welcome',
	 *         'action'     => 'index'
	 *     ));
	 *
	 * If no parameter is passed, this method will act as a getter.
	 *
	 * @param   array<string, mixed>   $defaults   key values
	 * @return  ($defaults is null ? array<string, mixed> : static)
	 */
	public function defaults(?array $defaults = null): static|array
	{
		if ($defaults === null)
		{
			return $this->_defaults;
		}

		$this->_defaults = $defaults;

		return $this;
	}

	/**
	 * Filters to be run before route parameters are returned:
	 *
	 *     $route->filter(
	 *         function(Route $route, $params, Request $request)
	 *         {
	 *             if ($request->method() !== HTTP_Request::POST)
	 *             {
	 *                 return false; // This route only matches POST requests
	 *             }
	 *             if ($params AND $params['controller'] === 'welcome')
	 *             {
	 *                 $params['controller'] = 'home';
	 *             }
	 *
	 *             return $params;
	 *         }
	 *     );
	 *
	 * To prevent a route from matching, return `false`. To replace the route
	 * parameters, return an array.
	 *
	 * [!!] Default parameters are added before filters are called!
	 *
	 * @throws  Kohana_Exception
	 * @param   callable   $callback   callback string, array, or closure
	 * @return  $this
	 */
	public function filter(callable $callback): static
	{
		$this->_filters[] = $callback;

		return $this;
	}

	/**
	 * Tests if the route matches a given Request. A successful match will return
	 * all of the routed parameters as an array. A failed match will return
	 * boolean false.
	 *
	 *     // Params: controller = users, action = edit, id = 10
	 *     $params = $route->matches(Request::factory('users/edit/10'));
	 *
	 * This method should almost always be used within an if/else block:
	 *
	 *     if ($params = $route->matches($request))
	 *     {
	 *         // Parse the parameters
	 *     }
	 *
	 * @param   Request $request  Request object to match
	 * @return  array<string, mixed>             on success
	 */
	public function matches(Request $request): array|false
	{
		// Get the URI from the Request
		$uri = trim($request->uri(), '/');

		if ( ! preg_match( (string) $this->_route_regex, $uri, $matches))
			return false;

		/** @var array<string, mixed> $params */
		$params = [];
		foreach ($matches as $key => $value)
		{
			if (is_int($key))
			{
				// Skip all unnamed keys
				continue;
			}

			// Set the value for all matched keys
			$params[$key] = $value;
		}

		foreach ($this->_defaults as $key => $value)
		{
			if ( ! isset($params[$key]) OR $params[$key] === '')
			{
				// Set default values for any key that was not matched
				$params[$key] = $value;
			}
		}

		if ( ! empty($params['controller']) AND is_string($params['controller']))
		{
			// PSR-0: Replace underscores with spaces, run ucwords, then replace underscore
			$params['controller'] = str_replace(' ', '_', ucwords(str_replace('_', ' ', $params['controller'])));
		}

		if ( ! empty($params['directory']) AND is_string($params['directory']))
		{
			// PSR-0: Replace underscores with spaces, run ucwords, then replace underscore
			$params['directory'] = str_replace(' ', '_', ucwords(str_replace('_', ' ', $params['directory'])));
		}

		if ($this->_filters)
		{
			foreach ($this->_filters as $callback)
			{
				// Execute the filter giving it the route, params, and request
				$return = call_user_func($callback, $this, $params, $request);

				if ($return === false)
				{
					// Filter has aborted the match
					return false;
				}
				elseif (is_array($return))
				{
					// Filter has modified the parameters
					$params = $return;
				}
			}
		}

		/** @var array<string, mixed> $params */
		return $params;
	}

	/**
	 * Returns whether this route is an external route
	 * to a remote controller.
	 *
	 * @return  bool
	 */
	public function is_external(): bool
	{
		return ! in_array(Arr::get($this->_defaults, 'host', false), Route::$localhosts);
	}

	/**
	 * Generates a URI for the current route based on the parameters given.
	 *
	 *     // Using the "default" route: "users/profile/10"
	 *     $route->uri(array(
	 *         'controller' => 'users',
	 *         'action'     => 'profile',
	 *         'id'         => '10'
	 *     ));
	 *
	 * @param   array<string, mixed>   $params URI parameters
	 * @return  string
	 * @throws  Kohana_Exception
	 * @uses    Route::REGEX_GROUP
	 * @uses    Route::REGEX_KEY
	 */
	public function uri(?array $params = null): string
	{
		if ($params !== null)
		{
			// Parameters that are null are treated as "not given". Other
			// values, including 0 and '0', are kept and URL-encoded.
			$params = array_filter($params, fn ($value) => $value !== null);
			$params = array_map(fn ($value) => rawurlencode(is_scalar($value) ? (string) $value : ''), $params);

			// Decode slashes back, see Apache docs about AllowEncodedSlashes and AcceptPathInfo
			$params = str_replace(['%2F', '%5C'], ['/', '\\'], $params);
		}

		$defaults = $this->_defaults;

		/**
		 * Recursively compiles a portion of a URI specification by replacing
		 * the specified parameters and any optional parameters that are needed.
		 *
		 * @param   string  $portion    Part of the URI specification
		 * @param   bool $required   Whether or not parameters are required (initially)
		 * @return  array   Tuple of the compiled portion and whether or not it contained specified parameters
		 */
		$compile = function (string $portion, bool $required) use (&$compile, $defaults, $params): array
		{
			$missing = [];

			$pattern = '#(?:'.Route::REGEX_KEY.'|'.Route::REGEX_GROUP.')#';
			$result = preg_replace_callback($pattern, function (array $matches) use (&$compile, $defaults, &$missing, $params, &$required): string
			{
				if ($matches[0][0] === '<')
				{
					// Parameter, unwrapped
					$param = $matches[1];

					if (isset($params[$param]))
					{
						// This portion is required when a specified
						// parameter does not match the default
						$required = ($required OR ! isset($defaults[$param]) OR $params[$param] !== $defaults[$param]);

						// Add specified parameter to this result
						return $params[$param];
					}

					// Add default parameter to this result
					if (isset($defaults[$param]))
						return is_scalar($defaults[$param]) ? (string) $defaults[$param] : '';

					// This portion is missing a parameter
					$missing[] = $param;
				}
				else
				{
					// Group, unwrapped
					/** @var array{0: string, 1: bool} $result */
					$result = $compile($matches[2], false);

					if ($result[1])
					{
						// This portion is required when it contains a group
						// that is required
						$required = true;

						// Add required groups to this result
						return $result[0];
					}

					// Do not add optional groups to this result
				}

				return '';
			}, $portion);

			if ($required AND $missing)
			{
				throw new Kohana_Exception(
					'Required route parameter not passed: :param',
					[':param' => reset($missing)]
				);
			}

			return [$result, $required];
		};

		/** @var array{0: string, 1: bool} $compiled */
		$compiled = $compile( (string) $this->_uri, true);

		$uri = $compiled[0];

		// Trim all extra slashes from the URI
		$uri = (string) preg_replace('#//+#', '/', rtrim($uri, '/'));

		if ($this->is_external())
		{
			// Need to add the host to the URI
			$host = is_string($this->_defaults['host'] ?? null) ? $this->_defaults['host'] : '';

			if ( ! str_contains($host, '://'))
			{
				// Use the default defined protocol
				$host = Route::$default_protocol.$host;
			}

			// Clean up the host and prepend it to the URI
			$uri = rtrim($host, '/').'/'.$uri;
		}

		return $uri;
	}

	/**
	 * Tests if a route with the given name was set.
	 *
	 *     if (Route::has('admin')) ...
	 *
	 * @param   string  $name  route name
	 * @return  bool
	 */
	public static function has(string $name): bool
	{
		return isset(Route::$_routes[$name]);
	}

}
