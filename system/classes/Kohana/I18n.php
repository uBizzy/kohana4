<?php
/**
 * Internationalization (i18n) class. Provides language loading and translation
 * methods without dependencies on [gettext](http://php.net/gettext).
 * Typically this class would never be used directly, but used via the __()
 * function, which loads the message and replaces parameters:
 *
 *     // Display a translated message in APPATH
 *     echo __('Hello, world');
 *
 *	   // Display a translated message in SYSPATH and MODPATH
 * 	   echo I18n::get('Hello, world');
 *
 *     // With parameter replacement in APPATH
 *     echo __('Hello, :user', [':user' => $username]);
 *
 *     // With parameter replacement in SYSPATH nad MODPATH
 * 	   echo I18n::get(['Hello, :user', [':user' => $username]]);
 *
 * @package    Kohana
 * @category   Base
 *
 * @copyright  (c) 2008 - 2016 Kohana Team
 * @copyright  (c) 2018-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_I18n {

	/**
	 * Target language: en-us, es-es, zh-cn, etc
	 * @var  string
	 */
	public static string $lang = 'en-us';

	/**
	 * Source language: en-us, es-es, zh-cb, etc
	 * @var string
	 */
	public static string $source = 'en-us';

	/**
	 * Cache of loaded languages
	 * @var array<string, array<string, string>>
	 */
	protected static array $_cache = [];

	/**
	 * Get and set the target language.
	 *
	 *     // Get the current language
	 *     $lang = I18n::lang();
	 *
	 *     // Change the current language to Spanish
	 *     I18n::lang('es-es');
	 *
	 * @param   string  $lang  New target language
	 *
	 * @return  string
	 * @since   3.0.2
	 */
	public static function lang(?string $lang = null): string
	{
		if ($lang && $lang !== I18n::$lang)
		{
			I18n::$lang = strtolower(str_replace([' ', '_'], '-', $lang));
		}

		return I18n::$lang;
	}

	/**
	 * Returns translation of a string. If no translation exists, the original
	 * string will be returned. No parameters are replaced.
	 *
	 *     $hello = I18n::get('Hello friends, my name is :name');
	 *
	 * @param   string|array{0: string, 1?: mixed}  $string Text to translate or array [text, values]
	 * @param   string  	  $lang   Target Language
	 * @param   string		  $source Source Language
	 * @return  string
	 */
	public static function get(string|array $string, ?string $lang = null, ?string $source = null): string
	{
		$values = [];

		// Check if $string is array [text, values]
		if (is_array($string))
		{
			if (isset($string[1]) && is_array($string[1]))
			{
				$values = $string[1];
			}
			$string = $string[0];
		}

		// Set Target Language if not set
		if ( ! $lang)
		{
			// Use the global target language
			$lang = I18n::$lang;
		}

		// Set source Language if not set
		if ( ! $source)
		{
			// Use the global source language
			$source = I18n::$source;
		}

		// Load Table only if Source language does not match target language
		if ($source !== $lang)
		{
			// Load the translation table for this language
			$table = I18n::load($lang);

			// Return the translated string if it exists
			$string = $table[$string] ?? $string;
		}

		return empty($values) ? $string : strtr($string, $values);
	}

	/**
	 * Returns the translation table for a given language.
	 *
	 *     // Get all defined English messages.
	 *     // This will look for translation inside 'i18n/en/us.php'
	 * 	   // and overwrite the ones in 'i18n/en.php'
	 *     $messages = I18n::load('en-us');
	 *
	 * @param   string  $lang   language to load
	 * @return  array<string, string>
	 */
	public static function load(string $lang): array
	{
		if (isset(I18n::$_cache[$lang]))
		{
			return I18n::$_cache[$lang];
		}

		// New translation table
		$table = [[]];

		// Split the language: language, region, locale, etc
		$parts = explode('-', $lang);

		// Loop through Paths
		foreach ([$parts[0], implode(DIRECTORY_SEPARATOR, $parts)] as $path)
		{
			// Load files
			$files = Kohana::find_file('i18n', $path);

			// Loop through files
			if ( ! empty($files))
			{
				$t = [[]];
				foreach ($files as $file)
				{
					// Merge the language strings into the sub table
					$t[] = (array) Kohana::load($file);
				}
				$table[] = $t;
			}
		}

		/** @var array<string, string> $table  text => translation */
		$table = array_merge(...array_merge(...$table));

		if (Kohana::$profiling)
		{
			// Loading is quick; the benchmark is there to show that it happened
			$benchmark = Profiler::start('I18n', $lang);
			Profiler::stop($benchmark);
			Profiler::note($benchmark, count($table).' texts');
		}

		// Cache the translation table locally
		return I18n::$_cache[$lang] = $table;
	}

	/**
	 * Tests if a translation exists for a string. This also works when the
	 * translation is the same text as the original.
	 *
	 *     if ( ! I18n::has('Checkout', 'es-es')) ...
	 *
	 * @param   string       $string  text to look up
	 * @param   string|null  $lang    target language, the current one by default
	 * @return  bool
	 */
	public static function has(string $string, ?string $lang = null): bool
	{
		return isset(I18n::load($lang ?? I18n::$lang)[$string]);
	}

}

if ( ! function_exists('__'))
{
	/**
	 * Kohana translation/internationalization function. The PHP function
	 * [strtr](http://php.net/strtr) is used for replacing parameters.
	 *
	 *    __('Welcome back, :user', array(':user' => $username));
	 *
	 * [!!] The target language is defined by [I18n::$lang].
	 *
	 * @uses    I18n::get
	 *
	 * @param   string  $string  Text to translate
	 * @param   array<string, mixed>   $values  Values to replace in the translated text
	 * @param   string  $lang    Source language
	 *
	 * @return  string
	 */
	function __(string $string, ?array $values = null, ?string $lang = null): string
	{
		return I18n::get($values ? [$string, $values] : $string, null, $lang);
	}

}
