# Error Handling

The framework turns every problem into an exception, shows it in the best form for who is looking, and writes it to the log.

## What the visitor sees

| | Development | Production |
|---|---|---|
| Uncaught exception | The error page: message, source, steps, values | "An error occurred" and a reference code |
| `HTTP_Exception` 4xx | The error page | The status text ("Not Found") and a reference code |
| PHP warning or notice | An exception, as above | An exception, as above |
| PHP deprecation | Listed in a panel at the bottom of the page | Only written to the log |
| Fatal error | The error page (no steps: PHP gives none) | "An error occurred" and a reference code |

The form follows the request: an HTML page for a browser, JSON for an AJAX request or one that asks for JSON, plain text on the command line.

```json
{"error": {"status": 500, "message": "An error occurred", "reference": "17B2CA99"}}
```

[!!] In production nothing about the error, the code or the server is sent, in any form. Everything is in the log.

## Finding an error in the log

Every error has a reference code. The visitor sees it; the log line starts with it.

```bash
grep -r "17B2CA99" application/logs/
```

```text
2026-11-11 10:44:12 --- EMERGENCY: [17B2CA99] Kohana_Exception [ 0 ]: Could not save the user ~ APPPATH/classes/Model/User.php [ 42 ]
Caused by: RuntimeException [ 0 ]: Connection lost ~ ...
```

## Throwing

```php
// A problem of your own, with values in the message
throw new Kohana_Exception('The order :id cannot be shipped twice', [':id' => $order->id]);

// Keep the cause: both are shown and logged
try
{
	$this->charge($order);
}
catch (Throwable $e)
{
	throw new Kohana_Exception('Payment of order :id failed', [':id' => $order->id], 0, $e);
}

// An HTTP status
throw HTTP_Exception::factory(404, 'No article :id', [':id' => $id]);
throw HTTP_Exception::factory(403);
```

Exceptions of your own extend `Kohana_Exception`:

```php
class Shop_Exception extends Kohana_Exception {}
```

## Catching

Catch what you can deal with; let the rest reach the error page.

```php
try
{
	$article->save();
}
catch (ORM_Validation_Exception $e)
{
	// Expected: show the form again
	$errors = $e->errors('models');
}
```

To note a problem and carry on:

```php
try
{
	$this->send_newsletter();
}
catch (Throwable $e)
{
	// Written once, with its reference code
	Kohana_Exception::log($e);
}
```

## The development error page

- The error and every error that caused it ("Caused by"), each with its source and steps.
- Steps in your own code are marked and the first one is open.
- Values passed to each function. Those with a secret name are shown as `***` (`Debug::$sensitive`).
- **Copy as text**: the whole error for a bug report.
- The deprecation notices of the request, and the environment (`$_GET`, `$_POST`, `$_SERVER`, loaded files).

Links that open a file in your editor:

```php
// application/bootstrap.php
Debug::$editor = 'vscode://file/:file::line';
// or 'phpstorm://open?file=:file&line=:line'

// The code runs in a container: where the same files are on your computer
Debug::$editor_paths = ['/var/www/html/' => '/home/me/project/'];
```

## Deprecations

A deprecation ("this will stop working in a later PHP version") does not stop the request. It is logged at level `NOTICE`; in development it is also listed in a panel at the bottom of each page.

```php
// Which PHP error levels are treated this way
Kohana::$soft_errors = [E_DEPRECATED, E_USER_DEPRECATED];

// Stop on deprecations too, to find them all
Kohana::$soft_errors = [];
```

## Your own pages

- The production page is the view `kohana/error_production`. Copy it to `application/views/kohana/error_production.php` and style it; it receives `$title`, `$status` and `$reference`.
- A page per status (your own 404): see [Custom Error Pages](tutorials/error-pages).

## Switching it off

```php
// The framework leaves PHP's own error handling alone
Kohana::init(['errors' => false]);
```
