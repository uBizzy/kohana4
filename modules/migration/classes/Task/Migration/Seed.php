<?php
/**
 * Fills the database with the data of the seed files, in the order of
 * their names.
 *
 *     ./minion migration:seed
 *     ./minion migration:seed --name=users
 *
 * Options:
 *  --db    name of the database connection, the default one when left out
 *  --name  run only the seed with this name
 *
 * @package    Kohana/Migration
 * @category   Tasks
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Task_Migration_Seed extends Minion_Task {

	/**
	 * @var  array<int|string, mixed>
	 */
	protected array $_options = [
		'db'   => null,
		'name' => null,
	];

	/**
	 * @param   array<int|string, mixed>  $params  options of the command line
	 * @return  mixed
	 */
	protected function _execute(array $params): mixed
	{
		$seeds = Migration_Runner::factory(is_string($params['db']) ? $params['db'] : null)
			->seed(is_string($params['name']) ? $params['name'] : null, fn (string $name) => Minion_CLI::write('seeded   '.$name));

		$seeds ? Minion_CLI::success(count($seeds).' '.Inflector::plural('seed', count($seeds)).' run.') : Minion_CLI::info('There are no seed files.');

		return null;
	}

}
