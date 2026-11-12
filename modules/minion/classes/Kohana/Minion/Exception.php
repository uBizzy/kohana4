<?php
/**
 * @package    Kohana/Minion
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Minion_Exception extends Kohana_Exception {

	/**
	 * Inline exception handler, displays the error message, source of the
	 * exception, and the stack trace of the error.
	 *
	 * Should this display a stack trace? It's useful.
	 *
	 * @uses    Kohana_Exception::text
	 * @param   Throwable  $t
	 * @return  never
	 */
	public static function handler(Throwable $t): never
	{
		try
		{
			// Log the exception
			Kohana_Exception::log($t);

			$text = ($t instanceof Minion_Exception) ? $t->format_for_cli() : Kohana_Exception::text($t);

			if ($text !== '')
			{
				// In red, on the error output
				Minion_CLI::error($text);
			}

			$exit_code = $t->getCode();

			// A shell only keeps exit codes 1 to 255: anything else (0, a
			// text code, or 256 which the shell would read as 0) becomes 1,
			// so a failure is never reported as success.
			if ( ! is_int($exit_code) OR $exit_code < 1 OR $exit_code > 255)
			{
				$exit_code = 1;
			}

			exit($exit_code);
		}
		catch (Throwable $e)
		{
			// Clean the output buffer if one exists
			ob_get_level() and ob_clean();

			// Display the exception text
			fwrite(STDERR, Kohana_Exception::text($e)."\n");

			// Exit with an error status
			exit(1);
		}
	}

	public function format_for_cli(): string
	{
		return Kohana_Exception::text($this);
	}

}
