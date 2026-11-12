<?php
/**
 * Array helper.
 *
 * @package    Kohana
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Arr {

	/**
	 * @var  non-empty-string  default delimiter for path()
	 */
	public static string $delimiter = '.';

	/**
	 * Tests if an array is associative or not.
	 *
	 *     // Returns true
	 *     Arr::is_assoc(array('username' => 'john.doe'));
	 *
	 *     // Returns false
	 *     Arr::is_assoc('foo', 'bar');
	 *
	 * @param   array<int|string, mixed>   $array  array to check
	 * @return  bool
	 */
	public static function is_assoc(array $array): bool
	{
		// A "list" has the keys 0, 1, 2, ... in order; anything else is associative
		return ! array_is_list($array);
	}

	/**
	 * Test if a value is an array with an additional check for array-like objects.
	 *
	 *     // Returns true
	 *     Arr::is_array(array());
	 *     Arr::is_array(new ArrayObject);
	 *
	 *     // Returns false
	 *     Arr::is_array(false);
	 *     Arr::is_array('not an array!');
	 *     Arr::is_array(Database::instance());
	 *
	 * @param   mixed   $value  value to check
	 * @return  bool
	 */
	public static function is_array(mixed $value): bool
	{
		// A Traversable object is functionally the same as an array
		return is_array($value) OR $value instanceof Traversable;
	}

	/**
	 * Gets a value from an array using a dot separated path.
	 *
	 *     // Get the value of $array['foo']['bar']
	 *     $value = Arr::path($array, 'foo.bar');
	 *
	 * Using a wildcard "*" will search intermediate arrays and return an array.
	 *
	 *     // Get the values of "color" in theme
	 *     $colors = Arr::path($array, 'theme.*.color');
	 *
	 *     // Using an array of keys
	 *     $colors = Arr::path($array, array('theme', '*', 'color'));
	 *
	 * [!!] With a wildcard, values that are empty (null, false, 0, '', array())
	 * are left out of the result.
	 *
	 * @param   mixed             $array      array to search; anything else returns the default
	 * @param   string|int|array<int, int|string>  $path       key path string (delimiter separated) or array of keys
	 * @param   mixed             $default    default value if the path is not set
	 * @param   non-empty-string|null  $delimiter  key path delimiter; null uses Arr::$delimiter
	 * @return  mixed
	 */
	public static function path(mixed $array, string|int|array $path, mixed $default = null, ?string $delimiter = null): mixed
	{
		if ( ! Arr::is_array($array))
		{
			// This is not an array!
			return $default;
		}

		/** @var array<int|string, mixed>|(ArrayAccess<int|string, mixed>&Traversable<int|string, mixed>) $array */

		if (is_array($path))
		{
			// The path has already been separated into keys
			$keys = $path;
		}
		else
		{
			if (isset($array[$path]))
			{
				// No need to do extra processing
				return $array[$path];
			}

			// Use the default delimiter
			$delimiter ??= Arr::$delimiter;

			// Remove starting delimiters and spaces
			$path = ltrim( (string) $path, "{$delimiter} ");

			// Remove ending delimiters, spaces, and wildcards
			$path = rtrim($path, "{$delimiter} *");

			// Split the keys by delimiter
			$keys = explode($delimiter, $path);
		}

		while ($keys)
		{
			$key = Arr::_path_key(array_shift($keys));

			if (isset($array[$key]))
			{
				if ( ! $keys)
				{
					// Found the path requested
					return $array[$key];
				}

				if ( ! Arr::is_array($array[$key]))
				{
					// Unable to dig deeper
					break;
				}

				// Dig down into the next part of the path
				/** @var array<int|string, mixed>|(ArrayAccess<int|string, mixed>&Traversable<int|string, mixed>) $array */
				$array = $array[$key];
			}
			elseif ($key === '*')
			{
				// Search every sub-array for the rest of the path
				return Arr::_path_wildcard($array, $keys) ?: $default;
			}
			else
			{
				// Unable to dig deeper
				break;
			}
		}

		// Unable to find the value requested
		return $default;
	}

	/**
	 * Normalises one key of a path: a key made only of digits becomes an
	 * integer, as PHP itself does for array keys.
	 *
	 * @param   mixed  $key  key as found in the path
	 * @return  int|string  integer for digit-only strings, otherwise the key unchanged
	 */
	protected static function _path_key(mixed $key): int|string
	{
		if (is_string($key) AND ctype_digit($key))
			return (int) $key;

		return (is_int($key) OR is_string($key)) ? $key : '';
	}

	/**
	 * Handles the "*" wildcard of [Arr::path]: looks up the remaining keys in
	 * every element of the array and collects the non-empty results.
	 *
	 * @param   iterable<int|string, mixed>  $array  array whose elements are searched
	 * @param   array<int, int|string>     $keys   remaining keys of the path
	 * @return  array<int, mixed>     values found; empty if there are none
	 */
	protected static function _path_wildcard(iterable $array, array $keys): array
	{
		$values = [];

		// Nothing left to look up after the wildcard: nothing can be found
		if ( ! $keys)
			return $values;

		foreach ($array as $arr)
		{
			if ($value = Arr::path($arr, $keys))
			{
				$values[] = $value;
			}
		}

		return $values;
	}

	/**
	 * Set a value on an array by path. Missing intermediate arrays are created.
	 *
	 *     // Set $array['foo']['bar'] = 'value'
	 *     Arr::set_path($array, 'foo.bar', 'value');
	 *
	 * @see     Arr::path()
	 * @param   array<int|string, mixed>|null    $array      Array to update (null becomes a new array)
	 * @param-out  array<int|string, mixed>  $array
	 * @param   string|array<int, int|string>  $path       Path, or array of keys
	 * @param   mixed         $value      Value to set
	 * @param   non-empty-string|null   $delimiter  Path delimiter; null uses Arr::$delimiter
	 * @return  void
	 */
	public static function set_path(?array & $array, string|array $path, mixed $value, ?string $delimiter = null): void
	{
		// Split the path into keys, unless it already is an array of keys
		$keys = is_array($path) ? $path : explode($delimiter ?: Arr::$delimiter, $path);

		// Walk down to the inner-most array of the path
		$target = & $array;

		while (count($keys) > 1)
		{
			$key = Arr::_path_key(array_shift($keys));

			if ( ! isset($target[$key]))
			{
				$target[$key] = [];
			}

			/** @var array<int|string, mixed> $target */
			$target = & $target[$key];
		}

		// Set key on inner-most array
		$target[array_shift($keys) ?? ''] = $value;
	}

	/**
	 * Fill an array with a range of numbers.
	 *
	 *     // Fill an array with values 5, 10, 15, 20
	 *     $values = Arr::range(5, 20);
	 *
	 * @param   int $step   stepping
	 * @param   int $max    ending number
	 * @return  array<int, int>
	 */
	public static function range(int $step = 10, int $max = 100): array
	{
		if ($step < 1)
			return [];

		$array = [];
		for ($i = $step; $i <= $max; $i += $step)
		{
			$array[$i] = $i;
		}

		return $array;
	}

	/**
	 * Retrieve a single key from an array. If the key does not exist in the
	 * array, the default value will be returned instead.
	 *
	 *     // Get the value "username" from $_POST, if it exists
	 *     $username = Arr::get($_POST, 'username');
	 *
	 *     // Get the value "sorting" from $_GET, if it exists
	 *     $sorting = Arr::get($_GET, 'sorting');
	 *
	 * @param   array<int|string, mixed>|ArrayAccess<*, *>|null  $array  array to extract from (null gives the default)
	 * @param   string|int  $key        key name
	 * @param   mixed   $default    default value
	 * @return  mixed
	 */
	public static function get(array|ArrayAccess|null $array, string|int $key, mixed $default = null): mixed
	{
		if ($array instanceof ArrayObject)
		{
			// An ArrayObject key that exists is returned even when its value is null
			return $array->offsetExists($key) ? $array->offsetGet($key) : $default;
		}

		return $array[$key] ?? $default;
	}

	/**
	 * Retrieves multiple paths from an array. If the path does not exist in the
	 * array, the default value will be added instead.
	 *
	 *     // Get the values "username", "password" from $_POST
	 *     $auth = Arr::extract($_POST, array('username', 'password'));
	 *
	 *     // Get the value "level1.level2a" from $data
	 *     $data = array('level1' => array('level2a' => 'value 1', 'level2b' => 'value 2'));
	 *     Arr::extract($data, array('level1.level2a', 'password'));
	 *
	 * @param   array<int|string, mixed>  $array    array to extract paths from
	 * @param   array<int, int|string>  $paths    list of path
	 * @param   mixed  $default  default value
	 * @return  array<int|string, mixed>
	 */
	public static function extract(mixed $array, array $paths, mixed $default = null): array
	{
		$found = [];
		foreach ($paths as $path)
		{
			Arr::set_path($found, (string) $path, Arr::path($array, $path, $default));
		}

		return $found;
	}

	/**
	 * Retrieves muliple single-key values from a list of arrays.
	 *
	 *     // Get all of the "id" values from a result
	 *     $ids = Arr::pluck($result, 'id');
	 *
	 * [!!] A list of arrays is an array that contains arrays, eg: array(array $a, array $b, array $c, ...)
	 *
	 * @param   iterable<int|string, mixed>   $array  list of arrays to check
	 * @param   string  $key    key to pluck
	 * @return  array<int, mixed>
	 */
	public static function pluck(iterable $array, string|int $key): array
	{
		$values = [];

		foreach ($array as $row)
		{
			if ((is_array($row) OR $row instanceof ArrayAccess) AND isset($row[$key]))
			{
				// Found a value in this row
				$values[] = $row[$key];
			}
		}

		return $values;
	}

	/**
	 * Adds a value to the beginning of an associative array.
	 *
	 *     // Add an empty value to the start of a select list
	 *     Arr::unshift($array, 'none', 'Select a value');
	 *
	 * @param   array<int|string, mixed>   $array  array to modify
	 * @param   string  $key    array key name
	 * @param   mixed   $val    array value
	 * @return  array<int|string, mixed>
	 */
	public static function unshift(array & $array, string|int $key, mixed $val): array
	{
		$array = array_reverse($array, true);
		$array[$key] = $val;
		$array = array_reverse($array, true);

		return $array;
	}

	/**
	 * Recursive version of [array_map](http://php.net/array_map), applies one or more
	 * callbacks to all elements in an array, including sub-arrays.
	 *
	 *     // Apply "strip_tags" to every element in the array
	 *     $array = Arr::map('strip_tags', $array);
	 *
	 *     // Apply $this->filter to every element in the array
	 *     $array = Arr::map(array(array($this,'filter')), $array);
	 *
	 *     // Apply strip_tags and $this->filter to every element
	 *     $array = Arr::map(array('strip_tags',array($this,'filter')), $array);
	 *
	 * [!!] Because you can pass an array of callbacks, if you wish to use an array-form callback
	 * you must nest it in an additional array as above. Calling Arr::map(array($this,'filter'), $array)
	 * will cause an error.
	 * [!!] Unlike `array_map`, this method requires a callback and will only map
	 * a single array.
	 *
	 * @param   array<int, mixed>|(callable)   $callbacks  array of callbacks to apply to every element in the array
	 * @param   array<int|string, mixed>   $array      array to map
	 * @param   array<int, int|string>   $keys       array of keys to apply to
	 * @return  array<int|string, mixed>
	 */
	public static function map(callable|array $callbacks, array $array, ?array $keys = null): array
	{
		foreach ($array as $key => $val)
		{
			if (is_array($val))
			{
				$array[$key] = Arr::map($callbacks, $val, $keys);
			}
			elseif ( ! is_array($keys) OR in_array($key, $keys))
			{
				if (is_array($callbacks))
				{
					/** @var callable $cb */
					foreach ($callbacks as $cb)
					{
						$array[$key] = call_user_func($cb, $array[$key]);
					}
				}
				else
				{
					$array[$key] = call_user_func($callbacks, $array[$key]);
				}
			}
		}

		return $array;
	}

	/**
	 * Recursively merge two or more arrays. Values in an associative array
	 * overwrite previous values with the same key. Values in an indexed array
	 * are appended, but only when they do not already exist in the result.
	 *
	 * Note that this does not work the same as [array_merge_recursive](http://php.net/array_merge_recursive)!
	 *
	 *     $john = array('name' => 'john', 'children' => array('fred', 'paul', 'sally', 'jane'));
	 *     $mary = array('name' => 'mary', 'children' => array('jane'));
	 *
	 *     // John and Mary are married, merge them together
	 *     $john = Arr::merge($john, $mary);
	 *
	 *     // The output of $john will now be:
	 *     array('name' => 'mary', 'children' => array('fred', 'paul', 'sally', 'jane'))
	 *
	 * @param   array<int|string, mixed>  $array1     initial array
	 * @param   array<int|string, mixed>  $array2     array to merge
	 * @param   array<int|string, mixed>  ...$arrays  more arrays to merge
	 * @return  array<int|string, mixed>
	 */
	public static function merge(array $array1, array $array2, array ...$arrays): array
	{
		// Merge every array, one after the other, into the first
		foreach ([$array2, ...$arrays] as $array)
		{
			if (Arr::is_assoc($array))
			{
				foreach ($array as $key => $value)
				{
					if (is_array($value) AND isset($array1[$key]) AND is_array($array1[$key]))
					{
						// Both sides are arrays: merge them recursively
						$array1[$key] = Arr::merge($array1[$key], $value);
					}
					else
					{
						// Otherwise the new value replaces the old one
						$array1[$key] = $value;
					}
				}
			}
			else
			{
				foreach ($array as $value)
				{
					// Indexed arrays: append values that are not there yet
					if ( ! in_array($value, $array1, true))
					{
						$array1[] = $value;
					}
				}
			}
		}

		return $array1;
	}

	/**
	 * Overwrites an array with values from input arrays.
	 * Keys that do not exist in the first array will not be added!
	 *
	 *     $a1 = array('name' => 'john', 'mood' => 'happy', 'food' => 'bacon');
	 *     $a2 = array('name' => 'jack', 'food' => 'tacos', 'drink' => 'beer');
	 *
	 *     // Overwrite the values of $a1 with $a2
	 *     $array = Arr::overwrite($a1, $a2);
	 *
	 *     // The output of $array will now be:
	 *     array('name' => 'jack', 'mood' => 'happy', 'food' => 'tacos')
	 *
	 * @param   array<int|string, mixed>  $array1     master array
	 * @param   array<int|string, mixed>  $array2     input array that will overwrite existing values
	 * @param   array<int|string, mixed>  ...$arrays  more input arrays
	 * @return  array<int|string, mixed>
	 */
	public static function overwrite(array $array1, array $array2, array ...$arrays): array
	{
		foreach ([$array2, ...$arrays] as $array)
		{
			// Only the keys that already exist in the master array are copied
			foreach (array_intersect_key($array, $array1) as $key => $value)
			{
				$array1[$key] = $value;
			}
		}

		return $array1;
	}

	/**
	 * Creates a callable function and parameter list from a string representation.
	 * Note that this function does not validate the callback string.
	 *
	 *     // Get the callback function and parameters
	 *     list($func, $params) = Arr::callback('Foo::bar(apple,orange)');
	 *
	 *     // Get the result of the callback
	 *     $result = call_user_func_array($func, $params);
	 *
	 * @param   string  $str    callback string
	 * @return  array<int, mixed>   function, params
	 */
	public static function callback(string $str): array
	{
		// Overloaded as parts are found
		$command = $params = null;

		// command[param,param]
		if (preg_match('/^([^\(]*+)\((.*)\)$/', $str, $match))
		{
			// command
			$command = $match[1];

			if ($match[2] !== '')
			{
				// param,param
				$params = preg_split('/(?<!\\\\),/', $match[2]) ?: [];
				$params = str_replace('\,', ',', $params);
			}
		}
		else
		{
			// command
			$command = $str;
		}

		if (str_contains($command, '::'))
		{
			// Create a static method callable command
			$command = explode('::', $command, 2);
		}

		return [$command, $params];
	}

	/**
	 * Convert a multi-dimensional array into a single-dimensional array.
	 *
	 *     $array = array('set' => array('one' => 'something'), 'two' => 'other');
	 *
	 *     // Flatten the array
	 *     $array = Arr::flatten($array);
	 *
	 *     // The array will now be
	 *     array('one' => 'something', 'two' => 'other');
	 *
	 * [!!] The keys of array values will be discarded.
	 *
	 * @param   array<int|string, mixed>   $array  array to flatten
	 * @return  array<int|string, mixed>
	 * @since   3.0.6
	 */
	public static function flatten(array $array): array
	{
		$is_assoc = Arr::is_assoc($array);

		$flat = [];
		foreach ($array as $key => $value)
		{
			if (is_array($value))
			{
				$flat = array_merge($flat, Arr::flatten($value));
			}
			else
			{
				if ($is_assoc)
				{
					$flat[$key] = $value;
				}
				else
				{
					$flat[] = $value;
				}
			}
		}
		return $flat;
	}

	/**
	 * Returns only the given keys of an array. The safe way to take just
	 * the form fields you expect:
	 *
	 *     // Only "username" and "email", whatever else was posted
	 *     $values = Arr::only($_POST, ['username', 'email']);
	 *
	 * Unlike [Arr::extract], keys that are not in the array are left out.
	 *
	 * @param   array<int|string, mixed>  $array  array to take from
	 * @param   array<int, int|string>  $keys   list of keys to keep
	 * @return  array<int|string, mixed>
	 */
	public static function only(array $array, array $keys): array
	{
		return array_intersect_key($array, array_flip($keys));
	}

	/**
	 * Returns an array without the given keys.
	 *
	 *     // Everything but the passwords
	 *     $values = Arr::except($_POST, ['password', 'password_confirm']);
	 *
	 * @param   array<int|string, mixed>  $array  array to take from
	 * @param   array<int, int|string>  $keys   list of keys to leave out
	 * @return  array<int|string, mixed>
	 */
	public static function except(array $array, array $keys): array
	{
		return array_diff_key($array, array_flip($keys));
	}

	/**
	 * Tests if a path exists in an array, also when its value is null
	 * (where [Arr::path] would return the default). Wildcards ("*") are
	 * not supported.
	 *
	 *     // true, even when the hostname is set to null
	 *     Arr::has($config, 'database.default.hostname');
	 *
	 * @param   array<int|string, mixed>         $array      array to search
	 * @param   string|array<int, int|string>  $path       key path string (delimiter separated) or array of keys
	 * @param   non-empty-string|null   $delimiter  key path delimiter
	 * @return  bool
	 */
	public static function has(array $array, string|array $path, ?string $delimiter = null): bool
	{
		$keys = is_array($path) ? $path : explode($delimiter ?? Arr::$delimiter, $path);

		foreach ($keys as $key)
		{
			if ( ! is_array($array) OR ! array_key_exists($key, $array))
				return false;

			// Go one level deeper
			$array = $array[$key];
		}

		return true;
	}

	/**
	 * Removes the value at a path from an array. Completes [Arr::path]
	 * and [Arr::set_path]. Wildcards ("*") are not supported.
	 *
	 *     // Removes $array['theme']['colors']['link']
	 *     Arr::delete_path($array, 'theme.colors.link');
	 *
	 * @param   array<int|string, mixed>         $array      array to change
	 * @param   string|array<int, int|string>  $path       key path string (delimiter separated) or array of keys
	 * @param   non-empty-string|null   $delimiter  key path delimiter
	 * @return  bool  true when the path existed and was removed
	 */
	public static function delete_path(array & $array, string|array $path, ?string $delimiter = null): bool
	{
		$keys = is_array($path) ? array_values($path) : explode($delimiter ?? Arr::$delimiter, $path);

		// The last key is the one to remove
		$last = array_pop($keys);

		foreach ($keys as $key)
		{
			if ( ! isset($array[$key]) OR ! is_array($array[$key]))
				return false;

			// Go one level deeper
			$array = & $array[$key];
		}

		if ($last === null OR ! array_key_exists($last, $array))
			return false;

		unset($array[$last]);

		return true;
	}

}
