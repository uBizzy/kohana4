# Deploying

The list to go through before a site goes live.

## The server

- The document root is `public/`. Nothing else can be reached from the web.
- `public/install.php` is deleted.
- `application/cache` and `application/logs` are writable by the web server, and nothing else is.
- HTTPS is on, and plain HTTP redirects to it.
- `display_errors` and `expose_php` are off in `php.ini`.
- Uploads are saved outside `public/`, and sent with `send_file()`.

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

With the minion module enabled, one command checks most of this list on the server: PHP and its extensions, the writable directories, the cookie salt, the database and cache connections, pending migrations, and in production the settings that belong to development or make the site slower or less safe (OPcache, `expose_php`, no Content-Security-Policy, uploads inside `public/`). See [Checking the Installation](../../minion/check).

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

// The secret that signs cookies: "cookie_salt" in application/config/app.php
Cookie::$salt = Kohana::$config->load('app')->get('cookie_salt');

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

The bootstrap that comes with the framework already switches `caching` on and `profile` off in production, and sends a Content-Security-Policy.

## Speed

- **OPcache is on.** Without it PHP compiles every file at every request; nothing else makes as much difference. When the files only change with a deploy, PHP need not look at them at all:

```text
; php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0   ; then restart PHP-FPM, or reload Apache, after each deploy
```

- **The framework remembers where files are** (`caching`, above).
- **Apache: put the rules of `.htaccess` in the configuration of the site** and set `AllowOverride None`. Apache then stops looking for `.htaccess` files in every directory at every request:

```text
<Directory /var/www/example/public>
	AllowOverride None
	# ... the content of public/example.htaccess ...
</Directory>
```

- **HTTP/2, and Brotli beside gzip**, are settings of the web server: both make pages with many files load faster.
- **Static files** are kept by the browser and checked for changes, as `public/example.htaccess` and `public/example.nginx.conf` set up. Give them a name that changes with their content and they can be kept for a year.

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
