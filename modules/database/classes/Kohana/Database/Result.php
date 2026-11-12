<?php
/**
 * Database result wrapper.  See [Results](/database/results) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query/Result
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 *
 * @implements Iterator<int, mixed>
 * @implements SeekableIterator<int, mixed>
 * @implements ArrayAccess<int, mixed>
 */
abstract class Kohana_Database_Result implements Countable, Iterator, SeekableIterator, ArrayAccess {

	// Executed SQL for this result
	protected string $_query;

	// Raw result resource
	protected mixed $_result;

	// Total number of rows and current row
	protected int $_total_rows  = 0;
	protected int $_current_row = 0;

	// Return rows as an object or associative array
	protected bool|string $_as_object = false;

	/**
	 * @var  array<int|string, mixed>|null  Parameters for __construct when using object results
	 */
	protected ?array $_object_params = null;

	/**
	 * Sets the total number of rows and stores the result locally.
	 *
	 * @param   mixed   $result     query result
	 * @param   string  $sql        SQL query
	 * @param   bool|object|string|null   $as_object
	 * @param   array<int|string, mixed>   $params
	 * @return  void
	 */
	public function __construct(mixed $result, string $sql, bool|string|object|null $as_object = false, ?array $params = null)
	{
		// Store the result locally
		$this->_result = $result;

		// Store the SQL locally
		$this->_query = $sql;

		if (is_object($as_object))
		{
			// Get the object class name
			$as_object = get_class($as_object);
		}

		// Results as objects or associative arrays
		// null means "as arrays", the same as false
		$this->_as_object = $as_object ?? false;

		if ($params)
		{
			// Object constructor params
			$this->_object_params = $params;
		}
	}

	/**
	 * Result destruction cleans up all open result sets.
	 *
	 * @return  void
	 */
	abstract public function __destruct();

	/**
	 * Get a cached database result from the current result iterator.
	 *
	 *     $cachable = serialize($result->cached());
	 *
	 * @return  Database_Result_Cached
	 * @since   3.0.5
	 */
	public function cached(): Database_Result_Cached
	{
		return new Database_Result_Cached(array_values($this->as_array()), $this->_query, $this->_as_object);
	}

	/**
	 * Return all of the rows in the result as an array.
	 *
	 *     // Indexed array of all rows
	 *     $rows = $result->as_array();
	 *
	 *     // Associative array of rows by "id"
	 *     $rows = $result->as_array('id');
	 *
	 *     // Associative array of rows, "id" => "name"
	 *     $rows = $result->as_array('id', 'name');
	 *
	 * @param   string  $key    column for associative keys
	 * @param   string  $value  column for values
	 * @return  array<int|string, mixed>
	 */
	public function as_array(?string $key = null, ?string $value = null): array
	{
		$results = [];

		if ($key === null AND $value === null)
		{
			// Indexed rows

			foreach ($this as $row)
			{
				$results[] = $row;
			}
		}
		elseif ($key === null)
		{
			// Indexed columns

			if ($this->_as_object)
			{
				foreach ($this as $row)
				{
					$results[] = $row->$value;
				}
			}
			else
			{
				/** @var array<string, mixed> $row */
				foreach ($this as $row)
				{
					$results[] = $row[$value];
				}
			}
		}
		elseif ($value === null)
		{
			// Associative rows

			if ($this->_as_object)
			{
				/** @var object $row */
				foreach ($this as $row)
				{
					/** @var int|string $id  the key column holds numbers or text */
					$id = $row->$key;

					$results[$id] = $row;
				}
			}
			else
			{
				/** @var array<string, int|string> $row  the key column holds numbers or text */
				foreach ($this as $row)
				{
					$results[$row[$key]] = $row;
				}
			}
		}
		else
		{
			// Associative columns

			if ($this->_as_object)
			{
				foreach ($this as $row)
				{
					/** @var int|string $id  the key column holds numbers or text */
					$id = $row->$key;

					$results[$id] = $row->$value;
				}
			}
			else
			{
				/** @var array<string, int|string> $row  the key column holds numbers or text */
				foreach ($this as $row)
				{
					$results[$row[$key]] = $row[$value];
				}
			}
		}

		$this->rewind();

		return $results;
	}

