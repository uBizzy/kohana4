<?php
/**
 * Kohana Cache provides a common interface to a variety of caching engines. Tags are
 * supported where available natively to the cache system. Kohana Cache supports multiple
 * instances of cache engines through a grouped singleton pattern.
 *
 * ### Supported cache engines
 *
 * *  [APC](http://php.net/manual/en/book.apc.php)
 * *  [eAccelerator](http://eaccelerator.net/)
 * *  File
 * *  [Memcache](http://memcached.org/)
 * *  [Memcached-tags](http://code.google.com/p/memcached-tags/)
 * *  [SQLite](http://www.sqlite.org/)
 * *  [Xcache](http://xcache.lighttpd.net/)
 *
 * ### Introduction to caching
 *
 * Caching should be implemented with consideration. Generally, caching the result of resources
 * is faster than reprocessing them. Choosing what, how and when to cache is vital. PHP APC is
 * presently one of the fastest caching systems available, closely followed by Memcache. SQLite
 * and File caching are two of the slowest cache methods, however usually faster than reprocessing
 * a complex set of instructions.
 *
 * Caching engines that use memory are considerably faster than the file based alternatives. But
 * memory is limited whereas disk space is plentiful. If caching large datasets it is best to use
 * file caching.
 *
 * ### Configuration settings
 *
 * Kohana Cache uses configuration groups to create cache instances. A configuration group can
 * use any supported driver, with successive groups using the same driver type if required.
 *
 * #### Configuration example
 *
 * Below is an example of a _memcache_ server configuration.
 *
 *     return array(
 *          'memcache' => array(                           // Name of group
 *                  'driver'         => 'memcache',        // using Memcache driver
 *                  'servers'        => array(             // Available server definitions
 *                         array(
 *                              'host'       => 'localhost',
 *                              'port'       => 11211,
 *                              'persistent' => false
 *                         )
 *                  ),
 *                  'compression'    => false,             // Use compression?
 *           ),
 *     )
 *
 * In cases where only one cache group is required, set `Cache::$default` (in your bootstrap,
 * or by extending `Kohana_Cache` class) to the name of the group, and use:
 *
 *     $cache = Cache::instance(); // instead of Cache::instance('memcache')
 *
 * It will return the cache instance of the group it has been set in `Cache::$default`.
 *
 * #### General cache group configuration settings
 *
 * Below are the settings available to all types of cache driver.
 *
 * Name           | Required | Description
 * -------------- | -------- | ---------------------------------------------------------------
 * driver         | __YES__  | (_string_) The driver type to use
 *
 * Details of the settings specific to each driver are available within the drivers documentation.
 *
 * ### System requirements
 *
 * *  Kohana 3.0.x
 * *  PHP 5.2.4 or greater
 *
 * @package    Kohana/Cache
 * @category   Base
 * @version    2.0
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Cache {

	const int DEFAULT_EXPIRE = 3600;

	/**
	 * @var   string     default driver to use
	 */
	public static string $default = 'file';

	/**
	 * @var   array<string, Cache> instances
	 */
	public static array $instances = [];

	/**
	 * Creates a singleton of a Kohana Cache group. If no group is supplied
	 * the __default__ cache group is used.
	 *
	 *     // Create an instance of the default group
	 *     $default_group = Cache::instance();
	 *
	 *     // Create an instance of a group
	 *     $foo_group = Cache::instance('foo');
	 *
	 *     // Access an instantiated group directly
	 *     $foo_group = Cache::$instances['default'];
	 *
	 * @param   string  $group  the name of the cache group to use [Optional]
	 * @return  Cache
	 * @throws  Cache_Exception
	 */
	public static function instance(?string $group = null): Cache
	{
		// If there is no group supplied, try to get it from the config
		if ($group === null)
		{
			$group = Kohana::$config->load('cache.default');
		}

		// If there is no group supplied
		if ( ! is_string($group))
		{
			// Use the default setting
			$group = Cache::$default;
		}

		if (isset(Cache::$instances[$group]))
		{
			// Return the current group if initiated already
			return Cache::$instances[$group];
		}

		/** @var Config_Group $config */
		$config = Kohana::$config->load('cache');

		if ( ! $config->offsetExists($group))
		{
			throw new Cache_Exception(
				'Failed to load Kohana Cache group: :group',
				[':group' => $group]
			);
		}

		/** @var array{driver: string} $config  the settings of the group */
		$config = $config->get($group);

		/** @var class-string<Cache> $cache_class  the driver class, e.g. Cache_File */
		$cache_class = 'Cache_'.ucfirst($config['driver']);
		Cache::$instances[$group] = new $cache_class($config);

		// Return the instance
		return Cache::$instances[$group];
	}

	/**
	 * @var  array<string, mixed>
	 */
	protected array $_config = [];

	/**
	 * Ensures singleton pattern is observed, loads the default expiry
	 *
	 * @param  array<string, mixed>  $config  configuration
	 */
	protected function __construct(array $config)
	{
		$this->config($config);
	}

	/**
	 * Getter and setter for the configuration. If no argument provided, the
	 * current configuration is returned. Otherwise the configuration is set
	 * to this class.
	 *
	 *     // Overwrite all configuration
	 *     $cache->config(array('driver' => 'memcache', '...'));
	 *
	 *     // Set a new configuration setting
	 *     $cache->config('servers', array(
	 *          'foo' => 'bar',
	 *          '...'
	 *          ));
	 *
	 *     // Get a configuration setting
	 *     $servers = $cache->config('servers);
	 *
	 * @param   array<string, mixed>|string|null  $key  key to set to array, either array or config path
	 * @param   mixed  $value  value to associate with key
	 * @return  mixed
	 */
	public function config(array|string|null $key = null, mixed $value = null): mixed
	{
		if ($key === null)
			return $this->_config;

		if (is_array($key))
		{
			$this->_config = $key;
		}
		else
		{
			if ($value === null)
				return Arr::get($this->_config, $key);

			$this->_config[$key] = $value;
		}

		return $this;
	}

	/**
	 * Overload the __clone() method to prevent cloning
	 *
	 * @return  void
	 * @throws  Cache_Exception
	 */
	final public function __clone()
	{
		throw new Cache_Exception('Cloning of Kohana_Cache objects is forbidden');
	}

	/**
	 * @var  array<string, int>  How often a value was found in a cache ("hits") and how
	 *              often it was not ("misses"), in this request, for all
	 *              cache groups together
	 */
	public static array $stats = ['hits' => 0, 'misses' => 0];

	/**
	 * @var  array<int, array{string, string, bool}>  The reads of this request, kept while [Kohana::$profiling]
	 *              is on, for tools such as the debug bar: lists of driver
	 *              class, id and found (true or false). At most 500 are kept.
	 */
	public static array $reads = [];

	/**
	 * Counts a read and returns its result. Every driver passes what it
	 * returns from get() through this method.
	 *
	 *     return $this->_read($id, true, $data);       // found
	 *     return $this->_read($id, false, $default);   // not found
	 *
	 * @param   string  $id     id that was asked for
	 * @param   bool    $found  was the value in the cache?
	 * @param   mixed   $value  the value, or the default
	 * @return  mixed   $value
	 */
	protected function _read(string $id, bool $found, mixed $value): mixed
	{
		Cache::$stats[$found ? 'hits' : 'misses']++;

		if (Kohana::$profiling AND count(Cache::$reads) < 500)
		{
			Cache::$reads[] = [get_class($this), $id, $found];
		}

		return $value;
	}

	/**
	 * Retrieve a cached value entry by id.
	 *
	 *     // Retrieve cache entry from default group
	 *     $data = Cache::instance()->get('foo');
	 *
	 *     // Retrieve cache entry from default group and return 'bar' if miss
	 *     $data = Cache::instance()->get('foo', 'bar');
	 *
	 *     // Retrieve cache entry from memcache group
	 *     $data = Cache::instance('memcache')->get('foo');
	 *
	 * @param   string  $id       id of cache to entry
	 * @param   string  $default  default value to return if cache miss
	 * @return  mixed
	 * @throws  Cache_Exception
	 */
	abstract public function get(string $id, mixed $default = null): mixed;

	/**
	 * Set a value to cache with id and lifetime
	 *
	 *     $data = 'bar';
	 *
	 *     // Set 'bar' to 'foo' in default group, using default expiry
	 *     Cache::instance()->set('foo', $data);
	 *
	 *     // Set 'bar' to 'foo' in default group for 30 seconds
	 *     Cache::instance()->set('foo', $data, 30);
	 *
	 *     // Set 'bar' to 'foo' in memcache group for 10 minutes
	 *     if (Cache::instance('memcache')->set('foo', $data, 600))
	 *     {
	 *          // Cache was set successfully
	 *          return
	 *     }
	 *
	 * @param   string   $id        id of cache entry
	 * @param   mixed    $data      data to set to cache
	 * @param   int  $lifetime  lifetime in seconds
	 * @return  bool
	 */
	abstract public function set(string $id, mixed $data, ?int $lifetime = 3600): bool;

	/**
	 * Delete a cache entry based on id
	 *
	 *     // Delete 'foo' entry from the default group
	 *     Cache::instance()->delete('foo');
	 *
	 *     // Delete 'foo' entry from the memcache group
	 *     Cache::instance('memcache')->delete('foo')
	 *
	 * @param   string  $id  id to remove from cache
	 * @return  bool
	 */
	abstract public function delete(string $id): bool;

	/**
	 * Delete all cache entries.
	 *
	 * Beware of using this method when
	 * using shared memory cache systems, as it will wipe every
	 * entry within the system for all clients.
	 *
	 *     // Delete all cache entries in the default group
	 *     Cache::instance()->delete_all();
	 *
	 *     // Delete all cache entries in the memcache group
	 *     Cache::instance('memcache')->delete_all();
	 *
	 * @return  bool
	 */
	abstract public function delete_all(): bool;

	/**
	 * Takes the sha1 of the id and adds prefix to avoid duplicates
	 *
	 * The id is converted to a sha1 to prevent any issues with
	 * id length or any special character
	 *
	 *     // Sanitize a cache id
	 *     $id = $this->_sanitize_id($id);
	 *
	 * @param   string  $id  id of cache to sanitize
	 * @return  string
	 */
	/**
	 * The lifetime to use when none is given: "default_expire" of the
	 * config group.
	 *
	 * @param   int  $default  lifetime when the config has none
	 * @return  int  seconds
	 */
	protected function _default_expire(int $default = Cache::DEFAULT_EXPIRE): int
	{
		$expire = $this->_config['default_expire'] ?? $default;

		return is_numeric($expire) ? (int) $expire : $default;
	}

	protected function _sanitize_id(string $id): string
	{

		// adding cache prefix to avoid duplicates
		$prefix = '';
		// configuration for the specific cache group
		if (isset($this->_config['prefix']))
		{
			$prefix = $this->_config['prefix'];
		}
		// prefix general configuration cache
		else
		{
			$prefix = Kohana::$config->load('cache.prefix');
		}

		// sha1 the id makes sure name is not too long and has not any not allowed characters
		return (is_string($prefix) ? $prefix : '').hash('sha256', $id);
	}
}
// End Kohana_Cache
