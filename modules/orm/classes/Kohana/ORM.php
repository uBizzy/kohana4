<?php
/**
 * [Object Relational Mapping][ref-orm] (ORM) is a method of abstracting database
 * access to standard PHP calls. All table rows are represented as model objects,
 * with object properties representing row data. ORM in Kohana generally follows
 * the [Active Record][ref-act] pattern.
 *
 * [ref-orm]: http://wikipedia.org/wiki/Object-relational_mapping
 * [ref-act]: http://wikipedia.org/wiki/Active_record
 *
 * @package    Kohana/ORM
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_ORM extends Model implements Serializable {

	/**
	 * Stores column information for ORM models
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	protected static array $_column_cache = [];

	/**
	 * Initialization storage for ORM models
	 * @var array<string, array<string, mixed>>
	 */
	protected static array $_init_cache = [];

	/**
	 * @var  array<int, string>  Names of the properties that are stored when a model is serialized
	 */
	protected static array $_serialized_properties = [
		'_primary_key_value', '_object', '_changed', '_loaded', '_saved', '_sorting', '_original_values',
	];

	/**
	 * Returns the state of the model for serialize(): its values and what
	 * has changed, not its database connection or table description.
	 *
	 *     $string = serialize($model);
	 *
	 * @return  array<string, mixed>
	 */
	public function __serialize(): array
	{
		$data = [];

		foreach (ORM::$_serialized_properties as $name)
		{
			$data[$name] = $this->{$name};
		}

		return $data;
	}

	/**
	 * Restores a model from unserialize(). Only the properties that
	 * [ORM::__serialize] stores are accepted, so serialized data cannot be
	 * used to set any other property of the model.
	 *
	 * @param   array<string, mixed>  $data  state returned by __serialize()
	 * @return  void
	 */
	public function __unserialize(array $data): void
	{
		// Initialize model
		$this->_initialize();

		foreach (ORM::$_serialized_properties as $name)
		{
			if (array_key_exists($name, $data))
			{
				$this->{$name} = $data[$name];
			}
		}

		if ($this->_reload_on_wakeup === true)
		{
			// Reload the object
			$this->reload();
		}
	}

	/**
	 * Creates and returns a new model.
	 * Model name must be passed with its' original casing, e.g.
	 *
	 *    $model = ORM::factory('User_Token');
	 *
	 * @chainable
	 * @param   string  $model  Model name
	 * @param   mixed   $id     Parameter for find()
	 * @return  ORM
	 */
	public static function factory(string $model, mixed $id = null): ORM
	{
		/** @var class-string<ORM> $model  the model class */
		$model = 'Model_'.$model;

		return new $model($id);
	}

	/**
	 * "Has one" relationships. A model may leave values out: they are
	 * filled in when it starts (see _init_relations).
	 * @var array<string, array<string, string>>
	 */
	protected array $_has_one = [];

	/**
	 * "Belongs to" relationships, completed like $_has_one
	 * @var array<string, array<string, string>>
	 */
	protected array $_belongs_to = [];

	/**
	 * "Has many" relationships, completed like $_has_one
	 * @var array<string, array<string, string|bool|null>>  model, foreign_key, through, far_key and the flag "update"
	 */
	protected array $_has_many = [];

	/**
	 * Relationships that should always be joined
	 * @var array<int, string>
	 */
	protected array $_load_with = [];

	/**
	 * Validation object created before saving/updating
	 * @var Validation
	 */
	protected ?Validation $_validation = null;

	/**
	 * Properties of current object.
	 * @var array<string, mixed>
	 */
	protected array $_object = [];

	/**
	 * @var array<string, string>
	 */
	protected array $_changed = [];

	/**
	 * @var array<string, mixed>
	 */
	protected array $_original_values = [];

	/**
	 * @var array<string, mixed>  related models that were loaded
	 */
	protected array $_related = [];

	/**
	 * @var bool
	 */
	protected bool $_valid = false;

	/**
	 * @var bool
	 */
	protected bool $_loaded = false;

	/**
	 * @var bool
	 */
	protected bool $_saved = false;

	/**
	 * @var array<string, string>
	 */
	protected array $_sorting = [];

	/**
	 * Foreign key suffix
	 * @var string
	 */
	protected string $_foreign_key_suffix = '_id';

	/**
	 * Model name
	 * @var string
	 */
	protected ?string $_object_name = null;

	/**
	 * Plural model name
	 * @var string
	 */
	protected ?string $_object_plural = null;

	/**
	 * Table name
	 * @var string
	 */
	protected ?string $_table_name = null;

	/**
	 * Table columns
	 * @var array<string, array<string, mixed>>|null
	 */
	protected ?array $_table_columns = null;

	/**
	 * Auto-update columns for updates
	 * @var array{column: string, format: string|true}|null
	 */
	protected ?array $_updated_column = null;

	/**
	 * Auto-update columns for creation
	 * @var array{column: string, format: string|true}|null
	 */
	protected ?array $_created_column = null;

	/**
	 * Auto-serialize and unserialize columns on get/set
	 * @var array<int, string>
	 */
	protected array $_serialize_columns = [];

	/**
	 * Table primary key
	 * @var string
	 */
	protected string $_primary_key = 'id';

	/**
	 * Primary key value
	 * @var mixed
	 */
	protected mixed $_primary_key_value = null;

	/**
	 * Model configuration, table names plural?
	 * @var bool
	 */
	protected bool $_table_names_plural = true;

	/**
	 * Model configuration, reload on wakeup?
	 * @var bool
	 */
	protected bool $_reload_on_wakeup = true;

	/**
	 * Database Object
	 * @var Database|null  set when the model starts, from [ORM::$_db_group]
	 */
	protected ?Database $_db = null;

	/**
	 * Database config group
	 * @var String
	 */
	protected ?string $_db_group = null;

	/**
	 * Database methods applied
	 * @var array<int|string, mixed>
	 */
	protected array $_db_applied = [];

	/**
	 * Database methods pending
	 * @var array<int, array{name: string, args: array<int|string, mixed>}>
	 */
	protected array $_db_pending = [];

	/**
	 * Reset builder
	 * @var bool
	 */
	protected bool $_db_reset = true;

	/**
	 * Database query builder: a select, update or delete query
	 * @var Database_Query_Builder_Select|Database_Query_Builder_Update|Database_Query_Builder_Delete|null
	 */
	protected ?Database_Query_Builder $_db_builder = null;

	/**
	 * With calls already applied
	 * @var array<string, mixed>
	 */
	protected array $_with_applied = [];

	/**
	 * Data to be loaded into the model from a database call cast
	 * @var array<string, mixed>
	 */
	protected array $_cast_data = [];

	/**
	 * The message filename used for validation errors. Defaults to `ORM::$_object_name`.
	 *
	 * @var string
	 */
	protected ?string $_errors_filename = null;

	/**
	 * List of behaviors
	 * @var array<int|string, ORM_Behavior>
	 */
	protected array $_behaviors = [];

	/**
	 * List of private columns that will not appear in array or object
	 * @var array<int, string>|null
	 */
	protected ?array $_private_columns = [];

	/**
	 * Constructs a new model and loads a record if given
	 *
	 * @param   mixed $id Parameter for find or object to load
	 */
	public function __construct(mixed $id = null)
	{
		$this->_initialize();

		// Invoke all behaviors
		foreach ($this->_behaviors as $behavior)
		{
			if (( ! $behavior->on_construct($this, $id)) OR $this->_loaded)
				return;
		}

		if ($id !== null)
		{
			if (is_array($id))
			{
				foreach ($id as $column => $value)
				{
					// Passing an array of column => values
					$this->where($column, '=', $value);
				}

				$this->find();
			}
			else
			{
				// Passing the primary key
				$this->where($this->_object_name.'.'.$this->_primary_key, '=', $id)->find();
			}
		}
		elseif ( ! empty($this->_cast_data))
		{
			// Load preloaded data from a database call cast
			$this->_load_values($this->_cast_data);

			$this->_cast_data = [];
		}
	}

	/**
	 * Prepares the model database connection, determines the table name,
	 * and loads column information.
	 *
	 * @return void
	 */
	protected function _initialize(): void
	{
		// Set the object name if none predefined
		if (empty($this->_object_name))
		{
			$this->_object_name = strtolower(substr(get_class($this), 6));
		}

		// The names and relations are the same for every object of a model:
		// they are worked out once and kept
		ORM::$_init_cache[$this->_object_name] ??= $this->_init_names() + $this->_init_relations();

		// Assign initialized properties to the current object
		foreach (ORM::$_init_cache[$this->_object_name] as $property => $value)
		{
			$this->{$property} = $value;
		}

		// Load column information
		$this->reload_columns();

		// Clear initial model state
		$this->clear();

		// Create the behaviors classes
		foreach ($this->behaviors() as $behavior => $behavior_config)
		{
			if ( ! is_object($behavior_config))
			{
				$this->_behaviors[$behavior] = ORM_Behavior::factory($behavior, $behavior_config);
			}
		}
	}

	/**
	 * Part of [ORM::_initialize]: works out the values of the properties
	 * that the model class left empty: plural name, error file, database
	 * and table name.
	 *
	 * @return  array<string, mixed>  property name => value
	 */
	protected function _init_names(): array
	{
		$init = [];

		// Set the object plural name if none predefined
		if ( ! isset($this->_object_plural))
		{
			$init['_object_plural'] = Inflector::plural( (string) $this->_object_name);
		}

		if ( ! $this->_errors_filename)
		{
			$init['_errors_filename'] = $this->_object_name;
		}

		if ( ! is_object($this->_db))
		{
			// Get database instance
			$init['_db'] = Database::instance($this->_db_group);
		}

		if (empty($this->_table_name))
		{
			// Table name is the same as the object name...
			$init['_table_name'] = $this->_object_name;

			if ($this->_table_names_plural === true)
			{
				// ...or its plural
				$init['_table_name'] = $init['_object_plural'] ?? $this->_object_plural;
			}
		}

		return $init;
	}

	/**
	 * Part of [ORM::_initialize]: completes the belongs_to, has_one and
	 * has_many definitions of the model with their default "model",
	 * "foreign_key", "through" and "far_key" values.
	 *
	 * @return  array<string, array<string, array<string, mixed>>>  with the keys "_belongs_to", "_has_one" and "_has_many"
	 */
	protected function _init_relations(): array
	{
		$relations = ['_belongs_to' => [], '_has_one' => [], '_has_many' => []];

		// Turns an alias such as "user_token" into the model name "User_Token"
		$model_name = fn (string $alias): string => str_replace(' ', '_', ucwords(str_replace('_', ' ', $alias)));

		foreach ($this->_belongs_to as $alias => $details)
		{
			$relations['_belongs_to'][$alias] = $details + [
				'model'       => $model_name($alias),
				'foreign_key' => $alias.$this->_foreign_key_suffix,
			];
		}

		foreach ($this->_has_one as $alias => $details)
		{
			$relations['_has_one'][$alias] = $details + [
				'model'       => $model_name($alias),
				'foreign_key' => $this->_object_name.$this->_foreign_key_suffix,
			];
		}

		foreach ($this->_has_many as $alias => $details)
		{
			$relations['_has_many'][$alias] = $details + [
				'model'       => $model_name(Inflector::singular($alias)),
				'foreign_key' => $this->_object_name.$this->_foreign_key_suffix,
				'through'     => null,
				'far_key'     => Inflector::singular($alias).$this->_foreign_key_suffix,
			];
		}

		return $relations;
	}

	/**
	 * Initializes validation rules, and labels
	 *
	 * @return void
	 */
	protected function _validation(): void
	{
		// Build the validation object with its rules
		$this->_validation = Validation::factory($this->_object)
			->bind(':model', $this)
			->bind(':original_values', $this->_original_values)
			->bind(':changed', $this->_changed);

		foreach ($this->rules() as $field => $rules)
		{
			/** @var array<int, array{0: array<int, mixed>|Closure|string, 1?: array<int, mixed>|null}> $rules  list of [rule, parameters] */
			$this->_validation->rules($field, $rules);
		}

		// Use column names by default for labels
		$columns = array_keys($this->table_columns());

		// Merge user-defined labels
		$labels = array_merge(array_combine($columns, $columns), $this->labels());

		foreach ($labels as $field => $label)
		{
			$this->_validation->label($field, $label);
		}
	}

	/**
	 * Reload column definitions.
	 *
	 * @chainable
	 * @param   bool $force Force reloading
	 * @return  static
	 */
	public function reload_columns(bool $force = false): static
	{
		if ($force === true OR empty($this->_table_columns))
		{
			if (isset(ORM::$_column_cache[$this->_object_name]))
			{
				// Use cached column information
				$this->_table_columns = ORM::$_column_cache[$this->_object_name];
			}
			else
			{
				// Grab column information from database
				$this->_table_columns = $this->list_columns();

				// Load column cache
				ORM::$_column_cache[$this->_object_name] = $this->_table_columns;
			}
		}

		return $this;
	}

	/**
	 * Unloads the current object and clears the status.
	 *
	 * @chainable
	 * @return static
	 */
	public function clear(): static
	{
		// Create an array with all the columns set to null
		$values = array_fill_keys(array_keys($this->table_columns()), null);

		// Replace the object and reset the object status
		$this->_object = $this->_changed = $this->_related = $this->_original_values = [];

		// Replace the current object with an empty one
		$this->_load_values($values);

		// Reset primary key
		$this->_primary_key_value = null;

		// Reset the loaded state
		$this->_loaded = false;

		$this->reset();

		return $this;
	}

	/**
	 * Reloads the current object from the database.
	 *
	 * @chainable
	 * @return static
	 */
	public function reload(): static
	{
		$primary_key = $this->pk();

		// Replace the object and reset the object status
		$this->_object = $this->_changed = $this->_related = $this->_original_values = [];

		// Only reload the object if we have one to reload
		if ($this->_loaded)
			return $this->clear()
				->where($this->_object_name.'.'.$this->_primary_key, '=', $primary_key)
				->find();
		else
			return $this->clear();
	}

	/**
	 * Checks if object data is set.
	 *
	 * @param  string $column Column name
	 * @return bool
	 */
	public function __isset(string $column): bool
	{
		return (isset($this->_object[$column]) OR
			isset($this->_related[$column]) OR
			isset($this->_has_one[$column]) OR
			isset($this->_belongs_to[$column]) OR
			isset($this->_has_many[$column]));
	}

	/**
	 * Unsets object data.
	 *
	 * @param  string $column Column name
	 * @return void
	 */
	public function __unset(string $column): void
	{
		unset($this->_object[$column], $this->_changed[$column], $this->_related[$column]);
	}

	/**
	 * Displays the primary key of a model when it is converted to a string.
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return is_scalar($pk = $this->pk()) ? (string) $pk : '';
	}

	/**
	 * Allows serialization of only the object data and state, to prevent
	 * "stale" objects being unserialized, which also requires less memory.
	 *
	 * @return string
	 */
	public function serialize(): ?string
	{
		// Kept for the Serializable interface; PHP itself uses __serialize()
		return serialize($this->__serialize());
	}

	/**
	 * Check whether the model data has been modified.
	 * If $field is specified, checks whether that field was modified.
	 *
	 * @param string  $field      Field to check for changes
	 * @return array<string, string>|string|null  Null if $field has not changed. String value if $field has changed.
	 *                            Array of all changed fields if $field is not provided.
	 */
	public function changed(?string $field = null): mixed
	{
		return ($field === null)
			? $this->_changed
			: ($this->_changed[$field] ?? null);
	}

	/**
	 * Check whether a specific field has changed. This function is similar to changed() except a field is required, and
	 * it returns a boolean.
	 *
	 * @param string $field  Field to check for changes.
	 * @return bool          True if field has changed, false if it has not.
	 */
	public function has_changed(string $field): bool
	{
		return array_key_exists($field, $this->_changed);
	}

	/**
	 * Prepares the database connection and reloads the object.
	 *
	 * @param string $data String for unserialization
	 * @return  void
	 */
	public function unserialize(string $data): void
	{
		// Kept for the Serializable interface and for strings serialized by
		// older versions; only plain values are accepted, never objects
		/** @var array<string, mixed> $values */
		$values = (array) unserialize($data, ['allowed_classes' => false]);

		$this->__unserialize($values);
	}

	/**
	 * Handles retrieval of all model values, relationships, and metadata.
	 * [!!] This should not be overridden.
	 *
	 * @param   string $column Column name
	 * @return  mixed
	 */
	public function __get(string $column): mixed
	{
		return $this->get($column);
	}

	/**
	 * Handles getting of column
	 * Override this method to add custom get behavior
	 *
	 * @param   string $column Column name
	 * @throws Kohana_Exception
	 * @return mixed
	 */
	public function get(string $column): mixed
	{
		if (array_key_exists($column, $this->_object))
		{
			return (in_array($column, $this->_serialize_columns))
				? $this->_unserialize_value(is_string($this->_object[$column]) ? $this->_object[$column] : null)
				: $this->_object[$column];
		}
		elseif (isset($this->_related[$column]))
		{
			// Return related model that has already been fetched
			return $this->_related[$column];
		}
		elseif (isset($this->_belongs_to[$column]))
		{
			/** @var ORM $model the relation exists, so this is never false */
			$model = $this->_related($column);

			// Use this model's column and foreign model's primary key
			$col = $model->_object_name.'.'.$model->_primary_key;
			$val = $this->_object[$this->_belongs_to[$column]['foreign_key']];

			// Make sure we don't run WHERE "AUTO_INCREMENT column" = null queries. This would
			// return the last inserted record instead of an empty result.
			// See: http://mysql.localhost.net.ar/doc/refman/5.1/en/server-session-variables.html#sysvar_sql_auto_is_null
			if ($val !== null)
			{
				$model->where($col, '=', $val)->find();
			}

			return $this->_related[$column] = $model;
		}
		elseif (isset($this->_has_one[$column]))
		{
			/** @var ORM $model the relation exists, so this is never false */
			$model = $this->_related($column);

			// Use this model's primary key value and foreign model's column
			$col = $model->_object_name.'.'.$this->_has_one[$column]['foreign_key'];
			$val = $this->pk();

			$model->where($col, '=', $val)->find();

			return $this->_related[$column] = $model;
		}
		elseif (isset($this->_has_many[$column]))
		{
			$model = ORM::factory( (string) $this->_has_many[$column]['model']);

			if (isset($this->_has_many[$column]['through']))
			{
				// Grab has_many "through" relationship table
				$through = $this->_has_many[$column]['through'];

				// Join on through model's target foreign key (far_key) and target model's primary key
				$join_col1 = $through.'.'.$this->_has_many[$column]['far_key'];
				$join_col2 = $model->_object_name.'.'.$model->_primary_key;

				$model->join($through)->on($join_col1, '=', $join_col2);

				// Through table's source foreign key (foreign_key) should be this model's primary key
				$col = $through.'.'.$this->_has_many[$column]['foreign_key'];
				$val = $this->pk();
			}
			else
			{
				// Simple has_many relationship, search where target model's foreign key is this model's primary key
				$col = $model->_object_name.'.'.$this->_has_many[$column]['foreign_key'];
				$val = $this->pk();
			}

			return $model->where($col, '=', $val);
		}
		else
		{
			throw new Kohana_Exception('The :property property does not exist in the :class class',
				[':property' => $column, ':class' => get_class($this)]);
		}
	}

	/**
	 * Base set method.
	 * [!!] This should not be overridden.
	 *
	 * @param  string $column  Column name
	 * @param  mixed  $value   Column value
	 * @return void
	 */
	public function __set(string $column, mixed $value): void
	{
		$this->set($column, $value);
	}

	/**
	 * Handles setting of columns
	 * Override this method to add custom set behavior
	 *
	 * @param  string $column Column name
	 * @param  mixed  $value  Column value
	 * @throws Kohana_Exception
	 * @return static
	 */
	public function set(string $column, mixed $value): static
	{
		if ( ! isset($this->_object_name))
		{
			// Object not yet constructed, so we're loading data from a database call cast
			$this->_cast_data[$column] = $value;

			return $this;
		}

		if (in_array($column, $this->_serialize_columns))
		{
			$value = $this->_serialize_value($value);
		}

		if (array_key_exists($column, $this->_object))
		{
			// Filter the data
			$value = $this->run_filter($column, $value);

			// See if the data really changed
			if ($value !== $this->_object[$column])
			{
				$this->_object[$column] = $value;

				// Data has changed
				$this->_changed[$column] = $column;

				// Object is no longer saved or valid
				$this->_saved = $this->_valid = false;
			}
		}
		elseif (isset($this->_belongs_to[$column]))
		{
			// Update related object itself
			$this->_related[$column] = $value;

			// Update the foreign key of this model
			$this->_object[$this->_belongs_to[$column]['foreign_key']] = ($value instanceof ORM)
				? $value->pk()
				: null;

			$this->_changed[$column] = $this->_belongs_to[$column]['foreign_key'];
		}
		elseif (isset($this->_has_many[$column]))
		{
			if (Arr::get($this->_has_many[$column], 'update', false))
			{
				$model = (string) $this->_has_many[$column]['model'];
				$pk = ORM::factory($model)->primary_key();

				/** @var ORM $related */
				$related = $this->get($column);
				/** @var array<int, int|string> $value  the ids that must stay */
				$value = (array) $value;

				/** @var array<int, int|string> $current_ids */
				$current_ids = $related->find_all()->as_array(null, 'id');

				$new_ids = array_diff($value, $current_ids);
				if (count($new_ids) > 0)
				{
					$objects = ORM::factory($model)->where($pk, 'IN', $new_ids)->find_all();
					foreach ($objects as $object)
					{
						$this->add($column, $object);
					}
				}

				$delete_ids = array_diff($current_ids, $value);
				if (count($delete_ids) > 0)
				{
					$objects = ORM::factory($model)->where($pk, 'IN', $delete_ids)->find_all();
					foreach ($objects as $object)
					{
						$this->remove($column, $object);
					}
				}
			}
			else
			{
				throw new Kohana_Exception('The :property: property is a to many relation in the :class: class',
					[':property:' => $column, ':class:' => get_class($this)]);
			}
		}
		else
		{
			throw new Kohana_Exception('The :property: property does not exist in the :class: class',
				[':property:' => $column, ':class:' => get_class($this)]);
		}

		return $this;
	}

	/**
	 * Set values from an array with support for one-one relationships. This method should be used
	 * for loading in post data, etc.
	 *
	 * @param  array<string, mixed> $values   Array of column => value
	 * @param  array<int|string, mixed>|null $expected Keys to take from $values; `'alias' => [keys]` for a related model
	 * @return static
	 */
	public function values(array $values, ?array $expected = null): static
	{
		// Default to expecting all columns
		if ($expected === null)
		{
			$expected = array_keys($this->table_columns());
		}

		// Don't set the primary key if model loaded and column not set
		if ($this->_primary_key AND $this->_loaded)
		{
			unset($values[$this->_primary_key]);
		}

		foreach ($expected as $key => $column)
		{
			if (is_string($key))
			{
				// isset() fails when the value is null (we want it to pass)
				if ( ! array_key_exists($key, $values))
					continue;

				// Try to set values to a related model
				$related = $this->{$key};

				if ($related instanceof ORM AND is_array($values[$key]) AND is_array($column))
				{
					/** @var array<string, mixed> $data  the values of the related model */
					$data = $values[$key];

					$related->values($data, $column);
				}
			}
			else
			{
				// isset() fails when the value is null (we want it to pass)
				if ( ! is_string($column) OR ! array_key_exists($column, $values))
					continue;

				// Update the column, respects __set()
				$this->$column = $values[$column];
			}
		}

		return $this;
	}

	/**
	 * Returns the type of the column
	 *
	 * @param string $column
	 * @return string
	 */
	public function table_column_type(string $column): string|false
	{
		if ( ! array_key_exists($column, $this->table_columns()))
			return false;

		$type = $this->table_columns()[$column]['type'] ?? false;

		return is_string($type) ? $type : false;
	}

	/**
	 * Returns a value as the native type, will return false if the
	 * value could not be casted.
	 *
	 * @param string $column
	 * @return mixed
	 */
	protected function get_typed(string $column): mixed
	{
		$value = $this->get($column);

		// Only plain values are cast; null and unserialized columns (arrays) are returned as they are
		if ( ! is_scalar($value))
			return $value;

		return match ($this->table_column_type($column))
		{
			'float'  => (float) $value,
			'int'    => (int) $value,
			'string' => (string) $value,
			default  => $value,
		};
	}

	/**
	 * Returns the values of this object as an array, including any related one-one
	 * models that have already been loaded using with()
	 *
	 * @return array<string, mixed>
	 */
	public function as_array(bool $show_all = false): array
	{
		$object = [];

		if ($show_all OR !is_array($this->_private_columns))
		{
			foreach ($this->_object as $column => $value)
			{
				// Call __get for any user processing
				$object[$column] = $this->__get($column);
			}
		}
		else
		{
			foreach ($this->_object as $column => $value)
			{
				// Call __get for any user processing
				if (!in_array($column, $this->_private_columns))
					$object[$column] = $this->__get($column);
			}
		}

		foreach ($this->_related as $column => $model)
		{
			// Include any related objects that are already loaded
			$object[$column] = ($model instanceof ORM) ? $model->as_array() : $model;
		}

		return $object;
	}

	/**
	 * Returns the values of this object as an new object, including any related
	 * one-one models that have already been loaded using with(). Removes private
	 * columns.
	 *
	 * @return stdClass
	 */
	public function as_object(bool $show_all = false): stdClass
	{
		$object = new stdClass;

		if ($show_all OR !is_array($this->_private_columns))
		{
			foreach ($this->_object as $column => $value)
			{
				$object->{$column} = $this->get_typed($column);
			}
		}
		else
		{
			foreach ($this->_object as $column => $value)
			{
				if (!in_array($column, $this->_private_columns))
				{
					$object->{$column} = $this->get_typed($column);
				}
			}
		}

		foreach ($this->_related as $column => $model)
		{
			// Include any related objects that are already loaded
			$object->{$column} = ($model instanceof ORM) ? $model->as_object() : $model;
		}

		return $object;
	}

	/**
	 * Binds another one-to-one object to this model.  One-to-one objects
	 * can be nested using 'object1:object2' syntax
	 *
	 * @param  string $target_path Target model to bind to
	 * @return static
	 */
	public function with(string $target_path): static
	{
		if (isset($this->_with_applied[$target_path]))
		{
			// Don't join anything already joined
			return $this;
		}

		// Split object parts
		$aliases = explode(':', $target_path);
		$target = $this;
		foreach ($aliases as $alias)
		{
			// Go down the line of objects to find the given target
			$parent = $target;
			$target = $parent->_related($alias);

			if ( ! $target)
			{
				// Can't find related object
				return $this;
			}
		}

		// Target alias is at the end
		$target_alias = $alias;

		// Pop-off top alias to get the parent path (user:photo:tag becomes user:photo - the parent table prefix)
		array_pop($aliases);
		$parent_path = implode(':', $aliases);

		if (empty($parent_path))
		{
			// Use this table name itself for the parent path
			$parent_path = $this->_object_name;
		}
		else
		{
			if ( ! isset($this->_with_applied[$parent_path]))
			{
				// If the parent path hasn't been joined yet, do it first (otherwise LEFT JOINs fail)
				$this->with($parent_path);
			}
		}

		// Add to with_applied to prevent duplicate joins
		$this->_with_applied[$target_path] = true;

		// Use the keys of the empty object to determine the columns
		foreach (array_keys($target->_object) as $column)
		{
			$name = $target_path.'.'.$column;
			$alias = $target_path.':'.$column;

			// Add the prefix so that load_result can determine the relationship
			$this->select([$name, $alias]);
		}

		if (isset($parent->_belongs_to[$target_alias]))
		{
			// Parent belongs_to target, use target's primary key and parent's foreign key
			$join_col1 = $target_path.'.'.$target->_primary_key;
			$join_col2 = $parent_path.'.'.$parent->_belongs_to[$target_alias]['foreign_key'];
		}
		else
		{
			// Parent has_one target, use parent's primary key as target's foreign key
			$join_col1 = $parent_path.'.'.$parent->_primary_key;
			$join_col2 = $target_path.'.'.$parent->_has_one[$target_alias]['foreign_key'];
		}

		// Join the related object into the result
		$this->join([$target->_table_name, $target_path], 'LEFT')->on($join_col1, '=', $join_col2);

		return $this;
	}

	/**
	 * Initializes the Database Builder to given query type
	 *
	 * @param  int $type Type of Database query
	 * @return static
	 */
	protected function _build(int $type): static
	{
		// Construct new builder object based on query type
		switch ($type)
		{
			case Database::SELECT:
				$this->_db_builder = DB::select();
			break;
			case Database::UPDATE:
				$this->_db_builder = DB::update([$this->_table_name, $this->_object_name]);
			break;
			case Database::DELETE:
				// Cannot use an alias for DELETE queries
				$this->_db_builder = DB::delete($this->_table_name);
		}

		// Process pending database method calls
		foreach ($this->_db_pending as $method)
		{
			$name = $method['name'];
			$args = $method['args'];

			$this->_db_applied[$name] = $name;

			$this->_db_builder->$name(...$args);
		}

		return $this;
	}

	/**
	 * Finds and loads a single database row into the object.
	 *
	 * @chainable
	 * @throws Kohana_Exception
	 * @return static
	 */
	public function find(): static
	{
		if ($this->_loaded)
			throw new Kohana_Exception('Method find() cannot be called on loaded objects');

		if ( ! empty($this->_load_with))
		{
			foreach ($this->_load_with as $alias)
			{
				// Bind auto relationships
				$this->with($alias);
			}
		}

		$this->_build(Database::SELECT);

		return $this->_load_result(false);
	}

	/**
	 * Finds multiple database rows and returns an iterator of the rows found.
	 *
	 * @throws Kohana_Exception
	 * @return Database_Result
	 */
	public function find_all(): Database_Result
	{
		if ($this->_loaded)
			throw new Kohana_Exception('Method find_all() cannot be called on loaded objects');

		if ( ! empty($this->_load_with))
		{
			foreach ($this->_load_with as $alias)
			{
				// Bind auto relationships
				$this->with($alias);
			}
		}

		$this->_build(Database::SELECT);

		return $this->_load_result(true);
	}

	/**
	 * Returns an array of columns to include in the select query. This method
	 * can be overridden to change the default select behavior.
	 *
	 * @return array<int, mixed> Columns to select
	 */
	protected function _build_select(): array
	{
		$columns = [];

		foreach ($this->table_columns() as $column => $_)
		{
			$columns[] = [$this->_object_name.'.'.$column, $column];
		}

		return $columns;
	}

	/**
	 * Loads a database result, either as a new record for this model, or as
	 * an iterator for multiple rows.
	 *
	 * @chainable
	 * @param  bool $multiple Return an iterator or load a single row
	 * @return ($multiple is true ? Database_Result : static)
	 */
	protected function _load_result(bool $multiple = false): static|Database_Result
	{
		// Only called after _build(Database::SELECT)
		/** @var Database_Query_Builder_Select $builder */
		$builder = $this->_db_builder;

		$builder->from([$this->_table_name, $this->_object_name]);

		if ($multiple === false)
		{
			// Only fetch 1 record
			$builder->limit(1);
		}

		// Select all columns by default
		$builder->select_array($this->_build_select());

		if ( ! isset($this->_db_applied['order_by']) AND ! empty($this->_sorting))
		{
			foreach ($this->_sorting as $column => $direction)
			{
				if (strpos($column, '.') === false)
				{
					// Sorting column for use in JOINs
					$column = $this->_object_name.'.'.$column;
				}

				$builder->order_by($column, $direction);
			}
		}

		if ($multiple === true)
		{
			// Return database iterator casting to this object type
			$result = $builder->as_object(get_class($this))->execute($this->_db);

			$this->reset();

			return $result;
		}
		else
		{
			// Load the result as an associative array
			$result = $builder->as_assoc()->execute($this->_db);

			$this->reset();

			if ($result->count() === 1)
			{
				// Load object values
				/** @var array<string, mixed> $row */
				$row = $result->current();

				$this->_load_values($row);
			}
			else
			{
				// Clear the object, nothing was found
				$this->clear();
			}

			return $this;
		}
	}

	/**
	 * Loads an array of values into into the current object.
	 *
	 * @chainable
	 * @param  array<string, mixed> $values Values to load
	 * @return static
	 */
	protected function _load_values(array $values): static
	{
		if (array_key_exists($this->_primary_key, $values))
		{
			if ($values[$this->_primary_key] !== null)
			{
				// Flag as loaded and valid
				$this->_loaded = $this->_valid = true;

				// Store primary key
				$this->_primary_key_value = $values[$this->_primary_key];
			}
			else
			{
				// Not loaded or valid
				$this->_loaded = $this->_valid = false;
			}
		}

		// Related objects
		$related = [];

		foreach ($values as $column => $value)
		{
			if ( ! str_contains($column, ':'))
			{
				// Load the value to this model
				$this->_object[$column] = $value;
			}
			else
			{
				// Column belongs to a related model
				list ($prefix, $column) = explode(':', $column, 2);

				$related[$prefix][$column] = $value;
			}
		}

		if ( ! empty($related))
		{
			foreach ($related as $object => $values)
			{
				// Load the related objects with the values in the result
				$model = $this->_related($object);

				if ($model)
				{
					$model->_load_values($values);
				}
			}
		}

		if ($this->_loaded)
		{
			// Store the object in its original state
			$this->_original_values = $this->_object;
		}

		return $this;
	}

	/**
	 * Behavior definitions
	 *
	 * @return array<int|string, mixed>
	 */
	public function behaviors(): array
	{
		return $this->_behaviors;
	}

	/**
	 * Rule definitions for validation
	 *
	 * @return array<string, array<int, array<int, mixed>>>
	 */
	public function rules(): array
	{
		return [];
	}

	/**
	 * Filters a value for a specific column
	 *
	 * @param  string $field  The column name
	 * @param  mixed  $value  The value to filter
	 * @return mixed  the filtered value
	 */
	protected function run_filter(string $field, mixed $value): mixed
	{
		$filters = $this->filters();

		// Get the filters for this column
		$wildcards = empty($filters[true]) ? [] : $filters[true];

		// Merge in the wildcards
		$filters = empty($filters[$field]) ? $wildcards : array_merge($wildcards, $filters[$field]);

		// Bind the field name and model so they can be used in the filter method
		$_bound = [
			':field' => $field,
			':model' => $this,
		];

		foreach ($filters as $array)
		{
			// Value needs to be bound inside the loop so we are always using the
			// version that was modified by the filters that already ran
			$_bound[':value'] = $value;

			// Filters are defined as array($filter, $params)
			$filter = $array[0];
			$params = (array) ($array[1] ?? [':value']);

			foreach ($params as $key => $param)
			{
				if (is_string($param) AND array_key_exists($param, $_bound))
				{
					// Replace with bound value
					$params[$key] = $_bound[$param];
				}
			}

			// PHP 8.1+: Many built-in string functions no longer accept null.
			// Normalize null to empty string for parameters that map to ':value'
			// Auto-detect null safety using Reflection
			if (is_string($filter) && strpos($filter, '::') === false) {
				foreach ($params as $key => $param) {
					if ($param === null && !self::_param_accepts_null($filter, (int) $key)) {
						$params[$key] = '';
					}
				}
			}

			if (is_array($filter) OR ! is_string($filter))
			{
				// This is either a callback as an array or a lambda
				/** @var callable $filter */
				$value = call_user_func_array($filter, $params);
			}
			elseif (strpos($filter, '::') === false)
			{
				// Use a function call
				$function = new ReflectionFunction($filter);

				// Call $function($this[$field], $param, ...) with Reflection
				$value = $function->invokeArgs($params);
			}
			else
			{
				// Split the class and method of the rule
				list($class, $method) = explode('::', $filter, 2);

				// Use a static method call
				$method = new ReflectionMethod($class, $method);

				// Call $Class::$method($this[$field], $param, ...) with Reflection
				$value = $method->invokeArgs(null, $params);
			}
		}

		return $value;
	}

	/**
	 * PHP 8.1+: Many built-in string functions no longer accept null.
	 * Uses reflection to check if a function's Nth parameter accepts null.
	 */
	private static function _param_accepts_null(string $function_name, int $param_index = 0): bool
	{
		try {
			$ref = new ReflectionFunction($function_name);
			$params = $ref->getParameters();
			if (!isset($params[$param_index])) {
				return true; // no param = no constraint
			}
			$type = $params[$param_index]->getType();
			if ($type === null) {
				return true; // untyped = anything goes
			}
			return $type->allowsNull();
		} catch (ReflectionException $e) {
			return true; // if we can't reflect, don't coerce
		}
	}

	/**
	 * Filter definitions for validation
	 *
	 * @return array<int|string, array<int, array<int, mixed>>>
	 */
	public function filters(): array
	{
		return [];
	}

	/**
	 * Label definitions for validation
	 *
	 * @return array<string, string>
	 */
	public function labels(): array
	{
		return [];
	}

	/**
	 * Validates the current model's data
	 *
	 * @param  Validation $extra_validation Validation object
	 * @throws ORM_Validation_Exception
	 * @return static
	 */
	public function check(?Validation $extra_validation = null): static
	{
		// Determine if any external validation failed
		$extra_errors = ($extra_validation AND ! $extra_validation->check());

		// Always build a new validation object
		$this->_validation();

		$array = $this->validation();

		if (($this->_valid = $array->check()) === false OR $extra_errors)
		{
			$exception = new ORM_Validation_Exception($this->errors_filename(), $array);

			if ($extra_errors)
			{
				// Merge any possible errors from the external object
				$exception->add_object('_external', $extra_validation);
			}
			throw $exception;
		}

		return $this;
	}

	/**
	 * Insert a new object to the database
	 * @param  Validation $validation Validation object
	 * @throws Kohana_Exception
	 * @return static
	 */
	public function create(?Validation $validation = null): static
	{
		if ($this->_loaded)
			throw new Kohana_Exception('Cannot create :model model because it is already loaded.', [':model' => $this->_object_name]);

		// Invoke all behaviors
		foreach ($this->_behaviors as $behavior)
		{
			$behavior->on_create($this);
		}

		// Require model validation before saving
		if ( ! $this->_valid OR $validation)
		{
			$this->check($validation);
		}

		$data = [];
		foreach ($this->_changed as $column)
		{
			// Generate list of column => values
			$data[$column] = $this->_object[$column];
		}

		if (is_array($this->_created_column))
		{
			// Fill the created column
			$column = $this->_created_column['column'];
			$format = $this->_created_column['format'];

			$data[$column] = $this->_object[$column] = ($format === true) ? time() : date($format);
		}

		$result = DB::insert($this->_table_name)
			->columns(array_keys($data))
			->values(array_values($data))
			->execute($this->_db);

		if ( ! array_key_exists($this->_primary_key, $data) OR ($this->_object[$this->_primary_key] === null))
		{
			// Load the insert id as the primary key if it was left out
			$this->_object[$this->_primary_key] = $this->_primary_key_value = $result[0];
		}
		else
		{
			$this->_primary_key_value = $this->_object[$this->_primary_key];
		}

		// Object is now loaded and saved
		$this->_loaded = $this->_saved = true;

		// All changes have been saved
		$this->_changed = [];
		$this->_original_values = $this->_object;

		return $this;
	}

	/**
	 * Updates a single record or multiple records
	 *
	 * @chainable
	 * @param  Validation $validation Validation object
	 * @throws Kohana_Exception
	 * @return static
	 */
	public function update(?Validation $validation = null): static
	{
		if ( ! $this->_loaded)
			throw new Kohana_Exception('Cannot update :model model because it is not loaded.', [':model' => $this->_object_name]);

		// Invoke all behaviors
		foreach ($this->_behaviors as $behavior)
		{
			$behavior->on_update($this);
		}

		// Run validation if the model isn't valid or we have additional validation rules.
		if ( ! $this->_valid OR $validation)
		{
			$this->check($validation);
		}

		if (empty($this->_changed))
		{
			// Nothing to update
			return $this;
		}

		$data = [];
		foreach ($this->_changed as $column)
		{
			// Compile changed data
			$data[$column] = $this->_object[$column];
		}

		if (is_array($this->_updated_column))
		{
			// Fill the updated column
			$column = $this->_updated_column['column'];
			$format = $this->_updated_column['format'];

			$data[$column] = $this->_object[$column] = ($format === true) ? time() : date($format);
		}

		// Use primary key value
		$id = $this->pk();

		// Update a single record
		DB::update($this->_table_name)
			->set($data)
			->where($this->_primary_key, '=', $id)
			->execute($this->_db);

		if (isset($data[$this->_primary_key]))
		{
			// Primary key was changed, reflect it
			$this->_primary_key_value = $data[$this->_primary_key];
		}

		// Object has been saved
		$this->_saved = true;

		// All changes have been saved
		$this->_changed = [];
		$this->_original_values = $this->_object;

		return $this;
	}

	/**
	 * Updates or Creates the record depending on loaded()
	 *
	 * @chainable
	 * @param  Validation $validation Validation object
	 * @return static
	 */
	public function save(?Validation $validation = null): static
	{
		return $this->loaded() ? $this->update($validation) : $this->create($validation);
	}

	/**
	 * Deletes a single record while ignoring relationships.
	 *
	 * @chainable
	 * @throws Kohana_Exception
	 * @return static
	 */
	public function delete(): static
	{
		if ( ! $this->_loaded)
			throw new Kohana_Exception('Cannot delete :model model because it is not loaded.', [':model' => $this->_object_name]);

		// Use primary key value
		$id = $this->pk();

		// Delete the object
		DB::delete($this->_table_name)
			->where($this->_primary_key, '=', $id)
			->execute($this->_db);

		return $this->clear();
	}

	/**
	 * Tests if this object has a relationship to a different model,
	 * or an array of different models. When providing far keys, the number
	 * of relations must equal the number of keys.
	 *
	 *
	 *     // Check if $model has the login role
	 *     $model->has('roles', ORM::factory('role', array('name' => 'login')));
	 *     // Check for the login role if you know the roles.id is 5
	 *     $model->has('roles', 5);
	 *     // Check for all of the following roles
	 *     $model->has('roles', array(1, 2, 3, 4));
	 *     // Check if $model has any roles
	 *     $model->has('roles')
	 *
	 * @param  string  $alias    Alias of the has_many "through" relationship
	 * @param  mixed   $far_keys Related model, primary key, or an array of primary keys
	 * @return bool
	 */
	public function has(string $alias, mixed $far_keys = null): bool
	{
		$count = $this->count_relations($alias, $far_keys);

		if ($far_keys === null)
		{
			return (bool) $count;
		}
		else
		{
			if (is_array($far_keys) OR $far_keys instanceof Countable)
			{
				$keys = count($far_keys);
			}
			else
			{
				$keys = 1;
			}

			return $keys === $count;
		}
	}

	/**
	 * Tests if this object has a relationship to a different model,
	 * or an array of different models. When providing far keys, this function
	 * only checks that at least one of the relationships is satisfied.
	 *
	 *     // Check if $model has the login role
	 *     $model->has('roles', ORM::factory('role', array('name' => 'login')));
	 *     // Check for the login role if you know the roles.id is 5
	 *     $model->has('roles', 5);
	 *     // Check for any of the following roles
	 *     $model->has('roles', array(1, 2, 3, 4));
	 *     // Check if $model has any roles
	 *     $model->has('roles')
	 *
	 * @param  string  $alias    Alias of the has_many "through" relationship
	 * @param  mixed   $far_keys Related model, primary key, or an array of primary keys
	 * @return bool
	 */
	public function has_any(string $alias, mixed $far_keys = null): bool
	{
		return (bool) $this->count_relations($alias, $far_keys);
	}

	/**
	 * Makes sure an alias is a "has many" relation with a "through" table,
	 * which [ORM::has], [ORM::add] and [ORM::remove] need.
	 *
	 * @param   string  $alias  alias of the relation
	 * @return  void
	 * @throws  Kohana_Exception
	 */
	protected function _check_through(string $alias): void
	{
		if (empty($this->_has_many[$alias]['through']))
			throw new Kohana_Exception('The :alias relation of :class is not a "has many through" relation', [
				':alias' => $alias,
				':class' => get_class($this),
			]);
	}

	/**
	 * Returns the number of relationships
	 *
	 *     // Counts the number of times the login role is attached to $model
	 *     $model->count_relations('roles', ORM::factory('role', array('name' => 'login')));
	 *     // Counts the number of times role 5 is attached to $model
	 *     $model->count_relations('roles', 5);
	 *     // Counts the number of times any of roles 1, 2, 3, or 4 are attached to
	 *     // $model
	 *     $model->count_relations('roles', array(1, 2, 3, 4));
	 *     // Counts the number roles attached to $model
	 *     $model->count_relations('roles')
	 *
	 * @param  string  $alias    Alias of the has_many "through" relationship
	 * @param  mixed   $far_keys Related model, primary key, or an array of primary keys
	 * @return int
	 */
	public function count_relations(string $alias, mixed $far_keys = null): int
	{
		$this->_check_through($alias);

		if ($far_keys === null)
		{
			/** @var int|string $count */
			$count = DB::select([DB::expr('COUNT(*)'), 'records_found'])
				->from($this->_has_many[$alias]['through'])
				->where($this->_has_many[$alias]['foreign_key'], '=', $this->pk())
				->execute($this->_db)->get('records_found');

			return (int) $count;
		}

		$far_keys = ($far_keys instanceof ORM) ? $far_keys->pk() : $far_keys;

		// We need an array to simplify the logic
		$far_keys = (array) $far_keys;

		// Nothing to check if the model isn't loaded or we don't have any far_keys
		if ( ! $far_keys OR ! $this->_loaded)
			return 0;

		/** @var int|string $count */
		$count = DB::select([DB::expr('COUNT(*)'), 'records_found'])
			->from($this->_has_many[$alias]['through'])
			->where($this->_has_many[$alias]['foreign_key'], '=', $this->pk())
			->where($this->_has_many[$alias]['far_key'], 'IN', $far_keys)
			->execute($this->_db)->get('records_found');

		// Rows found need to match the rows searched
		return (int) $count;
	}

	/**
	 * Adds a new relationship to between this model and another.
	 *
	 *     // Add the login role using a model instance
	 *     $model->add('roles', ORM::factory('role', array('name' => 'login')));
	 *     // Add the login role if you know the roles.id is 5
	 *     $model->add('roles', 5);
	 *     // Add multiple roles (for example, from checkboxes on a form)
	 *     $model->add('roles', array(1, 2, 3, 4));
	 *
	 * @param  string  $alias    Alias of the has_many "through" relationship
	 * @param  mixed   $far_keys Related model, primary key, or an array of primary keys
	 * @return static
	 */
	public function add(string $alias, mixed $far_keys): static
	{
		$this->_check_through($alias);

		$far_keys = ($far_keys instanceof ORM) ? $far_keys->pk() : $far_keys;

		$columns = [$this->_has_many[$alias]['foreign_key'], $this->_has_many[$alias]['far_key']];
		$foreign_key = $this->pk();

		$query = DB::insert( (string) $this->_has_many[$alias]['through'], $columns);

		foreach ( (array) $far_keys as $key)
		{
			$query->values([$foreign_key, $key]);
		}

		$query->execute($this->_db);

		return $this;
	}

	/**
	 * Removes a relationship between this model and another.
	 *
	 *     // Remove a role using a model instance
	 *     $model->remove('roles', ORM::factory('role', array('name' => 'login')));
	 *     // Remove the role knowing the primary key
	 *     $model->remove('roles', 5);
	 *     // Remove multiple roles (for example, from checkboxes on a form)
	 *     $model->remove('roles', array(1, 2, 3, 4));
	 *     // Remove all related roles
	 *     $model->remove('roles');
	 *
	 * @param  string $alias    Alias of the has_many "through" relationship
	 * @param  mixed  $far_keys Related model, primary key, or an array of primary keys
	 * @return static
	 */
	public function remove(string $alias, mixed $far_keys = null): static
	{
		$this->_check_through($alias);

		$far_keys = ($far_keys instanceof ORM) ? $far_keys->pk() : $far_keys;

		$query = DB::delete( (string) $this->_has_many[$alias]['through'])
			->where($this->_has_many[$alias]['foreign_key'], '=', $this->pk());

		if ($far_keys !== null)
		{
			// Remove all the relationships in the array
			$query->where($this->_has_many[$alias]['far_key'], 'IN', (array) $far_keys);
		}

		$query->execute($this->_db);

		return $this;
	}

	/**
	 * Count the number of records in the table.
	 *
	 * @return int
	 */
	public function count_all(): int
	{
		if ( ! empty($this->_load_with))
		{
			foreach ($this->_load_with as $alias)
			{
				// Bind relationship: its join can decide which rows count
				$this->with($alias);
			}
		}

		// Columns and sorting mean nothing to a count, and PostgreSQL refuses
		// them beside COUNT(): they are set aside, also those of with()
		$set_aside = [];

		foreach ($this->_db_pending as $key => $method)
		{
			if ($method['name'] === 'select' OR $method['name'] === 'order_by')
			{
				$set_aside[$key] = $method;
				unset($this->_db_pending[$key]);
			}
		}

		$this->_build(Database::SELECT);

		/** @var Database_Query_Builder_Select $builder */
		$builder = $this->_db_builder;

		/** @var int|string $records */
		$records = $builder->from([$this->_table_name, $this->_object_name])
			->select([DB::expr('COUNT('.$this->_db?->quote_column($this->_object_name.'.'.$this->_primary_key).')'), 'records_found'])
			->execute($this->_db)
			->get('records_found');

		// Put them back, for a find_all() that follows after reset(false)
		$this->_db_pending += $set_aside;

		$this->reset();

		// Return the total number of records in a table
		return (int) $records;
	}

	/**
	 * Proxy method to Database list_columns.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function list_columns(): array
	{
		// Proxy to database
		return $this->_db?->list_columns( (string) $this->_table_name) ?? [];
	}

	/**
	 * Returns an ORM model for the given one-one related alias
	 *
	 * @param  string $alias Alias name
	 * @return ORM|false  false when the model has no such relation
	 */
	protected function _related(string $alias): ORM|false
	{
		if (($this->_related[$alias] ?? null) instanceof ORM)
		{
			return $this->_related[$alias];
		}
		elseif (isset($this->_has_one[$alias]))
		{
			return $this->_related[$alias] = ORM::factory($this->_has_one[$alias]['model']);
		}
		elseif (isset($this->_belongs_to[$alias]))
		{
			return $this->_related[$alias] = ORM::factory($this->_belongs_to[$alias]['model']);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Returns the value of the primary key
	 *
	 * @return mixed Primary key
	 */
	public function pk(): mixed
	{
		return $this->_primary_key_value;
	}

	/**
	 * Returns the database this model reads and writes, for code that
	 * makes a query of its own on the same tables (a behavior).
	 *
	 * @return  Database|null
	 */
	public function db(): ?Database
	{
		return $this->_db;
	}

	/**
	 * Returns last executed query
	 *
	 * @return string
	 */
	public function last_query(): ?string
	{
		return $this->_db?->last_query;
	}

	/**
	 * Clears query builder.  Passing false is useful to keep the existing
	 * query conditions for another query.
	 *
	 * @param bool $next Pass false to avoid resetting on the next call
	 * @return static
	 */
	public function reset(bool $next = true): static
	{
		if ($next AND $this->_db_reset)
		{
			$this->_db_pending   = [];
			$this->_db_applied   = [];
			$this->_db_builder   = null;
			$this->_with_applied = [];
		}

		// Reset on the next call?
		$this->_db_reset = $next;

		return $this;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	protected function _serialize_value(mixed $value): string
	{
		if (is_null($value)) // FIX PHP >8.1 (Error_Exception: json_encode(): Passing null to parameter #1 ($json) of type string is deprecated)
			$value = []; // As we expect to receive an array, or we can return null.

		return json_encode($value, JSON_THROW_ON_ERROR);
	}

	/**
	 * @param string $value
	 * @return array<int|string, mixed>
	 */
	protected function _unserialize_value(?string $value): mixed
	{
		if (is_null($value)) // FIX PHP >8.1 (Error_Exception: json_decode(): Passing null to parameter #1 ($json) of type string is deprecated)
			return []; // As we expect to receive an array, or we can return null.

		return (array) json_decode($value, true);
	}

	/**
	 * @return string
	 */
	public function object_name(): string
	{
		return (string) $this->_object_name;
	}

	/**
	 * @return string
	 */
	public function object_plural(): string
	{
		return (string) $this->_object_plural;
	}

	/**
	 * @return bool
	 */
	public function loaded(): bool
	{
		return $this->_loaded;
	}

	/**
	 * @return bool
	 */
	public function saved(): bool
	{
		return $this->_saved;
	}

	/**
	 * @return string
	 */
	public function primary_key(): string
	{
		return $this->_primary_key;
	}

	/**
	 * @return string
	 */
	public function table_name(): string
	{
		return (string) $this->_table_name;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function table_columns(): array
	{
		return $this->_table_columns ?? [];
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function has_one(): array
	{
		return $this->_has_one;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function belongs_to(): array
	{
		return $this->_belongs_to;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function has_many(): array
	{
		return $this->_has_many;
	}

	/**
	 * @return array<int, string>
	 */
	public function load_with(): array
	{
		return $this->_load_with;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function original_values(): array
	{
		return $this->_original_values;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function created_column(): ?array
	{
		return $this->_created_column;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function updated_column(): ?array
	{
		return $this->_updated_column;
	}

	/**
	 * @return Validation
	 */
	public function validation(): Validation
	{
		if ( ! isset($this->_validation))
		{
			// Initialize the validation object
			$this->_validation();
		}

		/** @var Validation $validation made by _validation() */
		$validation = $this->_validation;

		return $validation;
	}

	/**
	 * Returns object properties.

	 * @return array<string, mixed>
	 */
	public function object(): array
	{
		return $this->_object;
	}

	/**
	 * @return string
	 */
	public function errors_filename(): string
	{
		return (string) $this->_errors_filename;
	}

	/**
	 * Alias of and_where()
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  static
	 */
	public function where(mixed $column, ?string $op, mixed $value): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'where',
			'args' => [$column, $op, $value],
		];

		return $this;
	}

	/**
	 * Creates a new "AND WHERE" condition for the query.
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  static
	 */
	public function and_where(mixed $column, ?string $op, mixed $value): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'and_where',
			'args' => [$column, $op, $value],
		];

		return $this;
	}

	/**
	 * Creates a new "OR WHERE" condition for the query.
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  static
	 */
	public function or_where(mixed $column, ?string $op, mixed $value): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'or_where',
			'args' => [$column, $op, $value],
		];

		return $this;
	}

	/**
	 * Alias of and_where_open()
	 *
	 * @return  static
	 */
	public function where_open(): static
	{
		return $this->and_where_open();
	}

	/**
	 * Opens a new "AND WHERE (...)" grouping.
	 *
	 * @return  static
	 */
	public function and_where_open(): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'and_where_open',
			'args' => [],
		];

		return $this;
	}

	/**
	 * Opens a new "OR WHERE (...)" grouping.
	 *
	 * @return  static
	 */
	public function or_where_open(): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'or_where_open',
			'args' => [],
		];

		return $this;
	}

	/**
	 * Closes an open "AND WHERE (...)" grouping.
	 *
	 * @return  static
	 */
	public function where_close(): static
	{
		return $this->and_where_close();
	}

	/**
	 * Closes an open "AND WHERE (...)" grouping.
	 *
	 * @return  static
	 */
	public function and_where_close(): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'and_where_close',
			'args' => [],
		];

		return $this;
	}

	/**
	 * Closes an open "OR WHERE (...)" grouping.
	 *
	 * @return  static
	 */
	public function or_where_close(): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'or_where_close',
			'args' => [],
		];

		return $this;
	}

	/**
	 * Applies sorting with "ORDER BY ..."
	 *
	 * @param   mixed   $column     column name or array($column, $alias) or object
	 * @param   string  $direction  direction of sorting
	 * @return  static
	 */
	public function order_by(mixed $column, ?string $direction = null): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'order_by',
			'args' => [$column, $direction],
		];

		return $this;
	}

	/**
	 * Return up to "LIMIT ..." results
	 *
	 * @param   int  $number  maximum results to return
	 * @return  static
	 */
	public function limit(?int $number): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'limit',
			'args' => [$number],
		];

		return $this;
	}

	/**
	 * Enables or disables selecting only unique columns using "SELECT DISTINCT"
	 *
	 * @param   bool  $value  enable or disable distinct columns
	 * @return  static
	 */
	public function distinct(bool $value): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'distinct',
			'args' => [$value],
		];

		return $this;
	}

	/**
	 * Choose the columns to select from.
	 *
	 * @param   mixed  $columns  column name or array($column, $alias) or object
	 * @return  static
	 */
	public function select(mixed ...$columns): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'select',
			'args' => $columns,
		];

		return $this;
	}

	/**
	 * Choose the tables to select "FROM ..."
	 *
	 * @param   mixed  $tables  table name or array($table, $alias) or object
	 * @return  static
	 */
	public function from(mixed ...$tables): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'from',
			'args' => $tables,
		];

		return $this;
	}

	/**
	 * Adds addition tables to "JOIN ...".
	 *
	 * @param   mixed   $table  column name or array($column, $alias) or object
	 * @param   string  $type   join type (LEFT, RIGHT, INNER, etc)
	 * @return  static
	 */
	public function join(mixed $table, ?string $type = null): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'join',
			'args' => [$table, $type],
		];

		return $this;
	}

	/**
	 * Adds "ON ..." conditions for the last created JOIN statement.
	 *
	 * @param   mixed   $c1  column name or array($column, $alias) or object
	 * @param   string  $op  logic operator
	 * @param   mixed   $c2  column name or array($column, $alias) or object
	 * @return  static
	 */
	public function on(mixed $c1, string $op, mixed $c2): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'on',
			'args' => [$c1, $op, $c2],
		];

		return $this;
	}

	/**
	 * Creates a "GROUP BY ..." filter.
	 *
	 * @param   mixed   $columns  column name or array($column, $alias) or object
	 * @return  static
	 */
	public function group_by(mixed ...$columns): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'group_by',
			'args' => $columns,
		];

		return $this;
	}

	/**
	 * Alias of and_having()
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  static
	 */
	public function having(mixed $column, ?string $op, mixed $value = null): static
	{
		return $this->and_having($column, $op, $value);
	}

	/**
	 * Creates a new "AND HAVING" condition for the query.
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  static
	 */
	public function and_having(mixed $column, ?string $op, mixed $value = null): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'and_having',
			'args' => [$column, $op, $value],
		];

		return $this;
	}

	/**
	 * Creates a new "OR HAVING" condition for the query.
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  static
	 */
	public function or_having(mixed $column, ?string $op, mixed $value = null): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'or_having',
			'args' => [$column, $op, $value],
		];

		return $this;
	}

	/**
	 * Alias of and_having_open()
	 *
	 * @return  static
	 */
	public function having_open(): static
	{
		return $this->and_having_open();
	}

	/**
	 * Opens a new "AND HAVING (...)" grouping.
	 *
	 * @return  static
	 */
	public function and_having_open(): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'and_having_open',
			'args' => [],
		];

		return $this;
	}

	/**
	 * Opens a new "OR HAVING (...)" grouping.
	 *
	 * @return  static
	 */
	public function or_having_open(): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'or_having_open',
			'args' => [],
		];

		return $this;
	}

	/**
	 * Closes an open "AND HAVING (...)" grouping.
	 *
	 * @return  static
	 */
	public function having_close(): static
	{
		return $this->and_having_close();
	}

	/**
	 * Closes an open "AND HAVING (...)" grouping.
	 *
	 * @return  static
	 */
	public function and_having_close(): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'and_having_close',
			'args' => [],
		];

		return $this;
	}

	/**
	 * Closes an open "OR HAVING (...)" grouping.
	 *
	 * @return  static
	 */
	public function or_having_close(): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'or_having_close',
			'args' => [],
		];

		return $this;
	}

	/**
	 * Start returning results after "OFFSET ..."
	 *
	 * @param   int   $number  starting result number
	 * @return  static
	 */
	public function offset(?int $number): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'offset',
			'args' => [$number],
		];

		return $this;
	}

	/**
	 * Enables the query to be cached for a specified amount of time.
	 *
	 * @param   int  $lifetime  number of seconds to cache
	 * @return  static
	 * @uses    Kohana::$cache_life
	 */
	public function cached(?int $lifetime = null): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'cached',
			'args' => [$lifetime],
		];

		return $this;
	}

	/**
	 * Set the value of a parameter in the query.
	 *
	 * @param   string   $param  parameter key to replace
	 * @param   mixed    $value  value to use
	 * @return  static
	 */
	public function param(string $param, mixed $value): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'param',
			'args' => [$param, $value],
		];

		return $this;
	}

	/**
	 * Adds "USING ..." conditions for the last created JOIN statement.
	 *
	 * @param   mixed  $columns  column names
	 * @return  static
	 */
	public function using(mixed ...$columns): static
	{
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = [
			'name' => 'using',
			'args' => $columns,
		];

		return $this;
	}

	/**
	 * Checks whether a column value is unique.
	 * Excludes itself if loaded.
	 *
	 * Without $match_case the database decides what is equal, by the
	 * collation of the column: MySQL ignores upper and lower case by
	 * default, SQLite and PostgreSQL do not.
	 *
	 * @param   string   $field       the field to check for uniqueness
	 * @param   mixed    $value       the value to check for uniqueness
	 * @param   bool     $match_case  only count values that are exactly the same
	 * @return  bool     whether the value is unique
	 */
	public function unique(string $field, mixed $value, bool $match_case = false): bool
	{
		// The value is passed as a parameter, so it cannot be read as SQL
		$found = ORM::factory($this->object_name())->where($field, '=', $value)->find_all();

		// Text of a value, to compare two of them exactly
		$text = fn (mixed $value): string => is_scalar($value) ? (string) $value : '';

		/** @var ORM $model */
		foreach ($found as $model)
		{
			// The exact comparison is done here, so it works on every database
			if ($match_case AND $text($model->{$field}) !== $text($value))
				continue;

			// The record itself is not a duplicate
			if ( ! $this->loaded() OR $model->pk() != $this->pk())
				return false;
		}

		return true;
	}


	/**
	 * Get the quoted table name from the model name
	 *
	 * @param   string   $orm_model  Model name
	 * @return  string   Quoted table name
	 */
	public static function quote_table(string $orm_model): string
	{
		return Database::instance()->quote_table(strtolower($orm_model));
	}
}
