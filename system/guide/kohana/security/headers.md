# Security Headers

Some headers tell the browser to be stricter with your pages. Three are sent with every response; two more are worth setting once you know your site.

## Sent by default

| Header | Value | Effect |
|---|---|---|
| `X-Content-Type-Options` | `nosniff` | The browser does not guess the type of a file |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Other sites see your domain, not the page a visitor came from |
| `X-Frame-Options` | `SAMEORIGIN` | Other sites cannot show your pages inside a frame |

They are in `HTTP::$security_headers`. Change them in the bootstrap:

```php
// No frames at all, not even your own
HTTP::$security_headers['x-frame-options'] = 'DENY';

// Your pages are meant to be embedded elsewhere: do not send the header
unset(HTTP::$security_headers['x-frame-options']);
```

A header that an action sets itself is left as it is.

## HTTPS only

Once every page of the site works over HTTPS, tell browsers to never use plain HTTP again:

```php
// One year
HTTP::$security_headers['strict-transport-security'] = 'max-age=31536000';
```

[!!] Start with a short time (`max-age=300`) and raise it when you are sure. Browsers remember this header; a mistake locks visitors out of an HTTP-only site until the time is over.

Also set `Cookie::$secure = true`.

## Content-Security-Policy

A Content-Security-Policy (CSP) lists where scripts, styles and images may come from. A script injected through an [XSS](xss) hole is then not run.

```php
HTTP::$security_headers['content-security-policy'] =
	"default-src 'self'; script-src 'self' 'nonce-{nonce}'; style-src 'self' 'nonce-{nonce}'; img-src 'self' data:";
```

`{nonce}` is replaced by a random value that is new for every request. Scripts and styles written inside a page must carry it:

```php
<script nonce="<?php echo HTML::chars(Security::nonce()) ?>">
	document.documentElement.classList.add('js');
</script>

<?php echo HTML::script('js/site.js', ['nonce' => Security::nonce()]) ?>
```

What the policy above means:

| Part | Allows |
|---|---|
| `default-src 'self'` | Everything only from your own site, unless said otherwise |
| `script-src 'self' 'nonce-...'` | Script files of your site, and inline scripts with the nonce |
| `style-src 'self' 'nonce-...'` | The same for styles |
| `img-src 'self' data:` | Images of your site and embedded ones |

With such a policy, `onclick="..."` attributes and `style="..."` attributes stop working. The framework's own pages (error page, profiler, this guide) do not use them.

### Trying a policy first

Send it as `Content-Security-Policy-Report-Only`: the browser reports what would be blocked, in its console, and blocks nothing.

```php
HTTP::$security_headers['content-security-policy-report-only'] = "default-src 'self'; script-src 'self' 'nonce-{nonce}'";
```

## Per page

```php
// In an action: this page may be framed by a partner
$this->response->headers('x-frame-options', 'ALLOW-FROM https://partner.example');

// This page must never be stored
$this->response->headers('cache-control', 'no-store');
```
