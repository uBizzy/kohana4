<?php
/**
 * Lists every migration and says if it was applied.
 *
 *     ./minion migration:status
 *
 * Options:
 *  --db  name of the database connection, the default one when left out
 *
 * @package    Kohana/Migration
 * @category   Tasks
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Task_Migration_Status extends Minion_Task {

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
		$runner  = Migration_Runner::factory(is_string($params['db']) ? $params['db'] : null);
		$pending = $runner->pending();
		$all     = $runner->all();

		$rows = [];

		foreach ($all as $name => $file)
		{
			$rows[] = [
				isset($pending[$name]) ? Minion_CLI::color('pending', 'yellow') : Minion_CLI::color('applied', 'green'),
				$name,
			];
		}

		if ($rows)
		{
			Minion_CLI::table($rows, ['Status', 'Migration']);
			Minion_CLI::write();
		}

		Minion_CLI::write(count($all).' '.Inflector::plural('migration', count($all)).', '.count($pending).' pending.');

		return null;
	}

}
