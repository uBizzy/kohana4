<?php
/**
 * [Kohana Cache](api/Kohana_Cache) File driver. Provides a file based
 * driver for the Kohana Cache library. This is one of the slowest
 * caching methods.
 *
 * ### Configuration example
 *
 * Below is an example of a _file_ server configuration.
 *
 *     return array(
 *          'file'   => array(                          // File driver group
 *                  'driver'         => 'file',         // using File driver
 *                  'cache_dir'     => APPPATH.'cache/.kohana_cache', // Cache location
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
 * cache_dir      | __NO__   | (_string_) The cache directory to use for this cache instance
 *
 * ### System requirements
 *
 * *  Kohana 3.0.x
 * *  PHP 5.2.4 or greater
 *
 * @package    Kohana/Cache
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Cache_File extends Cache implements Cache_GarbageCollect {

	/**
	 * Creates a hashed filename based on the string. This is used
	 * to create shorter unique IDs for each cache filename.
	 *
	 *     // Create the cache filename
	 *     $filename = Cache_File::filename($this->_sanitize_id($id));
	 *
	 * @param   string  $string  string to hash into filename
	 * @return  string
	 */
	protected static function filename(string $string): string
	{
		return hash('sha256', $string).'.cache';
	}

	/**
	 * @var  SplFileInfo   the caching directory, set by _check_cache_dir()
	 */
	protected SplFileInfo $_cache_dir;

	/**
	 * @var  bool  does the cache directory exists and writeable
	 */
	protected bool $_cache_dir_usable = false;

	/**
	 * Check that the cache directory exists and writeable. Attempts to create
	 * it if not exists.
	 *
	 * @throws  Cache_Exception
	 */
	protected function _check_cache_dir(): void
	{
		$directory = $this->_config['cache_dir'] ?? Kohana::$cache_dir;

		if ( ! is_string($directory))
			throw new Cache_Exception('The cache directory is not set');

		// A directory that is not there yet is made
		$this->_cache_dir = file_exists($directory)
			? new SplFileInfo($directory)
			: $this->_make_directory($directory, 0775, true);

		// If the defined directory is a file, get outta here
		if ($this->_cache_dir->isFile())
		{
			throw new Cache_Exception('Unable to create cache directory as a file already exists : :resource', [':resource' => $this->_cache_dir->getRealPath()]);
		}

		// Check the read status of the directory
		if ( ! $this->_cache_dir->isReadable())
		{
			throw new Cache_Exception('Unable to read from the cache directory :resource', [':resource' => $this->_cache_dir->getRealPath()]);
		}

		// Check the write status of the directory
		if ( ! $this->_cache_dir->isWritable())
		{
			throw new Cache_Exception('Unable to write to the cache directory :resource', [':resource' => $this->_cache_dir->getRealPath()]);
		}

		$this->_cache_dir_usable = true;
	}

	/**
	 * Retrieve a cached value entry by id.
	 *
	 *     // Retrieve cache entry from file group
	 *     $data = Cache::instance('file')->get('foo');
	 *
	 *     // Retrieve cache entry from file group and return 'bar' if miss
	 *     $data = Cache::instance('file')->get('foo', 'bar');
	 *
	 * @param   string   $id       id of cache to entry
	 * @param   string   $default  default value to return if cache miss
	 * @return  mixed
	 * @throws  Cache_Exception
	 */
	public function get(string $id, mixed $default = null): mixed
	{
		$this->_cache_dir_usable or $this->_check_cache_dir();

		$filename = Cache_File::filename($this->_sanitize_id($id));
		$directory = $this->_resolve_directory($filename);

		// Wrap operations in try/catch to return default value if it fails to unserialize or read cache
		try
		{
			// Open file
			$file = new SplFileInfo($directory.$filename);

			// If file does not exist
			if ( ! $file->isFile())
				return $this->_read($id, false, $default);

			// Test the expiry
			if ($this->_is_expired($file))
			{
				// Delete the file
				$this->_delete_file($file, false, true);

				return $this->_read($id, false, $default);
			}

			// The first line holds the lifetime; the rest is the serialized data
			$contents = (string) file_get_contents($file->getPathname());

			$serialized = substr($contents, strpos($contents, "\n") + 1);
			$data = @unserialize($serialized);

			if ($data === false AND $serialized !== 'b:0;')
			{
				// Not a stored "false": the file cannot be read back
				throw new Cache_Exception('Corrupt cache file');
			}

			return $this->_read($id, true, $data);
		}
		catch (Throwable $e)
		{
			// A file that cannot be read back is treated as "not cached":
			// remove it, so that it gets rebuilt
			$this->_delete_file(new SplFileInfo($directory.$filename), false, true);

			return $this->_read($id, false, $default);
		}
	}

	/**
	 * Set a value to cache with id and lifetime
	 *
	 *     $data = 'bar';
	 *
	 *     // Set 'bar' to 'foo' in file group, using default expiry
	 *     Cache::instance('file')->set('foo', $data);
	 *
	 *     // Set 'bar' to 'foo' in file group for 30 seconds
	 *     Cache::instance('file')->set('foo', $data, 30);
	 *
	 * @param   string   $id        id of cache entry
	 * @param   string   $data      data to set to cache
	 * @param   int  $lifetime  lifetime in seconds
	 * @return  bool
	 */
	public function set(string $id, mixed $data, ?int $lifetime = null): bool
	{
		$this->_cache_dir_usable or $this->_check_cache_dir();

		$filename = Cache_File::filename($this->_sanitize_id($id));
		$directory = $this->_resolve_directory($filename);

		// If lifetime is null, use the default
		$lifetime ??= $this->_default_expire();

		if ( ! is_dir($directory))
		{
			// Create the directory
			$this->_make_directory($directory, 0775, true);
		}

		// The first line holds the lifetime; the rest is the serialized data.
		// The lock stops another request from reading a half-written file.
		return file_put_contents($directory.$filename, $lifetime."\n".serialize($data), LOCK_EX) !== false;
	}

	/**
	 * Delete a cache entry based on id
	 *
	 *     // Delete 'foo' entry from the file group
	 *     Cache::instance('file')->delete('foo');
	 *
	 * @param   string   $id  id to remove from cache
	 * @return  bool
	 */
	public function delete(string $id): bool
	{
		$this->_cache_dir_usable or $this->_check_cache_dir();

		$filename = Cache_File::filename($this->_sanitize_id($id));
		$directory = $this->_resolve_directory($filename);

		return $this->_delete_file(new SplFileInfo($directory.$filename), false, true);
	}

	/**
	 * Delete all cache entries.
	 *
	 * Beware of using this method when
	 * using shared memory cache systems, as it will wipe every
	 * entry within the system for all clients.
	 *
	 *     // Delete all cache entries in the file group
	 *     Cache::instance('file')->delete_all();
	 *
	 * @return  bool
	 */
	public function delete_all(): bool
	{
		$this->_cache_dir_usable or $this->_check_cache_dir();

		return $this->_delete_file($this->_cache_dir, true);
	}

	/**
	 * Garbage collection method that cleans any expired
	 * cache entries from the cache.
	 *
	 * @return  void
	 */
	public function garbage_collect(): void
	{
		$this->_cache_dir_usable or $this->_check_cache_dir();

		$this->_delete_file($this->_cache_dir, true, false, true);
	}

	/**
	 * Deletes files recursively and returns false on any errors
	 *
	 *     // Delete a file or folder whilst retaining parent directory and ignore all errors
	 *     $this->_delete_file($folder, true, true);
	 *
	 * @param   SplFileInfo  $file                     file
	 * @param   bool      $retain_parent_directory  retain the parent directory
	 * @param   bool      $ignore_errors            ignore_errors to prevent all exceptions interrupting exec
	 * @param   bool      $only_expired             only expired files
	 * @return  bool
	 * @throws  Cache_Exception
	 */
	protected function _delete_file(SplFileInfo $file, bool $retain_parent_directory = false, bool $ignore_errors = false, bool $only_expired = false): bool
	{
		// Allow graceful error handling
		try
		{
			// If is file
			if ($file->isFile())
			{
				try
				{
					// Handle ignore files
					if (in_array($file->getFilename(), (array) $this->config('ignore_on_delete')))
					{
						$delete = false;
					}
					// If only expired is not set
					elseif ($only_expired === false)
					{
						// We want to delete the file
						$delete = true;
					}
					// Otherwise...
					else
					{
						// Assess the file expiry to flag it for deletion
						$delete = $this->_is_expired($file);
					}

					// If the delete flag is set delete file
					if ($delete === true)
						return unlink($file->getRealPath());
					else
						return false;
				}
				catch (ErrorException $e)
				{
					// Catch any delete file warnings
					if ($e->getCode() === E_WARNING)
					{
						throw new Cache_Exception(__METHOD__.' failed to delete file : :file', [':file' => $file->getRealPath()]);
					}
				}

				// The file could not be deleted
				return false;
			}
			// Else, is directory
			elseif ($file->isDir())
			{
				// Create new DirectoryIterator
				$files = new DirectoryIterator($file->getPathname());

				// Iterate over each entry
				while ($files->valid())
				{
					// Extract the entry name
					$name = $files->getFilename();

					// If the name is not a dot
					if ($name != '.' AND $name != '..')
					{
						// Create new file resource
						$fp = new SplFileInfo($files->getRealPath());
						// Delete the file
						$this->_delete_file($fp, $retain_parent_directory, $ignore_errors, $only_expired);
					}

					// Move the file pointer on
					$files->next();
				}

				// If set to retain parent directory, return now
				if ($retain_parent_directory)
				{
					return true;
				}

				try
				{
					// Remove the files iterator
					// (fixes Windows PHP which has permission issues with open iterators)
					unset($files);

					// Try to remove the parent directory
					return rmdir($file->getRealPath());
				}
				catch (ErrorException $e)
				{
					// Catch any delete directory warnings
					if ($e->getCode() === E_WARNING)
					{
						throw new Cache_Exception(__METHOD__.' failed to delete directory : :directory', [':directory' => $file->getRealPath()]);
					}
					throw $e;
				}
			}
			else
			{
				// We get here if a file has already been deleted
				return false;
			}
		}
		// Catch all exceptions
		catch (Throwable $e)
		{
			// If ignore_errors is on
			if ($ignore_errors === true)
			{
				// Return
				return false;
			}
			// Throw exception
			throw $e;
		}
	}

	/**
	 * Resolves the cache directory real path from the filename
	 *
	 *      // Get the realpath of the cache folder
	 *      $realpath = $this->_resolve_directory($filename);
	 *
	 * @param   string  $filename  filename to resolve
	 * @return  string
	 */
	protected function _resolve_directory(string $filename): string
	{
		return $this->_cache_dir->getRealPath().DIRECTORY_SEPARATOR.$filename[0].$filename[1].DIRECTORY_SEPARATOR;
	}

	/**
	 * Makes the cache directory if it doesn't exist. Simply a wrapper for
	 * `mkdir` to ensure DRY principles
	 *
	 * @link    http://php.net/manual/en/function.mkdir.php
	 * @param   string    $directory    directory path
	 * @param   int   $mode         chmod mode
	 * @param   bool   $recursive    allows nested directories creation
	 * @param   resource  $context      a stream context
	 * @return  SplFileInfo
	 * @throws  Cache_Exception
	 */
	protected function _make_directory(string $directory, int $mode = 0775, bool $recursive = false, mixed $context = null): SplFileInfo
	{
		// call mkdir according to the availability of a passed $context param
		$mkdir_result = $context ?
			mkdir($directory, $mode, $recursive, $context) :
			mkdir($directory, $mode, $recursive);

		// throw an exception if unsuccessful
		if ( ! $mkdir_result)
		{
			throw new Cache_Exception('Failed to create the defined cache directory : :directory', [':directory' => $directory]);
		}

		// chmod to solve potential umask issues
		chmod($directory, $mode);

		return new SplFileInfo($directory);
	}

	/**
	 * Test if cache file is expired
	 *
	 * @param SplFileInfo $file the cache file
	 * @return bool true if expired false otherwise
	 */
	protected function _is_expired(SplFileInfo $file): bool
	{
		// Open the file and parse data
		$created = $file->getMTime();
		$data = $file->openFile("r");
		$lifetime = (int) $data->fgets();

		// If we're at the EOF at this point, corrupted!
		if ($data->eof())
		{
			throw new Cache_Exception(__METHOD__ . ' corrupted cache file!');
		}

		//close file
		$data = null;

		// test for expiry and return
		return (($lifetime !== 0) AND ( ($created + $lifetime) < time()));
	}
}
