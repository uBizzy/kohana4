# Writing Tests

A test is a class in a file named `<Something>Test.php`, in a `tests/` directory. It extends `Unittest_TestCase`; each public method whose name starts with `test` is one test.

```php
// application/tests/PriceTest.php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('shop')]
class PriceTest extends Unittest_TestCase
{
	public function test_tax_is_added(): void
	{
		$this->assertSame(123.0, Price::with_tax(100));
	}

	/**
	 * Amounts, rates and what is expected.
	 */
	public static function provider_with_tax(): array
	{
		return [
			'standard rate' => [100, 0.23, 123.0],
			'reduced rate'  => [100, 0.06, 106.0],
			'nothing'       => [0, 0.23, 0.0],
		];
	}

	#[DataProvider('provider_with_tax')]
	public function test_with_tax(float $amount, float $rate, float $expected): void
	{
		$this->assertSame($expected, Price::with_tax($amount, $rate));
	}
}
```

```bash
vendor/bin/phpunit --group shop
```

## The rules of PHPUnit 13

- Tests are marked with **attributes**: `#[DataProvider('name')]`, `#[Group('name')]`, `#[Depends('test_name')]`. The old comments (`@dataProvider`, `@group`) are ignored.
- Data providers are `public static`.
- A method must start with `test`, or carry `#[Test]`.
- `setUp()` and `tearDown()` are declared `: void` and call the parent.

## Checking results

```php
$this->assertSame('ola-mundo', URL::title('Olá Mundo', '-', true));   // same value and type
$this->assertEquals(5, '5');                    // same value, any type: prefer assertSame
$this->assertTrue(Valid::email('ana@example.org'));
$this->assertNull(Arr::get([], 'missing'));
$this->assertCount(3, $rows);
$this->assertArrayHasKey('title', $errors);
$this->assertStringContainsString('<form', Form::open());
$this->assertInstanceOf(Response::class, $response);
```

An exception that must be thrown:

```php
public function test_unknown_operator_is_refused(): void
{
	$this->expectException(Database_Exception::class);

	DB::select()->from('users')->where('id', 'OR 1=1', 1)->compile();
}
```

## Changing the environment for a test

`setEnvironment()` sets server values, static properties and config options, and puts everything back after the test.

```php
public function test_secure_cookie_over_https(): void
{
	$this->setEnvironment([
		'HTTPS'                 => 'on',              // $_SERVER['HTTPS']
		'Cookie::$secure'       => null,              // a static property
		'Kohana::$environment'  => Kohana::PRODUCTION,
		'site.name'             => 'Test shop',       // Kohana::$config->load('site.name')
	]);

	$this->assertTrue(Cookie::is_secure());
}
```

For every test of a class:

```php
protected array $environmentDefault = [
	'Kohana::$base_url' => '/shop/',
	'url.trusted_hosts' => ['www\.example\.com'],
];
```

## Testing a controller

Run a request and look at the response.

```php
public function test_article_page(): void
{
	$response = Request::factory('article/view/1')->execute();

	$this->assertSame(200, $response->status());
	$this->assertStringContainsString('<h1>', $response->body());
}

public function test_missing_article_is_404(): void
{
	$this->expectException(HTTP_Exception_404::class);

	Request::factory('article/view/999999')->execute();
}
```

With form data:

```php
$response = Request::factory('contact/send')
	->method(HTTP_Request::POST)
	->post(['email' => 'not an address', 'message' => 'Hi'])
	->execute();

$this->assertStringContainsString('email address', $response->body());
```

## Skipping

```php
// phpcs:ignore
public function setUp(): void
{
	parent::setUp();

	if ( ! extension_loaded('redis'))
	{
		$this->markTestSkipped('The redis extension is needed');
	}

	if ( ! $this->hasInternet())
	{
		$this->markTestSkipped('No internet connection');
	}
}
```

## Helpers of Unittest_TestCase

| Method | Does |
|---|---|
| `setEnvironment(array)` | Sets and restores server values, static properties and config options |
| `cleanCacheDir()` | Empties `application/cache` |
| `hasInternet()` | Is there an internet connection? |
| `dirSeparator($path)` | Turns `/` into the directory separator of the system |
| `getMockForAbstractClass(...)` | A double of an abstract class: see [Stubs and Mocks](mocks) |

## Classes shared by tests

A base class or helper that several tests use, and that is not a test itself, must not be in a file named `*Test.php`. It is loaded by its name from the `tests/` directories: `Shop_TestBase` is `tests/Shop/TestBase.php`.

## What makes a good test

- One thing per test, named after what it checks: `test_expired_coupon_is_refused`.
- No test depends on another having run first.
- Tests clean up what they create: files, rows, config changes.
- Test through public methods. A test that reaches into private parts breaks at every refactoring.
