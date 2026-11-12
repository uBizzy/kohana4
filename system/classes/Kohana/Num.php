<?php
/**
 * Number helper class. Provides additional formatting methods that for working
 * with numbers.
 *
 * @package    Kohana
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Num {

	// Tie-breaking modes for Num::round(); the values match PHP's PHP_ROUND_HALF_* constants
	const int ROUND_HALF_UP		= 1;
	const int ROUND_HALF_DOWN	= 2;
	const int ROUND_HALF_EVEN	= 3;
	const int ROUND_HALF_ODD	= 4;

	/**
	 * @var  array<string, int>  Valid byte units => power of 2 that defines the unit's size
	 */
	public static array $byte_units = [
		'B'   => 0,
		'K'   => 10,
		'Ki'  => 10,
		'KB'  => 10,
		'KiB' => 10,
		'M'   => 20,
		'Mi'  => 20,
		'MB'  => 20,
		'MiB' => 20,
		'G'   => 30,
		'Gi'  => 30,
		'GB'  => 30,
		'GiB' => 30,
		'T'   => 40,
		'Ti'  => 40,
		'TB'  => 40,
		'TiB' => 40,
		'P'   => 50,
		'Pi'  => 50,
		'PB'  => 50,
		'PiB' => 50,
		'E'   => 60,
		'Ei'  => 60,
		'EB'  => 60,
		'EiB' => 60,
		'Z'   => 70,
		'Zi'  => 70,
		'ZB'  => 70,
		'ZiB' => 70,
		'Y'   => 80,
		'Yi'  => 80,
		'YB'  => 80,
		'YiB' => 80,
	];

	/**
	 * Returns the English ordinal suffix (th, st, nd, etc) of a number.
	 *
	 *     echo 2, Num::ordinal(2);   // "2nd"
	 *     echo 10, Num::ordinal(10); // "10th"
	 *     echo 33, Num::ordinal(33); // "33rd"
	 *
	 * @param   int     $number
	 * @return  string
	 */
	public static function ordinal(int $number): string
	{
		// 11, 12 and 13 are irregular: always "th"
		if ($number % 100 > 10 AND $number % 100 < 14)
		{
			return 'th';
		}

		switch ($number % 10)
		{
			case 1:
				return 'st';
			case 2:
				return 'nd';
			case 3:
				return 'rd';
			default:
				return 'th';
		}
	}

	/**
	 * Locale-aware number and monetary formatting.
	 *
	 *     // In English, "1,200.05"
	 *     // In Spanish, "1200,05"
	 *     // In Portuguese, "1 200,05"
	 *     echo Num::format(1200.05, 2);
	 *
	 *     // In English, "1,200.05"
	 *     // In Spanish, "1.200,05"
	 *     // In Portuguese, "1.200.05"
	 *     echo Num::format(1200.05, 2, true);
	 *
	 * @param   int|float $number   number to format
	 * @param   int       $places   decimal places
	 * @param   bool      $monetary monetary formatting?
	 * @return  string
	 * @since   3.0.2
	 */
	public static function format(int|float $number, int $places, bool $monetary = false): string
	{
		// Separators of the locale set with setlocale()
		$info = localeconv();

		if ($monetary)
		{
			// The C/POSIX locale has no monetary decimal point: use the numeric one
			$decimal   = $info['mon_decimal_point'] ?: $info['decimal_point'];
			$thousands = $info['mon_thousands_sep'];
		}
		else
		{
			$decimal   = $info['decimal_point'];
			$thousands = $info['thousands_sep'];
		}

		return number_format($number, $places, $decimal, $thousands);
	}

	/**
	 * Round a number to a specified precision, using a specified tie breaking technique
	 *
	 *     Num::round(2.5);                            // 3.0
	 *     Num::round(2.5, 0, Num::ROUND_HALF_EVEN);   // 2.0
	 *
	 * @param   int|float $value      Number to round
	 * @param   int       $precision  Desired precision
	 * @param   int<1, 4> $mode       Tie breaking mode, one of the Num::ROUND_HALF_* constants
	 * @param   bool      $native     Set to false to force use of the userland implementation
	 * @return  float     Rounded number
	 */
	public static function round(int|float $value, int $precision = 0, int $mode = self::ROUND_HALF_UP, bool $native = true): float
	{
		if ($native)
		{
			return round($value, $precision, $mode);
		}

		// Shift the decimal point so the digit to round sits just after it
		$factor = 10 ** $precision;
		$scaled = $value * $factor;

		// Only an exact tie (x.5) needs special handling, and only for the
		// modes that differ from PHP's default "half up"
		$is_tie = ($scaled - floor($scaled)) === 0.5;

		if ( ! $is_tie OR ! in_array($mode, [self::ROUND_HALF_DOWN, self::ROUND_HALF_EVEN, self::ROUND_HALF_ODD], true))
		{
			return round($value, $precision);
		}

		if ($mode === self::ROUND_HALF_DOWN)
		{
			// Round towards zero: that means up for negative numbers
			$up = ($value < 0);
		}
		else
		{
			// Round up when the integer part is odd and the mode is "even",
			// or when it is even and the mode is "odd"
			$is_odd = (bool) ((int) floor($scaled) & 1);
			$up     = ($is_odd === ($mode === self::ROUND_HALF_EVEN));
		}

		return ($up ? ceil($scaled) : floor($scaled)) / $factor;
	}

	/**
	 * Converts a file size number to a byte value. File sizes are defined in
	 * the format: SB, where S is the size (1, 8.5, 300, etc.) and B is the
	 * byte unit (K, MiB, GB, etc.). All valid byte units are defined in
	 * Num::$byte_units
	 *
	 *     echo Num::bytes('200K');  // 204800
	 *     echo Num::bytes('5MiB');  // 5242880
	 *     echo Num::bytes('1000');  // 1000
	 *     echo Num::bytes('2.5GB'); // 2684354560
	 *
	 * @param   string|int|float  $size  file size in SB format
	 * @return  float
	 * @throws  Kohana_Exception  if the size is not in a recognised format
	 */
	public static function bytes(string|int|float $size): float
	{
		// Prepare the size
		$size = trim( (string) $size);

		// Construct an OR list of byte units for the regex
		$accepted = implode('|', array_keys(Num::$byte_units));

		// Construct the regex pattern for verifying the size format
		$pattern = '/^([0-9]+(?:\.[0-9]+)?)('.$accepted.')?$/Di';

		// Verify the size format and store the matching parts
		if ( ! preg_match($pattern, $size, $matches))
			throw new Kohana_Exception('The byte unit size, ":size", is improperly formatted.', [
				':size' => $size,
			]);

		// Find the float value of the size
		$size = (float) $matches[1];

		// Find the actual unit, assume B if no unit specified
		$unit = $matches[2] ?? 'B';

		// Convert the size into bytes
		return $size * (2 ** Num::$byte_units[$unit]);
	}

}
