<?php
/**
 * Array and variable validation.
 *
 * @package    Kohana
 * @category   Security
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 *
 * @implements ArrayAccess<string, mixed>
 */
class Kohana_Validation implements ArrayAccess {

	/**
	 * Creates a new Validation instance.
	 *
	 * @param   array<int|string, mixed>   $array  array to use for validation
	 * @return  Validation
	 */
	public static function factory(array $array): Validation
	{
		return new Validation($array);
	}

	/**
	 * @var  array<string, mixed>  Bound values
	 */
	protected array $_bound = [];

	/**
	 * @var  array<int|string, array<int, array{0: array<int, mixed>|Closure|string, 1: array<int|string, mixed>}>>  Field rules: field => list of [rule, parameters]
	 */
	protected array $_rules = [];

	/**
	 * @var  array<string, string>  Field labels
	 */
	protected array $_labels = [];

	/**
	 * @var  array<int, string>  Rules that are executed even when the value is empty
	 */
	protected array $_empty_rules = ['not_empty', 'matches'];

	/**
	 * @var  array<string, array{0: string, 1: array<int|string, mixed>|null}>  Error list, field => [rule, parameters]
	 */
	protected array $_errors = [];

	/**
	 * @var  array<int|string, mixed>  Array to validate
	 */
	protected array $_data = [];

	/**
	 * Sets the unique "any field" key and creates an ArrayObject from the
	 * passed array.
	 *
	 * @param   array<int|string, mixed>   $array  array to validate
	 * @return  void
	 */
	public function __construct(array $array)
	{
		$this->_data = $array;
	}

	/**
	 * Throws an exception because Validation is read-only.
	 * Implements ArrayAccess method.
	 *
	 * @throws  Kohana_Exception
	 * @param   string   $offset    key to set
	 * @param   mixed    $value     value to set
	 * @return  void
	 */
	public function offsetSet(mixed $offset, mixed $value): void
	{
		throw new Kohana_Exception('Validation objects are read-only.');
	}

	/**
	 * Checks if key is set in array data.
	 * Implements ArrayAccess method.
	 *
	 * @param   string  $offset key to check
	 * @return  bool    whether the key is set
	 */
	public function offsetExists(mixed $offset): bool
	{
		return isset($this->_data[$offset]);
	}

	/**
	 * Throws an exception because Validation is read-only.
	 * Implements ArrayAccess method.
	 *
	 * @throws  Kohana_Exception
	 * @param   string  $offset key to unset
	 * @return  void
	 */
	public function offsetUnset(mixed $offset): void
	{
		throw new Kohana_Exception('Validation objects are read-only.');
	}

	/**
	 * Gets a value from the array data.
	 * Implements ArrayAccess method.
	 *
	 * @param   string|int  $offset key to return
	 * @return  mixed   value from array
	 */
	public function offsetGet(mixed $offset): mixed
	{
		return $this->_data[$offset];
	}

	/**
	 * Copies the current rules to a new array.
	 *
	 *     $copy = $array->copy($new_data);
	 *
	 * @param   array<int|string, mixed>   $array  new data set
	 * @return  static
	 * @since   3.0.5
	 */
	public function copy(array $array): static
	{
		// Create a copy of the current validation set
		$copy = clone $this;

		// Replace the data set
		$copy->_data = $array;

		return $copy;
	}

	/**
	 * Returns the array of data to be validated.
	 *
	 * @return  array<int|string, mixed>
	 */
	public function data(): array
	{
		return $this->_data;
	}

	/**
	 * Sets or overwrites the label name for a field.
	 *
	 * @param   string  $field  field name
	 * @param   string  $label  label
	 * @return  $this
	 */
	public function label(string $field, string $label): static
	{
		// Set the label for this field
		$this->_labels[$field] = $label;

		return $this;
	}

	/**
	 * Sets labels using an array.
	 *
	 * @param   array<string, string>   $labels list of field => label names
	 * @return  $this
	 */
	public function labels(array $labels): static
	{
		$this->_labels = $labels + $this->_labels;

		return $this;
	}

