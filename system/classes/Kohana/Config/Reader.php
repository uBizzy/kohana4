<?php

/**
 * Interface for config readers
 *
 * @package    Kohana
 * @category   Configuration
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
interface Kohana_Config_Reader extends Kohana_Config_Source
{

	/**
	 * Tries to load the specified configuration group
	 *
	 * Returns false if group does not exist or an array if it does
	 *
	 * @param  string $group Configuration group
	 * @return array<int|string, mixed>|false
	 */
	public function load(string $group): array|false;

}
