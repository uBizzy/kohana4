<?php
/**
 * Documentation generator: reads the classes of the application, the
 * modules and the system, and the comments above them, for the API
 * browser of the user guide.
 *
 * @package    Kohana/Userguide
 * @category   Base
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Kodoc {

	/**
	 * Pattern of one piece of a type as it is written after @param, @return
	 * or @var. A type ends at the first space, except inside `<...>`:
	 * `array<string, mixed>` is one type. The brackets are matched as
	 * they are and as HTML (`&lt;`, `&gt;`). Repeat it with `+` or `*`.
	 */
	public const string TYPE = '(?:[^\s<&]+|&lt;(?:(?!&gt;).)*&gt;|<[^>]*>|&)';

	/**
	 * @var  string  pattern that matches `Class`, `Class::method`, `Class::method()` or `Class::$property`
	 */
	public static string $regex_class_member = '((\w++)(?:::(\$?\w++))?(?:\(\))?)';

	/**
	 * Makes a link to a class, or to a method, property or constant of
	 * it, in the API browser.
	 *
	 *     // Matches of Kodoc::$regex_class_member
	 *     echo Kodoc::link_class_member(['Arr::get()', 'Arr::get()', 'Arr', 'get']);
	 *
	 * @param   array<int|string, string>  $matches  [1 => text of the link, 2 => class name, 3 => member name (optional)]
	 * @return  string
	 */
	public static function link_class_member(array $matches): string
	{
		$member = '';

		if (isset($matches[3]) AND $matches[3] !== '')
		{
			if ($matches[3][0] === '$')
			{
				// A property: Kohana::$base_url
				$member = '#property:'.substr($matches[3], 1);
			}
			elseif (preg_match('/^[A-Z_\x7f-\xff][A-Z0-9_\x7f-\xff]*$/', $matches[3]))
			{
				// A constant: Kohana::PRODUCTION
				$member = '#constant:'.$matches[3];
			}
			else
			{
				$member = '#'.$matches[3];
			}
		}

		return HTML::anchor(Route::get('docs/api')->uri(['class' => $matches[2]]).$member, HTML::chars($matches[1]), null, null, true);
	}

	/**
	 * Returns the documentation of a class.
	 *
	 * @param   string  $class  name of the class
	 * @return  Kodoc_Class
	 * @throws  ReflectionException  when the class does not exist
	 */
	public static function factory(string $class): Kodoc_Class
	{
		/** @var class-string $class */
		return new Kodoc_Class($class);
	}

	/**
	 * Returns the menu of the API browser: all classes, by package and category.
	 *
	 * @return  View
	 */
	public static function menu(): View
	{
		$classes = Kodoc::classes();
		$route = Route::get('docs/api');
		$menu = [];

		ksort($classes);

		foreach ($classes as $class)
		{
			if (Kodoc::is_transparent($class, $classes))
				continue;

			$class = Kodoc_Class::factory($class);

			if ( ! Kodoc::show_class($class))
				continue;

			$link = HTML::anchor($route->uri(['class' => $class->class->name]), $class->class->name, null, null, true);

			foreach ($class->tags['package'] ?? ['[Unknown]'] as $package)
			{
				foreach ($class->tags['category'] ?? ['Base'] as $category)
				{
					$menu[trim($package)][trim($category)][] = $link;
				}
			}
		}

		ksort($menu);

		return View::factory('userguide/api/menu', ['menu' => $menu]);
	}

	/**
	 * Returns the names of all classes, found by listing the "classes" directories.
	 *
	 * @param   array<string, mixed>|null  $list  files from [Kohana::list_files]; all classes when null
	 * @return  array<string, string>   class name => class name
	 */
	public static function classes(?array $list = null): array
	{
		$list ??= Kohana::list_files('classes');

		$classes = [];

		foreach ($list as $name => $path)
		{
			if (is_array($path))
			{
				/** @var array<string, mixed> $path  a directory: its files and directories */
				$classes += Kodoc::classes($path);
			}
			elseif (str_ends_with($name, EXT))
			{
				// classes/Cache/File.php is the class Cache_File
				$class = str_replace(DIRECTORY_SEPARATOR, '_', substr($name, 8, -strlen(EXT)));

				$classes[$class] = $class;
			}
		}

		return $classes;
	}

	/**
	 * Returns all classes with the names of the methods they declare.
	 *
	 * @param   array<string, mixed>|null  $list  files from [Kohana::list_files]; all classes when null
	 * @return  array<string, array<int, string>>   class name => list of method names
	 */
	public static function class_methods(?array $list = null): array
	{
		$classes = [];

		foreach (Kodoc::classes($list) as $class)
		{
			// Skip the base classes (Kohana_Arr): they are shown as Arr
			if (Kodoc::is_transparent($class))
				continue;

			try
			{
				/** @var class-string $class */
				$reflection = new ReflectionClass($class);
			}
			catch (Throwable $e)
			{
				// A class that cannot be loaded (a missing extension, for example)
				continue;
			}

			$methods = [];

			foreach ($reflection->getMethods() as $method)
			{
				$declares = $method->getDeclaringClass()->name;

				// A method declared in Kohana_Arr belongs to Arr
				if ((Kodoc::is_transparent($declares) ?: $declares) === $reflection->name)
				{
					$methods[] = $method->name;
				}
			}

			sort($methods);

			$classes[$reflection->name] = $methods;
		}

		return $classes;
	}

	/**
	 * Makes the HTML of the text of a comment tag (`@link`, `@throws`, ...).
	 *
	 * @param   string  $tag   name of the tag, without @
	 * @param   string  $text  text of the tag
	 * @return  string  HTML
	 */
	public static function format_tag(string $tag, string $text): string
	{
		if ($tag === 'license' AND str_contains($text, '://'))
			return HTML::anchor($text);

		if ($tag === 'link')
		{
			$split = preg_split('/\s+/', $text, 2) ?: [$text];

			return HTML::anchor($split[0], HTML::chars($split[1] ?? $split[0]));
		}

		if ($tag === 'copyright')
			return str_replace('(c)', '&copy;', HTML::chars($text));

		if ($tag === 'throws')
		{
			// "Class" or "Class text about it"
			preg_match('/^(\w+)(?:\W(.*))?$/sD', $text, $matches);

			if ($matches)
				return HTML::anchor(Route::get('docs/api')->uri(['class' => $matches[1]]), $matches[1], null, null, true)
					.(isset($matches[2]) ? ' '.HTML::chars($matches[2]) : '');
		}

		if (($tag === 'see' OR $tag === 'uses') AND preg_match('/^'.Kodoc::$regex_class_member.'/', $text, $matches))
			return Kodoc::link_class_member($matches);

		return HTML::chars($text);
	}

	/**
	 * Splits a comment into its description and its tags.
	 *
	 *     [$description, $tags] = Kodoc::parse($reflection->getDocComment());
	 *
	 * @param   string|false|null  $comment  the comment, with its `/**` and `*` marks
	 * @param   bool               $html     convert the description and the tags to HTML?
	 * @return  array{string, array<string, array<int, string>>}  [description, tag name => list of texts]
	 */
	public static function parse(string|false|null $comment, bool $html = true): array
	{
		// The text of each line, without the leading " * "
		preg_match_all('/^\s*\* ?(.*)\n/m', str_replace("\r\n", "\n", (string) $comment), $lines);

		$description = [];
		$tags = [];
		$tag = null;

		foreach ($lines[1] as $line)
		{
			if (preg_match('/^@(\S+)\s*(.+)?$/', $line, $matches))
			{
				// A new tag
				$tag = $matches[1];
				$tags[$tag][] = $matches[2] ?? '';
			}
			elseif ($tag !== null)
			{
				// More text of the tag above
				$tags[$tag][array_key_last($tags[$tag])] .= "\n".$line;
			}
			else
			{
				$description[] = $line;
			}
		}

		// @access says what the code already says
		unset($tags['access']);

		$description = trim(implode("\n", $description), "\n");

		if ($html)
		{
			foreach ($tags as $name => $texts)
			{
				$tags[$name] = array_map(fn ($text) => Kodoc::format_tag($name, $text), $texts);
			}

			if ($description !== '')
			{
				$description = Kodoc_Markdown::markdown($description);
			}
		}

		return [$description, $tags];
	}

	/**
	 * Returns lines of a file: the source code of a method.
	 *
	 * @param   string|false  $file   path of the file
	 * @param   int           $start  first line
	 * @param   int           $end    last line
	 * @return  string|false  false when there is no file (a class of PHP itself)
	 */
	public static function source(string|false $file, int $start, int $end): string|false
	{
		if ( ! $file)
			return false;

		$lines = array_slice(file($file, FILE_IGNORE_NEW_LINES) ?: [], $start - 1, $end - $start + 1);

		if ($lines AND preg_match('/^(\s+)/', $lines[0], $matches))
		{
			// Take the indentation of the first line away from every line
			$padding = strlen($matches[1]);

			foreach ($lines as & $line)
			{
				$line = substr($line, $padding);
			}
		}

		return implode("\n", $lines);
	}

	/**
	 * Tells whether a class is shown in the API browser, by the
	 * `api_packages` setting of the userguide config.
	 *
	 * @param   Kodoc_Class  $class  the class
	 * @return  bool
	 */
	public static function show_class(Kodoc_Class $class): bool
	{
		$api_packages = Kohana::$config->load('userguide.api_packages');

		if ($api_packages === true)
			return true;

		// The packages of the class; 'None' when it has no @package
		$packages = $class->tags['package'] ?? ['None'];

		return (bool) array_intersect($packages, explode(',', is_string($api_packages) ? $api_packages : ''));
	}

	/**
	 * Tells whether a class is a base class that the application reaches
	 * through an empty class of a shorter name (`Kohana_Arr` through
	 * `Arr`). Such classes are not listed on their own.
	 *
	 * The prefixes are in the userguide config:
	 *
	 *     'transparent_prefixes' => ['Kohana' => true],
	 *
	 * @param   string  $class    name of the class
	 * @param   array<string, string>|null  $classes  all class names; when given, the shorter class must exist
	 * @return  string|false  name of the shorter class, or false
	 */
	public static function is_transparent(string $class, ?array $classes = null): string|false
	{
		/** @var array<string, mixed>|null $prefixes */
		static $prefixes = null;

		$prefixes ??= (array) Kohana::$config->load('userguide.transparent_prefixes');

		// Kohana_Request_Client: prefix "Kohana", rest "Request_Client"
		$segments = explode('_', $class, 2);

		if (count($segments) !== 2 OR ! isset($prefixes[$segments[0]]))
			return false;

		// Kohana_Core is the base of Kohana
		$child = ($segments[1] === 'Core') ? $segments[0] : $segments[1];

		if ($classes AND ! isset($classes[$child]))
			return false;

		return $child;
	}

}
