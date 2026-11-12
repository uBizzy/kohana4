# Deploying

The list to go through before a site goes live.

## The server

- The document root is `public/`. Nothing else can be reached from the web.
- `public/install.php` is deleted.
- `application/cache` and `application/logs` are writable by the web server, and nothing else is.
- HTTPS is on, and plain HTTP redirects to it.
- `display_errors` is off in `php.ini`.

## The environment

```text
# Apache
SetEnv KOHANA_ENV production

# nginx
fastcgi_param KOHANA_ENV production;
```

In production:

- visitors see a general error page with a reference code, never the error itself;
- the user guide and the API browser are switched off;
- deprecation notices are only written to the log.

## Check it

With the minion module enabled, one command checks most of this list on the server: PHP and its extensions, the writable directories, the cookie salt, the database and cache connections, pending migrations, and in production the settings that belong to development. See [Checking the Installation](../../minion/check).

```bash
./minion check
```

## The bootstrap

```php
Kohana::init([
	'base_url'   => '/',
	'index_file' => false,
	'caching'    => Kohana::$environment === Kohana::PRODUCTION,   // remember where files are
	'profile'    => Kohana::$environment !== Kohana::PRODUCTION,   // no profiling
]);

// A long random value, only for this site
Cookie::$salt = 'at-least-32-random-characters';

// Once the whole site is on HTTPS
Cookie::$secure = true;
HTTP::$security_headers['strict-transport-security'] = 'max-age=31536000';

// Only the modules the site uses: no userguide, codebench or unittest
Kohana::modules([
	'database' => MODPATH.'database',
	'orm'      => MODPATH.'orm',
	'auth'     => MODPATH.'auth',
]);
```

## Settings

- `config/url.php` lists the host names of the site in `trusted_hosts`.
- `config/database.php` has the production connection, with a database user that can only do what the site needs.
- Encryption keys (`config/encrypt.php`) and the auth `hash_key` are set, random, and different from other installations.
- Behind a load balancer or CDN: `Request::$trusted_proxies` lists its addresses.
- Files with passwords are not in version control.

## After each deploy

```bash
# The framework remembers where files are: forget it when files change
rm -rf application/cache/*
```

## Check

```bash
# Nothing about the software in the headers
curl -sI https://example.org/ | grep -i -E "x-powered-by|server"

# An address that does not exist: a plain "Not Found", no file names
curl -s https://example.org/no-such-page

# The guide is off
curl -sI https://example.org/guide | head -1
```

Then cause an error on purpose and look in `application/logs/`: the line starts with the reference code the visitor saw.

## Keep watching

- Read the logs. A line with level `EMERGENCY` or `ERROR` is a visitor who saw an error page.
- Rotate or archive `application/logs/`; one file is written per day.
- Update PHP and the framework when security fixes come out.
