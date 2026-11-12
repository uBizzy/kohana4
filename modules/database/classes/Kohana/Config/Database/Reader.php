<?php

/**
 * Database reader for the Kohana config system
 *
 * @package    Kohana/Database
 * @category   Configuration
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Config_Database_Reader implements Kohana_Config_Reader
{
	protected ?string $_db_instance = null;

	protected string $_table_name  = 'config';

	/**
	 * Constructs the database reader object
	 *
	 * @param array{instance?: string, table_name?: string}|null  $config  Configuration for the reader
	 */
	public function __construct(?array $config = null)
	{
		if (isset($config['instance']))
		{
			$this->_db_instance = $config['instance'];
		}
		elseif ($this->_db_instance === null)
		{
			$this->_db_instance = Database::$default;
		}

		if (isset($config['table_name']))
		{
			$this->_table_name = $config['table_name'];
		}
	}

	/**
	 * Tries to load the specificed configuration group
	 *
	 * Returns false if group does not exist or an array if it does
	 *
	 * @param  string $group Configuration group
	 * @return array<int|string, mixed>|false
	 */
	public function load(string $group): array|false
	{
		/**
		 * Prevents the catch-22 scenario where the database config reader attempts to load the
		 * database connections details from the database.
		 *
		 * @link http://github.com/koseven/koseven/issues/4316
		 */
		if ($group === 'database')
			return false;

		$query = DB::select('config_key', 'config_value')
			->from($this->_table_name)
			->where('group_name', '=', $group)
			->execute($this->_db_instance);

		/** @var array<string, string> $rows */
		$rows = $query->as_array('config_key', 'config_value');

		if ( ! $rows)
			return false;

		// Settings are values and lists, never objects: none is made from
		// what the table holds, whoever wrote it there
		return array_map(fn ($value) => unserialize( (string) $value, ['allowed_classes' => false]), $rows);
	}
}
