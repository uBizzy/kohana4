# Installation

## Requirements

- PHP 8.4 or later
- The `mbstring` and `intl` extensions
- A web server that can send every request to one file (Apache, nginx, Caddy, or PHP's own server while developing)

## Install

1. Put the files on the server. Only the `public/` directory must be reachable from the web:

```text
application/    your code: controllers, models, views, config
modules/        optional parts of the framework
system/         the framework
public/         index.php and your CSS, JavaScript and images
```

2. Point the web server's document root at `public/`.
3. Make `application/cache` and `application/logs` writable by the web server.
4. Open the site in a browser. `public/install.php` checks the requirements:

![The page that checks the requirements](install.png)

5. When everything is green, **delete `public/install.php`**. The welcome page appears:

![The welcome page](welcome.png)

## Try it without a web server

```bash
cd public
php -S localhost:8000
```

Then open `http://localhost:8000/`.

## First settings

Open `application/bootstrap.php` and set these four things.

```php
// 1. The time zone and the language of your site
date_default_timezone_set('Europe/Lisbon');
I18n::lang('en-us');

// 2. Where the site is. '/' for a domain of its own, '/shop/' for a sub-directory
Kohana::init([
	'base_url'   => '/',
	'index_file' => false,   // see "Clean URLs"
]);

// 3. A long random text that signs the cookies
Cookie::$salt = 'put-at-least-32-random-characters-here';

// 4. The modules you use
Kohana::modules([
	'database' => MODPATH.'database',
	'orm'      => MODPATH.'orm',
]);
```

[!!] Generate the cookie salt, do not invent it: `php -r "echo bin2hex(random_bytes(32));"`

Also list the host names of your site in `application/config/url.php`. A request that names another host is refused as soon as a full address is built from it:

```php
// application/config/url.php
return [
	'trusted_hosts' => [
		'example\.org',
		'.*\.example\.org',
	],
];
```

The port is not part of the name: `'localhost'` also trusts `localhost:8000`.

## Development and production

The framework behaves differently in production: errors are hidden from visitors and this guide is switched off. Set the environment with a server variable:

```text
# Apache
SetEnv KOHANA_ENV production

# nginx (with PHP-FPM)
fastcgi_param KOHANA_ENV production;
```

The values are `development` (the default), `testing`, `staging` and `production`. In code:

```php
if (Kohana::$environment === Kohana::PRODUCTION)
{
	// Only on the live site
}
```

## Next

- [Clean URLs](tutorials/clean-urls): addresses without `index.php`
- [Hello World](tutorials/hello-world): your first controller and view
- [Deploying](security/deploying): the list to go through before going live
