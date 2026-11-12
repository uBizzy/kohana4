<?php
/**
 * Database Model base class.
 *
 * @package    Kohana/Database
 * @category   Models
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Model_Database extends Model {

	/**
	 * Create a new model instance. A [Database] instance or configuration
	 * group name can be passed to the model. If no database is defined, the
	 * "default" database group will be used.
	 *
	 *     $model = Model::factory($name);
	 *
	 * @param   string   $name  model name
	 * @param   mixed    $db    Database instance object or string
	 * @return  Model
	 */
	public static function factory(string $name, mixed $db = null): Model
	{
		/** @var class-string<Model> $class  the model class */
		$class = 'Model_'.$name;

		return new $class($db);
	}

	// Database instance
	protected Database|string|null $_db = null;

	/**
	 * Loads the database.
	 *
	 *     $model = new Foo_Model($db);
	 *
	 * @param   Database|string|null  $db  Database instance object or string
	 * @return  void
	 */
	public function __construct(Database|string|null $db = null)
	{
		if ($db)
		{
			// Set the instance or name
			$this->_db = $db;
		}
		elseif ( ! $this->_db)
		{
			// Use the default name
			$this->_db = Database::$default;
		}

		if (is_string($this->_db))
		{
			// Load the database
			$this->_db = Database::instance($this->_db);
		}
	}

} // End Model
