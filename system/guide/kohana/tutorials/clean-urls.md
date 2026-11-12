# Clean URLs

Out of the box, addresses contain the front file: `/index.php/article/view/1`. Two steps remove it.

## 1. Tell the web server

Every request that is not an existing file must go to `public/index.php`.

### Apache

Copy `public/example_2.4.htaccess` to `public/.htaccess` (`example.htaccess` for Apache 2.2). The part that matters:

```text
RewriteEngine On

# The path of the site: / or /shop/
RewriteBase /

# Real files and directories are served as they are
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Everything else goes to index.php
RewriteRule .* index.php [PT,QSA,L]
```

`mod_rewrite` must be enabled, and the site must allow `.htaccess` files (`AllowOverride All`).

### nginx

```text
server {
	root /var/www/example/public;
	index index.php;

	location / {
		try_files $uri $uri/ /index.php$is_args$args;
	}

	location ~ \.php$ {
		include fastcgi_params;
		fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
		fastcgi_param KOHANA_ENV production;
		fastcgi_pass unix:/run/php/php8.4-fpm.sock;
	}

	# Nothing that starts with a dot
	location ~ /\. {
		deny all;
	}
}
```

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
