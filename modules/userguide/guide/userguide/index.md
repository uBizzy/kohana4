# Userguide

The Userguide module shows the guides of the framework, of every module and of your application, and an API browser built from the comments in the code. You are reading it now.

```php
// application/bootstrap.php: while developing
Kohana::modules([
	'userguide' => MODPATH.'userguide',
]);
```

| Address | Shows |
|---|---|
| `/guide` | The list of guides |
| `/guide/<module>` | The guide of a module |
| `/guide-api` | The API browser: every class |
| `/guide-api/<Class>` | One class: constants, properties, methods |

## What is in it

- **Guides** written in [Markdown](markdown), one directory per module.
- **The API browser**: types come from the code, descriptions from the comments. See [The API Browser](api).
- **Search** over every page and every class: the box at the top, or press `/`.
- **Copy** on every code block.

## Off in production

The guide shows the whole code of the application, so by default it only works outside production.

```php
// application/config/userguide.php
return [
	// The default
	'enabled' => Kohana::$environment !== Kohana::PRODUCTION,
];
```

To publish the guide of a project on purpose, set `'enabled' => true`, and consider `'api_browser' => false`.

## Settings

```php
// application/config/userguide.php
return [
	'enabled'      => true,
	'api_browser'  => true,

	// Only these packages in the API browser (the @package of each class)
	'api_packages' => 'Kohana,Kohana/Database,Kohana/ORM',

	// Hide the guide of a module
	'modules' => [
		'codebench' => ['enabled' => false],
	],
];
```

## A guide for your application

Your application can have a guide like any module: see [Writing a Guide](writing).
