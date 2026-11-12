# Loading Classes

You never `require` a class file. The first time a class is used, the framework finds its file from its name.

```php
// Uses the class Model_User_Token: classes/Model/User/Token.php is loaded now
$token = new Model_User_Token;
```

## The rule

Replace each `_` of the class name with `/`, add `.php`, and look in the `classes/` directories of the [cascading filesystem](files).

| Class | File |
|---|---|
| `Text` | `classes/Text.php` |
| `Controller_Shop_Cart` | `classes/Controller/Shop/Cart.php` |
| `HTTP_Exception_404` | `classes/HTTP/Exception/404.php` |

The file name must match the class name exactly, capitals included. `classes/controller/shop/cart.php` is not found on a server with a case-sensitive filesystem (Linux).

The bootstrap sets this up with one line:

```php
spl_autoload_register(['Kohana', 'auto_load']);
```

## Your own classes

Put them in `application/classes/` and follow the rule. Nothing to register.

```php
// application/classes/Price.php
class Price {

	public static function with_tax(float $amount, float $rate = 0.23): float
	{
		return round($amount * (1 + $rate), 2);
	}
}
```

```php
// Anywhere
echo Price::with_tax(100);  // 123.0
```

## Classes of other libraries

Libraries installed with Composer are loaded by Composer's own loader; the bootstrap includes `vendor/autoload.php` when it exists.

```php
// composer require nesbot/carbon
$now = Carbon\Carbon::now();
```

A library that is not on Composer can register its own loader in the bootstrap, after Kohana's:

```php
spl_autoload_register(function (string $class): void {
	if (str_starts_with($class, 'Legacy_'))
	{
		require APPPATH.'vendor/legacy/'.$class.'.php';
	}
});
```

## Checking that everything loads

PHP only checks that a class fits its parent when the class is loaded. After changing method signatures, load every class once:

```bash
php docker/load-all-classes.php
```

It names each class that fails and why.
