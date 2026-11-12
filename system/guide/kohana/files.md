# Cascading Filesystem

The framework looks for every file (a class, a view, a config file) in several directories, in a fixed order, and uses the first one it finds:

1. `application/`
2. each enabled module, in the order they are listed in `Kohana::modules()`
3. `system/`

![The directories, searched from top to bottom](cascading_filesystem.svg)

So a file in `application/` replaces the file of the same name in a module or in the system. This is how you change the framework without editing it.

## What it means in practice

| You create | It replaces |
|---|---|
| `application/views/kohana/error_production.php` | The production error page of the system |
| `application/config/database.php` | Settings of the database module (merged, see below) |
| `application/classes/Cookie.php` | The empty `Cookie` class, so you can [extend](extension) `Kohana_Cookie` |
| `application/messages/validation.php` | The validation error texts |

## The directories

Every level (application, module, system) can have the same directories.

| Directory | Holds | Found with |
|---|---|---|
| `classes/` | Classes | automatically, by [class name](autoloading) |
| `views/` | [Views](mvc/views) | `View::factory('path/name')` |
| `config/` | [Settings](config) | `Kohana::$config->load('name')` |
| `i18n/` | [Translations](files/i18n) | `__('text')` |
| `messages/` | [Messages](files/messages) | `Kohana::message('file', 'key')` |
| `guide/` | Pages of this guide | the Userguide module |
| `media/` | Files a module serves itself | the module |

## Finding files yourself

```php
// The first file found: application, then modules, then system
$path = Kohana::find_file('views', 'article/view');
// "/var/www/application/views/article/view.php", or false

// Another extension
$css = Kohana::find_file('media', 'css/print', 'css');

// Every file of that name, from system up to application
$all = Kohana::find_file('config', 'database', null, true);

// Every file under a directory, from all levels merged
$views = Kohana::list_files('views');
```

`Kohana::find_file()` refuses names that contain `..`, so a name built from a visitor's input cannot leave its directory.

## Two kinds of merging

- **Classes and views**: the first file found wins; the others are ignored.
- **Config, i18n and message files**: all files of that name are **merged**. The application's values replace the module's, key by key. You only write the keys you change:

```php
// application/config/session.php: only the lifetime differs from system/config/session.php
return [
	'native' => [
		'lifetime' => 43200,
	],
];
```

## In production

Looking in many directories costs time. With `'caching' => true` in `Kohana::init()`, the framework remembers where each file was found. Clear `application/cache` after you deploy new files.
