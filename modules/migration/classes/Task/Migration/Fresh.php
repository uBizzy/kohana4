<?php
/**
 * Drops every table of the database and applies all migrations again:
 * an empty, up-to-date database for development and tests.
 *
 *     ./minion migration:fresh
 *     ./minion migration:fresh --seed
 *
 * All data is lost, so the task asks first; with "--no-interaction" it
 * does not ask. It never runs in production.
 *
 * Options:
 *  --db    name of the database connection, the default one when left out
 *  --seed  run the seed files afterwards
 *
 * @package    Kohana/Migration
 * @category   Tasks
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Task_Migration_Fresh extends Minion_Task {

	/**
	 * @var  array<int|string, mixed>
	 */
	protected array $_options = [
		'db'   => null,
		'seed' => false,
	];

	/**
	 * @param   array<int|string, mixed>  $params  options of the command line
	 * @return  mixed
	 * @throws  Kohana_Exception  in production
	 */
	protected function _execute(array $params): mixed
	{
		// Refused before anything is asked
		if (Kohana::$environment === Kohana::PRODUCTION)
			throw new Kohana_Exception('The tables of a production database are never dropped');

		$db     = is_string($params['db']) ? $params['db'] : null;
		$runner = Migration_Runner::factory($db);

		if (Minion_CLI::$interactive AND ! Minion_CLI::confirm('Drop all tables of the "'.($db ?? Database::$default).'" database and lose their data?'))
		{
			Minion_CLI::info('Nothing was changed.');

			return null;
		}

		$done = $runner->fresh(fn (string $name) => Minion_CLI::write('applied  '.$name));

		Minion_CLI::success('Tables dropped, '.count($done).' '.Inflector::plural('migration', count($done)).' applied.');

		if ($params['seed'] !== false)
		{
			$seeds = $runner->seed(null, fn (string $name) => Minion_CLI::write('seeded   '.$name));

			Minion_CLI::success(count($seeds).' '.Inflector::plural('seed', count($seeds)).' run.');
		}

		return null;
	}

}
