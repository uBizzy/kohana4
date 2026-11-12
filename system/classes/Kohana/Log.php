<?php
/**
 * Message logging with observer-based log writing.
 *
 * [!!] This class does not support extensions, only additional writers.
 *
 * @package    Kohana
 * @category   Logging
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Log {

	// Log message levels - Windows users see PHP Bug #18090
	const int EMERGENCY = LOG_EMERG;    // 0
	const int ALERT     = LOG_ALERT;    // 1
	const int CRITICAL  = LOG_CRIT;     // 2
	const int ERROR     = LOG_ERR;      // 3
	const int WARNING   = LOG_WARNING;  // 4
	const int NOTICE    = LOG_NOTICE;   // 5
	const int INFO      = LOG_INFO;     // 6
	const int DEBUG     = LOG_DEBUG;    // 7

	/**
	 * @var  bool  immediately write when logs are added
	 */
	public static bool $write_on_add = false;

	/**
	 * @var  Log  Singleton instance container
	 */
	protected static ?Log $_instance = null;

	/**
	 * Get the singleton instance of this class and enable writing at shutdown.
	 *
	 *     $log = Log::instance();
	 *
	 * @return  Log
	 */
	public static function instance(): Log
	{
		if (Log::$_instance === null)
		{
			// Create a new instance
			Log::$_instance = new Log;

			// Write the logs at shutdown
			register_shutdown_function([Log::$_instance, 'write']);
		}

		return Log::$_instance;
	}

	/**
	 * @var  array<int, array<string, mixed>>  list of added messages
	 */
	protected array $_messages = [];

	/**
	 * @var  array<string, array{object: Log_Writer, levels: array<int, int>}>  list of log writers
	 */
	protected array $_writers = [];

	/**
	 * Attaches a log writer, and optionally limits the levels of messages that
	 * will be written by the writer.
	 *
	 *     $log->attach($writer);
	 *
	 * @param   Log_Writer  $writer     instance
	 * @param   array<int, int>|int       $levels     array of messages levels to write OR max level to write
	 * @param   int     $min_level  min level to write IF $levels is not an array
	 * @return  static
	 */
	public function attach(Log_Writer $writer, array|int $levels = [], int $min_level = 0): static
	{
		if ( ! is_array($levels))
		{
			$levels = range($min_level, $levels);
		}

		$this->_writers["{$writer}"] = [
			'object' => $writer,
			'levels' => $levels
		];

		return $this;
	}

	/**
	 * Detaches a log writer. The same writer object must be used.
	 *
	 *     $log->detach($writer);
	 *
	 * @param   Log_Writer  $writer instance
	 * @return  static
	 */
	public function detach(Log_Writer $writer): static
	{
		// Remove the writer
		unset($this->_writers["{$writer}"]);

		return $this;
	}

	/**
	 * Adds a message to the log. Replacement values must be passed in to be
	 * replaced using [strtr](http://php.net/strtr).
	 *
	 *     $log->add(Log::ERROR, 'Could not locate user: :user', array(
	 *         ':user' => $username,
	 *     ));
	 *
	 * @param   int  $level       level of message
	 * @param   string  $message     message body
	 * @param   array<string, mixed>   $values      values to replace in the message
	 * @param   array<string, mixed>   $additional  additional custom parameters to supply to the log writer
	 * @return  static
	 */
	public function add(int $level, string $message, ?array $values = null, ?array $additional = null): static
	{
		if ($values)
		{
			// Insert the values into the message
			$message = strtr($message, $values);
		}

		// Where the message comes from, and how the code got there
		if (($additional['exception'] ?? null) instanceof Throwable)
		{
			// An error: the place it was thrown
			$trace = $additional['exception']->getTrace();
			$file  = $additional['exception']->getFile();
			$line  = $additional['exception']->getLine();
		}
		else
		{
			// The first step of the call stack is the call of this method:
			// its file and line are those of the code that logs
			$stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			$file  = $stack[0]['file'] ?? null;
			$line  = $stack[0]['line'] ?? null;
			$trace = array_slice($stack, 1);
		}

		$additional ??= [];

		// Create a new message
		$this->_messages[] = [
			'time'       => time(),
			'level'      => $level,
			'body'       => $message,
			'trace'      => $trace,
			'file'       => $file,
			'line'       => $line,
			'class'      => $trace[0]['class'] ?? null,
			'function'   => $trace[0]['function'] ?? null,
			'additional' => $additional,
		];

		if (Log::$write_on_add)
		{
			// Write logs as they are added
			$this->write();
		}

		return $this;
	}

	/**
	 * Write and clear all of the messages.
	 *
	 *     $log->write();
	 *
	 * @return  void
	 */
	public function write(): void
	{
		if (empty($this->_messages))
		{
			// There is nothing to write, move along
			return;
		}

		// Import all messages locally
		$messages = $this->_messages;

		// Reset the messages array
		$this->_messages = [];

		foreach ($this->_writers as $writer)
		{
			if (empty($writer['levels']))
			{
				// Write all of the messages
				$writer['object']->write($messages);
			}
			else
			{
				// Filtered messages
				$filtered = [];

				foreach ($messages as $message)
				{
					if (in_array($message['level'], $writer['levels']))
					{
						// Writer accepts this kind of message
						$filtered[] = $message;
					}
				}

				// Write the filtered messages
				$writer['object']->write($filtered);
			}
		}
	}

}
