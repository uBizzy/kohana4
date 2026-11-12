<?php
/**
 * Database expressions can be used to add unescaped SQL fragments to a
 * [Database_Query_Builder] object.
 *
 * For example, you can use an expression to generate a column alias:
 *
 *     // SELECT CONCAT(first_name, last_name) AS full_name
 *     $query = DB::select(array(DB::expr('CONCAT(first_name, last_name)'), 'full_name')));
 *
 * More examples are available on the [Query Builder](database/query/builder#database-expressions) page
 *
 * @package    Kohana/Database
 * @category   Base
 * 
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Database_Expression {

	/**
	 * @var  array<string, mixed>  Unquoted parameters
	 */
	protected array $_parameters = [];

	// Raw expression string
	protected string $_value = '';

	/**
	 * Sets the expression string.
	 *
	 *     $expression = new Database_Expression('COUNT(users.id)');
	 *
	 * @param   string  $value      raw SQL expression string
	 * @param   array<string, mixed>   $parameters unquoted parameter values
	 * @return  void
	 */
	public function __construct(string $value, array $parameters = [])
	{
		// Set the expression string
		$this->_value = $value;
		$this->_parameters = $parameters;
	}

	/**
	 * Bind a variable to a parameter.
	 *
	 * @param   string  $param  parameter key to replace
	 * @param   mixed   $var    variable to use
	 * @return  $this
	 */
	public function bind(string $param, mixed & $var): static
	{
		$this->_parameters[$param] =& $var;

		return $this;
	}

	/**
	 * Set the value of a parameter.
	 *
	 * @param   string  $param  parameter key to replace
	 * @param   mixed   $value  value to use
	 * @return  $this
	 */
	public function param(string $param, mixed $value): static
	{
		$this->_parameters[$param] = $value;

		return $this;
	}

	/**
	 * Add multiple parameter values.
	 *
	 * @param   array<string, mixed>   $params list of parameter values
	 * @return  $this
	 */
	public function parameters(array $params): static
	{
		$this->_parameters = $params + $this->_parameters;

		return $this;
	}

	/**
	 * Get the expression value as a string.
	 *
	 *     $sql = $expression->value();
	 *
	 * @return  string
	 */
	public function value(): string
	{
		return $this->_value;
	}

	/**
	 * Return the value of the expression as a string.
	 *
	 *     echo $expression;
	 *
	 * @return  string
	 * @uses    Database_Expression::value
	 */
	public function __toString(): string
	{
		return $this->value();
	}

	/**
	 * Compile the SQL expression and return it. Replaces any parameters with
	 * their given values.
	 *
	 * @param   Database|string|null  $db  Database instance or name of instance
	 * @return  string
	 */
	public function compile(Database|string|null $db = null): string
	{
		if ( ! is_object($db))
		{
			// Get the database instance
			$db = Database::instance($db);
		}

		$value = $this->value();

		if ( ! empty($this->_parameters))
		{
			// Quote all of the parameter values
			$params = array_map([$db, 'quote'], $this->_parameters);

			// Replace the values in the expression
			$value = strtr($value, $params);
		}

		return $value;
	}

} // End Database_Expression
