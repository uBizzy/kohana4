# Security

The framework is safe by default where it can be. This page lists what it does for you and what stays your job.

## Done for you

| Risk | What the framework does |
|---|---|
| Changed cookies | Cookies are signed (HMAC-SHA-256); a changed one is ignored. See [Cookies](cookies) |
| Cookies stolen by scripts or sent across sites | `HttpOnly`, `SameSite=Lax`, and `Secure` over HTTPS, by default |
| SQL injection | The [query builder](../database/query/builder) sends values apart from the SQL (prepared statements) |
| Session fixation | Strict session ids; a new id at log in |
| Weak password storage | The Auth module uses `password_hash()` and upgrades old hashes at log in |
| Dangerous uploads | `Upload::save()` refuses files a server could run. See [Files and Uploads](helpers/files) |
| Path tricks (`../`) | `Kohana::find_file()` refuses such names |
| Clickjacking, type sniffing | Three [security headers](security/headers) on every response |
| Leaking errors | In production the visitor sees a reference code, never the error. See [Error Handling](errors) |
| Forged client addresses | Forwarding headers are only read from [trusted proxies](requests) |
| Requests to odd schemes | Outgoing requests are limited to `http` and `https`, with timeouts |
| Invalid text encodings | All input is cleaned to valid UTF-8 |

## Your job

| Risk | What to do | Page |
|---|---|---|
| Cross-site scripting | Print every outside value with `HTML::chars()` | [XSS](security/xss) |
| Bad input | Check every value with `Validation` | [Validation](security/validation) |
| Forged forms | Put `Form::csrf()` in forms, check with `Security::check()` | [CSRF](security/csrf) |
| Too many attempts | Limit log in, forms and APIs with `Throttle` | [Rate Limiting](security/throttle) |
| Scripts from elsewhere | Set a Content-Security-Policy | [Security Headers](security/headers) |
| Other sites calling your API | Allow only the sites you name | [Cross-Origin Requests](security/cors) |
| A careless live server | Go through the list | [Deploying](security/deploying) |

## Three rules that cover most of it

```php
// 1. Check what comes in
$validation = Validation::factory($this->request->post())
	->rule('email', 'not_empty')
	->rule('email', 'email');

// 2. Never put values into SQL yourself
$user = DB::select()->from('users')->where('email', '=', $email)->execute()->current();

// 3. Escape what goes out
echo HTML::chars($user['name']);
```

## Secrets

- Give `Cookie::$salt` and the encryption keys long random values, different on each installation.
- Keep them, and database passwords, in config files that are not in version control.
- The error page and `Debug::vars()` hide values whose name looks secret (`password`, `token`, `salt`, ...). The list is `Debug::$sensitive`; add your own names to it.
