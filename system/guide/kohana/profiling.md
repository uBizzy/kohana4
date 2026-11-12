# Profiling

The profiler measures how long pieces of code take and how much memory they use.

## Showing the numbers

```php
<!-- At the end of your layout, while developing -->
<?php if (Kohana::$profiling): ?>
	<?php echo View::factory('profiler/stats') ?>
<?php endif ?>
```

The table lists every measured piece by group: minimum, maximum, average and total time and memory, and the totals of the request. The framework measures its own steps (finding files, requests, every view that is rendered) and the database module measures every query.

[!!] The debug bar of the `ub_debug` module (in `extra/`, not part of the core) shows the same measurements, and much more, in a bar at the bottom of the page, without anything in your layout.

## What the framework measures

Each measurement has a group, a name that says what it was about, and often a note that says what came of it.

| Group | Name | Note |
|---|---|---|
| `find file` | The file that was looked for: `views/shop/cart.php` | Where it was found, or `not found` |
| `classes` | A class the autoloader loaded | Its file |
| `config (config)` | A config group: `database` | |
| `routing` | The address of the request | The route that matched, and the routes tried before it |
| `requests` | The address of a request or sub-request | Controller, action and status of the answer |
| `controller` | `Controller_Shop::before`, `::action_cart`, `::after` | |
| `views` | A view that was rendered | The names of the variables it was given |
| `validation` | The fields that have rules | `passed`, or `failed:` with the fields that failed |
| `session` | `read` and `write`, with the session class | |
| `i18n` | A language whose texts were loaded | How many texts |
| `database (name)` | A query, by the database module | The number of rows it returned or changed |

Names and notes never hold values: a validation tells which fields failed, not what was typed, and a view tells the names of its variables, not their content.

## Measuring your own code

```php
if (Kohana::$profiling)
{
	// A group and a name of your choice
	$benchmark = Profiler::start('Shop', 'Price calculation');
}

$total = $cart->total();

if (isset($benchmark))
{
	Profiler::stop($benchmark);
}
```

The same in one line. `Profiler::measure()` returns what the function returns, stops the measurement even when the function throws, and only measures when profiling is on:

```php
$total = Profiler::measure('Shop', 'Price calculation', fn () => $cart->total());
```

Measure the same name several times and the profiler shows how often it ran and the average.

Say what came of a measurement with a note:

```php
$benchmark = Profiler::start('Search', 'products');
$found = $index->search($term);
Profiler::stop($benchmark);

Profiler::note($benchmark, count($found).' results');
```

A measurement can remember the place in the code it was made for. The database drivers do this for every query, so tools can tell which line ran it.

```php
$benchmark = Profiler::start('Search', $term, __FILE__, __LINE__);
```

```php
foreach ($feeds as $feed)
{
	$benchmark = Profiler::start('Feeds', $feed->url);

	$feed->refresh();

	Profiler::stop($benchmark);
}
```

A measurement that should not count (the code failed):

```php
Profiler::delete($benchmark);
```

## Reading the numbers in code

```php
// Time and memory of one measurement
[$time, $memory] = Profiler::total($benchmark);

// Of the whole request so far
$stats = Profiler::application();
echo $stats['current']['time'];
echo $stats['current']['memory'];

// Totals of a group
$groups = Profiler::group_stats('Shop');
echo $groups['Shop']['total']['time'];
```

```php
// The main numbers of the request so far
$summary = Profiler::summary();

echo $summary['time'];         // seconds since the request started
echo $summary['memory'];       // most memory used, in bytes
echo $summary['queries'];      // number of database queries
echo $summary['query_time'];   // seconds spent in them
echo $summary['files'];        // PHP files loaded
```

```php
// Every measurement, in the order they started: for a timeline
foreach (Profiler::marks() as $mark)
{
	echo $mark['group'], ' ', $mark['name'], ' ', $mark['stop_time'] - $mark['start_time'];
}
```

## Slow requests in the log

Set a number of seconds, and every request that takes longer is written to the [log](logging) as a warning. This works in every environment, production too, and also when profiling is off.

```php
// application/bootstrap.php
Profiler::$slow_request = 1.0;
```

```text
WARNING: Slow request: 2.318 s, 14.2 MB, 212 queries (1.904 s): GET /articles
```

Only the path is logged, not the query string. The number of queries is only known when profiling is on.

## Data of your own

Keep a value with the request, to look at it while developing. The debug bar of the `ub_debug` module shows it; without such a tool nothing happens with it, so the line can stay in the code.

```php
Profiler::add('Cart', $cart->as_array());
```

## Switching it on and off

```php
// application/bootstrap.php
Kohana::init([
	'profile' => Kohana::$environment !== Kohana::PRODUCTION,
]);
```

Profiling costs a little time and memory itself; leave it off in production.

## Comparing two ways to write something

For "which of these two functions is faster?", use the [Codebench module](../codebench): it runs each version many times on the same input.