	/**
	 * Overwrites or appends rules to a field. Each rule will be executed once.
	 * All rules must be string names of functions method names. Parameters must
	 * match the parameters of the callback function exactly
	 *
	 * Aliases you can use in callback parameters:
	 * - :validation - the validation object
	 * - :field - the field name
	 * - :value - the value of the field
	 *
	 *     // The "username" must not be empty and have a minimum length of 4
	 *     $validation->rule('username', 'not_empty')
	 *                ->rule('username', 'min_length', array(':value', 4));
	 *
	 *     // The "password" field must match the "password_repeat" field
	 *     $validation->rule('password', 'matches', array(':validation', 'password', 'password_repeat'));
	 *
	 *     // Using closure (anonymous function)
	 *     $validation->rule('index',
	 *         function(Validation $array, $field, $value)
	 *         {
	 *             if ($value > 6 AND $value < 10)
	 *             {
	 *                 $array->error($field, 'custom');
	 *             }
	 *         }
	 *         , array(':validation', ':field', ':value')
	 *     );
	 *
	 * [!!] Errors must be added manually when using closures!
	 *
	 * @param   string|true           $field  field name, or true to apply the rule to every field
	 * @param   string|array<int, mixed>|Closure  $rule   valid PHP callback or closure
	 * @param   array<int, mixed>       $params extra parameters for the rule
	 * @return  $this
	 */
	public function rule(string|true $field, string|array|Closure $rule, ?array $params = null): static
	{
		// Default to array(':value')
		$params ??= [':value'];

		if ($field !== true AND ! isset($this->_labels[$field]))
		{
			// Set the field label to the field name
			$this->_labels[$field] = $field;
		}

		// Store the rule and params for this rule
		$this->_rules[$field][] = [$rule, $params];

		return $this;
	}

	/**
	 * Add rules using an array.
	 *
	 * @param   string|true  $field  field name, or true to apply the rules to every field
	 * @param   array<int, array{0: array<int, mixed>|Closure|string, 1?: array<int, mixed>|null}>   $rules  list of callbacks
	 * @return  $this
	 */
	public function rules(string|true $field, array $rules): static
	{
		foreach ($rules as $rule)
		{
			$this->rule($field, $rule[0], $rule[1] ?? null);
		}

		return $this;
	}

	/**
	 * Bind a value to a parameter definition.
	 *
	 *     // This allows you to use :model in the parameter definition of rules
	 *     $validation->bind(':model', $model)
	 *         ->rule('status', 'valid_status', array(':model'));
	 *
	 * @param   array<string, mixed>|string  $key    variable name or an array of variables
	 * @param   mixed   $value  value
	 * @return  $this
	 */
	public function bind(string|array $key, mixed $value = null): static
	{
		if (is_array($key))
		{
			foreach ($key as $name => $value)
			{
				$this->_bound[$name] = $value;
			}
		}
		else
		{
			$this->_bound[$key] = $value;
		}

		return $this;
	}

	/**
	 * Executes all validation rules. This should
	 * typically be called within an if/else block.
	 *
	 *     if ($validation->check())
	 *     {
	 *          // The data is valid, do something here
	 *     }
	 *
	 * @return  bool
	 */
	public function check(): bool
	{
		if (Kohana::$profiling === true)
		{
			// Start a new benchmark, named after the fields that have rules.
			// Only names of fields: values are never part of a benchmark.
			$benchmark = Profiler::start('Validation', implode(', ', array_keys($this->_rules)) ?: 'no rules');
		}

		// New data set
		$this->_errors = [];

		// Store the original data because this class should not modify it post-validation
		$original = $this->_data;

		// The data of every expected field, and the rules of each field
		list($this->_data, $rules) = $this->_fields_and_rules();

		// Bind the validation object to :validation
		$this->bind(':validation', $this);
		// Bind the data to :data
		$this->bind(':data', $this->_data);

		// Execute the rules
		foreach ($rules as $field => $set)
		{
			// Get the field value
			$value = $this->offsetGet($field);

			// Bind the field name and value to :field and :value respectively
			$this->bind([
				':field' => $field,
				':value' => $value,
			]);

			foreach ($set as list($rule, $params))
			{
				foreach ($params as $key => $param)
				{
					if (is_string($param) AND array_key_exists($param, $this->_bound))
					{
						// Replace with bound value
						$params[$key] = $this->_bound[$param];
					}
				}

				list($passed, $error_name) = $this->_run_rule($rule, $params);

				// Ignore return values from rules when the field is empty
				if ( ! in_array($this->_valid_rule_name($rule), $this->_empty_rules, true) AND ! Valid::not_empty($value))
					continue;

				if ($passed === false AND $error_name !== false)
				{
					// Add the rule to the errors
					$this->error( (string) $field, $error_name, $params);

					// This field has an error, stop executing rules
					break;
				}
				elseif (isset($this->_errors[$field]))
				{
					// The callback added the error manually, stop checking rules
					break;
				}
			}
		}

		// Unbind all the automatic bindings to avoid memory leaks.
		unset($this->_bound[':validation']);
		unset($this->_bound[':data']);
		unset($this->_bound[':field']);
		unset($this->_bound[':value']);

		// Restore the data to its original form
		$this->_data = $original;

		if (isset($benchmark))
		{
			// Stop benchmarking
			Profiler::stop($benchmark);

			Profiler::note($benchmark, $this->_errors ? 'failed: '.implode(', ', array_keys($this->_errors)) : 'passed');
		}

		return empty($this->_errors);
	}

