<?php
/**
 * [Kohana Cache](api/Kohana_Cache) APCu data store driver for Kohana Cache
 * library.
 *
 * ### Configuration example
 *
 * Below is an example of an _apcu_ server configuration.
 *
 *     return array(
 *          'apcu' => array(                          // Driver group
 *                  'driver'         => 'apcu',         // using APCu driver
 *           ),
 *     )
 *
 * In cases where only one cache group is required, if the group is named `default` there is
 * no need to pass the group name when instantiating a cache instance.
 *
 * #### General cache group configuration settings
 *
 * Below are the settings available to all types of cache driver.
 *
 * Name           | Required | Description
 * -------------- | -------- | ---------------------------------------------------------------
 * driver         | __YES__  | (_string_) The driver type to use
 *
 * ### System requirements
 *
 * *  Kohana 3.0.x
 * *  PHP 5.2.4 or greater
 * *  APCu PHP extension
 *
 * @package    Kohana/Cache
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Cache_Apcu extends Cache implements Cache_Arithmetic {

	/**
	 * Check for existence of the APCu extension This method cannot be invoked externally. The driver must
	 * be instantiated using the `Cache::instance()` method.
	 *
	 * @param  array<string, mixed>  $config  configuration
	 * @throws Cache_Exception
	 */
	protected function __construct(array $config)
	{
		if ( ! extension_loaded('apcu'))
		{
			throw new Cache_Exception('PHP APCu extension is not available.');
		}

		parent::__construct($config);
	}

	/**
	 * Retrieve a cached value entry by id.
	 *
	 *     // Retrieve cache entry from apcu group
	 *     $data = Cache::instance('apcu')->get('foo');
	 *
	 *     // Retrieve cache entry from apcu group and return 'bar' if miss
	 *     $data = Cache::instance('apcu')->get('foo', 'bar');
	 *
	 * @param   string  $id       id of cache to entry
	 * @param   string  $default  default value to return if cache miss
	 * @return  mixed
	 * @throws  Cache_Exception
	 */
	public function get(string $id, mixed $default = null): mixed
	{
		$data = apcu_fetch($this->_sanitize_id($id), $success);

		return $this->_read($id, (bool) $success, $success ? $data : $default);
	}

	/**
	 * Set a value to cache with id and lifetime
	 *
	 *     $data = 'bar';
	 *
	 *     // Set 'bar' to 'foo' in apcu group, using default expiry
	 *     Cache::instance('apcu')->set('foo', $data);
	 *
	 *     // Set 'bar' to 'foo' in apcu group for 30 seconds
	 *     Cache::instance('apcu')->set('foo', $data, 30);
	 *
	 * @param   string   $id        id of cache entry
	 * @param   string   $data      data to set to cache
	 * @param   int  $lifetime  lifetime in seconds
	 * @return  bool
	 */
	public function set(string $id, mixed $data, ?int $lifetime = null): bool
	{
		if ($lifetime === null)
		{
			$lifetime = $this->_default_expire();
		}

		return apcu_store($this->_sanitize_id($id), $data, $lifetime);
	}

	/**
	 * Delete a cache entry based on id
	 *
	 *     // Delete 'foo' entry from the apcu group
	 *     Cache::instance('apcu')->delete('foo');
	 *
	 * @param   string  $id  id to remove from cache
	 * @return  bool
	 */
	public function delete(string $id): bool
	{
		return apcu_delete($this->_sanitize_id($id));
	}

	/**
	 * Delete all cache entries.
	 *
	 * Beware of using this method when
	 * using shared memory cache systems, as it will wipe every
	 * entry within the system for all clients.
	 *
	 *     // Delete all cache entries in the apcu group
	 *     Cache::instance('apcu')->delete_all();
	 *
	 * @return  bool
	 */
	public function delete_all(): bool
	{
		return apcu_clear_cache();
	}

	/**
	 * Increments a given value by the step value supplied.
	 * Useful for shared counters and other persistent integer based
	 * tracking.
	 *
	 * @param   string  $id  id of cache entry to increment
	 * @param   int  $step  step value to increment by
	 * @return  int|false
	 */
	public function increment(string $id, int $step = 1): int|false
	{
		$id = $this->_sanitize_id($id);
		if (apcu_exists($id)) {
			return apcu_inc($id, $step);
		}
		return false;
	}

	/**
	 * Decrements a given value by the step value supplied.
	 * Useful for shared counters and other persistent integer based
	 * tracking.
	 *
	 * @param   string  $id  id of cache entry to decrement
	 * @param   int  $step  step value to decrement by
	 * @return  int|false
	 */
	public function decrement(string $id, int $step = 1): int|false
	{
		$id = $this->_sanitize_id($id);
		if (apcu_exists($id)) {
			return apcu_dec($id, $step);
		}
		return false;
	}

} // End Kohana_Cache_Apcu
