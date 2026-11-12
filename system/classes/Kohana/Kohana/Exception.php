<?php
/**
 * Kohana exception class. Translates exceptions using the [I18n] class.
 *
 * @package    Kohana
 * @category   Exceptions
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Kohana_Exception extends Exception {

	/**
	 * @var  array<int, string>  PHP error code => human readable name
	 */
	public static array $php_errors = [
		E_ERROR              => 'Fatal Error',
		E_CORE_ERROR         => 'Core Error',
		E_COMPILE_ERROR      => 'Compile Error',
		E_PARSE              => 'Parse Error',
		E_USER_ERROR         => 'User Error',
		E_RECOVERABLE_ERROR  => 'Recoverable Error',
		E_WARNING            => 'Warning',
		E_CORE_WARNING       => 'Core Warning',
		E_COMPILE_WARNING    => 'Compile Warning',
		E_USER_WARNING       => 'User Warning',
		E_NOTICE             => 'Notice',
		E_USER_NOTICE        => 'User Notice',
		E_DEPRECATED         => 'Deprecated',
		E_USER_DEPRECATED    => 'User Deprecated',
	];

	/**
	 * @var  array<int, int>  PHP errors that end the script before a trace can be
	 *              made: for these, no trace is shown rather than a false one
	 */
	public static array $fatal_errors = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE];

	/**
	 * @var  string  view of the error page shown while developing
	 */
	public static string $error_view = 'kohana/error';

	/**
	 * @var  string  view of the error page shown in production: it must
	 *               never say anything about the error or the application
	 */
	public static string $error_view_production = 'kohana/error_production';

	/**
	 * @var  string  error view content type
	 */
	public static string $error_view_content_type = 'text/html';

	/**
	 * @var  WeakMap<Throwable, string>|null  reference code of each error that was handled
	 */
	protected static ?WeakMap $_references = null;

	/**
	 * @var  WeakMap<Throwable, bool>|null  errors that have been written to the log
	 */
	protected static ?WeakMap $_logged = null;

	/**
	 * Creates a new translated exception.
	 *
	 *     throw new Kohana_Exception('Something went terrible wrong, :user',
	 *         array(':user' => $user));
	 *
	 * @param   string          $message    error message
	 * @param   array<string, mixed>           $variables  translation variables
	 * @param   int|string  $code       the exception code
	 * @param   Throwable       $previous   Previous throwable
	 * @return  void
	 */
	public function __construct(string $message = "", ?array $variables = null, int|string $code = 0, ?Throwable $previous = null)
	{
		// Set the message
		$message = I18n::get([$message, $variables]);

		// Pass the message and integer code to the parent
		parent::__construct($message, (int) $code, $previous);

		// Save the unmodified code
		// @link http://bugs.php.net/39615
		$this->code = $code;
	}

	/**
	 * Magic object-to-string method.
	 *
	 *     echo $exception;
	 *
	 * @uses    Kohana_Exception::text
	 * @return  string
	 */
	public function __toString(): string
	{
		return Kohana_Exception::text($this);
	}

	/**
	 * Inline exception handler, displays the error message, source of the
	 * exception, and the stack trace of the error.
	 *
	 * @param   Throwable  $t
	 * @return  never
	 * @uses    Kohana_Exception::_handler
	 */
	public static function handler(Throwable $t): never
	{
		$response = Kohana_Exception::_handler($t);

		// Whatever the page had produced so far is thrown away: the error
		// is shown instead of the page, not in the middle of it
		while (ob_get_level() > 0)
		{
			ob_end_clean();
		}

		// Send the response to the browser
		$response->send_headers();

		$body = $response->body();

		echo is_scalar($body) ? $body : '';

		exit(1);
	}

	/**
	 * Exception handler, logs the exception and generates a Response object
	 * for display.
	 *
	 * @param   Throwable  $t
	 * @return  Response
	 * @uses    Kohana_Exception::log
	 * @uses    Kohana_Exception::response
	 */
	public static function _handler(Throwable $t): Response
	{
		try
		{
			// Log the exception
			Kohana_Exception::log($t);

			// Generate the response
			return Kohana_Exception::response($t);
		}
		catch (Throwable $e)
		{
			// Showing the error failed as well. Say as little as possible,
			// in the simplest way there is.
			$response = Response::factory();
			$response->status(500);
			$response->headers('Content-Type', 'text/plain; charset='.Kohana::$charset);
			$response->body(Kohana::$environment === Kohana::PRODUCTION
				? 'An error occurred.'
				: Kohana_Exception::text($t)."\n".Kohana_Exception::text($e));

			return $response;
		}
	}

	/**
	 * Returns the reference code of an error: eight characters that are
	 * written to the log with the error and shown on the error page. A
	 * visitor can report the code, and the full error is then found by
	 * searching the log for it. The same error always gives the same code.
	 *
	 * @param   Throwable  $t
	 * @return  string
	 */
	public static function reference(Throwable $t): string
	{
		Kohana_Exception::$_references ??= new WeakMap;

		return Kohana_Exception::$_references[$t] ??= strtoupper(bin2hex(random_bytes(4)));
	}

	/**
	 * Logs an exception, with its reference code and the errors that caused it.
	 *
	 * @param   Throwable  $t
	 * @param   int        $level
	 * @return  void
	 * @uses    Kohana_Exception::text
	 */
	public static function log(Throwable $t, int $level = Log::EMERGENCY): void
	{
		Kohana_Exception::$_logged ??= new WeakMap;

		if ( ! is_object(Kohana::$log) OR isset(Kohana_Exception::$_logged[$t]))
			return;

		Kohana_Exception::$_logged[$t] = true;

		// The reference code comes first, so the line is found by searching for it
		$error = '['.Kohana_Exception::reference($t).'] '.Kohana_Exception::text($t);

		for ($previous = $t->getPrevious(), $i = 0; $previous !== null AND $i < 10; $previous = $previous->getPrevious(), $i++)
		{
			$error .= PHP_EOL.'Caused by: '.Kohana_Exception::text($previous);
		}

		// Add this exception to the log
		Kohana::$log->add($level, $error, null, ['exception' => $t]);

		// Make sure the logs are written
		Kohana::$log->write();
	}

	/**
	 * Get a single line of text representing the exception:
	 *
	 * Error [ Code ]: Message ~ File [ Line ]
	 *
	 * @param   Throwable  $t
	 * @return  string
	 */
	public static function text(Throwable $t): string
	{
		return sprintf('%s [ %s ]: %s ~ %s [ %d ]',
			get_class($t), $t->getCode(), strip_tags($t->getMessage()), Debug::path($t->getFile()), $t->getLine());
	}

	/**
	 * Describes any error in one standard form, whatever it is: an
	 * exception, a PHP error turned into one, a TypeError, a fatal error.
	 * Every output of an error (page, JSON, text, log) is made from this.
	 *
	 *     $error = Kohana_Exception::describe($e);
	 *     // class, code, message, file, line, fatal, trace, previous
	 *
	 * - `code` is the name of the error for PHP errors ("Warning"), the code otherwise.
	 * - `trace` is a list of steps, each with file, line, function, args
	 *   and app (is the file part of the application?). The steps of the
	 *   error handlers themselves are left out. It is empty for a fatal
	 *   error, where PHP provides none.
	 * - `previous` is the description of the error that caused this one, or null.
	 *
	 * @param   Throwable  $t
	 * @param   int        $depth  how many causes deep this description is (internal)
	 * @return  array<string, mixed>
	 * @uses    Debug::trace
	 */
	public static function describe(Throwable $t, int $depth = 0): array
	{
		$code  = $t->getCode();
		$file  = $t->getFile();
		$line  = $t->getLine();
		$trace = $t->getTrace();

		// Steps that belong to the handling of the error, not to the error
		$handlers = ['error_handler', 'shutdown_handler'];

		while (isset($trace[0]['class'], $trace[0]['function']) AND $trace[0]['class'] === 'Kohana_Core' AND in_array($trace[0]['function'], $handlers, true))
		{
			array_shift($trace);
		}

		// HTTP_Exceptions are created by HTTP_Exception::factory(): show the
		// place that called the factory, not the factory itself
		if ($t instanceof HTTP_Exception AND isset($trace[0]) AND $trace[0]['function'] === 'factory')
		{
			$frame = array_shift($trace);
			$file  = $frame['file'] ?? $file;
			$line  = $frame['line'] ?? $line;
		}

		$fatal = false;

		if ($t instanceof ErrorException)
		{
			// PHP gives no trace for an error that ends the script
			if ($fatal = in_array($code, Kohana_Exception::$fatal_errors, true))
			{
				$trace = [];
			}

			// PHP errors are numbered: use their name ("Warning", "Notice", ...)
			$code = Kohana_Exception::$php_errors[$code] ?? $code;
		}

		$steps = [];

		foreach (Debug::trace($trace, false) as $step)
		{
			// Is this step in the application's own code?
			$step['app'] = (is_string($step['file']) AND str_starts_with($step['file'], APPPATH));

			unset($step['source']);

			$steps[] = $step;
		}

		$previous = $t->getPrevious();

		return [
			'class'    => get_class($t),
			'code'     => $code,
			'message'  => $t->getMessage(),
			'file'     => $file,
			'line'     => $line,
			'fatal'    => $fatal,
			'trace'    => $steps,
			// Follow the chain of causes, but not without end
			'previous' => ($previous !== null AND $depth < 10) ? Kohana_Exception::describe($previous, $depth + 1) : null,
		];
	}

	/**
	 * Returns the complete description of an error as plain text: the
	 * error, where it happened, the steps that led to it, and its causes.
	 * Used on the command line and for the "copy as text" button of the
	 * error page.
	 *
	 * @param   Throwable|array<string, mixed>  $error  an error, or its description from describe()
	 * @return  string
	 */
	public static function plain(Throwable|array $error): string
	{
		if ( ! is_array($error))
		{
			$error = Kohana_Exception::describe($error);
		}

		$text = '';

		for ($caused_by = false; $error !== null; $error = $error['previous'], $caused_by = true)
		{
			/** @var array{class: string, code: int|string, message: string, file: string, line: int, fatal: bool, trace: array<int, array{file: string|null, line: int|null, function: string, args?: mixed}>, previous: array<string, mixed>|null} $error  see describe() */
			$text .= ($caused_by ? PHP_EOL.'Caused by: ' : '')
				.$error['class'].' [ '.$error['code'].' ]: '.strip_tags($error['message']).PHP_EOL
				.'  in '.Debug::path($error['file']).' [ '.$error['line'].' ]'.PHP_EOL;

			if ($error['fatal'])
			{
				$text .= '  (PHP gives no trace for this kind of error)'.PHP_EOL;
			}

			foreach ($error['trace'] as $i => $step)
			{
				$text .= '  #'.$i.' '
					.($step['file'] !== null ? Debug::path($step['file']).' [ '.$step['line'].' ]' : '{PHP internal call}')
					.' » '.$step['function'].'()'.PHP_EOL;
			}
		}

		return $text;
	}

	/**
	 * Tells in which form an error should be shown for the current request:
	 *
	 * - "text" on the command line
	 * - "json" for an AJAX request, or one that asks for JSON and not for HTML
	 * - "html" for everything else
	 *
	 * @return  string
	 */
	public static function format(): string
	{
		if (PHP_SAPI === 'cli')
			return 'text';

		// Both are sent by the browser: anything but text is ignored
		$accept = is_string($_SERVER['HTTP_ACCEPT'] ?? null) ? $_SERVER['HTTP_ACCEPT'] : '';
		$with   = is_string($_SERVER['HTTP_X_REQUESTED_WITH'] ?? null) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : '';

		if (strtolower($with) === 'xmlhttprequest'
			OR (str_contains($accept, 'application/json') AND ! str_contains($accept, 'text/html')))
			return 'json';

		return 'html';
	}

	/**
	 * Get a Response object representing the exception.
	 *
	 * While developing, the response describes the error completely. In
	 * production ([Kohana::$environment] is Kohana::PRODUCTION) it says
	 * nothing about the error, the code or the server: only that an error
	 * happened, and the reference code under which it is in the log.
	 *
	 * @param   Throwable    $t
	 * @param   string|null  $format  "html", "json" or "text"; null chooses by the request (see format())
	 * @return  Response
	 * @uses    Kohana_Exception::describe
	 */
	public static function response(Throwable $t, ?string $format = null): Response
	{
		$format   ??= Kohana_Exception::format();
		$status     = ($t instanceof HTTP_Exception) ? $t->getCode() : 500;
		$reference  = Kohana_Exception::reference($t);
		$production = (Kohana::$environment === Kohana::PRODUCTION);

		$response = Response::factory();
		$response->status($status);

		try
		{
			if ($production)
			{
				// The standard name of the status ("Not Found") for errors the
				// visitor caused; nothing at all about errors of the application
				$title = ($status >= 400 AND $status < 500) ? Response::$messages[$status] : 'An error occurred';

				$body = match ($format)
				{
					'json'  => json_encode(['error' => ['status' => $status, 'message' => $title, 'reference' => $reference]]),
					'text'  => $title.'. Reference: '.$reference.PHP_EOL,
					default => View::factory(Kohana_Exception::$error_view_production, compact('status', 'title', 'reference'))->render(),
				};
			}
			else
			{
				$error = Kohana_Exception::describe($t);

				$body = match ($format)
				{
					'json'  => json_encode(['error' => Kohana_Exception::_json($error) + ['status' => $status, 'reference' => $reference]], JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
					'text'  => Kohana_Exception::plain($error),
					default => View::factory(Kohana_Exception::$error_view, [
						'error'     => $error,
						'reference' => $reference,
						'status'    => $status,
						// The same values under the names older error views use
						'class'     => $error['class'],
						'code'      => $error['code'],
						'message'   => $error['message'],
						'file'      => $error['file'],
						'line'      => $error['line'],
						'trace'     => $t->getTrace(),
					])->render(),
				};
			}

			$response->headers('Content-Type', match ($format)
			{
				'json'  => 'application/json',
				'text'  => 'text/plain',
				default => Kohana_Exception::$error_view_content_type,
			}.'; charset='.Kohana::$charset);

			$response->body($body);
		}
		catch (Throwable $e)
		{
			// Things are going badly for us: fall back to a simple message
			$response->status(500);
			$response->headers('Content-Type', 'text/plain; charset='.Kohana::$charset);
			$response->body($production
				? 'An error occurred. Reference: '.$reference
				: Kohana_Exception::text($t)."\n".Kohana_Exception::text($e));
		}

		return $response;
	}

	/**
	 * Prepares the description of an error for JSON: the values passed to
	 * each step can be anything (objects, resources), so they are left out.
	 *
	 * @param   array<string, mixed>  $error  description from describe()
	 * @return  array<string, mixed>
	 */
	protected static function _json(array $error): array
	{
		/** @var array{class: string, code: int|string, message: string, file: string, line: int, fatal: bool, trace: array<int, array{file: string|null, line: int|null, function: string, args?: mixed}>, previous: array<string, mixed>|null} $error */
		$error['file'] = Debug::path($error['file']);

		foreach ($error['trace'] as $i => $step)
		{
			unset($step['args']);

			$step['file'] = ($step['file'] !== null) ? Debug::path($step['file']) : null;

			$error['trace'][$i] = $step;
		}

		if ($error['previous'] !== null)
		{
			$error['previous'] = Kohana_Exception::_json($error['previous']);
		}

		return $error;
	}

}
