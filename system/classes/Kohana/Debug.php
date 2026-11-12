<?php
/**
 * Contains debugging and dumping tools.
 *
 * @package    Kohana
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Debug {

	/**
	 * @var  array<int, string>  Words that mark a value as secret. A value whose name
	 *              (array key, object property, function parameter)
	 *              contains one of them is shown as "***" in dumps, so
	 *              that passwords and keys do not end up on error pages,
	 *              in screenshots or in bug reports.
	 */
	public static array $sensitive = [
		'password', 'passwd', 'pwd', 'secret', 'token', 'authorization', 'cookie',
		'api_key', 'apikey', 'private_key', 'hash_key', 'salt', 'credit_card', 'card_number', 'cvv', 'ssn',
	];

	/**
	 * @var  ?string  Address that opens a file in your code editor, with
	 *                `:file` and `:line` in it. When set, the error page
	 *                shows an "open" link beside every file. Set it in
	 *                the bootstrap, for development only:
	 *
	 *     Debug::$editor = 'vscode://file/:file::line';
	 *     Debug::$editor = 'phpstorm://open?file=:file&line=:line';
	 *     Debug::$editor = 'subl://open?url=file://:file&line=:line';
	 */
	public static ?string $editor = null;

	/**
	 * @var  array<string, string>  When the code runs somewhere else than your editor (a
	 *              container, a virtual machine, a remote server): the
	 *              start of the path there => the start of the path on
	 *              your computer. Used by [Debug::editor_link].
	 *
	 *     Debug::$editor_paths = ['/var/www/html/' => '/home/me/project/'];
	 */
	public static array $editor_paths = [];

	/**
	 * Returns the address that opens a file at a line in the code editor,
	 * or null when no editor is set ([Debug::$editor]) or in production.
	 *
	 *     $link = Debug::editor_link($file, $line);
	 *
	 * @param   ?string  $file  path of the file
	 * @param   ?int     $line  line number
	 * @return  ?string
	 */
	public static function editor_link(?string $file, ?int $line = null): ?string
	{
		if (Debug::$editor === null OR $file === null OR Kohana::$environment === Kohana::PRODUCTION)
			return null;

		foreach (Debug::$editor_paths as $here => $there)
		{
			if (str_starts_with($file, $here))
			{
				// The same file, where the editor can find it
				$file = $there.substr($file, strlen($here));
				break;
			}
		}

		return strtr(Debug::$editor, [':file' => rawurlencode($file), ':line' => (int) ($line ?? 1)]);
	}

	/**
	 * Tells whether a name marks a secret value (see [Debug::$sensitive]).
	 *
	 *     Debug::is_sensitive('user_password');  // true
	 *     Debug::is_sensitive('HTTP_COOKIE');    // true
	 *     Debug::is_sensitive('username');       // false
	 *
	 * @param   mixed  $name  array key, property or parameter name
	 * @return  bool
	 */
	public static function is_sensitive(mixed $name): bool
	{
		if ( ! is_string($name))
			return false;

		$name = strtolower($name);

		foreach (Debug::$sensitive as $word)
		{
			if (str_contains($name, $word))
				return true;
		}

		return false;
	}

	/**
	 * Returns the HTML of a variable for an error page or panel: like
	 * [Debug::dump], but "***" when the name marks the value as secret.
	 *
	 * @param   mixed  $name   name the value is known under
	 * @param   mixed  $value  the value
	 * @param   int    $limit  how many levels of arrays and objects to show
	 * @return  string
	 */
	public static function dump_named(mixed $name, mixed $value, int $limit = 10): string
	{
		if ($value !== null AND $value !== '' AND Debug::is_sensitive($name))
			return '<small>hidden</small> ***';

		return Debug::dump($value, 128, $limit);
	}

	/**
	 * Returns an HTML string of debugging information about any number of
	 * variables wrapped in a "pre" tag:
	 *
	 *     // Displays the type and value of each variable
	 *     echo Debug::vars($foo, $bar, $baz);
	 *
	 * @param   mixed   ...$variables  variables to debug
	 * @return  string
	 */
	public static function vars(mixed ...$variables): ?string
	{
		if ( ! $variables)
			return null;

		$output = [];
		foreach ($variables as $var)
		{
			$output[] = Debug::_dump($var, 1024);
		}

		return '<pre class="debug">'.implode("\n", $output).'</pre>';
	}

	/**
	 * Returns an HTML string of information about a single variable.
	 *
	 * Borrows heavily on concepts from the Debug class of [Nette](https://nette.org).
	 *
	 * @param   mixed   $value              variable to dump
	 * @param   int $length             maximum length of strings
	 * @param   int $level_recursion    recursion limit
	 * @return  string
	 */
	public static function dump(mixed $value, int $length = 128, int $level_recursion = 10): string
	{
		return Debug::_dump($value, $length, $level_recursion);
	}

	/**
	 * Helper for Debug::dump(), handles recursion in arrays and objects.
	 *
	 * @param   mixed   $var    variable to dump
	 * @param   int $length maximum length of strings
	 * @param   int $limit  recursion limit
	 * @param   int $level  current recursion level (internal usage only!)
	 * @return  string
	 */
	protected static function _dump(mixed & $var, int $length = 128, int $limit = 10, int $level = 0): string
	{
		if ($var === null)
		{
			return '<small>NULL</small>';
		}
		elseif (is_bool($var))
		{
			return '<small>bool</small> '.($var ? 'TRUE' : 'FALSE');
		}
		elseif (is_float($var))
		{
			return '<small>float</small> '.$var;
		}
		elseif (is_resource($var))
		{
			$type = get_resource_type($var);

			$meta = ($type === 'stream') ? stream_get_meta_data($var) : [];

			if (isset($meta['uri']))
			{
				// A stream that has an address: a file, a URL
				$file = $meta['uri'];

				if (stream_is_local($file))
				{
					$file = Debug::path($file);
				}

				return '<small>resource</small><span>('.$type.')</span> '.htmlspecialchars($file, ENT_NOQUOTES, Kohana::$charset);
			}

			// Any other resource, and a stream without an address (a socket, a pipe)
			return '<small>resource</small><span>('.$type.')</span>';
		}
		elseif (is_string($var))
		{
			// Clean invalid multibyte characters. iconv is only invoked
			// if there are non ASCII characters in the string, so this
			// isn't too much of a hit.
			$var = UTF8::clean($var, Kohana::$charset);

			if (UTF8::strlen($var) > $length)
			{
				// Encode the truncated string
				$str = htmlspecialchars(UTF8::substr($var, 0, $length), ENT_NOQUOTES, Kohana::$charset).'&nbsp;&hellip;';
			}
			else
			{
				// Encode the string
				$str = htmlspecialchars($var, ENT_NOQUOTES, Kohana::$charset);
			}

			return '<small>string</small><span>('.strlen($var).')</span> "'.$str.'"';
		}
		elseif (is_array($var))
		{
			$output = [];

			// Indentation for this variable
			$space = str_repeat($s = '    ', $level);

			/** @var string|null $marker */
			static $marker = null;

			if ($marker === null)
			{
				// Make a unique marker - force it to be alphanumeric so that it is always treated as a string array key
				$marker = uniqid("\x00")."x";
			}

			if (empty($var))
			{
				// Do nothing
			}
			elseif (isset($var[$marker]))
			{
				$output[] = "(\n$space$s*RECURSION*\n$space)";
			}
			elseif ($level < $limit)
			{
				$output[] = "<span>(";

				$var[$marker] = true;
				foreach ($var as $key => & $val)
				{
					if ($key === $marker) continue;
					if ( ! is_int($key))
					{
						$key = '"'.htmlspecialchars($key, ENT_NOQUOTES, Kohana::$charset).'"';
					}

					// A value with a secret name is not shown
					$output[] = "$space$s$key => ".((Debug::is_sensitive($key) AND $val !== null AND $val !== '')
						? '<small>hidden</small> ***'
						: Debug::_dump($val, $length, $limit, $level + 1));
				}
				unset($var[$marker]);

				$output[] = "$space)</span>";
			}
			else
			{
				// Depth too great
				$output[] = "(\n$space$s...\n$space)";
			}

			return '<small>array</small><span>('.count($var).')</span> '.implode("\n", $output);
		}
		elseif (is_object($var))
		{
			// Copy the object as an array. A class that says itself what may
			// be shown of it (__debugInfo(), as var_dump() uses) is obeyed:
			// that is how classes keep passwords and keys out of dumps.
			$array = method_exists($var, '__debugInfo') ? (array) $var->__debugInfo() : (array) $var;

			$output = [];

			// Indentation for this variable
			$space = str_repeat($s = '    ', $level);

			$hash = spl_object_hash($var);

			// Objects that are being dumped
			/** @var array<string, bool> $objects */
			static $objects = [];

			if (isset($objects[$hash]))
			{
				$output[] = "{\n$space$s*RECURSION*\n$space}";
			}
			elseif ($level < $limit)
			{
				$output[] = "<code>{";

				$objects[$hash] = true;
				foreach ($array as $key => & $val)
				{
					// A numbered property (of a list turned into an object, or
					// from __debugInfo()) has a number as its key
					$key = (string) $key;

					if (str_starts_with($key, "\x00"))
					{
						// Determine if the access is protected or protected
						$access = '<small>'.(($key[1] === '*') ? 'protected' : 'private').'</small>';

						// Remove the access level from the variable name
						$key = substr($key, strrpos($key, "\x00") + 1);
					}
					else
					{
						$access = '<small>public</small>';
					}

					// A value with a secret name is not shown
					$output[] = "$space$s$access $key => ".((Debug::is_sensitive($key) AND $val !== null AND $val !== '')
						? '<small>hidden</small> ***'
						: Debug::_dump($val, $length, $limit, $level + 1));
				}
				unset($objects[$hash]);

				$output[] = "$space}</code>";
			}
			else
			{
				// Depth too great
				$output[] = "{\n$space$s...\n$space}";
			}

			return '<small>object</small> <span>'.get_class($var).'('.count($array).')</span> '.implode("\n", $output);
		}
		else
		{
			return '<small>'.gettype($var).'</small> '.htmlspecialchars(print_r($var, true), ENT_NOQUOTES, Kohana::$charset);
		}
	}

	/**
	 * Removes application, system, modpath, or docroot from a filename,
	 * replacing them with the plain text equivalents. Useful for debugging
	 * when you want to display a shorter path.
	 *
	 *     // Displays SYSPATH/classes/Kohana.php
	 *     echo Debug::path(Kohana::find_file('classes', 'Kohana'));
	 *
	 * @param   string  $file   path to debug
	 * @return  string
	 */
	public static function path(string $file): string
	{
		if (str_starts_with($file, APPPATH))
		{
			$file = 'APPPATH'.DIRECTORY_SEPARATOR.substr($file, strlen(APPPATH));
		}
		elseif (str_starts_with($file, SYSPATH))
		{
			$file = 'SYSPATH'.DIRECTORY_SEPARATOR.substr($file, strlen(SYSPATH));
		}
		elseif (str_starts_with($file, MODPATH))
		{
			$file = 'MODPATH'.DIRECTORY_SEPARATOR.substr($file, strlen(MODPATH));
		}
		elseif (str_starts_with($file, DOCROOT))
		{
			$file = 'DOCROOT'.DIRECTORY_SEPARATOR.substr($file, strlen(DOCROOT));
		}

		return $file;
	}

	/**
	 * Returns an HTML string, highlighting a specific line of a file, with some
	 * number of lines padded above and below.
	 *
	 *     // Highlights the current line of the current file
	 *     echo Debug::source(__FILE__, __LINE__);
	 *
	 * @param   string  $file           file to open
	 * @param   int $line_number    line number to highlight
	 * @param   int $padding        number of padding lines
	 * @return  string   source of file
	 */
	public static function source(?string $file, int $line_number, int $padding = 5): string|false
	{
		if ( ! $file OR ! is_readable($file))
		{
			// Continuing will cause errors
			return false;
		}

		// Open the file and set the line position
		$file = fopen($file, 'r');
		$line = 0;

		if ($file === false)
			return false;

		// Set the reading range
		$range = ['start' => $line_number - $padding, 'end' => $line_number + $padding];

		// Set the zero-padding amount for line numbers
		$format = '% '.strlen( (string) $range['end']).'d';

		$source = '';
		while (($row = fgets($file)) !== false)
		{
			// Increment the line number
			if (++$line > $range['end'])
				break;

			if ($line >= $range['start'])
			{
				// Make the row safe for output
				$row = htmlspecialchars($row, ENT_NOQUOTES, Kohana::$charset);

				// Trim whitespace and sanitize the row
				$row = '<span class="number">'.sprintf($format, $line).'</span> '.$row;

				if ($line === $line_number)
				{
					// Apply highlighting to this row
					$row = '<span class="line highlight">'.$row.'</span>';
				}
				else
				{
					$row = '<span class="line">'.$row.'</span>';
				}

				// Add to the captured source
				$source .= $row;
			}
		}

		// Close the file
		fclose($file);

		return '<pre class="source"><code>'.$source.'</code></pre>';
	}

	/**
	 * Returns an array of HTML strings that represent each step in the backtrace.
	 *
	 *     // Displays the entire current backtrace
	 *     echo implode('<br/>', Debug::trace());
	 *
	 * @param   array<int, array{function?: string, line?: int, file?: string, class?: string, type?: string, args?: array<int|string, mixed>}>|null   $trace     Stack to trace
	 * @return  array<int, array<string, mixed>>
	 */
	public static function trace(?array $trace = null, bool $with_source = true): array
	{
		if ($trace === null)
		{
			// Start a new trace
			$trace = debug_backtrace();
		}

		// Non-standard function calls
		$statements = ['include', 'include_once', 'require', 'require_once'];

		$output = [];
		foreach ($trace as $step)
		{
			if ( ! isset($step['function']))
			{
				// Invalid trace step
				continue;
			}

			if ($with_source AND isset($step['file']) AND isset($step['line']))
			{
				// Include the source of this step
				$source = Debug::source($step['file'], $step['line']);
			}

			if (isset($step['file']))
			{
				$file = $step['file'];

				if (isset($step['line']))
				{
					$line = $step['line'];
				}
			}

			// function()
			$function = $step['function'];

			if (in_array($step['function'], $statements))
			{
				if (empty($step['args']))
				{
					// No arguments
					$args = [];
				}
				else
				{
					// Sanitize the file path
					$args = [$step['args'][0]];
				}
			}
			elseif (isset($step['args']))
			{
				// The parameters of the function, to show each value under its name
				$params = null;

				if (str_contains($step['function'], '{closure'))
				{
					// A closure in a stack trace cannot be looked up by name
				}
				elseif (isset($step['class']))
				{
					if (method_exists($step['class'], $step['function']))
					{
						$params = (new ReflectionMethod($step['class'], $step['function']))->getParameters();
					}
					elseif (method_exists($step['class'], '__call'))
					{
						$params = (new ReflectionMethod($step['class'], '__call'))->getParameters();
					}
				}
				elseif (is_string($step['function']) AND function_exists($step['function']))
				{
					$params = (new ReflectionFunction($step['function']))->getParameters();
				}

				$args = [];

				foreach ($step['args'] as $i => $arg)
				{
					if (isset($params[$i]))
					{
						// Assign the argument by the parameter name
						$args[$params[$i]->name] = $arg;
					}
					else
					{
						// Assign the argument by number
						$args[$i] = $arg;
					}
				}
			}

			if (isset($step['class']))
			{
				// Class->method() or Class::method()
				$function = $step['class'].($step['type'] ?? '::').$step['function'];
			}

			$output[] = [
				'function' => $function,
				'args'     => isset($args)   ? $args : null,
				'file'     => isset($file)   ? $file : null,
				'line'     => isset($line)   ? $line : null,
				'source'   => isset($source) ? $source : null,
			];

			unset($function, $args, $file, $line, $source);
		}

		return $output;
	}

}
