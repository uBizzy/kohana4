<?php

/**
 * Interface for config writers
 *
 * Specifies the methods that a config writer must implement
 *
 * @package Kohana
 * ohana Team
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
interface Kohana_Config_Writer extends Kohana_Config_Source
{
	/**
	 * Writes the passed config for $group
	 *
	 * Returns chainable instance on success or throws
	 * Kohana_Config_Exception on failure
	 *
	 * @param string      $group  The config group
	 * @param string      $key    The config key to write to
	 * @param mixed       $config The value to write
	 * @return bool
	 */
	public function write(string $group, string|int $key, mixed $config): bool;

}
