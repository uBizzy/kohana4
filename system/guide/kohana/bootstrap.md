# Bootstrap

`application/bootstrap.php` runs at the start of every request. It is plain PHP that you edit; nothing in it is hidden. It does five things, in this order.

## 1. Environment

```php
date_default_timezone_set('Europe/Lisbon');
setlocale(LC_ALL, 'en_US.utf-8');

// How classes are found: see "Loading Classes"
spl_autoload_register(['Kohana', 'auto_load']);

I18n::lang('en-us');

// development, testing, staging or production
if (isset($_SERVER['KOHANA_ENV']))
{
	Kohana::$environment = constant('Kohana::'.strtoupper($_SERVER['KOHANA_ENV']));
}
```

## 2. Kohana::init()

```php
Kohana::init([
	'base_url'   => '/',
	'index_file' => false,
	'caching'    => Kohana::$environment === Kohana::PRODUCTION,
	'profile'    => Kohana::$environment !== Kohana::PRODUCTION,
]);
```

| Setting | Meaning | Default |
|---|---|---|
| `base_url` | Path of the site, with a `/` at both ends. `'/'` or `'/shop/'` | `'/'` |
| `index_file` | Name of the front file in addresses; `false` with [clean URLs](tutorials/clean-urls) | `'index.php'` |
| `charset` | Character set of input and output | `'utf-8'` |
| `cache_dir` | Directory of the internal cache | `APPPATH.'cache'` |
| `cache_life` | Seconds an internal cache entry lives | `60` |
| `errors` | Turn PHP errors into exceptions and show [error pages](errors) | `true` |
| `profile` | Measure the framework's own steps (see [Profiling](profiling)) | `true` |
| `caching` | Remember where files were found. Switch on in production | `false` |
| `expose` | Send an `X-Powered-By` header | `false` |

## 3. Log and configuration

```php
// Where log messages go (see "Logging")
Kohana::$log->attach(new Log_File(APPPATH.'logs'));

// Where settings are read from (see "Configuration")
Kohana::$config->attach(new Config_File);
```

## 4. Modules

```php
Kohana::modules([
	'auth'     => MODPATH.'auth',
	'database' => MODPATH.'database',
	'orm'      => MODPATH.'orm',
]);
```

Each module's `init.php` runs here. See [Modules](modules).

## 5. Your settings and routes

```php
Cookie::$salt = 'a-long-random-text';

// Extra headers on every response (see "Security Headers")
HTTP::$security_headers['strict-transport-security'] = 'max-age=31536000';

Route::set('default', '(<controller>(/<action>(/<id>)))')
	->defaults([
		'controller' => 'Welcome',
		'action'     => 'index',
	]);
```

[!!] Routes are tried in the order they are set. Keep the catch-all `default` route last.

## Different settings per environment

```php
if (Kohana::$environment === Kohana::DEVELOPMENT)
{
	// The guide and the benchmarks are only useful while developing
	Kohana::modules(Kohana::modules() + [
		'userguide' => MODPATH.'userguide',
		'codebench' => MODPATH.'codebench',
	]);

	// "open" links on the error page
	Debug::$editor = 'vscode://file/:file::line';
}
```
