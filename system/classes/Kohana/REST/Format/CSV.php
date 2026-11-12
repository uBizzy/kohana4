<?php
/**
 * Formats the body of a REST response as CSV: a table as text, one row
 * on each line, that every spreadsheet program opens.
 *
 *     // name,email
 *     // Ana,ana@example.com
 *     // Bea,bea@example.com
 *     $csv = REST_Format_CSV::encode([
 *         ['name' => 'Ana', 'email' => 'ana@example.com'],
 *         ['name' => 'Bea', 'email' => 'bea@example.com'],
 *     ]);
 *
 * - A list of rows becomes the rows; one row alone becomes one row.
 *   Data is a list of rows when every item of it is a list itself.
 * - When the rows have names for their values, the first line holds
 *   those names, and every row is written in that order.
 * - A value that is a list itself is written as JSON.
 *
 * [!!] A spreadsheet program runs a cell that starts with "=", "+", "-"
 * or "@" as a formula, which a visitor can use to make it do things
 * with the data of whoever opens the file. Such text gets a "'" in
 * front, which the program shows as plain text. Numbers stay numbers.
 *
 * @package        Kohana\REST
 *
 * @copyright  (c) since 2026 Konine Team
 * @license        https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_REST_Format_CSV extends REST_Format {

	/**
	 * @var  string  The character between two values: "," or, where the
	 *               comma is the decimal sign, ";"
	 */
	public static string $delimiter = ',';

	/**
	 * @var  bool  Start with the mark that tells Excel the text is UTF-8.
	 *             Without it Excel shows "é" as "Ã©"; other programs
	 *             do not need it.
	 */
	public static bool $bom = false;

	/**
	 * Format function
	 *
	 * @throws REST_Exception
	 *
	 * @return string
	 */
	public function format(): string
	{
		return static::encode(is_iterable($this->_body) ? $this->_body : []);
	}

	/**
	 * Writes data as CSV.
	 *
	 * @param   iterable<mixed>  $data  a list of rows, or one row
	 * @return  string
	 */
	public static function encode(iterable $data): string
	{
		$rows    = static::_rows($data);
		$columns = static::_columns($rows);
		$lines   = [];

		if (array_filter($columns, 'is_string'))
		{
			// The values have names: those are the first line
			$lines[] = array_map(fn ($column) => static::_cell($column), $columns);
		}

		foreach ($rows as $row)
		{
			$lines[] = array_map(fn ($column) => static::_cell($row[$column] ?? null), $columns);
		}

		$out = fopen('php://temp', 'r+');

		if ($out === false)
			throw new REST_Exception('Unable to write the CSV');

		foreach ($lines as $line)
		{
			// Quotes inside a value are doubled, nothing else is special
			fputcsv($out, $line, static::$delimiter, '"', '', "\r\n");
		}

		rewind($out);

		$csv = (string) stream_get_contents($out);

		fclose($out);

		return (static::$bom ? "\xEF\xBB\xBF" : '').$csv;
	}

	/**
	 * Returns the rows of the data. When every item is a list or an
	 * object, the items are the rows; otherwise the data is one row
	 * (which may hold a list as one of its values).
	 *
	 * @param   iterable<mixed>  $data  the data
	 * @return  array<int, array<int|string, mixed>>
	 */
	protected static function _rows(iterable $data): array
	{
		$data = is_array($data) ? $data : iterator_to_array($data);
		$rows = [];

		foreach ($data as $item)
		{
			if (is_array($item))
			{
				$rows[] = $item;
			}
			elseif ($item instanceof Traversable)
			{
				$rows[] = iterator_to_array($item);
			}
			elseif (is_object($item))
			{
				$rows[] = get_object_vars($item);
			}
			else
			{
				// A value among the items: the data is one row
				return [$data];
			}
		}

		return $rows;
	}

	/**
	 * Returns the names of the columns: every key of every row, in the
	 * order they are first seen. Rows need not have the same keys.
	 *
	 * @param   array<int, array<int|string, mixed>>  $rows  the rows
	 * @return  array<int, int|string>
	 */
	protected static function _columns(array $rows): array
	{
		$columns = [];

		foreach ($rows as $row)
		{
			$columns += array_fill_keys(array_keys($row), true);
		}

		return array_keys($columns);
	}

	/**
	 * Returns a value as the text of a cell.
	 *
	 * @param   mixed  $value  the value
	 * @return  string
	 */
	protected static function _cell(mixed $value): string
	{
		if (is_bool($value))
			return $value ? '1' : '0';

		if ($value === null OR is_int($value) OR is_float($value))
			return (string) $value;

		if ( ! is_string($value))
		{
			// A list inside a row
			$value = (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
		}

		// Text that a spreadsheet would run as a formula is marked as text
		if ( ! is_numeric($value) AND preg_match('/^[=+\-@\t\r]/', $value))
			return "'".$value;

		return $value;
	}

}
