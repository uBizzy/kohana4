<?php
/**
 * Writes log messages to files as one JSON object on each line: the
 * same messages as [Log_File], in a form that programs can read without
 * guessing. Use it beside the text log, or in its place.
 *
 *     Kohana::$log->attach(new Log_JSON(APPPATH.'logs'));
 *
 * A line holds the time, the level, the message, where it was logged,
 * and, for an error, its reference code, class and trace:
 *
 *     {"time":"2026-11-12T10:15:02+00:00","level":"ERROR","message":"...","file":"...","line":42,
 *      "request":"3f9a1c0e","method":"GET","uri":"articles/7","reference":"A1B2C3D4","exception":"...","trace":"..."}
 *
 * Every line of one request carries the same "request" code, so all
 * that happened in a request can be put together again.
 *
 * The files are named like those of the text log, with ".json.php" at
 * the end: logs/2026/11/12.json.php. They start with the same line that
 * stops PHP, so a log can never be shown by the web server.
 *
 * [!!] The address of the visitor is not written. A message holds what
 * the code that logged it put in: keep passwords and keys out of those.
 *
 * @package    Kohana
 * @category   Logging
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Log_JSON extends Log_File {

	/**
	 * @var  string|null  The code of this request, made on first use
	 */
	protected static ?string $_request = null;

	/**
	 * Returns the code that every log line of this request carries:
	 * eight characters, made once for each request.
	 *
	 * @return  string
	 */
	public static function request_id(): string
	{
		return static::$_request ??= bin2hex(random_bytes(4));
	}

	/**
	 * Writes each message as a line of JSON.
	 *
	 * @param   array<int, array<string, mixed>>  $messages
	 * @return  void
	 */
	public function write(array $messages): void
	{
		// Logs are stored as <directory>/<year>/<month>/<day>.json.php
		$directory = $this->_directory.date('Y');

		$this->_make_directory($directory);

		$directory .= DIRECTORY_SEPARATOR.date('m');

		$this->_make_directory($directory);

		$filename = $directory.DIRECTORY_SEPARATOR.date('d').'.json'.EXT;

		if ( ! file_exists($filename))
		{
			// Create the log file, starting with the line that protects it
			file_put_contents($filename, Log_File::FILE_SECURITY.PHP_EOL);

			// Set permissions (must be manually set to fix umask issues)
			chmod($filename, Log_File::$file_mode);
		}

		$lines = '';

		foreach ($messages as $message)
		{
			$lines .= json_encode($this->_entry($message), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR).PHP_EOL;
		}

		// All lines at once, with a lock: two requests never write into each other's line
		file_put_contents($filename, $lines, FILE_APPEND | LOCK_EX);
	}

	/**
	 * Returns what is written for a message.
	 *
	 * @param   array<string, mixed>  $message  a message of [Log::add]
	 * @return  array<string, mixed>
	 */
	protected function _entry(array $message): array
	{
		$time  = is_numeric($message['time'] ?? null) ? (int) $message['time'] : time();
		$level = is_int($message['level'] ?? null) ? $message['level'] : LOG_INFO;
		$body  = is_scalar($message['body'] ?? null) ? (string) $message['body'] : '';

		$entry = [
			'time'    => date(DATE_ATOM, $time),
			'level'   => $this->_log_levels[$level] ?? 'INFO',
			'message' => $body,
			'file'    => is_string($message['file'] ?? null) ? Debug::path($message['file']) : null,
			'line'    => is_int($message['line'] ?? null) ? $message['line'] : null,
			'request' => static::request_id(),
		];

		$request = Request::$initial;

		if ($request instanceof Request)
		{
			$entry['method'] = $request->method();
			$entry['uri']    = $request->uri();
		}
		elseif (PHP_SAPI === 'cli')
		{
			$entry['method'] = 'CLI';
		}

		$additional = is_array($message['additional'] ?? null) ? $message['additional'] : [];
		$exception  = $additional['exception'] ?? null;

		if ($exception instanceof Throwable)
		{
			$entry['reference'] = Kohana_Exception::reference($exception);
			$entry['exception'] = get_class($exception);
			$entry['trace']     = $exception->getTraceAsString();
		}
		elseif (preg_match('/^\[([0-9A-F]{8})\] /', $body, $match))
		{
			// The reference code that Kohana_Exception::log() puts first
			$entry['reference'] = $match[1];
		}

		return $entry;
	}

}
