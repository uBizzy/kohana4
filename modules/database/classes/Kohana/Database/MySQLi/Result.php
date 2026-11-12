<?php
/**
 * MySQLi database result.   See [Results](/database/results) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query/Result
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Database_MySQLi_Result extends Database_Result {

	protected int $_internal_row = 0;

	/**
	 * @var  mysqli_result  the rows, as the MySQLi extension gives them
	 */
	protected mixed $_result;

	/**
	 * @var  bool  Convert numbers in the rows to strings?
	 */
	protected bool $_stringify = false;

	/**
	 * Sets whether numbers in the rows are converted to strings.
	 *
	 * MySQL returns every value as a string for a normal query, but
	 * integers and floats as numbers for a prepared statement. Converting
	 * them keeps the rows the same in both cases.
	 *
	 * @param   bool  $stringify
	 * @return  $this
	 */
	public function stringify(bool $stringify): static
	{
		$this->_stringify = $stringify;

		return $this;
	}

	/**
	 * @param   mysqli_result  $result
	 * @param   array<int|string, mixed>  $params
	 */
	public function __construct(mixed $result, string $sql, bool|string|object|null $as_object = false, ?array $params = null)
	{
		parent::__construct($result, $sql, $as_object, $params);

		// Find the number of rows in the result
		$this->_total_rows = (int) $result->num_rows;
	}

	public function __destruct()
	{
		// A mysqli result is an object, not a resource
		if (isset($this->_result))
		{
			$this->_result->free();
		}
	}

	public function seek(int $offset): void
	{
		if ($this->offsetExists($offset) AND $this->_result->data_seek($offset))
			$this->_current_row = $this->_internal_row = $offset;
	}

	public function current(): mixed
	{
		if ($this->_current_row !== $this->_internal_row)
		{
			// The driver is not at the row we want: move it there.
			// seek() returns nothing, so the offset is checked first.
			if ( ! $this->offsetExists($this->_current_row))
				return null;

			$this->seek($this->_current_row);
		}

		// Increment internal row for optimization assuming rows are fetched in order
		$this->_internal_row++;

		if ($this->_stringify)
			return $this->_current_stringified();

		if ($this->_as_object === true)
		{
			// Return an stdClass
			return $this->_result->fetch_object();
		}
		elseif (is_string($this->_as_object))
		{
			// Return an object of given class name
			/** @var class-string $class */
			$class = $this->_as_object;

			return $this->_result->fetch_object($class, (array) $this->_object_params);
		}
		else
		{
			// Return an array of the row
			return $this->_result->fetch_assoc();
		}
	}

	/**
	 * Returns the current row with its numbers converted to strings, in
	 * the form that was asked for: array, stdClass or object of a class.
	 *
	 * @return  mixed  the row, or null when there are no more rows
	 */
	protected function _current_stringified(): mixed
	{
		$row = $this->_result->fetch_assoc();

		if ( ! is_array($row))
			return null;

		foreach ($row as $column => $value)
		{
			if (is_int($value) OR is_float($value))
			{
				$row[$column] = (string) $value;
			}
		}

		if ($this->_as_object === true)
		{
			// Return an stdClass
			return (object) $row;
		}

		if (is_string($this->_as_object))
		{
			// Build the object as the database driver does: the columns
			// are set first, then the constructor is called
			if ( ! class_exists($this->_as_object))
				throw new Database_Exception('The class :class for the rows of a result does not exist', [':class' => $this->_as_object]);

			$class  = new ReflectionClass($this->_as_object);
			$object = $class->newInstanceWithoutConstructor();

			foreach ($row as $column => $value)
			{
				$object->$column = $value;
			}

			$class->getConstructor()?->invokeArgs($object, array_values( (array) $this->_object_params));

			return $object;
		}

		// Return an array of the row
		return $row;
	}

}
