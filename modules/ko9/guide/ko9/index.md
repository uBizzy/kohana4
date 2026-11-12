# KO9 compatibility

This module lets an application written with the `KO9` class names run on this framework without renaming its classes.

```php
// application/bootstrap.php
Kohana::modules([
	'ko9' => MODPATH.'ko9',
	// ... the other modules
]);
```

## What it provides

| Old name | Is now |
|---|---|
| `KO9` | `Kohana` |
| `KO9_Arr`, `KO9_Request`, ... | `Kohana_Arr`, `Kohana_Request`, ... |
| `KO9_KO9_Exception` | `Kohana_Kohana_Exception` |
| `KO9_START_TIME`, `KO9_START_MEMORY` | `KOHANA_START_TIME`, `KOHANA_START_MEMORY` |

The old names are **aliases**, not copies: `KO9_Arr` and `Kohana_Arr` are the same class. This covers every class and interface of the framework and of all enabled modules.

```php
// Both lines work, and mean the same
class Cookie extends KO9_Cookie {}
class Cookie extends Kohana_Cookie {}

if ($e instanceof KO9_Exception)
{
	// ...
}
```

## What you still have to change

The module is loaded by `Kohana::modules()`. The old names do not exist before that call, so the code that runs earlier must use the new names:

| File | Change |
|---|---|
| `public/index.php` | Use the one of this version |
| `application/bootstrap.php`, before `Kohana::modules()` | `KO9::init()` becomes `Kohana::init()`, `KO9::\$log` becomes `Kohana::\$log`, and so on |

And one thing no alias can do: **types**. Classes of yours that extend the framework must declare the same parameter, return and property types as their parents. See [Upgrading to 4.0](../kohana/upgrading).

## Cost

One small autoloader. A class used under its new name costs nothing; a class used under its old name costs one extra lookup, the first time only.

## Moving off the old names

The module is meant as a bridge. To finish the move:

1. Replace `KO9_` with `Kohana_`, and `KO9::` with `Kohana::`, across the application.
2. Run `php docker/load-all-classes.php` and the tests.
3. Remove the module from the bootstrap.
