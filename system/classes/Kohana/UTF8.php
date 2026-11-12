<?php
/**
 * Multi-byte aware string functions.
 *
 * Every method is a thin wrapper around PHP's native multi-byte support, so
 * the [mbstring](http://php.net/mbstring) extension is required, and the
 * [intl](http://php.net/intl) extension for [UTF8::transliterate_to_ascii].
 * The character set used is [Kohana::$charset].
 *
 * Text arguments accept null and treat it as an empty string, because
 * values such as empty database columns are commonly passed straight in.
 *
 * This class started as a port of [phputf8](http://phputf8.sourceforge.net/).
 * None of the ported code remains, so the file is now under the same
 * license as the rest of the framework.
 *
 * @package    Kohana
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_UTF8 {

	/**
	 * @var  bool  Does the server support UTF-8 natively? Always true now that
	 *             mbstring is required; kept for compatibility.
	 */
	public static bool $server_utf8 = true;

	/**
	 * @var  array<int|string, mixed>  No longer used; kept for compatibility.
	 */
	public static array $called = [];

	/**
	 * Recursively cleans arrays, objects, and strings. Removes ASCII control
	 * codes and converts to the requested charset while silently discarding
	 * incompatible characters.
	 *
	 *     UTF8::clean($_GET); // Clean GET data
	 *
	 * @param   mixed        $var      variable to clean
	 * @param   ?string  $charset  character set, defaults to Kohana::$charset
	 * @return  ($var is string ? string : mixed)
	 * @uses    UTF8::clean
	 * @uses    UTF8::strip_ascii_ctrl
	 * @uses    UTF8::is_ascii
	 */
	public static function clean(mixed $var, ?string $charset = null): mixed
	{
		// Use the application character set
		$charset = $charset ?: Kohana::$charset;

		if (is_iterable($var))
		{
			$vars = [];

			foreach ($var as $key => $val)
			{
				// Recursion! Keys are cleaned as well as values
				$key = is_string($key) ? UTF8::clean($key, $charset) : (is_int($key) ? $key : 0);

				$vars[$key] = UTF8::clean($val, $charset);
			}

			$var = $vars;
		}
		elseif (is_string($var) AND $var !== '')
		{
			// Remove control characters
			$var = UTF8::strip_ascii_ctrl($var);

			if ( ! UTF8::is_ascii($var))
			{
				// Temporarily save the mb_substitute_character() value into a variable
				$substitute_character = mb_substitute_character();

				// Disable substituting illegal characters with the default '?' character
				mb_substitute_character('none');

				// convert encoding, this is expensive, used when $var is not ASCII
				$var = mb_convert_encoding($var, $charset, $charset);

				// Reset mb_substitute_character() value back to the original setting
				mb_substitute_character($substitute_character);
			}
		}

		return $var;
	}

	/**
	 * Tests whether a string contains only 7-bit ASCII bytes.
	 *
	 *     $ascii = UTF8::is_ascii($str);
	 *
	 * @param   string|array<int|string, string>|null  $str  string or array of strings to check
	 * @return  bool
	 */
	public static function is_ascii(string|array|null $str): bool
	{
		if (is_array($str))
		{
			$str = implode($str);
		}

		return ! preg_match('/[^\x00-\x7F]/S', (string) $str);
	}

	/**
	 * Strips out device control codes in the ASCII range.
	 *
	 *     $str = UTF8::strip_ascii_ctrl($str);
	 *
	 * @param   ?string  $str  string to clean
	 * @return  string
	 */
	public static function strip_ascii_ctrl(?string $str): string
	{
		return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S', '', (string) $str);
	}

	/**
	 * Strips out all non-7bit ASCII bytes.
	 *
	 *     $str = UTF8::strip_non_ascii($str);
	 *
	 * @param   ?string  $str  string to clean
	 * @return  string
	 */
	public static function strip_non_ascii(?string $str): string
	{
		return (string) preg_replace('/[^\x00-\x7F]+/S', '', (string) $str);
	}

	/**
	 * Replaces special/accented Latin characters by ASCII-7 "equivalents",
	 * using the intl extension ("ñ" becomes "n", "ß" becomes "ss").
	 * Characters of other scripts, such as Cyrillic, are left untouched.
	 *
	 *     $ascii = UTF8::transliterate_to_ascii($utf8);
	 *
	 * @param   ?string  $str   string to transliterate
	 * @param   int          $case  -1 lowercase only, +1 uppercase only, 0 both cases
	 * @return  string
	 * @uses    Transliterator
	 */
	public static function transliterate_to_ascii(?string $str, int $case = 0): string
	{
		// Created once and reused
		/** @var Transliterator|null $transliterator */
		static $transliterator = null;

		$transliterator ??= Transliterator::create('Latin-ASCII');

		// Each non-ASCII character is looked at on its own, so that the
		// "lowercase only" and "uppercase only" modes can skip characters
		return preg_replace_callback('/[^\x00-\x7F]/u', function (array $matches) use ($case, $transliterator): string
		{
			$char = $matches[0];

			// Lowercase only: leave characters that have a lowercase form (they are uppercase)
			if ($case < 0 AND mb_strtolower($char, 'UTF-8') !== $char)
				return $char;

			// Uppercase only: leave characters that have an uppercase form (they are lowercase)
			if ($case > 0 AND mb_strtoupper($char, 'UTF-8') !== $char)
				return $char;

			// Without a transliterator (it could not be made) the character stays
			return ($transliterator === null) ? $char : ( (string) $transliterator->transliterate($char));
		}, (string) $str) ?? (string) $str;
	}

	/**
	 * Returns the length of the given string. This is a UTF8-aware version
	 * of [strlen](http://php.net/strlen).
	 *
	 *     $length = UTF8::strlen($str);
	 *
	 * @param   ?string  $str  string being measured for length
	 * @return  int
	 */
	public static function strlen(?string $str): int
	{
		return mb_strlen( (string) $str, Kohana::$charset);
	}

	/**
	 * Finds position of first occurrence of a UTF-8 string. This is a
	 * UTF8-aware version of [strpos](http://php.net/strpos).
	 *
	 *     $position = UTF8::strpos($str, $search);
	 *
	 * @param   ?string  $str     haystack
	 * @param   ?string  $search  needle
	 * @param   int          $offset  offset from which character in haystack to start searching
	 * @return  int|false    position of needle, false if the needle is not found
	 */
	public static function strpos(?string $str, ?string $search, int $offset = 0): int|false
	{
		return mb_strpos( (string) $str, (string) $search, $offset, Kohana::$charset);
	}

	/**
	 * Finds position of last occurrence of a char in a UTF-8 string. This is
	 * a UTF8-aware version of [strrpos](http://php.net/strrpos).
	 *
	 *     $position = UTF8::strrpos($str, $search);
	 *
	 * @param   ?string  $str     haystack
	 * @param   ?string  $search  needle
	 * @param   int          $offset  offset from which character in haystack to start searching
	 * @return  int|false    position of needle, false if the needle is not found
	 */
	public static function strrpos(?string $str, ?string $search, int $offset = 0): int|false
	{
		return mb_strrpos( (string) $str, (string) $search, $offset, Kohana::$charset);
	}

	/**
	 * Returns part of a UTF-8 string. This is a UTF8-aware version
	 * of [substr](http://php.net/substr).
	 *
	 *     $sub = UTF8::substr($str, $offset);
	 *
	 * @param   ?string  $str     input string
	 * @param   int          $offset  offset
	 * @param   ?int     $length  length limit
	 * @return  string
	 */
	public static function substr(?string $str, int $offset, ?int $length = null): string
	{
		return mb_substr( (string) $str, $offset, $length, Kohana::$charset);
	}

	/**
	 * Replaces text within a portion of a UTF-8 string. This is a UTF8-aware
	 * version of [substr_replace](http://php.net/substr_replace).
	 *
	 *     $str = UTF8::substr_replace($str, $replacement, $offset);
	 *
	 * @param   ?string  $str          input string
	 * @param   ?string  $replacement  replacement string
	 * @param   int          $offset       offset
	 * @param   ?int     $length       length; null replaces up to the end
	 * @return  string
	 */
	public static function substr_replace(?string $str, ?string $replacement, int $offset, ?int $length = null): string
	{
		// Work on arrays of characters, so offsets count characters and not bytes
		$chars = mb_str_split( (string) $str, 1, Kohana::$charset);

		array_splice($chars, $offset, $length ?? count($chars), mb_str_split( (string) $replacement, 1, Kohana::$charset));

		return implode('', $chars);
	}

	/**
	 * Makes a UTF-8 string lowercase. This is a UTF8-aware version
	 * of [strtolower](http://php.net/strtolower).
	 *
	 *     $str = UTF8::strtolower($str);
	 *
	 * @param   ?string  $str  mixed case string
	 * @return  string
	 */
	public static function strtolower(?string $str): string
	{
		return mb_strtolower( (string) $str, Kohana::$charset);
	}

	/**
	 * Makes a UTF-8 string uppercase. This is a UTF8-aware version
	 * of [strtoupper](http://php.net/strtoupper).
	 *
	 * @param   ?string  $str  mixed case string
	 * @return  string
	 */
	public static function strtoupper(?string $str): string
	{
		return mb_strtoupper( (string) $str, Kohana::$charset);
	}

	/**
	 * Makes a UTF-8 string's first character uppercase. This is a UTF8-aware
	 * version of [ucfirst](http://php.net/ucfirst).
	 *
	 *     $str = UTF8::ucfirst($str);
	 *
	 * @param   ?string  $str  mixed case string
	 * @return  string
	 */
	public static function ucfirst(?string $str): string
	{
		// Uppercase the first character only; the rest is left as it is
		return UTF8::strtoupper(UTF8::substr($str, 0, 1)).UTF8::substr($str, 1);
	}

	/**
	 * Makes the first character of every word in a UTF-8 string uppercase.
	 * This is a UTF8-aware version of [ucwords](http://php.net/ucwords).
	 *
	 *     $str = UTF8::ucwords($str);
	 *
	 * @param   ?string  $str  mixed case string
	 * @return  string
	 */
	public static function ucwords(?string $str): string
	{
		// A word starts at the beginning of the string or after a form feed,
		// tab, vertical tab, line feed, carriage return or space, which is
		// the definition used by http://php.net/ucwords
		return preg_replace_callback(
			'/(?<=^|[\x0c\x09\x0b\x0a\x0d\x20])[^\x0c\x09\x0b\x0a\x0d\x20]/u',
			fn (array $matches): string => UTF8::strtoupper($matches[0]),
			(string) $str
		) ?? (string) $str;
	}

	/**
	 * Case-insensitive UTF-8 string comparison. This is a UTF8-aware version
	 * of [strcasecmp](http://php.net/strcasecmp).
	 *
	 *     $compare = UTF8::strcasecmp($str1, $str2);
	 *
	 * @param   ?string  $str1  string to compare
	 * @param   ?string  $str2  string to compare
	 * @return  int  -1 if str1 is less than str2, 1 if it is greater, 0 if they are equal
	 */
	public static function strcasecmp(?string $str1, ?string $str2): int
	{
		return strcmp(UTF8::strtolower($str1), UTF8::strtolower($str2));
	}

	/**
	 * Returns a string or an array with all occurrences of search in subject
	 * (ignoring case) and replaced with the given replace value. This is a
	 * UTF8-aware version of [str_ireplace](http://php.net/str_ireplace).
	 *
	 * @param   string|array<int|string, string>  $search   text to replace
	 * @param   string|array<int|string, string>  $replace  replacement text
	 * @param   string|array<int|string, string>  $str      subject text
	 * @param   ?int      $count    number of matched and replaced needles will be returned via this parameter which is passed by reference
	 * @return  string|array<int|string, mixed>  a string if the input was a string, an array if it was an array
	 */
	public static function str_ireplace(string|array $search, string|array $replace, string|array $str, ?int & $count = 0): string|array
	{
		$patterns = $replacements = [];

		foreach ( (array) $search as $key => $needle)
		{
			// An empty needle has nothing to find
			if ( (string) $needle === '')
				continue;

			// Match the needle literally, ignoring case, with Unicode support
			$patterns[] = '/'.preg_quote( (string) $needle, '/').'/iu';

			// A list of replacements is paired with the needles by position;
			// a needle without a replacement is removed
			$with = is_array($replace) ? ($replace[$key] ?? '') : $replace;

			// "\" and "$" have a special meaning in a regex replacement
			$replacements[] = addcslashes( (string) $with, '\\$');
		}

		if ( ! $patterns)
		{
			$count = 0;
			return $str;
		}

		// If the expression itself fails, the text is returned as it was
		return preg_replace($patterns, $replacements, $str, -1, $count) ?? $str;
	}

	/**
	 * Case-insensitive UTF-8 version of strstr. Returns all of input string
	 * from the first occurrence of needle to the end. This is a UTF8-aware
	 * version of [stristr](http://php.net/stristr).
	 *
	 *     $found = UTF8::stristr($str, $search);
	 *
	 * @param   ?string  $str     input string
	 * @param   ?string  $search  needle
	 * @return  string|false  matched substring if found, false if not
	 */
	public static function stristr(?string $str, ?string $search): string|false
	{
		return mb_stristr( (string) $str, (string) $search, false, Kohana::$charset);
	}

	/**
	 * Finds the length of the initial segment matching mask. This is a
	 * UTF8-aware version of [strspn](http://php.net/strspn).
	 *
	 *     $found = UTF8::strspn($str, $mask);
	 *
	 * @param   ?string  $str     input string
	 * @param   ?string  $mask    mask for search
	 * @param   ?int     $offset  start position of the string to examine
	 * @param   ?int     $length  length of the string to examine
	 * @return  int  length of the initial segment that contains characters in the mask
	 */
	public static function strspn(?string $str, ?string $mask, ?int $offset = null, ?int $length = null): int
	{
		return UTF8::_span( (string) $str, (string) $mask, $offset, $length, true);
	}

	/**
	 * Finds the length of the initial segment not matching mask. This is a
	 * UTF8-aware version of [strcspn](http://php.net/strcspn).
	 *
	 *     $found = UTF8::strcspn($str, $mask);
	 *
	 * @param   ?string  $str     input string
	 * @param   ?string  $mask    mask for search
	 * @param   ?int     $offset  start position of the string to examine
	 * @param   ?int     $length  length of the string to examine
	 * @return  int  length of the initial segment that contains characters not in the mask
	 */
	public static function strcspn(?string $str, ?string $mask, ?int $offset = null, ?int $length = null): int
	{
		return UTF8::_span( (string) $str, (string) $mask, $offset, $length, false);
	}

	/**
	 * Shared implementation of [UTF8::strspn] and [UTF8::strcspn]: counts the
	 * leading characters of a string that are (or are not) in the mask.
	 * PHP has no multi-byte version of these two functions.
	 *
	 * @param   string    $str      input string
	 * @param   string    $mask     characters to look for
	 * @param   ?int  $offset   start position of the string to examine
	 * @param   ?int  $length   length of the string to examine
	 * @param   bool      $in_mask  true to count characters in the mask, false for characters not in it
	 * @return  int
	 */
	protected static function _span(string $str, string $mask, ?int $offset, ?int $length, bool $in_mask): int
	{
		if ($str === '' OR $mask === '')
			return 0;

		if ($offset !== null OR $length !== null)
		{
			$str = UTF8::substr($str, (int) $offset, $length);
		}

		// Escape the characters that are special inside a regex character class:  - [ ] . : \ ^ /
		$mask = (string) preg_replace('#[-[\].:\\\\^/]#', '\\\\$0', $mask);

		// "^" inside the class negates it: characters NOT in the mask
		preg_match('/^['.($in_mask ? '' : '^').$mask.']+/u', $str, $matches);

		return isset($matches[0]) ? UTF8::strlen($matches[0]) : 0;
	}

	/**
	 * Pads a UTF-8 string to a certain length with another string. This is a
	 * UTF8-aware version of [str_pad](http://php.net/str_pad).
	 *
	 *     $str = UTF8::str_pad($str, $length);
	 *
	 * @param   ?string  $str               input string
	 * @param   int          $final_str_length  desired string length after padding
	 * @param   string       $pad_str           string to use as padding
	 * @param   int          $pad_type          padding type: STR_PAD_RIGHT, STR_PAD_LEFT, or STR_PAD_BOTH
	 * @return  string
	 * @throws  ValueError  for an unknown padding type
	 */
	public static function str_pad(?string $str, int $final_str_length, string $pad_str = ' ', int $pad_type = STR_PAD_RIGHT): string
	{
		return mb_str_pad( (string) $str, $final_str_length, $pad_str, $pad_type, Kohana::$charset);
	}

	/**
	 * Converts a UTF-8 string to an array. This is a UTF8-aware version of
	 * [str_split](http://php.net/str_split).
	 *
	 *     $array = UTF8::str_split($str);
	 *
	 * @param   ?string  $str           input string
	 * @param   int          $split_length  maximum length of each chunk
	 * @return  array<int, string>|false  the chunks, or false if the length is below 1
	 */
	public static function str_split(?string $str, int $split_length = 1): array|false
	{
		if ($split_length < 1)
			return false;

		return mb_str_split( (string) $str, $split_length, Kohana::$charset);
	}

	/**
	 * Reverses a UTF-8 string. This is a UTF8-aware version of [strrev](http://php.net/strrev).
	 *
	 *     $str = UTF8::strrev($str);
	 *
	 * @param   ?string  $str  string to be reversed
	 * @return  string
	 */
	public static function strrev(?string $str): string
	{
		return implode('', array_reverse(mb_str_split( (string) $str, 1, Kohana::$charset)));
	}

	/**
	 * Strips whitespace (or other UTF-8 characters) from the beginning and
	 * end of a string. This is a UTF8-aware version of [trim](http://php.net/trim).
	 *
	 *     $str = UTF8::trim($str);
	 *
	 * @param   ?string  $str       input string
	 * @param   ?string  $charlist  string of characters to remove; null for whitespace
	 * @return  string
	 */
	public static function trim(?string $str, ?string $charlist = null): string
	{
		return mb_trim( (string) $str, $charlist, Kohana::$charset);
	}

	/**
	 * Strips whitespace (or other UTF-8 characters) from the beginning of
	 * a string. This is a UTF8-aware version of [ltrim](http://php.net/ltrim).
	 *
	 *     $str = UTF8::ltrim($str);
	 *
	 * @param   ?string  $str       input string
	 * @param   ?string  $charlist  string of characters to remove; null for whitespace
	 * @return  string
	 */
	public static function ltrim(?string $str, ?string $charlist = null): string
	{
		return mb_ltrim( (string) $str, $charlist, Kohana::$charset);
	}

	/**
	 * Strips whitespace (or other UTF-8 characters) from the end of a string.
	 * This is a UTF8-aware version of [rtrim](http://php.net/rtrim).
	 *
	 *     $str = UTF8::rtrim($str);
	 *
	 * @param   ?string  $str       input string
	 * @param   ?string  $charlist  string of characters to remove; null for whitespace
	 * @return  string
	 */
	public static function rtrim(?string $str, ?string $charlist = null): string
	{
		return mb_rtrim( (string) $str, $charlist, Kohana::$charset);
	}

	/**
	 * Returns the unicode ordinal for a character. This is a UTF8-aware
	 * version of [ord](http://php.net/ord).
	 *
	 *     $digit = UTF8::ord($character);
	 *
	 * @param   string  $chr  UTF-8 encoded character
	 * @return  int
	 * @throws  UTF8_Exception  if the character is not valid UTF-8
	 */
	public static function ord(string $chr): int
	{
		$ord = ($chr === '') ? 0 : mb_ord($chr, 'UTF-8');

		if ($ord === false)
			throw new UTF8_Exception('UTF8::ord: Invalid UTF-8 character');

		return $ord;
	}

	/**
	 * Takes an UTF-8 string and returns an array of ints representing the Unicode characters.
	 *
	 *     $array = UTF8::to_unicode($str);
	 *
	 * @param   ?string  $str  UTF-8 encoded string
	 * @return  array<int, int>|false  unicode code points, or false if the string is invalid
	 */
	public static function to_unicode(?string $str): array|false
	{
		$str = (string) $str;

		if ( ! mb_check_encoding($str, 'UTF-8'))
			return false;

		return array_map(fn (string $char): int => mb_ord($char, 'UTF-8'), mb_str_split($str, 1, 'UTF-8'));
	}

	/**
	 * Takes an array of ints representing the Unicode characters and returns a UTF-8 string.
	 *
	 *     $str = UTF8::from_unicode($array);
	 *
	 * @param   array<int, int>   $arr  unicode code points representing a string
	 * @return  string  UTF-8 string of characters
	 * @throws  UTF8_Exception  if a code point is not a valid Unicode character
	 */
	public static function from_unicode(array $arr): string
	{
		$str = '';

		foreach ($arr as $index => $code)
		{
			// The byte order mark is not text: skip it
			if ($code === 0xFEFF)
				continue;

			// mb_chr() gives false for surrogates and values outside the Unicode range
			$char = mb_chr( (int) $code, 'UTF-8');

			if ($char === false)
				throw new UTF8_Exception("UTF8::from_unicode: Invalid code point at index: ':index', value: ':value'", [
					':index' => $index,
					':value' => $code,
				]);

			$str .= $char;
		}

		return $str;
	}

}