	/**
	 * Return the named column from the current row.
	 *
	 *     // Get the "id" value
	 *     $id = $result->get('id');
	 *
	 * @param   string  $name     column to get
	 * @param   mixed   $default  default value if the column does not exist
	 * @return  mixed
	 */
	public function get(string $name, mixed $default = null): mixed
	{
		$row = $this->current();

		if ($this->_as_object)
		{
			if (isset($row->$name))
				return $row->$name;
		}
		else
		{
			if (is_array($row) AND isset($row[$name]))
				return $row[$name];
		}

		return $default;
	}

	/**
	 * Implements [Countable::count], returns the total number of rows.
	 *
	 *     echo count($result);
	 *
	 * @return  int
	 */
	public function count(): int
	{
		return $this->_total_rows;
	}

	/**
	 * Implements [ArrayAccess::offsetExists], determines if row exists.
	 *
	 *     if (isset($result[10]))
	 *     {
	 *         // Row 10 exists
	 *     }
	 *
	 * @param   int     $offset
	 * @return  bool
	 */
	public function offsetExists(mixed $offset): bool
	{
		return ($offset >= 0 AND $offset < $this->_total_rows);
	}

	/**
	 * Implements [ArrayAccess::offsetGet], gets a given row.
	 *
	 *     $row = $result[10];
	 *
	 * @param   int     $offset
	 * @return  mixed
	 */
	public function offsetGet(mixed $offset): mixed
	{
		// seek() returns nothing, so the offset is checked here
		if ( ! $this->offsetExists($offset))
			return null;

		$this->seek($offset);

		return $this->current();
	}

	/**
	 * Implements [ArrayAccess::offsetSet], throws an error.
	 *
	 * [!!] You cannot modify a database result.
	 *
	 * @param   mixed   $offset
	 * @param   mixed   $value
	 * @return  void
	 * @throws  Kohana_Exception
	 */
	final public function offsetSet(mixed $offset, mixed $value): void
	{
		throw new Kohana_Exception('Database results are read-only');
	}

	/**
	 * Implements [ArrayAccess::offsetUnset], throws an error.
	 *
	 * [!!] You cannot modify a database result.
	 *
	 * @param   int     $offset
	 * @return  void
	 * @throws  Kohana_Exception
	 */
	final public function offsetUnset(mixed $offset): void
	{
		throw new Kohana_Exception('Database results are read-only');
	}

	/**
	 * Implements [Iterator::key], returns the current row number.
	 *
	 *     echo key($result);
	 *
	 * @return  int
	 */
	public function key(): mixed
	{
		return $this->_current_row;
	}

	/**
	 * Implements [Iterator::next], moves to the next row.
	 *
	 *     next($result);
	 *
	 * @return  void
	 */
	public function next(): void
	{
		++$this->_current_row;
	}

	/**
	 * Implements [Iterator::prev], moves to the previous row.
	 *
	 *     prev($result);
	 *
	 * @return  static
	 */
	public function prev(): static
	{
		--$this->_current_row;
		return $this;
	}

	/**
	 * Implements [Iterator::rewind], sets the current row to zero.
	 *
	 *     rewind($result);
	 *
	 * @return  void
	 */
	public function rewind(): void
	{
		$this->_current_row = 0;
	}

	/**
	 * Implements [Iterator::valid], checks if the current row exists.
	 *
	 * [!!] This method is only used internally.
	 *
	 * @return  bool
	 */
	public function valid(): bool
	{
		return $this->offsetExists($this->_current_row);
	}

} // End Database_Result
