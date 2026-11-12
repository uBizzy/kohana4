<?php
/**
 * ORM Validation exceptions.
 *
 * @package    Kohana/ORM
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_ORM_Validation_Exception extends Kohana_Exception {

	/**
   * Array of validation objects
   * @var array<string, mixed>
   */
	protected array $_objects = [];

	/**
   * The alias of the main ORM model this exception was created for
   * @var string
   */
	protected ?string $_alias = null;

	/**
	 * Constructs a new exception for the specified model
	 *
	 * @param  string     $alias       The alias to use when looking for error messages
	 * @param  Validation $object      The Validation object of the model
	 * @param  string     $message     The error message
	 * @param  array<string, mixed>      $values      The array of values for the error message
	 * @param  int    $code        The error code for the exception
	 * @return void
	 */
	public function __construct(string $alias, Validation $object, string $message = 'Failed to validate array', ?array $values = null, int|string $code = 0, ?Throwable $previous = null)
	{
		$this->_alias = $alias;
		$this->_objects['_object'] = $object;
		$this->_objects['_has_many'] = false;

		parent::__construct($message, $values, $code, $previous);
	}

	/**
	 * Adds a Validation object to this exception
	 *
	 *     // The following will add a validation object for a profile model
	 *     // inside the exception for a user model.
	 *     $e->add_object('profile', $validation);
	 *     // The errors array will now look something like this
	 *     // array
	 *     // (
	 *     //   'username' => 'This field is required',
	 *     //   'profile'  => array
	 *     //   (
	 *     //     'first_name' => 'This field is required',
	 *     //   ),
	 *     // );
	 *
	 * @param  string     $alias    The relationship alias from the model
	 * @param  Validation $object   The Validation object to merge
	 * @param  bool|int|string      $has_many The array key to use if this exception can be merged multiple times
	 * @return static
	 */
	public function add_object(string $alias, Validation $object, bool|string|int $has_many = false): static
	{
		// What is known of this alias so far
		/** @var array<int|string, mixed> $entry */
		$entry = (array) ($this->_objects[$alias] ?? []);

		// We will need this when generating errors
		$entry['_has_many'] = ($has_many !== false);

		if ($has_many === true)
		{
			// This is most likely a has_many relationship
			$entry[] = ['_object' => $object];
		}
		elseif ($has_many)
		{
			// This is most likely a has_many relationship
			$entry[$has_many] = ['_object' => $object];
		}
		else
		{
			$entry['_object'] = $object;
		}

		$this->_objects[$alias] = $entry;

		return $this;
	}

	/**
	 * Merges an ORM_Validation_Exception object into the current exception
	 * Useful when you want to combine errors into one array
	 *
	 * @param  ORM_Validation_Exception $object   The exception to merge
	 * @param  bool|int|string                    $has_many The array key to use if this exception can be merged multiple times
	 * @return static
	 */
	public function merge(ORM_Validation_Exception $object, bool|string|int $has_many = false): static
	{
		$alias = $object->alias();

		// What is known of this alias so far
		/** @var array<int|string, mixed> $entry */
		$entry = (array) ($this->_objects[$alias] ?? []);

		// We will need this when generating errors
		$entry['_has_many'] = ($has_many !== false);

		if ($has_many === true)
		{
			// This is most likely a has_many relationship
			$entry[] = $object->objects();
		}
		elseif ($has_many)
		{
			// This is most likely a has_many relationship
			$entry[$has_many] = $object->objects();
		}
		else
		{
			$entry = $object->objects();
		}

		$this->_objects[$alias] = $entry;

		return $this;
	}

	/**
	 * Returns a merged array of the errors from all the Validation objects in this exception
	 *
	 *     // Will load Model_User errors from messages/orm-validation/user.php
	 *     $e->errors('orm-validation');
	 *
	 * @param   string  $directory Directory to load error messages from
	 * @param   bool|string   $translate Translate the message
	 * @return  array<int|string, mixed>
	 * @see generate_errors()
	 */
	public function errors(?string $directory = null, bool|string $translate = true): array
	{
		return $this->generate_errors( (string) $this->_alias, $this->_objects, $directory, $translate);
	}

	/**
	 * Recursive method to fetch all the errors in this exception
	 *
	 * @param  string $alias     Alias to use for messages file
	 * @param  array<int|string, mixed>  $array     Array of Validation objects to get errors from
	 * @param  string $directory Directory to load error messages from
	 * @param  bool|string  $translate Translate the message
	 * @return array<int|string, mixed>
	 */
	protected function generate_errors(string $alias, array $array, ?string $directory, bool|string $translate): array
	{
		$errors = [];

		foreach ($array as $key => $object)
		{
			if (is_array($object))
			{
				$errors[$key] = ($key === '_external')
					// Search for errors in $alias/_external.php
					? $this->generate_errors($alias.'/'.$key, $object, $directory, $translate)
					// Regular models get their own file not nested within $alias
					: $this->generate_errors((string) $key, $object, $directory, $translate);
			}
			elseif ($object instanceof Validation)
			{
				if ($directory === null)
				{
					// Return the raw errors
					$file = null;
				}
				else
				{
					$file = trim($directory.'/'.$alias, '/');
				}

				// Merge in this array of errors
				$errors += $object->errors($file, $translate);
			}
		}

		return $errors;
	}

	/**
	 * Returns the protected _objects property from this exception
	 *
	 * @return array<string, mixed>
	 */
	public function objects(): array
	{
		return $this->_objects;
	}

	/**
	 * Returns the protected _alias property from this exception
	 *
	 * @return string
	 */
	public function alias(): ?string
	{
		return $this->_alias;
	}
} // End Kohana_ORM_Validation_Exception
