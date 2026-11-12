<?php

/**
 * Database writer for the config system
 *
 * Schema for configuration table:
 *
 *    CREATE TABLE IF NOT EXISTS `config` (
 *      `group_name` varchar(128) NOT NULL,
 *      `config_key` varchar(128) NOT NULL,
 *      `config_value` text,
 *       PRIMARY KEY (`group_name`,`config_key`)
 *     ) ENGINE=InnoDB;
 *
 * @package    Kohana
 * @category   Configuration
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Config_Database_Writer extends Config_Database_Reader implements Kohana_Config_Writer
{
	/**
	 * @var  array<string, array<int|string, int|string>>  the keys that were loaded, by group
	 */
	protected array $_loaded_keys = [];

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
		$config = parent::load($group);

		if ($config !== false)
		{
			$this->_loaded_keys[$group] = array_combine(array_keys($config), array_keys($config));
		}

		return $config;
	}

	/**
	 * Writes the passed config for $group
	 *
	 * Returns chainable instance on success or throws
	 * Kohana_Config_Exception on failure
	 *
	 * @param string      $group  The config group
	 * @param string      $key    The config key to write to
	 * @param mixed       $config The configuration to write
	 * @return bool
	 */
	public function write(string $group, string|int $key, mixed $config): bool
	{
		$config = serialize($config);

		// Check to see if we've loaded the config from the table already
		if (isset($this->_loaded_keys[$group][$key]))
		{
			$this->_update($group, $key, $config);
		}
		else
		{
			// Attempt to run an insert query
			// This may fail if the config key already exists in the table
			// and we don't know about it
			try
			{
				$this->_insert($group, $key, $config);
			}
			catch (Database_Exception $e)
			{
				// Attempt to run an update instead
				$this->_update($group, $key, $config);
			}
		}

		return true;
	}

	/**
	 * Insert the config values into the table
	 *
	 * @param string      $group  The config group
	 * @param string      $key    The config key to write to
	 * @param string      $config The serialized configuration to write
	 * @return static
	 */
	protected function _insert(string $group, string|int $key, mixed $config): static
	{
		DB::insert($this->_table_name, ['group_name', 'config_key', 'config_value'])
			->values([$group, $key, $config])
			->execute($this->_db_instance);

		return $this;
	}

	/**
	 * Update the config values in the table
	 *
	 * @param string      $group  The config group
	 * @param string      $key    The config key to write to
	 * @param string      $config The serialized configuration to write
	 * @return static
	 */
	protected function _update(string $group, string|int $key, mixed $config): static
	{
		DB::update($this->_table_name)
			->set(['config_value' => $config])
			->where('group_name', '=', $group)
			->where('config_key', '=', $key)
			->execute($this->_db_instance);

		return $this;
	}
}
