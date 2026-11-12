<?php
/**
 * Help of one task: its description (the doc comment of the class) and
 * the options it accepts with their defaults.
 */
echo Minion_CLI::style('Usage', 'bold'), PHP_EOL;
echo '  ', Minion_Task::command(), ' ', $task, ' [--option=value ...]', PHP_EOL, PHP_EOL;

echo Minion_CLI::style('Description', 'bold'), PHP_EOL;

foreach (explode("\n", $description) as $line)
{
	echo '  ', $line, PHP_EOL;
}

if ($options)
{
	echo PHP_EOL, Minion_CLI::style('Options and their defaults', 'bold'), PHP_EOL;

	foreach ($options as $name => $default)
	{
		echo '  --', $name, ($default === null ? '' : '='.var_export($default, true)), PHP_EOL;
	}
}
