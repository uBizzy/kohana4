<?php
/**
 * Validation rules.
 *
 * Every rule receives the value to check as its first argument. That value
 * usually comes straight from user input ($_POST, $_GET, ...), so it can be
 * anything: a string, an array, null. For that reason the value is typed
 * `mixed` and each rule decides what it accepts. Rules that check text go
 * through [Valid::_text], which only lets strings and numbers through: an
 * array or an object never passes a text rule.
 *
 * @package    Kohana
 * @category   Security
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Valid {

	/**
	 * Converts a value to the text a rule should check.
	 *
	 * Only strings, integers and floats are text. Anything else (array, object,
	 * boolean, null) returns null, and the calling rule must then fail. This
	 * stops values like `field[]=x` from being cast to the string "Array" and
	 * slipping through a rule.
	 *
	 * @param   mixed  $value  value to convert
	 * @return  string|null    the value as a string, or null if it is not text
	 */
	protected static function _text(mixed $value): ?string
	{
		if (is_string($value))
			return $value;

		if (is_int($value) OR is_float($value))
			return (string) $value;

		return null;
	}

	/**
	 * Returns the decimal point of the current locale (set with setlocale()).
	 *
	 * @return  string
	 */
	protected static function _decimal_point(): string
	{
		return localeconv()['decimal_point'];
	}

	/**
	 * Checks if a field is not empty.
	 *
	 * @param   mixed  $value  value to check
	 * @return  bool
	 */
	public static function not_empty(mixed $value): bool
	{
		if ($value instanceof ArrayObject)
		{
			// Get the array from the ArrayObject
			$value = $value->getArrayCopy();
		}

		// Value cannot be null, false, '', or an empty array
		return ! in_array($value, [null, false, '', []], true);
	}

	/**
	 * Checks a field against a regular expression.
	 *
	 * @param   mixed   $value      value
	 * @param   string  $expression regular expression to match (including delimiters)
	 * @return  bool
	 */
	public static function regex(mixed $value, string $expression): bool
	{
		$value = Valid::_text($value);

		return $value !== null AND (bool) preg_match($expression, $value);
	}

	/**
	 * Checks that a field is long enough.
	 *
	 * @param   mixed  $value  value
	 * @param   int    $length minimum length required
	 * @return  bool
	 */
	public static function min_length(mixed $value, int $length): bool
	{
		$value = Valid::_text($value);

		return $value !== null AND UTF8::strlen($value) >= $length;
	}

	/**
	 * Checks that a field is short enough.
	 *
	 * @param   mixed  $value  value
	 * @param   int    $length maximum length required
	 * @return  bool
	 */
	public static function max_length(mixed $value, int $length): bool
	{
		$value = Valid::_text($value);

		return $value !== null AND UTF8::strlen($value) <= $length;
	}

	/**
	 * Checks that a field is exactly the right length.
	 *
	 * @param   mixed      $value  value
	 * @param   int|array<int, int>  $length exact length required, or array of valid lengths
	 * @return  bool
	 */
	public static function exact_length(mixed $value, int|array $length): bool
	{
		$value = Valid::_text($value);

		if ($value === null)
			return false;

		// A single length is checked the same way as a list of one
		return in_array(UTF8::strlen($value), (array) $length, true);
	}

	/**
	 * Checks that a field is exactly the value required (same value and type).
	 *
	 * @param   mixed  $value      value
	 * @param   mixed  $required   required value
	 * @return  bool
	 */
	public static function equals(mixed $value, mixed $required): bool
	{
		return ($value === $required);
	}

	/**
	 * Validates an e-mail address, using PHP's own e-mail filter.
	 *
	 * @link  http://www.iamcal.com/publish/articles/php/parsing_email/
	 * @link  http://www.w3.org/Protocols/rfc822/
	 *
	 * @param   mixed  $email   e-mail address
	 * @param   bool   $strict  kept for compatibility; the check is always strict
	 * @return  bool
	 */
	public static function email(mixed $email, bool $strict = false): bool
	{
		$email = Valid::_text($email);

		return $email !== null AND filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
	}

	/**
	 * Validate the domain of an email address by checking if the domain has a
	 * valid MX record.
	 *
	 * @link  http://php.net/checkdnsrr  not added to Windows until PHP 5.3.0
	 *
	 * @param   mixed  $email  email address
	 * @return  bool
	 */
	public static function email_domain(mixed $email): bool
	{
		$email = Valid::_text($email);

		// Empty fields cause issues with checkdnsrr()
		if ($email === null OR $email === '')
			return false;

		// Check if the email domain (everything after the "@") has a valid MX record
		return checkdnsrr( (string) preg_replace('/^[^@]++@/', '', $email), 'MX');
	}

	/**
	 * Validate a URL.
	 *
	 * @param   mixed  $url  URL
	 * @return  bool
	 */
	public static function url(mixed $url): bool
	{
		$url = Valid::_text($url);

		if ($url === null)
			return false;

		// Based on http://www.apps.ietf.org/rfc/rfc1738.html#sec-5
		if ( ! preg_match(
			'~^

			# scheme
			[-a-z0-9+.]++://

			# username:password (optional)
			(?:
				    [-a-z0-9$_.+!*\'(),;?&=%]++   # username
				(?::[-a-z0-9$_.+!*\'(),;?&=%]++)? # password (optional)
				@
			)?

			(?:
				# ip address
				\d{1,3}+(?:\.\d{1,3}+){3}+

				| # or

				# hostname (captured)
				(
					     (?!-)[-a-z0-9]{1,63}+(?<!-)
					(?:\.(?!-)[-a-z0-9]{1,63}+(?<!-)){0,126}+
				)
			)

			# port (optional)
			(?::\d{1,5}+)?

			# path (optional)
			(?:/.*)?

			$~iDx', $url, $matches))
			return false;

		// We matched an IP address
		if ( ! isset($matches[1]))
			return true;

		// Check maximum length of the whole hostname
		// http://en.wikipedia.org/wiki/Domain_name#cite_note-0
		if (strlen($matches[1]) > 253)
			return false;

		// An extra check for the top level domain
		// It must start with a letter
		$tld = ltrim(substr($matches[1], (int) strrpos($matches[1], '.')), '.');

		return ctype_alpha($tld[0]);
	}

	/**
	 * Validate an IP.
	 *
	 * @param   mixed  $ip             IP address
	 * @param   bool   $allow_private  allow private IP networks
	 * @return  bool
	 */
	public static function ip(mixed $ip, bool $allow_private = true): bool
	{
		if ( ! is_string($ip))
			return false;

		// Do not allow reserved addresses
		$flags = FILTER_FLAG_NO_RES_RANGE;

		if ($allow_private === false)
		{
			// Do not allow private or reserved addresses
			$flags = $flags | FILTER_FLAG_NO_PRIV_RANGE;
		}

		return filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false;
	}

	/**
	 * Validates a credit card number, with a Luhn check if possible.
	 *
	 * The card types and their rules are in the `credit_cards` config file.
	 *
	 * @param   mixed              $number credit card number
	 * @param   string|array<int, string>|null  $type   card type, or an array of card types
	 * @return  bool
	 * @uses    Valid::luhn
	 */
	public static function credit_card(mixed $number, string|array|null $type = null): bool
	{
		$number = Valid::_text($number);

		if ($number === null)
			return false;

		// Remove all non-digit characters from the number
		if (($number = (string) preg_replace('/\D+/', '', $number)) === '')
			return false;

		if (is_array($type))
		{
			foreach ($type as $t)
			{
				// Test each type for validity
				if (Valid::credit_card($number, $t))
					return true;
			}

			return false;
		}

		// Use the default type when none is given
		$type = strtolower($type ?: 'default');

		$card = Kohana::$config->load('credit_cards.'.$type);

		// Check card type
		if ( ! is_array($card))
			return false;

		/** @var array{length: string, prefix: string, luhn: bool} $card */

		// Validate the card length by the card type (e.g. "14,16")
		if ( ! in_array( (string) strlen($number), preg_split('/\D+/', $card['length']) ?: [], true))
			return false;

		// Check card number prefix. The group keeps "^" applied to every
		// alternative of prefixes such as "36|55|30[0-5]"
		if ( ! preg_match('/^(?:'.$card['prefix'].')/', $number))
			return false;

		// No Luhn check required
		if ($card['luhn'] == false)
			return true;

		return Valid::luhn($number);
	}

	/**
	 * Validate a number against the [Luhn](http://en.wikipedia.org/wiki/Luhn_algorithm)
	 * (mod10) formula.
	 *
	 * @param   mixed  $number number to check
	 * @return  bool
	 */
	public static function luhn(mixed $number): bool
	{
		// The number is handled as a string of digits: converting it to an
		// integer could exceed PHP_INT_MAX
		$number = Valid::_text($number);

		if ($number === null OR ! ctype_digit($number))
		{
			// Luhn can only be used on numbers!
			return false;
		}

		// Check number length
		$length = strlen($number);

		// Checksum of the card number
		$checksum = 0;

		for ($i = $length - 1; $i >= 0; $i -= 2)
		{
			// Add up every 2nd digit, starting from the right
			$checksum += (int) $number[$i];
		}

		for ($i = $length - 2; $i >= 0; $i -= 2)
		{
			// Add up every 2nd digit doubled, starting from the right
			$double = (int) $number[$i] * 2;

			// Subtract 9 from the double where value is greater than 10
			$checksum += ($double >= 10) ? ($double - 9) : $double;
		}

		// If the checksum is a multiple of 10, the number is valid
		return ($checksum % 10 === 0);
	}

	/**
	 * Checks if a phone number is valid, by counting its digits.
	 *
	 * @param   mixed       $number   phone number to check
	 * @param   array<int, int>|null  $lengths  accepted numbers of digits; default 7, 10 and 11
	 * @return  bool
	 */
	public static function phone(mixed $number, ?array $lengths = null): bool
	{
		$number = Valid::_text($number);

		if ($number === null)
			return false;

		// Remove all non-digit characters from the number
		$number = (string) preg_replace('/\D+/', '', $number);

		// Check if the number of digits is one of the accepted lengths
		return in_array(strlen($number), $lengths ?? [7, 10, 11]);
	}

	/**
	 * Tests if a string is a valid date string, as understood by strtotime().
	 *
	 * @param   mixed  $str    date to check
	 * @return  bool
	 */
	public static function date(mixed $str): bool
	{
		$str = Valid::_text($str);

		return $str !== null AND strtotime($str) !== false;
	}

	/**
	 * Checks whether a string consists of alphabetical characters only.
	 *
	 * @param   mixed  $str    input string
	 * @param   bool   $utf8   trigger UTF-8 compatibility
	 * @return  bool
	 */
	public static function alpha(mixed $str, bool $utf8 = false): bool
	{
		$str = Valid::_text($str);

		if ($str === null)
			return false;

		return $utf8 ? (bool) preg_match('/^\pL++$/uD', $str) : ctype_alpha($str);
	}

	/**
	 * Checks whether a string consists of alphabetical characters and numbers only.
	 *
	 * @param   mixed  $str    input string
	 * @param   bool   $utf8   trigger UTF-8 compatibility
	 * @return  bool
	 */
	public static function alpha_numeric(mixed $str, bool $utf8 = false): bool
	{
		$str = Valid::_text($str);

		if ($str === null)
			return false;

		return $utf8 ? (bool) preg_match('/^[\pL\pN]++$/uD', $str) : ctype_alnum($str);
	}

	/**
	 * Checks whether a string consists of alphabetical characters, numbers, underscores and dashes only.
	 *
	 * @param   mixed  $str    input string
	 * @param   bool   $utf8   trigger UTF-8 compatibility
	 * @return  bool
	 */
	public static function alpha_dash(mixed $str, bool $utf8 = false): bool
	{
		$str = Valid::_text($str);

		if ($str === null)
			return false;

		$regex = $utf8 ? '/^[-\pL\pN_]++$/uD' : '/^[-a-z0-9_]++$/iD';

		return (bool) preg_match($regex, $str);
	}

	/**
	 * Checks whether a string consists of digits only (no dots or dashes).
	 *
	 * @param   mixed  $str    input string
	 * @param   bool   $utf8   trigger UTF-8 compatibility
	 * @return  bool
	 */
	public static function digit(mixed $str, bool $utf8 = false): bool
	{
		// Floats are not accepted: "1.5" contains a dot
		if (is_float($str))
			return false;

		// A negative integer becomes "-5", which fails the checks below
		$str = Valid::_text($str);

		if ($str === null)
			return false;

		return $utf8 ? (bool) preg_match('/^\pN++$/uD', $str) : ctype_digit($str);
	}

	/**
	 * Checks whether a string is a valid number (negative and decimal numbers allowed).
	 *
	 * Uses {@link http://www.php.net/manual/en/function.localeconv.php locale conversion}
	 * to allow decimal point to be locale specific.
	 *
	 * @param   mixed  $str    input string
	 * @return  bool
	 */
	public static function numeric(mixed $str): bool
	{
		$str = Valid::_text($str);

		if ($str === null)
			return false;

		// Get the decimal point for the current locale
		$decimal = preg_quote(Valid::_decimal_point(), '/');

		// A lookahead is used to make sure the string contains at least one digit (before or after the decimal point)
		return (bool) preg_match('/^-?+(?=.*[0-9])[0-9]*+'.$decimal.'?+[0-9]*+$/D', $str);
	}

	/**
	 * Tests if a number is within a range.
	 *
	 *     Valid::range(5, 1, 10);       // true
	 *     Valid::range(5, 0, 10, 2);    // false: 5 is not 0, 2, 4, ...
	 *     Valid::range(1.5, 0, 2, 0.5); // true
	 *
	 * @param   mixed           $number number to check
	 * @param   int|float       $min    minimum value
	 * @param   int|float       $max    maximum value
	 * @param   int|float|null  $step   increment size; default 1
	 * @return  bool
	 */
	public static function range(mixed $number, int|float $min, int|float $max, int|float|null $step = null): bool
	{
		// Only real numbers (or numeric strings) can be in a range
		if ( ! is_numeric($number))
			return false;

		// The "+ 0" turns a numeric string into an int or a float
		$number += 0;

		if ($number < $min OR $number > $max)
		{
			// Number is outside of range
			return false;
		}

		// Default to steps of 1
		$step = $step ?: 1;

		// How many steps the number is away from the minimum: it must be a
		// whole number (a tiny tolerance absorbs float rounding, e.g. 0.1 steps)
		$steps = ($number - $min) / $step;

		return abs($steps - round($steps)) < 1e-9;
	}

	/**
	 * Checks if a string is a proper decimal format. Optionally, a specific
	 * number of digits can be checked too.
	 *
	 * @param   mixed     $str    number to check
	 * @param   int       $places number of decimal places
	 * @param   ?int  $digits number of digits before the decimal point; null for any
	 * @return  bool
	 */
	public static function decimal(mixed $str, int $places = 2, ?int $digits = null): bool
	{
		$str = Valid::_text($str);

		if ($str === null)
			return false;

		// A specific number of digits, or any number of digits
		$digits = ($digits > 0) ? '{'.$digits.'}' : '+';

		// Get the decimal point for the current locale
		$decimal = preg_quote(Valid::_decimal_point(), '/');

		return (bool) preg_match('/^[+-]?[0-9]'.$digits.$decimal.'[0-9]{'.$places.'}$/D', $str);
	}

	/**
	 * Checks if a string is a proper hexadecimal HTML color value. The validation
	 * is quite flexible as it does not require an initial "#" and also allows for
	 * the short notation using only three instead of six hexadecimal characters.
	 *
	 * @param   mixed  $str    input string
	 * @return  bool
	 */
	public static function color(mixed $str): bool
	{
		$str = Valid::_text($str);

		return $str !== null AND (bool) preg_match('/^#?+[0-9a-f]{3}(?:[0-9a-f]{3})?$/iD', $str);
	}

	/**
	 * Checks whether a string is a UUID, in the usual form of 32 hexadecimal
	 * digits in five groups: "123e4567-e89b-12d3-a456-426614174000".
	 *
	 * @param   mixed  $str  input string
	 * @return  bool
	 */
	public static function uuid(mixed $str): bool
	{
		return is_string($str) AND (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $str);
	}

	/**
	 * Checks whether a string is valid JSON.
	 *
	 * @param   mixed  $str  input string
	 * @return  bool
	 */
	public static function json(mixed $str): bool
	{
		return is_string($str) AND json_validate($str);
	}

	/**
	 * Checks whether a value is a yes/no value as forms and APIs send it:
	 * true, false, 1, 0, "1", "0", "true" or "false".
	 *
	 * @param   mixed  $value  value to check
	 * @return  bool
	 */
	public static function boolean(mixed $value): bool
	{
		return in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true);
	}

	/**
	 * Checks that a value is one of a list of allowed values.
	 *
	 *     $validation->rule('size', 'in', [':value', ['small', 'medium', 'large']]);
	 *
	 * Values are compared as text, so 5 and "5" are the same but "5" and
	 * "05" are not (PHP's own in_array() would accept both).
	 *
	 * @param   mixed  $value    value to check
	 * @param   array<int|string, mixed>  $allowed  allowed values
	 * @return  bool
	 */
	public static function in(mixed $value, array $allowed): bool
	{
		$value = Valid::_text($value);

		return $value !== null AND in_array($value, array_map(fn ($item) => is_scalar($item) ? (string) $item : '', $allowed), true);
	}

	/**
	 * Checks that a string is a date written in exactly the given format
	 * (see the formats of PHP's date() function).
	 *
	 *     Valid::date_format('2026-02-28', 'Y-m-d');  // true
	 *     Valid::date_format('2026-02-30', 'Y-m-d');  // false: no such day
	 *     Valid::date_format('28/02/2026', 'Y-m-d');  // false: other format
	 *
	 * @param   mixed   $str     date to check
	 * @param   string  $format  expected format
	 * @return  bool
	 */
	public static function date_format(mixed $str, string $format): bool
	{
		$str = Valid::_text($str);

		if ($str === null)
			return false;

		// "!" resets the parts the format does not mention, instead of using the current time
		$date = DateTime::createFromFormat('!'.$format, $str);

		// Writing the date back must give the same text: that rejects
		// impossible dates, which PHP would otherwise roll over to the next month
		return $date !== false AND $date->format($format) === $str;
	}

	/**
	 * Checks if a field matches the value of another field.
	 *
	 * @param   array<int|string, mixed>|ArrayAccess<*, *>  $array  array of values (or a Validation object)
	 * @param   string             $field  field name
	 * @param   string             $match  field name to match
	 * @return  bool
	 */
	public static function matches(array|ArrayAccess $array, string $field, string $match): bool
	{
		// A missing field counts as null
		return (($array[$field] ?? null) === ($array[$match] ?? null));
	}

	/**
	 * Checks type of field value.
	 *
	 *     Valid::type($value, 'string');           // gettype() names
	 *     Valid::type($value, 'numeric', 'array'); // or any is_*() function
	 *
	 * @param   mixed   $value     Field value.
	 * @param   string  ...$types  Valid value types.
	 * @return  bool
	 */
	public static function type(mixed $value, string ...$types): bool
	{
		if (in_array(gettype($value), $types, true))
			return true;

		// Check extra types as "numeric", "iterable", "countable" and etc.
		foreach ($types as $type)
		{
			$func = 'is_'.$type;

			if (function_exists($func) AND $func($value))
				return true;
		}

		return false;
	}

	/**
	 * Tests if a field value more than minimum.
	 *
	 * @param   mixed      $value  Field value.
	 * @param   int|float  $min    Minimum value.
	 * @return  bool
	 */
	public static function more(mixed $value, int|float $min): bool
	{
		return is_numeric($value) AND $value > $min;
	}

	/**
	 * Tests if a field value more or equal than minimum.
	 *
	 * @param   mixed      $value  Field value.
	 * @param   int|float  $min    Minimum value.
	 * @return  bool
	 */
	public static function more_or_equal(mixed $value, int|float $min): bool
	{
		return is_numeric($value) AND $value >= $min;
	}

	/**
	 * Tests if a field value less than maximum.
	 *
	 * @param   mixed      $value  Field value.
	 * @param   int|float  $max    Maximum value.
	 * @return  bool
	 */
	public static function less(mixed $value, int|float $max): bool
	{
		return is_numeric($value) AND $value < $max;
	}

	/**
	 * Tests if a field value less or equal than maximum.
	 *
	 * @param   mixed      $value  Field value.
	 * @param   int|float  $max    Maximum value.
	 * @return  bool
	 */
	public static function less_or_equal(mixed $value, int|float $max): bool
	{
		return is_numeric($value) AND $value <= $max;
	}

}
