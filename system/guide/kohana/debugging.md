# Debugging

## Looking at values

```php
// One or more values, with their types, as HTML
echo Debug::vars($user, $this->request->post());

// One value, as text of at most 128 characters per string, 3 levels deep
echo Debug::dump($order, 128, 3);
```

```text
array(2) (
    "name" => string(3) "Ana"
    "password" => hidden ***
)
```

Values whose name looks secret are hidden. Add names of your own:

```php
Debug::$sensitive[] = 'iban';
```

## Where am I?

```php
// A short path: "APPPATH/classes/Controller/Article.php"
echo Debug::path(__FILE__);

// The source around a line, as HTML
echo Debug::source(__FILE__, __LINE__);

```

The quickest way to see everything at one point is to stop there:

```php
throw new Kohana_Exception('Here: :value', [':value' => Debug::dump($value)]);
```

The [error page](errors) then shows the steps, the values passed to each function and the request.

## Writing to the log instead

When the page must keep working, or on a server where you cannot print:

```php
Kohana::$log->add(Log::DEBUG, 'Order :id has :count lines', [':id' => $order->id, ':count' => count($lines)]);
```

See [Logging](logging).

## What did the database do?

```php
// The last query that ran
echo Database::instance()->last_query;

// With profiling on, every query and its time is in the profiler
echo View::factory('profiler/stats');
```

See [Profiling](profiling).

## An error as text

```php
try
{
	// ...
}
catch (Throwable $e)
{
	// One line: class, code, message, file and line
	echo Kohana_Exception::text($e);

	// Everything, as on the error page, in plain text
	echo Kohana_Exception::plain($e);
}
```

## Tools around the framework

| Tool | For |
|---|---|
| `php docker/load-all-classes.php` | Classes that no longer fit their parent after a change |
| `vendor/bin/phpunit` | The tests: see the [Unittest module](../unittest) |
| `vendor/bin/phpcs` | Code style |
| `vendor/bin/phpstan analyse` | Types and calls that cannot be right |
| Xdebug | Stepping through the code in your editor |
