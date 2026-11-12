<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the output helpers of Minion_CLI. The output is written to a
 * stream in memory, so it can be read back.
 *
 * @package    Kohana/Minion
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.minion')]
class Minion_CLITest extends Unittest_TestCase
{
	public function setUp(): void
	{
		parent::setUp();

		Minion_CLI::$stdout = fopen('php://memory', 'w+');
		Minion_CLI::$stderr = fopen('php://memory', 'w+');
		Minion_CLI::$colors = false;
		Minion_CLI::$interactive = true;
	}

	public function tearDown(): void
	{
		Minion_CLI::$stdout = null;
		Minion_CLI::$stderr = null;
		Minion_CLI::$colors = null;
		Minion_CLI::$interactive = true;

		parent::tearDown();
	}

	/**
	 * Returns what was written to the normal or the error output.
	 */
	protected function written(bool $error = false): string
	{
		return stream_get_contents($error ? Minion_CLI::$stderr : Minion_CLI::$stdout, -1, 0);
	}

	public function test_messages_go_to_the_right_output(): void
	{
		Minion_CLI::success('saved');
		Minion_CLI::info('reading');
		Minion_CLI::warning('skipped');
		Minion_CLI::error('failed');

		$this->assertSame("✔ saved\n› reading\n", str_replace(PHP_EOL, "\n", $this->written()));
		$this->assertSame("! skipped\n✖ failed\n", str_replace(PHP_EOL, "\n", $this->written(true)));
	}

	public function test_colors_can_be_switched_on_and_off(): void
	{
		Minion_CLI::$colors = true;
		$this->assertSame("\033[0;32mok\033[0m", Minion_CLI::color('ok', 'green'));
		$this->assertSame("\033[1;4mTitle\033[0m", Minion_CLI::style('Title', 'bold', 'underline'));

		Minion_CLI::$colors = false;
		$this->assertSame('ok', Minion_CLI::color('ok', 'green'));
		$this->assertSame('Title', Minion_CLI::style('Title', 'bold'));
	}

	public function test_no_colors_when_the_output_is_not_a_terminal(): void
	{
		// Decide by itself: the stream in memory is no terminal
		Minion_CLI::$colors = null;

		$this->assertFalse(Minion_CLI::supports_color());
		$this->assertSame('ok', Minion_CLI::color('ok', 'green'));
	}

	public function test_unknown_color_or_style_is_an_error_even_without_colors(): void
	{
		foreach ([fn () => Minion_CLI::color('x', 'no_such_color'), fn () => Minion_CLI::style('x', 'no_such_style')] as $call)
		{
			try
			{
				$call();

				$this->fail('No exception was thrown');
			}
			catch (Kohana_Exception $e)
			{
				$this->assertStringContainsString('Invalid CLI', $e->getMessage());
			}
		}
	}

	public function test_table_aligns_columns(): void
	{
		Minion_CLI::table([
			['name' => 'Ana', 'role' => 'admin'],
			['name' => 'Bruno', 'role' => 'editor'],
		]);

		$expected = "name   role\n"
			."-----  ------\n"
			."Ana    admin\n"
			."Bruno  editor\n";

		$this->assertSame($expected, str_replace(PHP_EOL, "\n", $this->written()));
	}

	public function test_table_with_own_titles_colors_and_wide_text(): void
	{
		Minion_CLI::$colors = true;

		Minion_CLI::table([
			[Minion_CLI::color('ok', 'green'), 'Zoë'],
			['failed', '日本'],
		], ['Status', 'Name']);

		// Without the colour codes every column lines up
		$plain = preg_replace('/\033\[[0-9;]*m/', '', str_replace(PHP_EOL, "\n", $this->written()));

		$this->assertSame("Status  Name\n------  ----\nok      Zoë\nfailed  日本\n", $plain);
	}

	public function test_table_without_titles(): void
	{
		Minion_CLI::table([['a', 'bbb'], ['cc', 'd']]);

		$this->assertSame("a   bbb\ncc  d\n", str_replace(PHP_EOL, "\n", $this->written()));
	}

	public function test_progress_writes_a_line_at_every_tenth_when_not_on_a_terminal(): void
	{
		for ($i = 1; $i <= 50; $i++)
		{
			Minion_CLI::progress($i, 50);
		}

		$lines = explode("\n", rtrim(str_replace(PHP_EOL, "\n", $this->written())));

		$this->assertCount(10, $lines);
		$this->assertSame(' 10% (5/50)', $lines[0]);
		$this->assertSame('100% (50/50)', $lines[9]);
	}

	public function test_title(): void
	{
		Minion_CLI::title('Import');

		$this->assertSame("Import\n======\n", str_replace(PHP_EOL, "\n", $this->written()));
	}

	public function test_without_interaction_confirm_gives_the_default(): void
	{
		Minion_CLI::$interactive = false;

		$this->assertFalse(Minion_CLI::confirm('Delete everything?'));
		$this->assertTrue(Minion_CLI::confirm('Continue?', true));

		// Nothing was asked
		$this->assertSame('', $this->written());
	}

	public function test_without_interaction_a_question_is_an_error(): void
	{
		Minion_CLI::$interactive = false;

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('--no-interaction');

		Minion_CLI::read('Your name');
	}

	public function test_global_options_are_not_passed_to_the_task(): void
	{
		$task = Minion_Task::factory(['task' => 'help', 'no-interaction' => null, 'no-color' => null]);

		$this->assertFalse(Minion_CLI::$interactive);
		$this->assertFalse(Minion_CLI::$colors);
		$this->assertArrayNotHasKey('no-interaction', $task->get_options());
		$this->assertArrayNotHasKey('no-color', $task->get_options());
	}

	public function test_task_summary_is_the_first_paragraph_of_its_comment(): void
	{
		$this->assertSame('Lists all tasks with a short description.', Minion_Task::summary('help'));
		$this->assertSame('', Minion_Task::summary('no:such:task'));
	}

	/**
	 * Task_Check is an empty class that extends Kohana_Task_Check, and an
	 * application extends it the same way: the description is the one of
	 * the class that has a comment.
	 */
	public function test_task_without_a_comment_has_the_summary_of_its_parent(): void
	{
		$this->assertStringStartsWith('Checks that the application can run here', Minion_Task::summary('check'));

		ob_start();
		Minion_Task::factory(['task' => 'check', 'help' => null])->execute();
		$help = (string) ob_get_clean();

		$this->assertStringContainsString('Checks that the application can run here', $help);
	}

	public function test_help_lists_tasks_with_their_summary(): void
	{
		Minion_Task::factory(['task' => 'help'])->execute();

		$output = $this->written();

		$this->assertMatchesRegularExpression('/^help\s+Lists all tasks with a short description\.$/m', $output);
		$this->assertStringContainsString('--no-interaction', $output);
	}

	public function test_invalid_options_end_the_task_with_an_error(): void
	{
		try
		{
			Minion_Task::factory(['task' => 'help', 'bogus' => '1'])->execute();

			$this->fail('No exception was thrown');
		}
		catch (Minion_Exception_InvalidOptions $e)
		{
			$this->assertStringContainsString('help --help', $e->getMessage());
		}

		$this->assertStringContainsString('--bogus', $this->written(true));
	}

	public function test_unknown_task_is_named_in_the_error(): void
	{
		$this->expectException(Minion_Exception_InvalidTask::class);
		$this->expectExceptionMessage('There is no task "no:such:task"');

		Minion_Task::factory(['task' => 'no:such:task']);
	}
}
