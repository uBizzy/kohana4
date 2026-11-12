# Modules

A module is a directory with the same layout as `application/`: `classes/`, `views/`, `config/` and so on. Enabling it adds that directory to the [cascading filesystem](files).

## Enabling modules

```php
// application/bootstrap.php
Kohana::modules([
	'auth'       => MODPATH.'auth',       // Log in and roles
	'cache'      => MODPATH.'cache',      // Caching
	'database'   => MODPATH.'database',   // Database access
	'encrypt'    => MODPATH.'encrypt',    // Encryption
	'image'      => MODPATH.'image',      // Image manipulation
	'minion'     => MODPATH.'minion',     // Command line tasks
	'migration'  => MODPATH.'migration',  // Database changes in files
	'orm'        => MODPATH.'orm',        // Models for database tables
	'pagination' => MODPATH.'pagination', // Page links
	'unittest'   => MODPATH.'unittest',   // Unit testing
	'userguide'  => MODPATH.'userguide',  // This guide
]);
```

- The **order matters**: when two modules have a file of the same name, the one listed first wins.
- A module that needs another must come before it is used, not before it in the list: `orm` needs `database`, and both just have to be enabled.

### Extra modules

Modules that are not part of the core are in a repository of their own, [kohana4-modules](https://github.com/uBizzy/kohana4-modules). Copy the ones you want into `modules/`; they are enabled the same way, and their classes start with `UB_`:

```php
Kohana::modules([
	'ub_debug' => MODPATH.'ub_debug',   // Debug bar, development only
]);
```

```php
// Which modules are enabled?
$modules = Kohana::modules();

if (isset($modules['cache']))
{
	// The cache module is on
}
```

## Writing a module

```text
modules/shop/
	init.php                  runs when the module is enabled (optional)
	classes/
		Shop.php              class Shop extends Kohana_Shop {}
		Kohana/Shop.php       class Kohana_Shop { ... }
		Controller/Shop.php
		Model/Product.php
	config/shop.php
	views/shop/cart.php
	messages/shop.php
	guide/shop/index.md       its guide (see the Userguide module)
	tests/ShopTest.php
```

Write the code in `Kohana_Shop` and add an empty `Shop` class, so that applications can [extend](extension) it.

### init.php

`init.php` runs once, when `Kohana::modules()` enables the module. Use it for routes and nothing heavy: it runs on every request.

```php
// modules/shop/init.php
Route::set('shop', 'shop(/<action>(/<id>))')
	->defaults([
		'controller' => 'Shop',
		'action'     => 'index',
	]);
```

### Settings of a module

Give the module a config file with safe defaults. The application overrides single keys in its own `application/config/shop.php`.

```php
// modules/shop/config/shop.php
return [
	'currency' => 'EUR',
	'per_page' => 20,
];
```

```php
$per_page = Kohana::$config->load('shop.per_page');
```

## Modules outside MODPATH

A module can be anywhere; the path is all that matters.

```php
Kohana::modules([
	'shop' => DOCROOT.'vendor/acme/kohana-shop',
]);
```
