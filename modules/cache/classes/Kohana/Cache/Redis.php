<?php
/**
 * Cache Class for Redis
 *
 * Taken from Yoshiharu Shibata <shibata@zoga.me>
 * and Chris Go <chris@velocimedia.com>
 *
 * - requires PhpRedis
 *
 * @package    Kohana\Cache
 * @category   Base
 *
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Cache_Redis extends Cache implements Cache_Tagging
{

	/**
	 * Redis instance
	 * @var Redis
	 */
	protected Redis $_redis;

	/**
	 * Prefix for tag
	 * @var string
	 */
	protected string $_tag_prefix = '_tag';

	/**
	 * Ensures singleton pattern is observed, loads the default expiry
	 *
	 * @param  array<string, mixed> $config            Configuration
	 * @throws Cache_Exception          Redis ext. not loaded or server not configured
	 */
	public function __construct(array $config)
	{
		if ( ! extension_loaded('redis'))
		{
			// @codeCoverageIgnoreStart
			throw new Cache_Exception(__METHOD__.' Redis PHP extension not loaded!');
			// @codeCoverageIgnoreEnd
		}

		parent::__construct($config);

		// Get Configured Servers
		/** @var array<int|string, array{host: string, port: int, persistent?: bool, password?: string, prefix?: string}> $servers */
		$servers = (array) ($this->_config['servers'] ?? []);
		if (empty($servers))
		{
			throw new Cache_Exception('No Redis servers defined in configuration');
		}

		$this->_redis = new Redis();

		// Global cache prefix so the keys in redis is organized
		$cache_prefix = $this->_config['cache_prefix'] ?? null;
		$tag_prefix = $this->_config['tag_prefix'] ?? null;
		$this->_tag_prefix = (is_string($tag_prefix) ? $tag_prefix : $this->_tag_prefix).':';


		foreach($servers as $server)
		{
			// Connection method
			$method = empty($server['persistent']) ? 'connect' : 'pconnect';
			$this->_redis->{$method}($server['host'], $server['port'], 1);
			// See if there is a password
			$password = $server['password'] ?? null;
			if ( ! empty($password))
			{
				$this->_redis->auth($password);
			}
			// Prefix a name space
			$prefix = $server['prefix'] ?? null;
			if ( ! empty($prefix))
			{
				if (is_string($cache_prefix) AND $cache_prefix !== '')
				{
					$prefix .= ':'.$cache_prefix;
				}
				$prefix .= ':';
				$this->_redis->setOption(Redis::OPT_PREFIX, $prefix);
			}
		}

		// serialize stuff
		// if use Redis::SERIALIZER_IGBINARY, "run configure with --enable-redis-igbinary"
		$this->_redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);

	}

	/**
	 * Get value from cache
	 *
	 * @param array<int, string>|string  $id
	 * @param mixed         $default
	 * @return mixed
	 */
	public function get(string|array $id, mixed $default = null): mixed
	{
		$value = null;
		if (is_array($id))
		{
			// sanitize keys
			$ids = array_map([$this, '_sanitize_id'], $id);

			// return key/value
			$value = array_combine($id, $this->_redis->mget($ids));

			foreach ($value as $key => $one)
			{
				// Redis answers false for a key that does not exist
				$this->_read( (string) $key, $one !== false, $one);
			}

			return $value;
		}

		$value = $this->_redis->get($this->_sanitize_id($id));

		// Redis answers false for a key that does not exist
		$found = ($value !== false AND $value !== null);

		return $this->_read($id, $found, $found ? $value : $default);
	}

	/**
	 * Set value
	 *  - supports multi set but assumes count of ids == count of data
	 *
	 * @param array<int, string>|string  $id             Cache Key or assoc
	 * @param mixed         $data           Cache Value
	 * @param int           $lifetime       Cache lifetime
	 * @return bool                    Set|NotSet
	 */
	public function set(string|array $id, mixed $data, ?int $lifetime = 3600): bool
	{
		if (is_array($id))
		{
			// sanitize keys
			$ids = array_map([$this, '_sanitize_id'], $id);
			// use mset to put it all in redis
			$set = $this->_redis->mset(array_combine($ids, array_values( (array) $data)));
			$this->_set_ttl($ids, $lifetime);  // give it an array of keys and one lifetime
		}
		else
		{
			$id = $this->_sanitize_id($id);
			$set = $this->_redis->mset([$id => $data]);
			$this->_set_ttl($id, $lifetime);
		}

		return (bool) $set;
	}

	/**
	 * Delete Value
	 *
	 * @param  string $id    Cached Key
	 * @return bool   Number of Keys deleted
	 */
	public function delete(string $id): bool
	{
		$id = $this->_sanitize_id($id);
		return $this->_redis->del($id) >= 1;
	}

	/**
	 * Delete all values
	 *
	 * @return bool     Always True
	 */
	public function delete_all(): bool
	{
		return $this->_redis->flushDB();
	}

	/**
	 * Set the lifetime
	 *
	 * @param array<int, int|string>|string  $keys      Cache Key or Array
	 * @param int    $lifetime  Lifetime in seconds
	 */
	protected function _set_ttl(string|array $keys, ?int $lifetime = null): void
	{
		// If lifetime is null
		if ($lifetime === null)
		{
			$lifetime = $this->_default_expire(3600);
		}

		if ($lifetime > 0) {
			if (is_array($keys))
			{
				foreach ($keys as $key)
				{
					$this->_redis->expire( (string) $key, $lifetime);
				}
			}
			else
			{
				$this->_redis->expire($keys, $lifetime);
			}
		}
	}

	// ==================== TAGS ====================

	/**
	 * Set a value based on an id with tags
	 *
	 * @param   string   $id         Cache Key
	 * @param   mixed    $data       Cache Data
	 * @param   int  $lifetime   Lifetime [Optional]
	 * @param   array<int, string>    $tags       Tags [Optional]
	 * @return  bool            Set|NotSet
	 */
	public function set_with_tags(string $id, mixed $data, ?int $lifetime = null, ?array $tags = null): bool
	{
		// set() builds the stored key itself
		$result = $this->set($id, $data, $lifetime);

		// The tag lists hold the stored keys
		$id = $this->_sanitize_id($id);
		if ($result AND $tags)
		{
			foreach ($tags as $tag)
			{
				$this->_redis->lPush($this->_tag_prefix.$tag, $id);
			}
		}

		return $result;
	}

	/**
	 * Delete cache entries based on a tag
	 *
	 * @param   string  $tag    Tag
	 * @return  bool            Deleted?
	 */
	public function delete_tag(string $tag): bool
	{
		if ($this->_redis->exists($this->_tag_prefix.$tag)) {
			/** @var array<int, string> $keys  the list holds stored keys */
			$keys = $this->_redis->lRange($this->_tag_prefix.$tag, 0, -1);
			if ($keys)
			{
				foreach ($keys as $key)
				{
					// The list holds stored keys: delete them as they are
					$this->_redis->del($key);
				}
			}

			// Then delete the tag itself
			$this->_redis->del($this->_tag_prefix.$tag);

			return true;
		}
		return false;
	}

	/**
	 * Find cache entries based on a tag. Entries that have expired are
	 * left out.
	 *
	 * @param   string  $tag  Tag
	 * @return  array<string, mixed>  values by their stored key
	 */
	public function find(string $tag): array
	{
		if ($this->_redis->exists($this->_tag_prefix.$tag))
		{
			/** @var array<int, string> $keys  the list holds stored keys */
			$keys = $this->_redis->lRange($this->_tag_prefix.$tag, 0, -1);
			if ($keys)
			{
				$rows = [];
				foreach ($keys as $key)
				{
					// The list holds stored keys: read them as they are
					$value = $this->_redis->get($key);

					// Redis answers false for an entry that has expired:
					// it is no longer in the cache, so it is not found
					if ($value !== false)
					{
						$rows[$key] = $value;
					}
				}
				return $rows;
			}
		}

		return [];
	}
}
