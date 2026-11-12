<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the log writer that writes one JSON object on each line.
 *
 * @package    Kohana
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.log')]
class Kohana_LogJSONTest extends Unittest_TestCase
{
	/**
	 * @var string  Directory of the logs of one test
	 */
	protected $directory;

	public function setUp(): void
	{
		parent::setUp();

		$this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kohana_log_json_'.bin2hex(random_bytes(6));

		mkdir($this->directory);

		$this->setEnvironment(['Request::$initial' => null]);
	}

	public function tearDown(): void
	{
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file)
		{
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}

		rmdir($this->directory);

		parent::tearDown();
	}

	/**
	 * Returns a log that writes to the test directory.
	 */
	protected function log(): Log
	{
		$log = new Log;
		$log->attach(new Log_JSON($this->directory));

		return $log;
	}

	/**
	 * Returns the lines of today's log file, decoded.
	 */
	protected function lines(): array
	{
		$lines = file($this->directory.date('/Y/m/d').'.json.php', FILE_IGNORE_NEW_LINES);

		// The first line stops PHP
		$this->assertSame(Log_File::FILE_SECURITY, array_shift($lines));

		return array_map(fn ($line) => json_decode($line, true, 16, JSON_THROW_ON_ERROR), $lines);
	}

	public function test_a_message_is_one_line_of_json(): void
	{
		$log = $this->log();

		$line = __LINE__ + 2;

		$log->add(Log::INFO, 'User :name logged in', [':name' => 'Ana']);
		$log->add(Log::WARNING, "Two\nlines, \"quotes\" and olá");
		$log->write();

		$lines = $this->lines();

		$this->assertCount(2, $lines);

		$this->assertSame('INFO', $lines[0]['level']);
		$this->assertSame('User Ana logged in', $lines[0]['message']);
		$this->assertSame($line, $lines[0]['line'], 'The line of the code that logged the message');
		$this->assertStringEndsWith('LogJSONTest.php', $lines[0]['file']);
		$this->assertEqualsWithDelta(time(), strtotime($lines[0]['time']), 5);
		$this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d[+-]\d\d:\d\d$/', $lines[0]['time']);

		$this->assertSame('WARNING', $lines[1]['level']);
		$this->assertSame("Two\nlines, \"quotes\" and olá", $lines[1]['message']);
		$this->assertArrayNotHasKey('reference', $lines[1]);
	}

	/**
	 * Every line of one request carries the same code, and says which
	 * request it was.
	 */
	public function test_lines_of_one_request_belong_together(): void
	{
		$log = $this->log();

		$log->add(Log::INFO, 'Before the request');
		$log->write();

		Request::$initial = Request::factory('articles/view/7')->method('POST');

		$log->add(Log::INFO, 'In the request');
		$log->add(Log::ERROR, 'Still in it');
		$log->write();

		$lines = $this->lines();

		$this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $lines[0]['request']);
		$this->assertSame([$lines[0]['request']], array_values(array_unique(array_column($lines, 'request'))));
		$this->assertSame(Log_JSON::request_id(), $lines[0]['request']);

		$this->assertSame('CLI', $lines[0]['method']);
		$this->assertSame('POST', $lines[1]['method']);
		$this->assertSame('articles/view/7', $lines[1]['uri']);
	}

	/**
	 * An error is written with its reference code, its class and its trace.
	 */
	public function test_an_error_has_its_code_and_trace(): void
	{
		$this->setEnvironment(['Kohana::$log' => $this->log()]);

		$error = new Kohana_Exception('The payment of :who failed', [':who' => 'Ana']);

		Kohana_Exception::log($error);

		$lines = $this->lines();

		$this->assertCount(1, $lines);
		$this->assertSame(Kohana_Exception::reference($error), $lines[0]['reference']);
		$this->assertSame('Kohana_Exception', $lines[0]['exception']);
		$this->assertStringContainsString('The payment of Ana failed', $lines[0]['message']);
		$this->assertStringStartsWith('#0 ', $lines[0]['trace']);

		// The place the error was made, not a place that called it
		$this->assertStringEndsWith('LogJSONTest.php', $lines[0]['file']);
		$this->assertSame($error->getLine(), $lines[0]['line']);
		$this->assertStringContainsString('test_an_error_has_its_code_and_trace', $lines[0]['trace']);
	}

	/**
	 * A message that is not valid text still gives a line that can be read.
	 */
	public function test_bytes_that_are_no_text_do_not_break_the_line(): void
	{
		$log = $this->log();

		$log->add(Log::NOTICE, "Bad bytes: \xB1\x31 end");
		$log->write();

		$lines = $this->lines();

		$this->assertStringStartsWith('Bad bytes: ', $lines[0]['message']);
		$this->assertStringEndsWith(' end', $lines[0]['message']);
	}

	/**
	 * The JSON log can be written beside the text log.
	 */
	public function test_beside_the_text_log(): void
	{
		$log = $this->log();
		$log->attach(new Log_File($this->directory));

		$log->add(Log::INFO, 'In both');
		$log->write();

		$this->assertCount(1, $this->lines());
		$this->assertStringContainsString('INFO: In both', file_get_contents($this->directory.date('/Y/m/d').'.php'));
	}
}
