<?php
/**
 * Log writer abstract class. All [Log] writers must extend this class.
 *
 * @package    Kohana
 * @category   Logging
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Log_Writer {

	/**
	 * @var  string  timestamp format for log entries.
	 *
	 * Defaults to Date::$timestamp_format
	 */
	public static ?string $timestamp = null;

	/**
	 * @var  string  timezone for log entries
	 *
	 * Defaults to Date::$timezone, which defaults to date_default_timezone_get()
	 */
	public static ?string $timezone = null;

	/**
	 * Numeric log level to string lookup table.
	 * @var array<int, string>
	 */
	protected array $_log_levels = [
		LOG_EMERG   => 'EMERGENCY',
		LOG_ALERT   => 'ALERT',
		LOG_CRIT    => 'CRITICAL',
		LOG_ERR     => 'ERROR',
		LOG_WARNING => 'WARNING',
		LOG_NOTICE  => 'NOTICE',
		LOG_INFO    => 'INFO',
		LOG_DEBUG   => 'DEBUG',
	];

	/**
	 * @var  int  Level to use for stack traces
	 */
	public static int $strace_level = LOG_DEBUG;

	/**
	 * Write an array of messages.
	 *
	 *     $writer->write($messages);
	 *
	 * @param   array<int, array<string, mixed>>   $messages
	 * @return  void
	 */
	abstract public function write(array $messages): void;

	/**
	 * Allows the writer to have a unique key when stored.
	 *
	 *     echo $writer;
	 *
	 * @return  string
	 */
	final public function __toString(): string
	{
		return spl_object_hash($this);
	}

	/**
	 * Formats a log entry.
	 *
	 * @param   array<string, mixed>   $message
	 * @param   string  $format
	 * @return  string
	 */
	public function format_message(array $message, string $format = "time --- level: body in file:line"): string
	{
		/** @var array{time: int|string, level: int, body: string, additional?: array<string, mixed>|null} $message  a message as Log hands it over */
		$exception = $message['additional']['exception'] ?? null;

		$message['time'] = Date::formatted_time('@'.$message['time'], Log_Writer::$timestamp, Log_Writer::$timezone);
		$message['level'] = $this->_log_levels[$message['level']];

		$string = strtr($format, array_filter($message, 'is_scalar'));

		if ($exception instanceof Throwable)
		{
			// Re-use as much as possible, just resetting the body to the trace
			$message['body'] = $exception->getTraceAsString();
			$message['level'] = $this->_log_levels[Log_Writer::$strace_level];

			$string .= PHP_EOL.strtr($format, array_filter($message, 'is_scalar'));
		}

		return $string;
	}

}
