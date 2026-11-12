# Stubs and Mocks

To test one class alone, replace the classes it uses with stand-ins. PHPUnit makes two kinds.

| | A stub | A mock |
|---|---|---|
| For | Giving the tested code what it needs | Checking that the tested code calls something |
| Made with | `createStub()` | `createMock()` |
| Has expectations | no | yes |

## A stub: canned answers

```php
public function test_report_uses_the_cache(): void
{
	// A cache that always has the value
	$cache = $this->createStub(Cache_File::class);
	$cache->method('get')->willReturn(['total' => 42]);

	$report = new Model_Report($cache);

	$this->assertSame(42, $report->total());
}
```

```php
// Different answers on each call
$cache->method('get')->willReturn(null, ['total' => 42]);

// An answer computed from the arguments
$cache->method('get')->willReturnCallback(fn (string $id, mixed $default = null) => $id === 'known' ? 1 : $default);

// An exception
$cache->method('set')->willThrowException(new Cache_Exception('Disk full'));
```

## A mock: expected calls

```php
public function test_saving_clears_the_cache(): void
{
	$cache = $this->createMock(Cache_File::class);

	// The test fails unless delete('product.12') is called exactly once
	$cache->expects($this->once())
		->method('delete')
		->with('product.12');

	(new Model_Catalog($cache))->changed(12);
}
```

| Expectation | Passes when the method is called |
|---|---|
| `$this->once()` | exactly once |
| `$this->never()` | not at all |
| `$this->exactly(3)` | three times |
| `$this->atLeastOnce()` | once or more |

[!!] PHPUnit reports a mock that has no expectation: use `createStub()` when you only need answers.

## Calls in a given order

There is no `at()` any more. Record the calls and check them afterwards:

```php
$calls = [];

$cache->expects($this->exactly(2))
	->method('set')
	->willReturnCallback(function (string $id, mixed $data) use (&$calls) {
		$calls[] = $id;

		return true;
	});

$model->warm_up();

$this->assertSame(['settings', 'menu'], $calls);
```

## Keeping part of the real class

```php
// Only send() is replaced; everything else is the real code
$mailer = $this->getMockBuilder(Mailer::class)
	->setConstructorArgs(['smtp.example.org'])
	->onlyMethods(['send'])
	->getMock();

$mailer->expects($this->once())->method('send')->willReturn(true);
```

## Abstract classes

`getMockForAbstractClass()` of `Unittest_TestCase` makes an object of an abstract class: its abstract methods are replaced, the rest is real.

```php
// The real Session, with the storage methods replaced
$session = $this->getMockForAbstractClass('Session', [[]]);

$session->set('cart', [1, 2]);

$this->assertSame([1, 2], $session->get('cart'));
```

```php
// getMockForAbstractClass($class, $constructor_arguments, $call_constructor, $other_methods_to_replace)
$session = $this->getMockForAbstractClass('Session', [], false, ['read']);
```

A test class that uses such objects only as plain objects, without expectations, carries this attribute:

```php
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class SessionTest extends Unittest_TestCase
{
}
```

## What cannot be replaced

- **Static methods** (`Arr::get()`, `DB::select()`): they are called on the class itself. Test them for real, or wrap what you need in an object you can replace.
- **`Request`**: PHPUnit cannot double a class that has a method named `method`. Make a real one: `Request::factory('article/view/1')`.
- **Final classes and private methods.**

## Making code testable

Code that fetches its helpers itself cannot be given stand-ins. Let it take them:

```php
class Model_Report {

	// The cache can be replaced in a test; the default is the real one
	public function __construct(protected ?Cache $cache = null)
	{
		$this->cache ??= Cache::instance();
	}

	public function total(): int
	{
		return (int) ($this->cache->get('report')['total'] ?? 0);
	}
}
```
