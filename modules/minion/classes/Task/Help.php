<?php
/**
 * Lists all tasks with a short description.
 *
 * @package    Kohana
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Task_Help extends Minion_Task {

	/**
	 * Generates a help list for all tasks
	 *
	 * @param   array<int|string, mixed>  $params  options of the command line
	 * @return  mixed
	 */
	protected function _execute(array $params): mixed
	{
		$rows = [];

		foreach ($this->_compile_task_list(Kohana::list_files('classes/Task')) as $task)
		{
			$rows[] = [Minion_CLI::color($task, 'green'), Minion_Task::summary($task)];
		}

		$command = Minion_Task::command();

		Minion_CLI::title('Minion: tasks for the command line');
		Minion_CLI::write();
		Minion_CLI::write('Usage: '.$command.' {task} [--option=value ...]');
		Minion_CLI::write();
		Minion_CLI::table($rows, ['Task', 'Does']);
		Minion_CLI::write();
		Minion_CLI::write('Options of every task:');
		Minion_CLI::table([
			['  --help', 'Shows what the task does and its options'],
			['  --no-interaction', 'Asks no questions: for tasks that run unattended'],
			['  --no-color', 'Writes without colours'],
		]);

		return null;
	}

}
