# Kohana unit testing

This module prepares Kohana for [PHPUnit](https://phpunit.de) and holds the
helpers the framework's own tests use. Tests need PHPUnit 13 (PHP 8.4+).

## Installation

	composer install

## Running the tests

	vendor/bin/phpunit                          # everything
	vendor/bin/phpunit --group kohana.core.arr  # one group
	vendor/bin/phpunit --filter ArrTest         # one class
	vendor/bin/phpunit system/tests/kohana/ArrTest.php

`phpunit.xml` in the project root says where the tests are (`system/tests`
and `modules/*/tests`; files named `*Test.php`) and loads
`modules/unittest/bootstrap.php`, which starts the application with every
module enabled.

Warnings, notices and deprecations make a run fail.

## Writing tests

	use PHPUnit\Framework\Attributes\DataProvider;
	use PHPUnit\Framework\Attributes\Group;

	#[Group('myapp')]
	class Model_ThingTest extends Unittest_TestCase
	{
		public static function provider_double(): array
		{
			return [[1, 2], [4, 8]];
		}

		#[DataProvider('provider_double')]
		public function test_double(int $value, int $expected): void
		{
			$this->assertSame($expected, Model_Thing::double($value));
		}
	}

- Tests are marked with attributes. The old docblock annotations
  (`@dataProvider`, `@group`, `@depends`, ...) are ignored by PHPUnit 12+.
- Data providers must be `public static`.
- `Unittest_TestCase` adds `setEnvironment()` (set and restore `$_SERVER`
  values, static properties and config options), `cleanCacheDir()`,
  `hasInternet()` and `getMockForAbstractClass()`.
- A class that tests share and that is not a test itself (a base class, a
  helper) must not be in a file named `*Test.php`; it is loaded from the
  `tests` directories by its name (`Foo_Base` is `tests/Foo/Base.php`).
- PHPUnit lowers `error_reporting()` while a test runs and reports PHP
  warnings itself: code must not count on a warning becoming an exception.

## Coverage

	vendor/bin/phpunit --coverage-html build/coverage

needs the Xdebug or PCOV extension. The `<source>` section of `phpunit.xml`
lists the code that is measured.
