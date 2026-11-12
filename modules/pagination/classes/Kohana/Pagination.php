<?php

/**
 * Pagination links generator.
 *
 * @package    Koseven/Pagination
 * @category   Base
 * 
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Pagination {

	/**
	 * @var  array<string, mixed>  Merged configuration settings
	 */
	protected array $config = [
		'current_page' => ['source' => 'query_string', 'key' => 'page'],
		'total_items' => 0,
		'items_per_page' => 10,
		'view' => 'pagination/basic',
		'auto_hide' => true,
		'first_page_in_url' => false,
	];

	// Current page number
	protected ?int $current_page = null;

	// Total item count
	protected int $total_items = 0;

	// How many items to show per page
	protected int $items_per_page = 10;

	// Total page count
	protected int $total_pages = 0;

	// Item offset for the first item displayed on the current page
	protected int $current_first_item = 0;

	// Item offset for the last item displayed on the current page
	protected int $current_last_item = 0;

	// Previous page number; false if the current page is the first one
	protected int|false $previous_page = false;

	// Next page number; false if the current page is the last one
	protected int|false $next_page = false;

	// First page number; false if the current page is the first one
	protected int|false $first_page = false;

	// Last page number; false if the current page is the last one
	protected int|false $last_page = false;

	// Query offset
	protected int $offset = 0;

	// Request object
	protected Request $_request;

	// Route to use for URIs
	protected ?Route $_route = null;

	/**
	 * @var  array<string, mixed>  Parameters to use with Route to create URIs
	 */
	protected array $_route_params = [];

	/**
	 * Creates a new Pagination object.
	 *
	 * @param   array<string, mixed>  $config  configuration
	 * @return  Pagination
	 */
	public static function factory(array $config = [], ?Request $request = null): Pagination
	{
		return new Pagination($config, $request);
	}

	/**
	 * Creates a new Pagination object.
	 *
	 * @param   array<string, mixed>  $config  configuration
	 * @return  void
	 */
	public function __construct(array $config = [], ?Request $request = null)
	{
		// Overwrite system defaults with application defaults
		$this->config = $this->config_group() + $this->config;

		// Assing Request
		if ($request === null)
		{
			$request = Request::current();
		}

		if ($request === null)
			throw new Kohana_Exception('Pagination needs a request: there is none to take the page number from');

		$this->_request = $request;

		// Assign default Route
		$this->_route = $request->route();

		// Assign default route params
		$this->_route_params = $request->param();

		// Add controller and action to route params for routes with variable controllers and actions
		$this->_route_params['controller'] = $request->controller();
		$this->_route_params['action'] = $request->action();
		$this->_route_params['directory'] = $request->directory();

		// Pagination setup
		$this->setup($config);
	}

	/**
	 * Retrieves a pagination config group from the config file. One config group can
	 * refer to another as its parent, which will be recursively loaded.
	 *
	 * @param   string  $group  pagination config group; "default" if none given
	 * @return  array<string, mixed>   config settings
	 */
	public function config_group(string $group = 'default'): array
	{
		// Load the pagination config file
		$config_file = Kohana::$config->load('pagination');

		// Initialize the $config array
		$config['group'] = (string) $group;

		// Recursively load requested config groups
		while (isset($config['group']) AND isset($config_file->{$config['group']}))
		{
			// Temporarily store config group name
			$group = $config['group'];
			unset($config['group']);

			// Add config group values, not overwriting existing keys
			$config += (array) $config_file->$group;
		}

		// Get rid of possible stray config group names
		unset($config['group']);

		// Return the merged config group settings
		/** @var array<string, mixed> $config */
		return $config;
	}

	/**
	 * Loads configuration settings into the object and (re)calculates pagination if needed.
	 * Allows you to update config settings after a Pagination object has been constructed.
	 *
	 * @param   array<string, mixed>  $config  configuration
	 * @return  static  Pagination
	 */
	public function setup(array $config = []): static
	{
		if (is_string($config['group'] ?? null))
		{
			// Recursively load requested config groups
			$config += $this->config_group($config['group']);
		}

		// Overwrite the current config settings
		$this->config = $config + $this->config;

		// Only (re)calculate pagination when needed
		if ($this->current_page === null
			OR isset($config['current_page'])
			OR isset($config['total_items'])
			OR isset($config['items_per_page']))
		{
			/** @var array{current_page: array{source: string, key: string, page?: int|string}, total_items: int|string, items_per_page: int|string} $settings  the merged settings */
			$settings = $this->config;

			// Retrieve the current page number
			if ( ! empty($settings['current_page']['page']))
			{
				// The current page number has been set manually
				$this->current_page = (int) $settings['current_page']['page'];
			}
			else
			{
				$query_key = $settings['current_page']['key'];

				// The number in the address is visitor input: anything but a number is page 1
				$page = ($settings['current_page']['source'] === 'route')
					? $this->_request->param($query_key, 1)
					: $this->_request->query($query_key);

				$this->current_page = is_numeric($page) ? (int) $page : 1;
			}

			// Calculate and clean all pagination variables
			$this->total_items = max(0, (int) $settings['total_items']);
			$this->items_per_page = max(1, (int) $settings['items_per_page']);
			$this->total_pages = (int) ceil($this->total_items / $this->items_per_page);
			$this->current_page = (int) min(max(1, $this->current_page), max(1, $this->total_pages));
			$this->current_first_item = (int) min((($this->current_page - 1) * $this->items_per_page) + 1, $this->total_items);
			$this->current_last_item = (int) min($this->current_first_item + $this->items_per_page - 1, $this->total_items);
			$this->previous_page = ($this->current_page > 1) ? $this->current_page - 1 : false;
			$this->next_page = ($this->current_page < $this->total_pages) ? $this->current_page + 1 : false;
			$this->first_page = ($this->current_page === 1) ? false : 1;
			$this->last_page = ($this->current_page >= $this->total_pages) ? false : $this->total_pages;
			$this->offset = (int) (($this->current_page - 1) * $this->items_per_page);
		}

		// Chainable method
		return $this;
	}

	/**
	 * Generates the full URL for a certain page.
	 *
	 * @param   int  $page  page number
	 * @return  string   page URL
	 */
	public function url(int $page = 1): string
	{
		// Clean the page number
		$page = max(1, (int) $page);

		// No page number in URLs to first page
		if ($page === 1 AND ! $this->config['first_page_in_url'])
		{
			$page = null;
		}

		if ($this->_route === null)
			throw new Kohana_Exception('Pagination has no route to make its links with');

		/** @var array{source: string, key: string} $current  where the page number is put */
		$current = $this->config['current_page'];

		switch ($current['source'])
		{
			case 'query_string':
				return URL::site($this->_route->uri($this->_route_params).
					$this->query([$current['key'] => $page]));

			case 'route':
				return URL::site($this->_route->uri(array_merge($this->_route_params,
					[$current['key'] => $page])).$this->query());
		}

		return '#';
	}

	/**
	 * Checks whether the given page number exists.
	 *
	 * @param   mixed  $page  page number
	 * @return  bool
	 * @since   3.0.7
	 */
	public function valid_page(mixed $page): bool
	{
		// Page number has to be a clean integer
		if ( ! Valid::digit($page))
			return false;

		return $page > 0 AND $page <= $this->total_pages;
	}

	/**
	 * Renders the pagination links.
	 *
	 * @param   View|string|null  $view  string of the view to use, or a Kohana_View object
	 * @return  string  pagination output (HTML)
	 */
	public function render(View|string|null $view = null): string
	{
		// Automatically hide pagination whenever it is superfluous
		if ($this->config['auto_hide'] === true AND $this->total_pages <= 1)
			return '';

		if ($view === null)
		{
			// Use the view from config
			$view = $this->config['view'];
		}

		if ( ! $view instanceof View)
		{
			// Load the view file
			$view = View::factory(is_string($view) ? $view : null);
		}

		// Pass on the whole Pagination object
		/** @var array<string, mixed> $vars */
		$vars = get_object_vars($this);

		return $view->set($vars)->set('page', $this)->render();
	}


	/**
	 * Request setter / getter
	 *
	 * @param	?Request  $request
	 * @return	($request is null ? Request : static)	If used as getter
	 */
	public function request(?Request $request = null): Request|static|null
	{
		if ($request === null)
			return $this->_request;

		$this->_request = $request;

		return $this;
	}

	/**
	 * Route setter / getter
	 *
	 * @param	?Route  $route
	 * @return	($route is null ? Route|null : static)	Route if used as getter
	 */
	public function route(?Route $route = null): Route|static|null
	{
		if ($route === null)
			return $this->_route;

		$this->_route = $route;

		return $this;
	}

	/**
	 * Route parameters setter / getter
	 *
	 * @param	array<string, mixed>|null  $route_params  Route parameters to set
	 * @return	($route_params is null ? array<string, mixed> : static)
	 */
	public function route_params(?array $route_params = null): array|static
	{
		if ($route_params === null)
			return $this->_route_params;

		$this->_route_params = $route_params;

		return $this;
	}

	/**
	 * URL::query() replacement for Pagination use only
	 *
	 * @param	array<string, mixed>|null  $params  Parameters to override
	 * @return	string
	 */
	public function query(?array $params = null): string
	{
		if ($params === null)
		{
			// Use only the current parameters
			$params = $this->_request->query();
		}
		else
		{
			// Merge the current and new parameters
			$params = array_merge($this->_request->query(), $params);
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
	 * Renders the pagination links.
	 *
	 * @return  string  pagination output (HTML)
	 */
	public function __toString(): string
	{
		// An error while rendering is thrown like any other exception
		return $this->render();
	}

	/**
	 * Returns a Pagination property.
	 *
	 * @param   string  $key  property name
	 * @return  mixed   Pagination property; null if not found
	 */
	public function __get(string $key): mixed
	{
		return $this->$key ?? null;
	}

	/**
	 * Updates a single config setting, and recalculates pagination if needed.
	 *
	 * @param   string  $key  config key
	 * @param   mixed  $value  config value
	 * @return  void
	 */
	public function __set(string $key, mixed $value): void
	{
		$this->setup([$key => $value]);
	}

}
