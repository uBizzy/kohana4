<?php
/**
 * Syslog log writer.
 *
 * @package    Kohana
 * @category   Logging
 * @author     Jeremy Bush
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Log_Syslog extends Log_Writer {

	/**
	 * @var  string  The syslog identifier
	 */
	protected string $_ident;

	/**
	 * Creates a new syslog logger.
	 *
	 * @link    http://www.php.net/manual/function.openlog
	 *
	 * @param   string  $ident      syslog identifier
	 * @param   int     $facility   facility to log to
	 * @return  void
	 */
	public function __construct(string $ident = 'KohanaPHP', int $facility = LOG_USER)
	{
		$this->_ident = $ident;

		// Open the connection to syslog
		openlog($this->_ident, LOG_CONS, $facility);
	}

	/**
	 * Writes each of the messages into the syslog.
	 *
	 * @param   array<int, array<string, mixed>>   $messages
	 * @return  void
	 */
	public function write(array $messages): void
	{
		foreach ($messages as $message)
		{
			/** @var array{time: int|string, level: int, body: string, additional?: array<string, mixed>|null} $message */
			syslog($message['level'], $message['body']);

			$exception = $message['additional']['exception'] ?? null;

			if ($exception instanceof Throwable)
			{
				syslog(Log_Writer::$strace_level, $exception->getTraceAsString());
			}
		}
	}

	/**
	 * Closes the syslog connection
	 *
	 * @return  void
	 */
	public function __destruct()
	{
		// Close connection to syslog
		closelog();
	}

}
