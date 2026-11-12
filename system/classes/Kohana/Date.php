<?php
/**
 * Date helper.
 *
 * @package    Kohana
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Date {

	// Second amounts for various time increments
	const int YEAR   = 31556926;
	const int MONTH  = 2629744;
	const int WEEK   = 604800;
	const int DAY    = 86400;
	const int HOUR   = 3600;
	const int MINUTE = 60;

	// Available formats for Date::months(). The values are only identifiers,
	// kept from the time month names were produced with strftime().
	const string MONTHS_LONG  = '%B';
	const string MONTHS_SHORT = '%b';

	/**
	 * Default timestamp format for formatted_time
	 * @var  string
	 */
	public static string $timestamp_format = 'Y-m-d H:i:s';

	/**
	 * Timezone for formatted_time
	 * @link http://uk2.php.net/manual/en/timezones.php
	 * @var  string|null  null uses PHP's default timezone
	 */
	public static ?string $timezone = null;

	/**
	 * Returns the offset (in seconds) between two time zones. Use this to
	 * display dates to users in different time zones.
	 *
	 *     $seconds = Date::offset('America/Chicago', 'GMT');
	 *
	 * [!!] A list of time zones that PHP supports can be found at
	 * <http://php.net/timezones>.
	 *
	 * @param   string  $remote timezone that to find the offset of
	 * @param   string  $local  timezone used as the baseline
	 * @param   int|string|null   $now    UNIX timestamp or date string
	 * @return  int
	 */
	public static function offset(string $remote, ?string $local = null, int|string|null $now = null): int
	{
		// Use the default timezone
		$local ??= date_default_timezone_get();

		if (is_int($now))
		{
			// Convert the timestamp into a string
			$now = date(DateTime::RFC2822, $now);
		}

		// Create timezone objects
		$zone_remote = new DateTimeZone($remote);
		$zone_local  = new DateTimeZone($local);

		// Create date objects from timezones
		// null means the current time
		$time_remote = new DateTime($now ?? 'now', $zone_remote);
		$time_local  = new DateTime($now ?? 'now', $zone_local);

		// Find the offset
		$offset = $zone_remote->getOffset($time_remote) - $zone_local->getOffset($time_local);

		return $offset;
	}

	/**
	 * Number of seconds in a minute, incrementing by a step. Typically used as
	 * a shortcut for generating a list that can used in a form.
	 *
	 *     $seconds = Date::seconds(); // 01, 02, 03, ..., 58, 59, 60
	 *
	 * @param   int $step   amount to increment each step by, 1 to 30
	 * @param   int $start  start value
	 * @param   int $end    end value
	 * @return  array<int, string>   A mirrored (foo => foo) array from 1-60.
	 */
	public static function seconds(int $step = 1, int $start = 0, int $end = 60): array
	{
		// A step below 1 would never reach the end
		$step = max(1, $step);

		$seconds = [];

		for ($i = $start; $i < $end; $i += $step)
		{
			$seconds[$i] = sprintf('%02d', $i);
		}

		return $seconds;
	}

	/**
	 * Number of minutes in an hour, incrementing by a step. Typically used as
	 * a shortcut for generating a list that can be used in a form.
	 *
	 *     $minutes = Date::minutes(); // 05, 10, 15, ..., 50, 55, 60
	 *
	 * @uses    Date::seconds
	 * @param   int $step   amount to increment each step by, 1 to 30
	 * @return  array<int, string>   A mirrored (foo => foo) array from 1-60.
	 */
	public static function minutes(int $step = 5): array
	{
		// Because there are the same number of minutes as seconds in this set,
		// we choose to re-use seconds(), rather than creating an entirely new
		// function. Shhhh, it's cheating! ;) There are several more of these
		// in the following methods.
		return Date::seconds($step);
	}

	/**
	 * Number of hours in a day. Typically used as a shortcut for generating a
	 * list that can be used in a form.
	 *
	 *     $hours = Date::hours(); // 01, 02, 03, ..., 10, 11, 12
	 *
	 * @param   int $step   amount to increment each step by
	 * @param   bool $long   use 24-hour time
	 * @param   int $start  the hour to start at
	 * @return  array<int, string>   A mirrored (foo => foo) array from start-12 or start-23.
	 */
	public static function hours(int $step = 1, bool $long = false, ?int $start = null): array
	{
		// A step below 1 would never reach the end
		$step = max(1, $step);

		// 12-hour time starts at 1, 24-hour time at 0
		$start ??= $long ? 0 : 1;

		// Last hour of the list
		$size = $long ? 23 : 12;

		$hours = [];

		for ($i = $start; $i <= $size; $i += $step)
		{
			$hours[$i] = (string) $i;
		}

		return $hours;
	}

	/**
	 * Returns AM or PM, based on a given hour (in 24 hour format).
	 *
	 *     $type = Date::ampm(12); // PM
	 *     $type = Date::ampm(1);  // AM
	 *
	 * @param   int $hour   number of the hour
	 * @return  string
	 */
	public static function ampm(int $hour): string
	{
		return ($hour > 11) ? 'PM' : 'AM';
	}

	/**
	 * Adjusts a non-24-hour number into a 24-hour number.
	 *
	 *     $hour = Date::adjust(3, 'pm'); // 15
	 *
	 * @param   int $hour   hour to adjust
	 * @param   string  $ampm   AM or PM
	 * @return  string
	 */
	public static function adjust(int $hour, string $ampm): string
	{
		$ampm = strtolower($ampm);

		switch ($ampm)
		{
			case 'am':
				if ($hour == 12)
				{
					$hour = 0;
				}
			break;
			case 'pm':
				if ($hour < 12)
				{
					$hour += 12;
				}
			break;
		}

		return sprintf('%02d', $hour);
	}

	/**
	 * Number of days in a given month and year. Typically used as a shortcut
	 * for generating a list that can be used in a form.
	 *
	 *     Date::days(4, 2010); // 1, 2, 3, ..., 28, 29, 30
	 *
	 * @param   int $month  number of month
	 * @param   int|false|null $year   number of year to check month, defaults to the current year
	 * @return  array<int, string>   A mirrored (foo => foo) array of the days.
	 */
	public static function days(int $month, int|false|null $year = null): array
	{
		// Results already built, by year and month
		/** @var array<int, array<int, array<int, string>>> $months */
		static $months = [];

		// Use the current year by default (false is accepted for compatibility)
		$year = (int) ($year ?: date('Y'));

		// We use caching for months, because time functions are used
		if (empty($months[$year][$month]))
		{
			$months[$year][$month] = [];

			// Use date to find the number of days in the given month
			$total = (int) date('t', (int) mktime(1, 0, 0, $month, 1, $year)) + 1;

			for ($i = 1; $i < $total; $i++)
			{
				$months[$year][$month][$i] = (string) $i;
			}
		}

		return $months[$year][$month];
	}

	/**
	 * Number of months in a year. Typically used as a shortcut for generating
	 * a list that can be used in a form.
	 *
	 * By default a mirrored array of $month_number => $month_number is returned
	 *
	 *     Date::months();
	 *     // aray(1 => 1, 2 => 2, 3 => 3, ..., 12 => 12)
	 *
	 * But you can customise this by passing in either Date::MONTHS_LONG
	 *
	 *     Date::months(Date::MONTHS_LONG);
	 *     // array(1 => 'January', 2 => 'February', ..., 12 => 'December')
	 *
	 * Or Date::MONTHS_SHORT
	 *
	 *     Date::months(Date::MONTHS_SHORT);
	 *     // array(1 => 'Jan', 2 => 'Feb', ..., 12 => 'Dec')
	 *
	 * Month names are written in the current [I18n::$lang] language.
	 *
	 * @uses    Date::hours
	 * @uses    IntlDateFormatter
	 * @param   string  $format The format to use for months
	 * @return  array<int, int|string>   An array of months based on the specified format
	 */
	public static function months(?string $format = null): array
	{
		$months = [];

		if ($format === Date::MONTHS_LONG OR $format === Date::MONTHS_SHORT)
		{
			// Month names come from the intl extension, in the language set with
			// I18n::lang(). 'LLLL' is the full stand-alone name, 'LLL' the short one.
			$formatter = new IntlDateFormatter(
				I18n::$lang,
				IntlDateFormatter::NONE,
				IntlDateFormatter::NONE,
				'UTC',
				null,
				($format === Date::MONTHS_LONG) ? 'LLLL' : 'LLL'
			);

			for ($i = 1; $i <= 12; ++$i)
			{
				$months[$i] = (string) $formatter->format( (int) gmmktime(0, 0, 0, $i, 1));
			}
		}
		else
		{
			$months = Date::hours();
		}

		return $months;
	}

	/**
	 * Returns an array of years between a starting and ending year. By default,
	 * the the current year - 5 and current year + 5 will be used. Typically used
	 * as a shortcut for generating a list that can be used in a form.
	 *
	 *     $years = Date::years(2000, 2010); // 2000, 2001, ..., 2009, 2010
	 *
	 * @param   int|false|null $start  starting year (default is current year - 5)
	 * @param   int|false|null $end    ending year (default is current year + 5)
	 * @return  array<int, string>
	 */
	public static function years(int|false|null $start = null, int|false|null $end = null): array
	{
		// Default values (false is accepted for compatibility)
		$start = ($start === null OR $start === false) ? ( (int) date('Y') - 5) : $start;
		$end   = ($end   === null OR $end   === false) ? ( (int) date('Y') + 5) : $end;

		$years = [];

		for ($i = $start; $i <= $end; $i++)
		{
			$years[$i] = (string) $i;
		}

		return $years;
	}

	/**
	 * Returns time difference between two timestamps, in human readable format.
	 * If the second timestamp is not given, the current time will be used.
	 * Also consider using [Date::fuzzy_span] when displaying a span.
	 *
	 *     $span = Date::span(60, 182, 'minutes,seconds'); // array('minutes' => 2, 'seconds' => 2)
	 *     $span = Date::span(60, 182, 'minutes'); // 2
	 *
	 * @param   int       $remote timestamp to find the span of
	 * @param   ?int  $local  timestamp to use as the baseline; null for now
	 * @param   string    $output formatting string
	 * @return  int|array<string, int>|false   a number when a single output is requested,
	 *                            an associative list when several are,
	 *                            false when no output is requested
	 */
	public static function span(int $remote, ?int $local = null, string $output = 'years,months,weeks,days,hours,minutes,seconds'): int|array|false
	{
		// Normalize output
		$output = trim(strtolower($output));

		if ( ! $output)
		{
			// Invalid output
			return false;
		}

		// Array with the output formats, each starting at 0
		$output = array_fill_keys(preg_split('/[^a-z]+/', $output) ?: [], 0);

		// Calculate timespan (seconds)
		$timespan = abs($remote - ($local ?? time()));

		// Seconds in each unit, from the largest to the smallest
		$units = [
			'years'   => Date::YEAR,
			'months'  => Date::MONTH,
			'weeks'   => Date::WEEK,
			'days'    => Date::DAY,
			'hours'   => Date::HOUR,
			'minutes' => Date::MINUTE,
			'seconds' => 1,
		];

		foreach ($units as $unit => $seconds)
		{
			if (isset($output[$unit]))
			{
				// Count the whole units, and keep the remainder for the smaller ones
				$output[$unit] = intdiv($timespan, $seconds);
				$timespan     -= $output[$unit] * $seconds;
			}
		}

		if (count($output) === 1)
		{
			// Only a single output was requested, return it
			return array_pop($output);
		}

		// Return array
		return $output;
	}

	/**
	 * Returns the difference between a time and now in a "fuzzy" way.
	 * Displaying a fuzzy time instead of a date is usually faster to read and understand.
	 *
	 *     $span = Date::fuzzy_span(time() - 10); // "moments ago"
	 *     $span = Date::fuzzy_span(time() + 20); // "in moments"
	 *
	 * A second parameter is available to manually set the "local" timestamp,
	 * however this parameter shouldn't be needed in normal usage and is only
	 * included for unit tests
	 *
	 * @param   int $timestamp          "remote" timestamp
	 * @param   int $local_timestamp    "local" timestamp, defaults to time()
	 * @return  string
	 */
	public static function fuzzy_span(int $timestamp, ?int $local_timestamp = null): string
	{
		$local_timestamp ??= time();

		// Determine the difference in seconds
		$offset = abs($local_timestamp - $timestamp);

		// Each wording applies while the difference is below its limit (in seconds)
		$spans = [
			Date::MINUTE + 1  => 'moments',
			Date::MINUTE * 20 => 'a few minutes',
			Date::HOUR        => 'less than an hour',
			Date::HOUR * 4    => 'a couple of hours',
			Date::DAY         => 'less than a day',
			Date::DAY * 2     => 'about a day',
			Date::DAY * 4     => 'a couple of days',
			Date::WEEK        => 'less than a week',
			Date::WEEK * 2    => 'about a week',
			Date::MONTH       => 'less than a month',
			Date::MONTH * 2   => 'about a month',
			Date::MONTH * 4   => 'a couple of months',
			Date::YEAR        => 'less than a year',
			Date::YEAR * 2    => 'about a year',
			Date::YEAR * 4    => 'a couple of years',
			Date::YEAR * 8    => 'a few years',
			Date::YEAR * 12   => 'about a decade',
			Date::YEAR * 24   => 'a couple of decades',
			Date::YEAR * 64   => 'several decades',
		];

		// Used when the difference is beyond the last limit
		$span = 'a long time';

		foreach ($spans as $limit => $text)
		{
			if ($offset < $limit)
			{
				$span = $text;
				break;
			}
		}

		// This is in the past, or in the future
		return ($timestamp <= $local_timestamp) ? $span.' ago' : 'in '.$span;
	}

	/**
	 * Converts a UNIX timestamp to DOS format. There are very few cases where
	 * this is needed, but some binary formats use it (eg: zip files.)
	 * Converting the other direction is done using {@link Date::dos2unix}.
	 *
	 *     $dos = Date::unix2dos($unix);
	 *
	 * @param   int|false $timestamp  UNIX timestamp; false for now
	 * @return  int
	 */
	public static function unix2dos(int|false $timestamp = false): int
	{
		$timestamp = ($timestamp === false) ? getdate() : getdate($timestamp);

		if ($timestamp['year'] < 1980)
		{
			return (1 << 21 | 1 << 16);
		}

		$timestamp['year'] -= 1980;

		// What voodoo is this? I have no idea... Geert can explain it though,
		// and that's good enough for me.
		return ($timestamp['year']    << 25 | $timestamp['mon']     << 21 |
				$timestamp['mday']    << 16 | $timestamp['hours']   << 11 |
				$timestamp['minutes'] << 5  | $timestamp['seconds'] >> 1);
	}

	/**
	 * Converts a DOS timestamp to UNIX format.There are very few cases where
	 * this is needed, but some binary formats use it (eg: zip files.)
	 * Converting the other direction is done using {@link Date::unix2dos}.
	 *
	 *     $unix = Date::dos2unix($dos);
	 *
	 * @param   int $timestamp  DOS timestamp
	 * @return  int
	 */
	public static function dos2unix(int|false $timestamp = false): int
	{
		// false means "no timestamp", which is 0 in DOS format
		$timestamp = (int) $timestamp;

		$sec  = 2 * ($timestamp & 0x1f);
		$min  = ($timestamp >>  5) & 0x3f;
		$hrs  = ($timestamp >> 11) & 0x1f;
		$day  = ($timestamp >> 16) & 0x1f;
		$mon  = ($timestamp >> 21) & 0x0f;
		$year = ($timestamp >> 25) & 0x7f;

		return (int) mktime($hrs, $min, $sec, $mon, $day, $year + 1980);
	}

	/**
	 * Returns a date/time string with the specified timestamp format
	 *
	 *     $time = Date::formatted_time('5 minutes ago');
	 *
	 * @link    http://www.php.net/manual/datetime.construct
	 * @param   string  $datetime_str       datetime string
	 * @param   string  $timestamp_format   timestamp format
	 * @param   string  $timezone           timezone identifier
	 * @return  string
	 */
	public static function formatted_time(string $datetime_str = 'now', ?string $timestamp_format = null, ?string $timezone = null): string
	{
		$timestamp_format = ($timestamp_format == null) ? Date::$timestamp_format : $timestamp_format;
		$timezone         = ($timezone === null) ? Date::$timezone : $timezone;

		$tz   = new DateTimeZone($timezone ? $timezone : date_default_timezone_get());
		$time = new DateTime($datetime_str, $tz);

		// Convert the time back to the expected timezone if required (in case the datetime_str provided a timezone,
		// offset or unix timestamp. This also ensures that the timezone reported by the object is correct on HHVM
		// (see https://github.com/facebook/hhvm/issues/2302).
		$time->setTimeZone($tz);

		return $time->format($timestamp_format);
	}

}
