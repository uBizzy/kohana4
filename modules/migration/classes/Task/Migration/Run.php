<?php
/**
 * Applies every pending migration, oldest first.
 *
 *     ./minion migration:run
 *
 * Options:
 *  --db  name of the database connection, the default one when left out
 *
 * @package    Kohana/Migration
 * @category   Tasks
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Task_Migration_Run extends Minion_Task {

	/**
	 * @var  array<int|string, mixed>
	 */
	protected array $_options = [
		'db' => null,
	];

	/**
	 * @param   array<int|string, mixed>  $params  options of the command line
	 * @return  mixed
	 */
	protected function _execute(array $params): mixed
	{
		// Each migration is reported as soon as it is done
		$done = Migration_Runner::factory(is_string($params['db']) ? $params['db'] : null)->run(fn (string $name) => Minion_CLI::write('applied  '.$name));

		$done ? Minion_CLI::success(count($done).' '.Inflector::plural('migration', count($done)).' applied.') : Minion_CLI::info('Nothing to apply.');

		return null;
	}

}
