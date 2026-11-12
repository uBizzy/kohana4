# Dates and Numbers

## Lengths of time

```php
Date::MINUTE;   // 60
Date::HOUR;     // 3600
Date::DAY;      // 86400
Date::WEEK;     // 604800
Date::MONTH;    // 2629744 (an average month)
Date::YEAR;     // 31556926

Cookie::set('theme', 'dark', 30 * Date::DAY);
```

## Time between two moments

```php
// For people
Date::fuzzy_span(time() - 4000);        // 'a couple of hours ago'
Date::fuzzy_span(time() - 3 * Date::DAY);   // 'a couple of days ago'
Date::fuzzy_span(time() + 90000);       // 'in about a day'

// Exactly
Date::span($start, $end);
// ['years' => 0, 'months' => 0, 'weeks' => 0, 'days' => 1, 'hours' => 1, 'minutes' => 1, 'seconds' => 1]

// Only some units
Date::span($start, $end, 'days,hours');   // ['days' => 1, 'hours' => 1]
Date::span($start, $end, 'hours');        // 25
```

## Time zones

```php
// A moment, written for a time zone
Date::formatted_time('now', 'Y-m-d H:i', 'Europe/Lisbon');
Date::formatted_time('2026-11-11 10:00', 'D, d M Y', 'America/New_York');

// Seconds between two zones
Date::offset('America/New_York', 'Europe/Lisbon');   // -18000
```

Keep moments in the database as UTC or as timestamps, and convert when you show them.

## Lists for date and time fields

```php
echo Form::select('month', Date::months(Date::MONTHS_LONG), date('n'));
// January, February, ... in the language of I18n::lang()

echo Form::select('day', Date::days(2, 2028));        // 1 to 29
echo Form::select('year', Date::years(2024, 2030));
echo Form::select('hour', Date::hours());             // 1 to 12
echo Form::select('hour', Date::hours(1, true));      // 0 to 23
echo Form::select('minute', Date::minutes(15));       // 00, 15, 30, 45

Date::ampm(15);          // 'PM'
Date::adjust(3, 'pm');   // '15'
```

## Numbers

```php
// For the locale set with setlocale()
Num::format(1234567.891, 2);         // '1,234,567.89'

// 1st, 2nd, 3rd
echo 22 .Num::ordinal(22);           // '22nd'

// "2MB" as written in php.ini, in bytes
Num::bytes('2MB');                   // 2097152.0

// The other way round
Text::bytes(2097152);                // '2.10 MB'
```

### Rounding

```php
Num::round(2.5);                            // 3.0
Num::round(2.5, 0, Num::ROUND_HALF_EVEN);   // 2.0 ("banker's rounding")
Num::round(1.955, 2);                       // 1.96
```

| Mode | 2.5 becomes |
|---|---|
| `Num::ROUND_HALF_UP` (default) | 3 |
| `Num::ROUND_HALF_DOWN` | 2 |
| `Num::ROUND_HALF_EVEN` | 2 |
| `Num::ROUND_HALF_ODD` | 3 |

[!!] For money, keep amounts as whole cents (integers) and format them only when shown. Floating point numbers cannot hold every decimal exactly.
