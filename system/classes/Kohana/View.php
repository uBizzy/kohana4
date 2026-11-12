<?php
/**
 * Acts as an object wrapper for HTML pages with embedded PHP, called "views".
 * Variables can be assigned with the view object and referenced locally within
 * the view.
 *
 * @package    Kohana
 * @category   Base
 *
 * @copyright  (c) 2007-2020  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_View {

	/**
	 * @var array<string, mixed> Global variables
	 */
	protected static array $_global_data = [];

	/**
	 * Returns a new View object. If you do not define the "file" parameter,
	 * you must call [View::set_filename].
	 *
	 * @param   ?string  $file   view filename
	 * @param   iterable<string, mixed>     $data   array of values
	 * @return  View
	 */
	public static function factory(?string $file = null, iterable $data = []): View
	{
		return new View($file, $data);
	}

	/**
	 * Captures the output that is generated when a view is included.
	 * The view data will be extracted to make local variables. This method
	 * is static to prevent object scope resolution.
	 *
	 * @param   string  $kohana_view_filename   filename
	 * @param   array<string, mixed>   $kohana_view_data       variables
	 * @return  string
	 * @throws  Throwable
	 */
	protected static function capture(string $kohana_view_filename, array $kohana_view_data): string
	{
		// Import the view variables to local namespace
		extract($kohana_view_data, EXTR_SKIP);

		if (View::$_global_data)
		{
			// Import the global view variables to local namespace
			extract(View::$_global_data, EXTR_SKIP | EXTR_REFS);
		}

		// Capture the view output
		ob_start();

		try
		{
			// Load the view within the current scope
			include $kohana_view_filename;
		}
		catch (Throwable $e)
		{
			// Delete the output buffer
			ob_end_clean();
			// Pass the error on as it is, so that it still says in which
			// file and on which line of the template it happened
			throw $e;
		}

		// Get the captured output and close the buffer
		return (string) ob_get_clean();
	}

	/**
	 * Sets a global variable, similar to [View::set], except that the
	 * variable will be accessible to all views.
	 *
	 *     View::set_global($name, $value);
	 *
	 * You can also use an array or Traversable object to set several values at once:
	 *
	 *     // Create the values $food and $beverage in the view
	 *     View::set_global(['food' => 'bread', 'beverage' => 'water']);
	 *
	 * [!!] Note: When setting with using Traversable object we're not attaching the whole object to the view,
	 * i.e. the object's standard properties will not be available in the view context.
	 *
	 * @param   string|iterable<string, mixed>  $key    variable name or an array of variables
	 * @param   mixed            $value  value
	 * @return  void
	 */
	public static function set_global(string|iterable $key, mixed $value = null): void
	{
		if (is_iterable($key))
		{
			foreach ($key as $name => $value)
			{
				View::$_global_data[$name] = $value;
			}
		}
		else
		{
			View::$_global_data[$key] = $value;
		}
	}

	/**
	 * Assigns a global variable by reference, similar to [View::bind], except
	 * that the variable will be accessible to all views.
	 * 
	 * @param   string  $key    variable name
	 * @param   mixed   $value  referenced variable
	 * @return  void
	 */
	public static function bind_global(string $key, mixed &$value): void
	{
		View::$_global_data[$key] =& $value;
	}

	/**
	 * @var string Absolute path to view
	 */
	protected ?string $_file = null;

	/**
	 * @var string Relative path to view
	 */
	protected ?string $_source_file = null;

	/**
	 * @var array<string, mixed> Local variables
	 */
	protected array $_data = [];

	/**
	 * Sets the initial view filename and local data. Views should almost
	 * always only be created using [View::factory].
	 * 
	 * @param   ?string  $file   view filename
	 * @param   iterable<string, mixed>     $data   array of values
	 */
	public function __construct(?string $file = null, iterable $data = [])
	{
		if ($file !== null)
		{
			$this->set_filename($file);
		}
		if ($data)
		{
			$this->set($data);
		}
	}

	/**
	 * Magic method, searches for the given variable and returns its value.
	 * Local variables will be returned before global variables.
	 * 
	 * [!!] If the variable has not yet been set, an exception will be thrown.
	 *
	 * @param   string  $key  Variable name
	 * @return  mixed
	 * @throws  View_Exception
	 */
	public function &__get(string $key): mixed
	{
		if (array_key_exists($key, $this->_data))
		{
			return $this->_data[$key];
		}
		elseif (array_key_exists($key, View::$_global_data))
		{
			return View::$_global_data[$key];
		}
		throw new View_Exception(
			'Variable is not set: :var',
			[':var' => $key]
		);
	}

	/**
	 * Magic method, calls [View::set] with the same parameters.
	 * 
	 * @param   string  $key    variable name
	 * @param   mixed   $value  value
	 * @return  void
	 */
	public function __set(string $key, mixed $value): void
	{
		$this->set($key, $value);
	}

	/**
	 * Magic method, determines if a variable is set.
	 * 
	 * [!!] `null` variables are not considered to be set by [isset](https://php.net/isset).
	 * 
	 * @param   string  $key  variable name
	 * @return  bool
	 */
	public function __isset(string $key): bool
	{
		return isset($this->_data[$key]) OR isset(View::$_global_data[$key]);
	}

	/**
	 * Magic method, unsets a given variable.
	 * 
	 * @param   string  $key    variable name
	 * @return  void
	 */
	public function __unset(string $key): void
	{
		unset($this->_data[$key], View::$_global_data[$key]);
	}

	/**
	 * Magic method, returns the output of [View::render].
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		// An error while rendering is thrown like any other exception
		return $this->render();
	}

	/**
	 * Sets the view filename.
	 * 
	 * @param   string  $file   view filename
	 * @return  $this
	 * @throws  View_Exception
	 */
	public function set_filename(string $file): static
	{
		$path = Kohana::find_file('views', $file);
		if (! $path)
		{
			throw new View_Exception(
				'The requested view :file could not be found',
				[':file' => $file]
			);
		}

		// Store the file path locally
		$this->_file = $path;
		$this->_source_file = $file;

		return $this;
	}

	/**
	 * Gets the view filename.
	 * 
	 * @param bool $source If true, return source filename, else absolute path
	 * @return string|null
	 */
	public function get_filename(bool $source = false): ?string
	{
		return $source ? $this->_source_file : $this->_file;
	}

	/**
	 * Assigns a variable by name. Assigned values will be available as a
	 * variable within the view file.
	 * 
	 * You can also use an array or Traversable object to set several values at once:
	 *
	 *     // Create the values $food and $beverage in the view
	 *     $view->set(['food' => 'bread', 'beverage' => 'water']);
	 *
	 * [!!] Note: When setting with using Traversable object we're not attaching the whole object to the view,
	 * i.e. the object's standard properties will not be available in the view context.
	 *
	 * @param   string|iterable<string, mixed>  $key    variable name or an array of variables
	 * @param   mixed            $value  value
	 * @return  $this
	 */
	public function set(string|iterable $key, mixed $value = null): static
	{
		if (is_iterable($key))
		{
			foreach ($key as $name => $value)
			{
				$this->_data[$name] = $value;
			}
		}
		else
		{
			$this->_data[$key] = $value;
		}

		return $this;
	}

	/**
	 * Assigns a value by reference. The benefit of binding is that values can
	 * be altered without re-setting them. It is also possible to bind variables
	 * before they have values. Assigned values will be available as a
	 * variable within the view file.
	 * 
	 * @param   string  $key    variable name
	 * @param   mixed   $value  referenced variable
	 * @return  $this
	 */
	public function bind(string $key, mixed &$value): static
	{
		$this->_data[$key] =& $value;

		return $this;
	}

	/**
	 * Renders the view object to a string. Global and local data are merged
	 * and extracted to create local variables within the view file.
	 * 
	 * [!!] Global variables with the same key name as local variables will be
	 * overwritten by the local variable.
	 *
	 * @param   ?string  $file   view filename
	 * @return  string
	 * @throws  View_Exception
	 */
	public function render(?string $file = null): string
	{
		if ($file !== null)
		{
			$this->set_filename($file);
		}
		if (! $this->get_filename())
		{
			throw new View_Exception(
				'You must set the file to use within your view before rendering'
			);
		}
		if ( ! Kohana::$profiling)
		{
			// Combine local and global data and capture the output
			return View::capture($this->get_filename(), $this->_data);
		}

		// The same, measured: one benchmark for every view that is rendered
		$benchmark = Profiler::start('Views', Debug::path($this->get_filename()));

		// Only the names of the variables the view was given, never their values
		Profiler::note($benchmark, implode(', ', array_map(fn ($name) => '$'.$name, array_keys($this->_data))));

		try
		{
			return View::capture($this->get_filename(), $this->_data);
		}
		finally
		{
			Profiler::stop($benchmark);
		}
	}
}
