<?php
/**
 * Database query builder for WHERE statements. See [Query Builder](/database/query/builder) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Database_Query_Builder_Where extends Database_Query_Builder {

	/**
	 * @var  array<int, array<string, mixed>>  WHERE ...
	 */
	protected array $_where = [];

	/**
	 * @var  array<int, array<int, mixed>>  ORDER BY ...
	 */
	protected array $_order_by = [];

	// LIMIT ...
	protected ?int $_limit = null;

	/**
	 * Alias of and_where()
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  $this
	 */
	public function where(mixed $column, ?string $op, mixed $value): static
	{
		return $this->and_where($column, $op, $value);
	}

	/**
	 * Creates a new "AND WHERE" condition for the query.
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  $this
	 */
	public function and_where(mixed $column, ?string $op, mixed $value): static
	{
		$this->_where[] = ['AND' => [$column, $op, $value]];

		return $this;
	}

	/**
	 * Creates a new "OR WHERE" condition for the query.
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  $this
	 */
	public function or_where(mixed $column, ?string $op, mixed $value): static
	{
		$this->_where[] = ['OR' => [$column, $op, $value]];

		return $this;
	}

	/**
	 * Alias of and_where_open()
	 *
	 * @return  $this
	 */
	public function where_open(): static
	{
		return $this->and_where_open();
	}

	/**
	 * Opens a new "AND WHERE (...)" grouping.
	 *
	 * @return  $this
	 */
	public function and_where_open(): static
	{
		$this->_where[] = ['AND' => '('];

		return $this;
	}

	/**
	 * Opens a new "OR WHERE (...)" grouping.
	 *
	 * @return  $this
	 */
	public function or_where_open(): static
	{
		$this->_where[] = ['OR' => '('];

		return $this;
	}

	/**
	 * Closes an open "WHERE (...)" grouping.
	 *
	 * @return  $this
	 */
	public function where_close(): static
	{
		return $this->and_where_close();
	}

	/**
	 * Closes an open "WHERE (...)" grouping or removes the grouping when it is
	 * empty.
	 *
	 * @return  $this
	 */
	public function where_close_empty(): static
	{
		$group = end($this->_where);

		if ($group AND reset($group) === '(')
		{
			array_pop($this->_where);

			return $this;
		}

		return $this->where_close();
	}

	/**
	 * Closes an open "WHERE (...)" grouping.
	 *
	 * @return  $this
	 */
	public function and_where_close(): static
	{
		$this->_where[] = ['AND' => ')'];

		return $this;
	}

	/**
	 * Closes an open "WHERE (...)" grouping.
	 *
	 * @return  $this
	 */
	public function or_where_close(): static
	{
		$this->_where[] = ['OR' => ')'];

		return $this;
	}

	/**
	 * Applies sorting with "ORDER BY ..."
	 *
	 * @param   mixed   $column     column name or array($column, $alias) or object
	 * @param   string  $direction  direction of sorting
	 * @return  $this
	 */
	public function order_by(mixed $column, ?string $direction = null): static
	{
		$this->_order_by[] = [$column, $direction];

		return $this;
	}

	/**
	 * Return up to "LIMIT ..." results
	 *
	 * @param   int  $number  maximum results to return or null to reset
	 * @return  $this
	 */
	public function limit(?int $number): static
	{
		$this->_limit = $number;

		return $this;
	}

} // End Database_Query_Builder_Where