	/**
	 * Part of [Validation::check]: collects the data of every expected field
	 * (the ones in the data and the ones that have a label) and the rules to
	 * run on each, adding the rules registered for all fields (field true).
	 *
	 * @return  array{0: array<int|string, mixed>, 1: array<int|string, array<int, array{0: array<int, mixed>|Closure|string, 1: array<int|string, mixed>}>>}  list of: data (field => value), rules (field => list of [rule, params])
	 */
	protected function _fields_and_rules(): array
	{
		$data = [];

		// Get a list of the expected fields
		$expected = array_unique(array_merge(array_keys($this->_data), array_keys($this->_labels)));

		// Import the rules locally
		$rules = $this->_rules;

		// Rules registered with rule(true, ...) apply to every field
		$for_all = $rules[true] ?? [];

		foreach ($expected as $field)
		{
			// Use the submitted value or null if no data exists
			$data[$field] = Arr::get($this, $field);

			if ($for_all)
			{
				// Append the rules for all fields to this field's own rules
				$rules[$field] = array_merge($rules[$field] ?? [], $for_all);
			}
		}

		// They have been copied to each field: remove the original entry
		unset($rules[true]);

		return [$data, $rules];
	}

	/**
	 * Returns the plain [Valid] method name of a rule, so "matches",
	 * "Valid::matches" and array('Valid', 'matches') are seen as the same
	 * rule. Any other kind of rule returns null.
	 *
	 * @param   string|array<int, mixed>|Closure  $rule  the rule
	 * @return  string|null
	 */
	protected function _valid_rule_name(string|array|Closure $rule): ?string
	{
		if (is_array($rule) AND ($rule[0] ?? null) === 'Valid' AND is_string($rule[1] ?? null))
			return $rule[1];

		if (is_string($rule))
			return str_starts_with($rule, 'Valid::') ? substr($rule, 7) : $rule;

		return null;
	}

	/**
	 * Part of [Validation::check]: runs a single rule. A rule can be:
	 *
	 * - an array callback, e.g. array($object, 'method') or array(':model', 'method')
	 * - a closure
	 * - the name of a [Valid] method, e.g. "not_empty"
	 * - the name of a function, e.g. "is_numeric"
	 * - a static method written as a string, e.g. "Model_User::unique"
	 *
	 * @param   string|array<int, mixed>|Closure  $rule    the rule
	 * @param   array<int|string, mixed>                 $params  arguments for the rule, bound values already replaced
	 * @return  array{0: mixed, 1: string|false}  list of: what the rule returned, the name to report an
	 *                 error under (false when the rule reports errors itself)
	 */
	protected function _run_rule(string|array|Closure $rule, array $params): array
	{
		// Arguments are passed by position, never by name
		$args = array_values($params);

		if (is_array($rule))
		{
			// Allows rule('field', array(':model', 'some_rule'));
			if (is_string($rule[0]) AND array_key_exists($rule[0], $this->_bound))
			{
				// Replace with bound value
				$rule[0] = $this->_bound[$rule[0]];
			}

			// The error is named after the method
			return [$this->_callable($rule)(...$args), is_string($rule[1] ?? null) ? $rule[1] : false];
		}

		if ( ! is_string($rule))
		{
			// A closure adds its own errors through the :validation object
			return [call_user_func_array($rule, $args), false];
		}

		if (method_exists('Valid', $rule))
		{
			// Valid rules are not run on a null value, except "not_empty"
			if (($args[0] ?? null) === null AND $rule !== 'not_empty')
				return [true, $rule];

			return [Valid::$rule(...$args), $rule];
		}

		// A plain function, or a static method written as "Class::method"
		return [$this->_callable($rule)(...$args), $rule];
	}

	/**
	 * Makes sure that a rule can be called, so that a rule that was
	 * misspelled is reported by its name.
	 *
	 * @param   mixed  $rule  function name, "Class::method" or [object, method]
	 * @return  callable
	 * @throws  Kohana_Exception  if there is no such function or method
	 */
	protected function _callable(mixed $rule): callable
	{
		if ( ! is_callable($rule))
		{
			throw new Kohana_Exception('The validation rule :rule cannot be called', [
				':rule' => is_array($rule) ? (is_string($rule[1] ?? null) ? $rule[1] : '') : (is_string($rule) ? $rule : ''),
			]);
		}

		return $rule;
	}

