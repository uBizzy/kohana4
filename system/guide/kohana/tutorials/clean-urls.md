# Clean URLs

Out of the box, addresses contain the front file: `/index.php/article/view/1`. Two steps remove it.

## 1. Tell the web server

Every request that is not an existing file must go to `public/index.php`.

### Apache

Copy `public/example.htaccess` to `public/.htaccess`. It is written for Apache 2.4. The part that matters:

```text
RewriteEngine On

# The path of the site: / or /shop/
RewriteBase /

# Real files and directories are served as they are
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Everything else goes to index.php
RewriteRule ^ index.php [L]
```

`mod_rewrite` must be enabled, and the site must allow `.htaccess` files (`AllowOverride All`).

The file does a few more things, each explained in a comment:

- hidden files and directories (`.git`, `.env`) are not served;
- no PHP file but `index.php` is run, so a script that reaches the directory through an upload does nothing;
- directories are not listed;
- the `Authorization` header is passed on to PHP;
- files that Apache serves itself are compressed and sent with `X-Content-Type-Options`; the browser keeps them and asks each time whether they changed, so a new CSS file is seen at once.

### nginx

`public/example.nginx.conf` is a complete site to start from. The part that matters:

```text
server {
	root /var/www/example/public;
	index index.php;

	# Real files are served as they are; everything else goes to index.php
	location / {
		try_files $uri $uri/ /index.php$is_args$args;
	}

	# PHP runs through index.php only
	location = /index.php {
		include fastcgi_params;
		fastcgi_param SCRIPT_FILENAME $document_root/index.php;
		fastcgi_param KOHANA_ENV production;
		fastcgi_pass unix:/run/php/php8.4-fpm.sock;
	}
}
```

The file does the same extra things as the one for Apache: hidden files and directories are not served, no other PHP file is run, and static files are compressed and checked for changes.

### Caddy

```text
example.org {
	root * /var/www/example/public
	php_fastcgi unix//run/php/php8.4-fpm.sock
	file_server
}
```

### PHP's own server (development)

```bash
cd public
php -S localhost:8000 index.php
```

## 2. Tell the framework

```php
// application/bootstrap.php
Kohana::init([
	'base_url'   => '/',
	'index_file' => false,
]);
```

Now `URL::site('article/view/1')` gives `/article/view/1`, and so do `HTML::anchor()`, `Route::url()` and `Form::open()`.

## When it does not work

| What you see | Look at |
|---|---|
| "404 Not Found" from the web server, not from the framework | The rewrite rules are not used: `mod_rewrite` off, or `.htaccess` not allowed |
| The home page works, other pages do not | The same |
| Pages work, CSS and images do not | `base_url` is wrong, or the files are not under `public/` |
| Links still contain `index.php` | `index_file` is not `false`, or links are written by hand instead of with the helpers |
| In a sub-directory: links go to the domain root | `base_url` must be `'/shop/'`, and `RewriteBase /shop/` |
