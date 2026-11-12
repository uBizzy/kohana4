<?php
/**
 * Undoes the newest applied migration.
 *
 *     ./minion migration:rollback
 *     ./minion migration:rollback --steps=3
 *
 * Options:
 *  --steps  how many migrations to undo, 1 when left out
 *  --db     name of the database connection, the default one when left out
 *
 * @package    Kohana/Migration
 * @category   Tasks
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Task_Migration_Rollback extends Minion_Task {

	/**
	 * @var  array<int|string, mixed>
	 */
	protected array $_options = [
		'db'    => null,
		'steps' => 1,
	];

	/**
	 * Adds the rule for the "steps" option.
	 *
	 * @param   Validation  $validation
	 * @return  Validation
	 */
	public function build_validation(Validation $validation): Validation
	{
		return parent::build_validation($validation)
			->rule('steps', 'digit');
	}

	/**
	 * @param   array<int|string, mixed>  $params  options of the command line
	 * @return  mixed
	 */
	protected function _execute(array $params): mixed
	{
		$done = Migration_Runner::factory(is_string($params['db']) ? $params['db'] : null)->rollback(is_numeric($params['steps']) ? (int) $params['steps'] : 1, fn (string $name) => Minion_CLI::write('undone   '.$name));

		$done ? Minion_CLI::success(count($done).' '.Inflector::plural('migration', count($done)).' undone.') : Minion_CLI::info('Nothing to undo.');

		return null;
	}

}