	/**
	 * Add an error to a field.
	 *
	 * @param   string  $field  field name
	 * @param   string  $error  error message
	 * @param   array<int|string, mixed>   $params
	 * @return  $this
	 */
	public function error(string $field, string $error, ?array $params = null): static
	{
		$this->_errors[$field] = [$error, $params];

		return $this;
	}

	/**
	 * Returns the error messages. If no file is specified, the error message
	 * will be the name of the rule that failed. When a file is specified, the
	 * message will be loaded from "field/rule", or if no rule-specific message
	 * exists, "field/default" will be used. If neither is set, the returned
	 * message will be "file/field/rule".
	 *
	 * By default all messages are translated using the default language.
	 * A string can be used as the second parameter to specified the language
	 * that the message was written in.
	 *
	 *     // Get errors from messages/forms/login.php
	 *     $errors = $Validation->errors('forms/login');
	 *
	 * @uses    Kohana::message
	 * @param   string  $file       file to load error messages from
	 * @param   bool|string   $translate  translate the message
	 * @return  array<string, mixed>
	 */
	public function errors(?string $file = null, bool|string $translate = true): array
	{
		if ($file === null)
		{
			// Return the error list
			return $this->_errors;
		}

		// Create a new message list
		$messages = [];

		// A list of values as one text, for use in a message
		$text = fn (array $list): string => implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : '', Arr::flatten($list)));

		foreach ($this->_errors as $field => list($error, $params))
		{
			// Start the translation values list
			$values = [
				':field' => $this->_translate($this->_labels[$field], $translate),
				':value' => Arr::get($this, $field),
			];

			if (is_array($values[':value']))
			{
				// All values must be strings
				$values[':value'] = $text($values[':value']);
			}

			foreach ( (array) $params as $key => $value)
			{
				if (is_array($value))
				{
					// All values must be strings
					$value = $text($value);
				}
				elseif (is_object($value))
				{
					// Objects cannot be used in message files
					continue;
				}

				// Check if a label for this parameter exists
				if ((is_string($value) OR is_int($value)) AND isset($this->_labels[$value]))
				{
					// Make the value into its label
					$value = $this->_translate($this->_labels[$value], $translate);
				}

				// Add each parameter as a numbered value, starting from 1
				$values[':param'.( (int) $key + 1)] = $value;
			}

			$message = $this->_error_message($file, $field, $error);

			if ($translate)
			{
				// Translate the message, a string in $translate is the language it was written in
				$message = I18n::get([$message, $values], null, is_string($translate) ? $translate : null);
			}
			else
			{
				// Do not translate, just replace the values
				$message = strtr($message, $values);
			}

			// Set the message for this field
			$messages[$field] = $message;
		}

		return $messages;
	}

	/**
	 * Part of [Validation::errors]: translates a label, unless translation is off.
	 *
	 * @param   string       $text       text to translate
	 * @param   bool|string  $translate  false for no translation, true to translate,
	 *                                   or the language the text was written in
	 * @return  string
	 * @uses    I18n::get
	 */
	protected function _translate(string $text, bool|string $translate): string
	{
		if ( ! $translate)
			return $text;

		return I18n::get($text, null, is_string($translate) ? $translate : null);
	}

	/**
	 * Part of [Validation::errors]: finds the message for a failed rule.
	 * The first one that exists is used:
	 *
	 * 1. "<field>.<rule>" in the given message file
	 * 2. "<field>.default" in the given message file
	 * 3. "<rule>" in the given message file
	 * 4. "<rule>" in the "validation" message file
	 *
	 * When none exists, the path "<file>.<field>.<rule>" is returned so the
	 * missing message is easy to spot.
	 *
	 * @param   string  $file   message file
	 * @param   string  $field  field name
	 * @param   string  $error  name of the failed rule
	 * @return  string
	 * @uses    Kohana::message
	 */
	protected function _error_message(string $file, string $field, string $error): string
	{
		$candidates = [
			[$file, "{$field}.{$error}"],
			[$file, "{$field}.default"],
			[$file, $error],
			['validation', $error],
		];

		foreach ($candidates as list($message_file, $path))
		{
			$message = Kohana::message($message_file, $path);

			// A message must be a non-empty string
			if ($message AND is_string($message))
				return $message;
		}

		// No message exists, display the path expected
		return "{$file}.{$field}.{$error}";
	}

}
