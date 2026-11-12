# Cookies

```php
Cookie::set('theme', 'dark');

$theme = Cookie::get('theme', 'light');   // 'light' when there is none

Cookie::delete('theme');
```

## Set the salt first

Every cookie is signed, so that a visitor cannot change its value. The signature needs a secret text of your own. Without it, setting a cookie throws an exception.

```php
// application/bootstrap.php
Cookie::$salt = 'at-least-32-random-characters';
```

```bash
# Make one
php -r "echo bin2hex(random_bytes(32));"
```

A cookie whose value was changed, or that an old version signed, reads as if it was not there, and is deleted.

## Using cookies

```php
// For 30 days
Cookie::set('theme', 'dark', 30 * Date::DAY);

// Until the browser closes
Cookie::set('seen_intro', '1', 0);

// Is it there?
if (Cookie::has('theme'))
{
	// ...
}

// Values are text: encode anything else
Cookie::set('filters', json_encode(['color' => 'red', 'size' => 'M']));
$filters = json_decode(Cookie::get('filters', '{}'), true);
```

[!!] A signed cookie cannot be changed by the visitor, but it **can be read**. Never put a password or anything secret in one.

## Settings

Set these in the bootstrap. The defaults are the safe choice for most sites.

| Setting | Default | Meaning |
|---|---|---|
| `Cookie::$expiration` | `0` | Seconds a cookie lives; `0` ends with the browser |
| `Cookie::$path` | `'/'` | Part of the site that gets the cookie |
| `Cookie::$domain` | `''` | `'.example.org'` to share with sub-domains |
| `Cookie::$secure` | `null` | `null`: only over HTTPS when the request is HTTPS. `true`: always |
| `Cookie::$httponly` | `true` | JavaScript cannot read the cookie |
| `Cookie::$samesite` | `'Lax'` | `'Strict'`, `'Lax'` or `'None'` |

```php
// A site that is only on HTTPS
Cookie::$secure = true;

// Cookies kept for two weeks unless said otherwise
Cookie::$expiration = 2 * Date::WEEK;
```

### SameSite

| Value | The cookie is sent |
|---|---|
| `Strict` | Only for requests that start on your own site |
| `Lax` | Also when a visitor follows a link to your site. Not for forms posted from other sites |
| `None` | Always, also inside other sites (needs HTTPS; the secure flag is forced) |

`Lax` stops most [CSRF](security/csrf) attacks and is right for most sites. Use `None` only for something meant to be embedded in other sites.

### A cookie JavaScript must read

```php
// Only for this one, and only for a value that is not sensitive
Cookie::$httponly = false;
Cookie::set('sidebar', 'closed');
Cookie::$httponly = true;
```

## Cookies on the response

In a controller you can also add the cookie to the response; it is sent with the same settings and signature.

```php
$this->response->cookie('theme', 'dark');
```
