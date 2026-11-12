<?php

/**
 * @package    Kohana/Minion
 * 
 * @copyright  (c) 2015 Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Minion_CLI {

	public static string $wait_msg = 'Press any key to continue...';
	public static string $invalid_option_msg = 'This is not a valid option. Please try again.';

	/**
	 * @var  bool  May the task ask questions? Switched off by the option
	 *             "--no-interaction", for tasks that run unattended.
	 */
	public static bool $interactive = true;

	/**
	 * @var  bool|null  Use colours? null decides by itself: colours on a
	 *                  terminal, none in a file or a pipe. Switched off by
	 *                  the option "--no-color" and the NO_COLOR variable.
	 */
	public static ?bool $colors = null;

	/**
	 * @var  resource|null  Stream that normal output is written to, STDOUT when null
	 */
	public static mixed $stdout = null;

	/**
	 * @var  resource|null  Stream that errors and warnings are written to, STDERR when null
	 */
	public static mixed $stderr = null;

	/**
	 * @var  array<string, array{string, string}>  Mark and colour of each kind of message
	 */
	public static array $messages = [
		'success' => ['✔', 'green'],
		'error'   => ['✖', 'red'],
		'warning' => ['!', 'yellow'],
		'info'    => ['›', 'cyan'],
	];

	/**
	 * @var  array<string, string>  Text styles for [Minion_CLI::style]
	 */
	protected static array $styles = [
		'bold'      => '1',
		'dim'       => '2',
		'italic'    => '3',
		'underline' => '4',
	];

	/**
	 * @var  array<string, string>
	 */
	protected static array $foreground_colors = [
		'black'        => '0;30',
		'dark_gray'    => '1;30',
		'blue'         => '0;34',
		'light_blue'   => '1;34',
		'green'        => '0;32',
		'light_green'  => '1;32',
		'cyan'         => '0;36',
		'light_cyan'   => '1;36',
		'red'          => '0;31',
		'light_red'    => '1;31',
		'purple'       => '0;35',
		'light_purple' => '1;35',
		'brown'        => '0;33',
		'yellow'       => '1;33',
		'light_gray'   => '0;37',
		'white'        => '1;37',

		// The names most terminals and tools use today
		'magenta'       => '0;35',
		'light_magenta' => '1;35',
		'gray'          => '0;90',
	];
	/**
	 * @var  array<string, string>
	 */
	protected static array $background_colors = [
		'black'      => '40',
		'red'        => '41',
		'green'      => '42',
		'yellow'     => '43',
		'blue'       => '44',
		'magenta'    => '45',
		'cyan'       => '46',
		'light_gray' => '47',
	];

	/**
	 * Returns one or more command-line options. Options are specified using
	 * standard CLI syntax:
	 *
	 *     php index.php --username=john.smith --password=secret --var="some value with spaces"
	 *
	 *     // Get the values of "username" and "password"
	 *     $auth = Minion_CLI::options('username', 'password');
	 *
	 * @param   string  $options,...    option name
	 * @return  mixed  the values by name; the value itself when one name is given
	 */
	public static function options(string ...$options): mixed
	{
		// Found option values
		$values = [];

		// Skip the first option, it is always the file executed
		foreach (array_slice( (array) ($_SERVER['argv'] ?? []), 1) as $opt)
		{
			if ( ! is_string($opt))
				continue;

			if (substr($opt, 0, 2) !== '--')
			{
				// This is a positional argument
				$values[] = $opt;
				continue;
			}

			// Remove the "--" prefix
			$opt = substr($opt, 2);

			if (strpos($opt, '='))
			{
				// Separate the name and value
				list ($opt, $value) = explode('=', $opt, 2);
			}
			else
			{
				$value = null;
			}

			$values[$opt] = $value;
		}

		if ($options)
		{
			foreach ($values as $opt => $value)
			{
				if ( ! in_array($opt, $options))
				{
					// Set the given value
					unset($values[$opt]);
				}
			}
		}

		return count($options) == 1 ? array_pop($values) : $values;
	}

	/**
	 * Reads input from the user. This can have either 1 or 2 arguments.
	 *
	 * Usage:
	 *
	 * // Takes any input
	 * $color = Minion_CLI::read('What is your favorite color?');
	 *
	 * // Will only accept the options in the array
	 * $ready = Minion_CLI::read('Are you ready?', ['y','n']);
	 *
	 * @param  string  $text    text to show user before waiting for input
	 * @param  array<int, string>   $options array of options the user is shown
	 * @return string  the user input
	 */
	public static function read(string $text = '', ?array $options = null): string
	{
		// If a question has been asked with the read
		if ( ! empty($options))
		{
			$text .= ' [ '.implode(', ', $options).' ]';
		}
		if ($text != '')
		{
			$text .= ': ';
		}

		Minion_CLI::_need_interaction($text);

		fwrite(Minion_CLI::_stream(), $text);

		// Read the input from keyboard.
		$input = trim( (string) fgets(STDIN));

		// If options are provided and the choice is not in the array, tell them to try again
		if ( ! empty($options) && ! in_array($input, $options))
		{
			Minion_CLI::write(Minion_CLI::$invalid_option_msg);

			$input = Minion_CLI::read($text, $options);
		}

		// Read the input
		return $input;
	}

	/**
	 * Experimental feature.
	 *
	 * Reads hidden input from the user
	 *
	 * Usage:
	 *
	 * $password = Minion_CLI::password('Enter your password');
	 *
	 * @author Mathew Davies.
	 * @return string
	 */
	public static function password(string $text = ''): string
	{
		$text .= ': ';

		Minion_CLI::_need_interaction($text);

		fwrite(Minion_CLI::_stream(), $text);

		if (Kohana::$is_windows)
		{
			// Windows has no simple, safe way to hide typing in a console,
			// so the password is read as normal input and the user is told.
			Minion_CLI::write('(the password will be visible while you type)');

			$password = fgets(STDIN);
		}
		else
		{

			// Switch the echo of typed characters off while the password is
			// read. The prompt text is never part of a shell command.
			shell_exec('stty -echo');
			$password = fgets(STDIN);
			shell_exec('stty echo');
		}

		Minion_CLI::write();

		return trim( (string) $password);
	}

	/**
	 * Outputs a string to the cli. If you send an array it will implode them
	 * with a line break.
	 *
	 * @param string|array<int, string> $text the text to output, or array of lines
	 */
	public static function write(string|array $text = ''): void
	{
		if (is_array($text))
		{
			foreach ($text as $line)
			{
				Minion_CLI::write($line);
			}
		}
		else
		{
			fwrite(Minion_CLI::_stream(), $text.PHP_EOL);
		}
	}

	/**
	 * Outputs a replacable line to the cli. You can continue replacing the
	 * line until `true` is passed as the second parameter in order to indicate
	 * you are done modifying the line.
	 *
	 *     // Sample progress indicator
	 *     Minion_CLI::write_replace('0%');
	 *     Minion_CLI::write_replace('25%');
	 *     Minion_CLI::write_replace('50%');
	 *     Minion_CLI::write_replace('75%');
	 *     // Done writing this line
	 *     Minion_CLI::write_replace('100%', true);
	 *
	 * @param string  $text      the text to output
	 * @param bool $end_line  whether the line is done being replaced
	 */
	public static function write_replace(string $text = '', bool $end_line = false): void
	{
		// Append a newline if $end_line is true
		$text = $end_line ? $text.PHP_EOL : $text;

		if (Kohana::$is_windows)
		{
			fwrite(Minion_CLI::_stream(), "\r".$text);
		}
		else
		{
			fwrite(Minion_CLI::_stream(), "\r\033[K".$text);
		}
	}

	/**
	 * Waits a certain number of seconds, optionally showing a wait message and
	 * waiting for a key press.
	 *
	 * @author     Fuel Development Team
	 * @license    MIT License
	 * @copyright  2010 - 2011 Fuel Development Team
	 * @link       http://fuelphp.com
	 * @param int $seconds number of seconds
	 * @param bool $countdown show a countdown or not
	 */
	public static function wait(int $seconds = 0, bool $countdown = false): void
	{
		if ($countdown === true)
		{
			$time = $seconds;

			while ($time > 0)
			{
				fwrite(Minion_CLI::_stream(), $time.'... ');
				sleep(1);
				--$time;
			}

			Minion_CLI::write();
		}
		else
		{
			if ($seconds > 0)
			{
				sleep($seconds);
			}
			elseif (Minion_CLI::$interactive)
			{
				Minion_CLI::write(Minion_CLI::$wait_msg);
				Minion_CLI::read();
			}
		}
	}

	/**
	 * Returns the given text with the correct color codes for a foreground and
	 * optionally a background color.
	 *
	 * @author     Fuel Development Team
	 * @license    MIT License
	 * @copyright  2010 - 2011 Fuel Development Team
	 * @link       http://fuelphp.com
	 * @param string $text the text to color
	 * @param string $foreground the foreground color
	 * @param string $background the background color
	 * @return string the color coded string
	 */
	public static function color(string $text, string $foreground, ?string $background = null): string
	{
		if ( ! array_key_exists($foreground, Minion_CLI::$foreground_colors))
			throw new Kohana_Exception('Invalid CLI foreground color: '.$foreground);

		if ($background !== null AND ! array_key_exists($background, Minion_CLI::$background_colors))
			throw new Kohana_Exception('Invalid CLI background color: '.$background);

		// The names are checked first, so a typing mistake shows everywhere
		if ( ! Minion_CLI::supports_color())
			return $text;

		$string = "\033[".Minion_CLI::$foreground_colors[$foreground]."m";

		if ($background !== null)
		{
			$string .= "\033[".Minion_CLI::$background_colors[$background]."m";
		}

		$string .= $text."\033[0m";

		return $string;
	}

	/**
	 * Tests if colours and styles are used. By default they are when the
	 * output is a terminal, and not when it goes to a file or another
	 * program, where the colour codes would only be noise.
	 *
	 * They are switched off by the option "--no-color", by the environment
	 * variable NO_COLOR, or by setting [Minion_CLI::$colors] yourself.
	 *
	 * @return  bool
	 */
	public static function supports_color(): bool
	{
		if (Minion_CLI::$colors !== null)
			return Minion_CLI::$colors;

		// The convention of https://no-color.org
		if ( (string) getenv('NO_COLOR') !== '')
			return false;

		$stream = Minion_CLI::_stream();

		if (Kohana::$is_windows)
		{
			// Windows 10 and later show colours once this mode is on
			return function_exists('sapi_windows_vt100_support') AND @sapi_windows_vt100_support($stream, true);
		}

		return @stream_isatty($stream);
	}

	/**
	 * Returns the text in one or more styles: bold, dim, italic, underline.
	 *
	 *     Minion_CLI::write(Minion_CLI::style('Important', 'bold'));
	 *     Minion_CLI::write(Minion_CLI::style('Title', 'bold', 'underline'));
	 *
	 * @param   string  $text       text to style
	 * @param   string  ...$styles  names of the styles
	 * @return  string
	 * @throws  Kohana_Exception  for a style that does not exist
	 */
	public static function style(string $text, string ...$styles): string
	{
		$codes = [];

		foreach ($styles as $style)
		{
			if ( ! isset(Minion_CLI::$styles[$style]))
				throw new Kohana_Exception('Invalid CLI style: '.$style);

			$codes[] = Minion_CLI::$styles[$style];
		}

		if ( ! $codes OR ! Minion_CLI::supports_color())
			return $text;

		return "\033[".implode(';', $codes).'m'.$text."\033[0m";
	}

	/**
	 * Writes a title: the text in bold with a line under it.
	 *
	 *     Minion_CLI::title('Importing products');
	 *
	 * @param   string  $text  the title
	 * @return  void
	 */
	public static function title(string $text): void
	{
		Minion_CLI::write([
			Minion_CLI::style($text, 'bold'),
			str_repeat('=', mb_strwidth($text)),
		]);
	}

	/**
	 * Writes a message that something went well, in green.
	 *
	 *     Minion_CLI::success('120 products imported.');
	 *
	 * @param   string  $text  the message
	 * @return  void
	 */
	public static function success(string $text): void
	{
		Minion_CLI::_message('success', $text);
	}

	/**
	 * Writes an error message, in red, to the error output. A script that
	 * calls the task can so tell errors from the normal output.
	 *
	 *     Minion_CLI::error('The file was not found.');
	 *
	 * [!!] This only writes the message. To end the task with an error
	 * status, throw an exception.
	 *
	 * @param   string  $text  the message
	 * @return  void
	 */
	public static function error(string $text): void
	{
		Minion_CLI::_message('error', $text, true);
	}

	/**
	 * Writes a warning, in yellow, to the error output.
	 *
	 *     Minion_CLI::warning('3 rows had no price and were skipped.');
	 *
	 * @param   string  $text  the message
	 * @return  void
	 */
	public static function warning(string $text): void
	{
		Minion_CLI::_message('warning', $text, true);
	}

	/**
	 * Writes a piece of information, in cyan.
	 *
	 *     Minion_CLI::info('Reading products.csv');
	 *
	 * @param   string  $text  the message
	 * @return  void
	 */
	public static function info(string $text): void
	{
		Minion_CLI::_message('info', $text);
	}

	/**
	 * Asks a yes or no question.
	 *
	 *     if (Minion_CLI::confirm('Delete 120 rows?')) ...
	 *
	 *     // "yes" when the user only presses Enter
	 *     if (Minion_CLI::confirm('Continue?', true)) ...
	 *
	 * With the option "--no-interaction" nothing is asked and the default
	 * is the answer, so give the safe choice as default.
	 *
	 * @param   string  $text     the question
	 * @param   bool    $default  answer for Enter, and when no questions may be asked
	 * @return  bool
	 */
	public static function confirm(string $text, bool $default = false): bool
	{
		if ( ! Minion_CLI::$interactive)
			return $default;

		while (true)
		{
			fwrite(Minion_CLI::_stream(), $text.($default ? ' [Y/n]: ' : ' [y/N]: '));

			$line = fgets(STDIN);

			// Enter, or no more input at all
			if ($line === false OR trim($line) === '')
				return $default;

			$answer = strtolower(trim($line));

			if (in_array($answer, ['y', 'yes'], true))
				return true;

			if (in_array($answer, ['n', 'no'], true))
				return false;

			Minion_CLI::write(Minion_CLI::$invalid_option_msg);
		}
	}

	/**
	 * Writes rows as a table with aligned columns.
	 *
	 *     Minion_CLI::table([
	 *         ['name' => 'Ana',   'role' => 'admin'],
	 *         ['name' => 'Bruno', 'role' => 'editor'],
	 *     ]);
	 *
	 *     // Titles of your own
	 *     Minion_CLI::table($rows, ['Name', 'Role']);
	 *
	 * Without titles, the keys of the first row are used when they are
	 * names. Values may be coloured: the codes do not count for the width.
	 *
	 * @param   array<int, array<int|string, mixed>>       $rows     list of rows, each a list of values
	 * @param   array<int, string>|null  $headers  titles of the columns
	 * @return  void
	 */
	public static function table(array $rows, ?array $headers = null): void
	{
		$first = reset($rows);

		if ($headers === null AND is_array($first) AND ! array_is_list($first))
		{
			$headers = array_keys($first);
		}

		// Every row as a plain list of texts
		$rows = array_map(fn ($row) => array_map(fn ($cell) => is_scalar($cell) ? (string) $cell : '', array_values( (array) $row)), array_values($rows));

		// The widest text of each column
		$widths = [];

		foreach (array_merge($headers ? [array_map('strval', array_values($headers))] : [], $rows) as $row)
		{
			foreach ($row as $i => $cell)
			{
				$widths[$i] = max($widths[$i] ?? 0, Minion_CLI::_width($cell));
			}
		}

		// Fills a row up with spaces, two between the columns
		$line = function (array $row, bool $bold = false) use ($widths): string
		{
			$cells = [];

			foreach ($widths as $i => $width)
			{
				$cell = is_string($row[$i] ?? null) ? $row[$i] : '';

				$cells[] = ($bold ? Minion_CLI::style($cell, 'bold') : $cell).str_repeat(' ', $width - Minion_CLI::_width($cell));
			}

			return rtrim(implode('  ', $cells));
		};

		if ($headers)
		{
			Minion_CLI::write($line(array_map('strval', array_values($headers)), true));
			Minion_CLI::write($line(array_map(fn ($width) => str_repeat('-', $width), $widths)));
		}

		foreach ($rows as $row)
		{
			Minion_CLI::write($line($row));
		}
	}

	/**
	 * Shows how far a long job is. Call it after every step.
	 *
	 *     foreach ($rows as $i => $row)
	 *     {
	 *         // ... the work ...
	 *
	 *         Minion_CLI::progress($i + 1, count($rows));
	 *     }
	 *
	 * On a terminal a bar is drawn again and again on one line. In a file
	 * or a pipe one line is written at every tenth of the way, so a log
	 * stays short.
	 *
	 * @param   int     $done   number of steps done
	 * @param   int     $total  number of steps in all
	 * @param   string  $label  text shown after the bar
	 * @return  void
	 */
	public static function progress(int $done, int $total, string $label = ''): void
	{
		$total   = max(1, $total);
		$done    = max(0, min($done, $total));
		$percent = intdiv($done * 100, $total);
		$text    = str_pad( (string) $percent, 3, ' ', STR_PAD_LEFT).'% ('.$done.'/'.$total.')'.($label === '' ? '' : ' '.$label);

		if ( ! @stream_isatty(Minion_CLI::_stream()))
		{
			// Not a terminal: a line when a new tenth is reached
			if ($done === $total OR intdiv($done * 10, $total) !== intdiv(max(0, $done - 1) * 10, $total))
			{
				Minion_CLI::write($text);
			}

			return;
		}

		$filled = intdiv($done * 30, $total);

		Minion_CLI::write_replace('['.str_repeat('=', $filled).str_repeat(' ', 30 - $filled).'] '.$text, $done === $total);
	}

	/**
	 * Writes one of the standard messages.
	 *
	 * @param   string  $kind   key of [Minion_CLI::$messages]
	 * @param   string  $text   the message
	 * @param   bool    $error  write to the error output?
	 * @return  void
	 */
	protected static function _message(string $kind, string $text, bool $error = false): void
	{
		[$mark, $color] = Minion_CLI::$messages[$kind];

		// The mark is coloured and the text too, so the kind shows at a glance
		$line = Minion_CLI::color($mark.' '.$text, $color).PHP_EOL;

		fwrite($error ? (Minion_CLI::$stderr ?? STDERR) : Minion_CLI::_stream(), $line);
	}

	/**
	 * Returns the stream that normal output is written to.
	 *
	 * @return  resource
	 */
	protected static function _stream(): mixed
	{
		return Minion_CLI::$stdout ?? STDOUT;
	}

	/**
	 * Returns the width of a text on the screen: colour codes take no
	 * room, and wide characters take two places.
	 *
	 * @param   string  $text
	 * @return  int
	 */
	protected static function _width(string $text): int
	{
		return mb_strwidth( (string) preg_replace('/\033\[[0-9;]*m/', '', $text));
	}

	/**
	 * Stops a question that would wait for an answer nobody can give.
	 *
	 * @param   string  $question  the question, for the message
	 * @return  void
	 * @throws  Kohana_Exception  when questions are switched off
	 */
	protected static function _need_interaction(string $question): void
	{
		if ( ! Minion_CLI::$interactive)
			throw new Kohana_Exception('The task asks ":question" but runs with --no-interaction: give the value as an option', [
				':question' => trim($question, ': '),
			]);
	}

}
