<?php
/**
 * Kohana Cache Sqlite Driver
 *
 * Requires SQLite3 and PDO
 *
 * @package    Kohana/Cache
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Cache_Sqlite extends Cache implements Cache_Tagging, Cache_GarbageCollect {

	/**
	 * Database resource
	 *
	 * @var  PDO
	 */
	protected PDO $_db;

	/**
	 * Sets up the PDO SQLite table and
	 * initialises the PDO connection
	 *
	 * @param  array<string, mixed>  $config  configuration
	 * @throws  Cache_Exception
	 */
	protected function __construct(array $config)
	{
		parent::__construct($config);

		$database = Arr::get($this->_config, 'database', null);

		if ($database === null)
		{
			throw new Cache_Exception('Database path not available in Kohana Cache configuration');
		}

		// Load new Sqlite DB
		$this->_db = new PDO('sqlite:'.(is_string($database) ? $database : ''));

		// Test for existing DB
		$statement = $this->_db->query("SELECT * FROM sqlite_master WHERE name = 'caches' AND type = 'table'");
		$result = ($statement === false) ? [] : $statement->fetchAll();

		// If there is no table, create a new one
		if (0 == count($result))
		{
			$database_schema = Arr::get($this->_config, 'schema', null);

			if ($database_schema === null)
			{
				throw new Cache_Exception('Database schema not found in Kohana Cache configuration');
			}

			try
			{
				// Create the caches table
				$schema = $this->_config['schema'] ?? null;

				$this->_db->query(is_string($schema) ? $schema : '');
			}
			catch (PDOException $e)
			{
				throw new Cache_Exception('Failed to create new SQLite caches table with the following error : :error', [':error' => $e->getMessage()]);
			}
		}
	}

	/**
	 * Retrieve a value based on an id
	 *
	 * @param   string  $id       id
	 * @param   string  $default  default [Optional] Default value to return if id not found
	 * @return  mixed
	 * @throws  Cache_Exception
	 */
	public function get(string $id, mixed $default = null): mixed
	{
		// Prepare statement
		$statement = $this->_db->prepare('SELECT id, expiration, cache FROM caches WHERE id = :id LIMIT 0, 1');

		// Try and load the cache based on id
		try
		{
			$statement->execute([':id' => $this->_sanitize_id($id)]);
		}
		catch (PDOException $e)
		{
			throw new Cache_Exception('There was a problem querying the local SQLite3 cache. :error', [':error' => $e->getMessage()]);
		}

		if ( ! $result = $statement->fetch(PDO::FETCH_OBJ))
		{
			return $this->_read($id, false, $default);
		}

		// If the cache has expired
		if ($result->expiration != 0 and $result->expiration <= time())
		{
			// Delete it and return default value
			$this->delete($id);
			return $this->_read($id, false, $default);
		}
		// Otherwise return cached object
		else
		{

			// Return the valid cache data
			return $this->_read($id, true, Cache_Sqlite::_unserialize(is_string($result->cache) ? $result->cache : ''));
		}
	}

	/**
	 * Set a value based on an id. Optionally add tags.
	 *
	 * @param   string   $id        id
	 * @param   mixed    $data      data
	 * @param   int  $lifetime  lifetime [Optional]
	 * @return  bool
	 */
	public function set(string $id, mixed $data, ?int $lifetime = null): bool
	{
		return (bool) $this->set_with_tags($id, $data, $lifetime);
	}

	/**
	 * Delete a cache entry based on id
	 *
	 * @param   string  $id  id
	 * @return  bool
	 * @throws  Cache_Exception
	 */
	public function delete(string $id): bool
	{
		// Prepare statement
		$statement = $this->_db->prepare('DELETE FROM caches WHERE id = :id');

		// Remove the entry
		try
		{
			$statement->execute([':id' => $this->_sanitize_id($id)]);
		}
		catch (PDOException $e)
		{
			throw new Cache_Exception('There was a problem querying the local SQLite3 cache. :error', [':error' => $e->getMessage()]);
		}

		return (bool) $statement->rowCount();
	}

	/**
	 * Delete all cache entries
	 *
	 * @return  bool
	 */
	public function delete_all(): bool
	{
		// Prepare statement
		$statement = $this->_db->prepare('DELETE FROM caches');

		// Remove the entry
		try
		{
			$statement->execute();
		}
		catch (PDOException $e)
		{
			throw new Cache_Exception('There was a problem querying the local SQLite3 cache. :error', [':error' => $e->getMessage()]);
		}

		return (bool) $statement->rowCount();
	}

	/**
	 * Set a value based on an id. Optionally add tags.
	 *
	 * @param   string   $id        id
	 * @param   mixed    $data      data
	 * @param   int  $lifetime  lifetime [Optional]
	 * @param   array<int, string>    $tags      tags [Optional]
	 * @return  bool
	 * @throws  Cache_Exception
	 */
	public function set_with_tags(string $id, mixed $data, ?int $lifetime = null, ?array $tags = null): bool
	{
		// Serialize the data
		$data = serialize($data);

		// Normalise tags
		$tags = (null === $tags) ? null : ('<'.implode('>,<', $tags).'>');

		// Setup lifetime
		if ($lifetime === null)
		{
			// A default of 0 means: keep for ever
			$lifetime = ($this->_default_expire() === 0) ? 0 : ($this->_default_expire() + time());
		}
		else
		{
			$lifetime = (0 === $lifetime) ? 0 : ($lifetime + time());
		}

		// Prepare statement
		// $this->exists() may throw Cache_Exception, no need to catch/rethrow
		$statement = $this->exists($id) ? $this->_db->prepare('UPDATE caches SET expiration = :expiration, cache = :cache, tags = :tags WHERE id = :id') : $this->_db->prepare('INSERT INTO caches (id, cache, expiration, tags) VALUES (:id, :cache, :expiration, :tags)');

		// Try to insert
		try
		{
			$statement->execute([':id' => $this->_sanitize_id($id), ':cache' => $data, ':expiration' => $lifetime, ':tags' => $tags]);
		}
		catch (PDOException $e)
		{
			throw new Cache_Exception('There was a problem querying the local SQLite3 cache. :error', [':error' => $e->getMessage()]);
		}

		return (bool) $statement->rowCount();
	}

	/**
	 * Returns the pattern that finds the rows with a tag. In a pattern
	 * "%" and "_" stand for any text and any character: in the name of
	 * a tag they are meant as themselves ("user_1" is not "userA1").
	 *
	 * @param   string  $tag  name of the tag
	 * @return  string
	 */
	protected function _like(string $tag): string
	{
		return '%<'.addcslashes($tag, '\\%_').'>%';
	}

	/**
	 * Delete cache entries based on a tag
	 *
	 * @param   string  $tag  tag
	 * @return  bool
	 * @throws  Cache_Exception
	 */
	public function delete_tag(string $tag): bool
	{
		// Prepare the statement
		$statement = $this->_db->prepare('DELETE FROM caches WHERE tags LIKE :tag ESCAPE \'\\\'');

		// Try to delete
		try
		{
			$statement->execute([':tag' => $this->_like($tag)]);
		}
		catch (PDOException $e)
		{
			throw new Cache_Exception('There was a problem querying the local SQLite3 cache. :error', [':error' => $e->getMessage()]);
		}

		return (bool) $statement->rowCount();
	}

	/**
	 * Find cache entries based on a tag
	 *
	 * @param   string  $tag  tag
	 * @return  array<string, mixed>
	 * @throws  Cache_Exception
	 */
	public function find(string $tag): array
	{
		// Prepare the statement
		$statement = $this->_db->prepare('SELECT id, cache FROM caches WHERE tags LIKE :tag ESCAPE \'\\\'');

		// Try to find
		try
		{
			if ( ! $statement->execute([':tag' => $this->_like($tag)]))
			{
				return [];
			}
		}
		catch (PDOException $e)
		{
			throw new Cache_Exception('There was a problem querying the local SQLite3 cache. :error', [':error' => $e->getMessage()]);
		}

		$result = [];

		while ($row = $statement->fetchObject())
		{
			if ( ! is_string($row->id) OR ! is_string($row->cache))
				continue;

			$result[$row->id] = Cache_Sqlite::_unserialize($row->cache);

			// Turn notices back on
		}

		return $result;
	}

	/**
	 * Garbage collection method that cleans any expired
	 * cache entries from the cache.
	 *
	 * @return  void
	 */
	public function garbage_collect(): void
	{
		// Create the sequel statement
		$statement = $this->_db->prepare('DELETE FROM caches WHERE expiration < :expiration');

		try
		{
			$statement->execute([':expiration' => time()]);
		}
		catch (PDOException $e)
		{
			throw new Cache_Exception('There was a problem querying the local SQLite3 cache. :error', [':error' => $e->getMessage()]);
		}
	}

	/**
	 * Tests whether an id exists or not
	 *
	 * @param   string  $id  id
	 * @return  bool
	 * @throws  Cache_Exception
	 */
	protected function exists(string $id): bool
	{
		$statement = $this->_db->prepare('SELECT id FROM caches WHERE id = :id');
		try
		{
			$statement->execute([':id' => $this->_sanitize_id($id)]);
		}
		catch (PDOException $e)
		{
			throw new Cache_Exception('There was a problem querying the local SQLite3 cache. :error', [':error' => $e->getMessage()]);
		}

		return (bool) $statement->fetchAll();
	}
	/**
	 * Unserializes a stored value. Data that cannot be read back gives
	 * false, without raising a PHP warning.
	 *
	 * @param   string  $data  serialized value
	 * @return  mixed
	 */
	protected static function _unserialize(string $data): mixed
	{
		return @unserialize($data);
	}

}
