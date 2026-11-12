# When Tests Fail

## Messages of PHPUnit

| Message | Meaning and cure |
|---|---|
| `Class X cannot be found in .../YTest.php` | The class in a test file must be named like the file, or end with it (`Kohana_ArrTest` in `ArrTest.php`) |
| `The data provider ... is invalid ... not static` | Declare the provider `public static function` |
| `Using $this when not in object context` | A data provider used `$this`. Providers are static: use static helpers |
| `No expectations were configured for the mock object` | Use `createStub()`, or add an expectation |
| `Doubling ... that have a method named "method" is not supported` | That class (`Request`) cannot be mocked: use a real object |
| `Call to undefined method ...::at()` / `returnValue()` / `setMethods()` | Removed from PHPUnit: see [Stubs and Mocks](mocks) |
| `Test code or tested code printed unexpected output` | Something printed. Remove the `echo`, or catch it with `ob_start()` |
| `This test did not perform any assertions` | Add an assertion, or `$this->expectNotToPerformAssertions()` |

## A test passes alone and fails in the suite (or the other way round)

One test leaves something behind that another depends on.

- Static properties and config changed without `setEnvironment()` stay changed.
- Run the test with the one before it to find the pair:

```bash
vendor/bin/phpunit --filter 'CookieTest|SessionTest'
vendor/bin/phpunit --order-by random
```

## PHP warnings behave differently in tests

While a test runs, PHPUnit handles PHP warnings itself and lowers `error_reporting()`. In a normal request the framework turns a warning into an exception; in a test it is reported and the code goes on.

So code must not count on "a warning becomes an exception". Check return values:

```php
// Works in a request and in a test
$handle = @fopen($file, 'r');

if ($handle === false)
	throw new Kohana_Exception('Cannot open :file', [':file' => $file]);
```

To test the framework's own error handler, set the level for the test:

```php
$old = error_reporting(E_ALL);

try
{
	// ...
}
finally
{
	error_reporting($old);
}
```

## Deprecations fail the run

By design. The message names the file and line; fix the code. For a deprecation in a library you cannot change, the `<source>` part of `phpunit.xml` limits which code counts.

## The default route catches everything

The test bootstrap loads `application/bootstrap.php`, with its routes, **before** the modules add theirs. A request for a module's address then matches the application's `default` route first. Give the request the routes it should use:

```php
$routes = [Route::get('docs/guide')];

$response = Request::factory('guide/kohana', [], false, $routes)->execute();
```

## Tests are slow

```bash
# The ten slowest
vendor/bin/phpunit --log-junit build/junit.xml

# Only what you are working on
vendor/bin/phpunit --filter ArrTest
vendor/bin/phpunit --group kohana.core
```

Slow tests are almost always ones that reach outside: the network, a real database, the disk.

## Looking at a value in a test

```php
fwrite(STDERR, print_r($value, true));
```

`echo` would be caught as "unexpected output".
