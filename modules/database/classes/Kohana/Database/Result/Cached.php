<?php
/**
 * Object used for caching the results of select queries.  See [Results](/database/results#select-cached) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query/Result
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Database_Result_Cached extends Database_Result {

	/**
	 * @var  array<int, mixed>  the rows
	 */
	protected mixed $_result;

	/**
	 * @param   array<int|string, mixed>  $params
	 * @param   array<int, mixed>  $result
	 */
	public function __construct(array $result, string $sql, bool|string|object|null $as_object = null, ?array $params = null)
	{
		parent::__construct($result, $sql, $as_object, $params);

		// Find the number of rows in the result
		$this->_total_rows = count($result);
	}

	public function __destruct()
	{
		// Cached results do not use resources
	}

	public function cached(): Database_Result_Cached
	{
		/** @var Database_Result_Cached $this  this object is of that class or extends it */
		return $this;
	}

	public function seek(int $offset): void
	{
		if ($this->offsetExists($offset))
			$this->_current_row = $offset;
	}

	public function current(): mixed
	{
		// Return an array of the row
		return $this->valid() ? $this->_result[$this->_current_row] : null;
	}

} // End Database_Result_Cached
