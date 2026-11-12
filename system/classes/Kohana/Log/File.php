<?php
/**
 * File log writer. Writes out messages and stores them in a YYYY/MM directory.
 *
 * @package    Kohana
 * @category   Logging
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Log_File extends Log_Writer {

	/**
	 * @var  string  Directory to place log files in
	 */
	protected string $_directory;

	/**
	 * @var  int  Permissions of the year and month directories: owner and
	 *            group may write, others may only read. The leading 2 makes
	 *            new files inherit the directory's group.
	 */
	public static int $directory_mode = 02775;

	/**
	 * @var  int  Permissions of the log files: owner and group may write.
	 *            If the web server and command-line scripts run as users of
	 *            different groups, put both in one group or loosen this.
	 */
	public static int $file_mode = 0664;

	/**
	 * @var  string  First line of every log file. Log files have a .php
	 *               extension and contain text that visitors can influence
	 *               (URLs, user agents). If a log file is ever requested
	 *               directly through the web server, this line stops PHP
	 *               before anything in the file can run.
	 */
	const string FILE_SECURITY = "<?php defined('SYSPATH') OR die('No direct script access.'); ?>";

	/**
	 * Creates a new file logger. Checks that the directory exists and
	 * is writable.
	 *
	 *     $writer = new Log_File($directory);
	 *
	 * @param   string  $directory  log directory
	 * @return  void
	 */
	public function __construct(string $directory)
	{
		if ( ! is_dir($directory) OR ! is_writable($directory))
		{
			throw new Kohana_Exception('Directory :dir must be writable',
				[':dir' => Debug::path($directory)]);
		}

		// Determine the directory path
		$this->_directory = realpath($directory).DIRECTORY_SEPARATOR;
	}

	/**
	 * Writes each of the messages into the log file. The log file will be
	 * appended to the `YYYY/MM/DD.log.php` file, where YYYY is the current
	 * year, MM is the current month, and DD is the current day.
	 *
	 *     $writer->write($messages);
	 *
	 * @param   array<int, array<string, mixed>>   $messages
	 * @return  void
	 */
	public function write(array $messages): void
	{
		// Logs are stored as <directory>/<year>/<month>/<day>.php
		$directory = $this->_directory.date('Y');
		$this->_make_directory($directory);

		$directory .= DIRECTORY_SEPARATOR.date('m');
		$this->_make_directory($directory);

		// Set the name of the log file
		$filename = $directory.DIRECTORY_SEPARATOR.date('d').EXT;

		if ( ! file_exists($filename))
		{
			// Create the log file, starting with the line that protects it
			file_put_contents($filename, Log_File::FILE_SECURITY.PHP_EOL);

			// Set permissions (must be manually set to fix umask issues)
			chmod($filename, Log_File::$file_mode);
		}

		foreach ($messages as $message)
		{
			// Write each message into the log file
			file_put_contents($filename, PHP_EOL.$this->format_message($message), FILE_APPEND);
		}
	}

	/**
	 * Creates a log directory if it does not exist yet.
	 *
	 * @param   string  $directory  full path of the directory
	 * @return  void
	 */
	protected function _make_directory(string $directory): void
	{
		if (is_dir($directory))
			return;

		mkdir($directory, Log_File::$directory_mode);

		// Set permissions (must be manually set to fix umask issues)
		chmod($directory, Log_File::$directory_mode);
	}

}
