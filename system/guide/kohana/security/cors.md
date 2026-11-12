# Cross-Origin Requests

A browser does not let JavaScript on `https://app.example.com` read answers from `https://api.example.org`, unless the second site allows the first. This is Cross-Origin Resource Sharing (CORS). You need it when a front end on another domain calls your API.

Nothing is allowed until you list the sites.

## Allowing a site

```php
// application/config/cors.php
return [
	'origins' => ['https://app.example.com'],
];
```

That is all for a `Controller_REST`: it adds the headers and answers the browser's preflight (`OPTIONS`) requests by itself.

## All settings

| Setting | Default | Meaning |
|---|---|---|
| `origins` | `[]` | Sites that may call: `scheme://host`, with `:port` when it is not the usual one. `['*']` is every site |
| `methods` | `GET, POST, PUT, PATCH, DELETE, OPTIONS` | HTTP methods they may use |
| `headers` | `Content-Type, Authorization, X-Requested-With` | Request headers they may send |
| `expose` | `[]` | Response headers their scripts may read |
| `credentials` | `false` | May the browser send cookies with the call? |
| `max_age` | `600` | Seconds a browser remembers the answer to a preflight |

```php
// application/config/cors.php: a front end that logs in with the session cookie
return [
	'origins'     => ['https://app.example.com', 'http://localhost:5173'],
	'headers'     => ['Content-Type', 'X-CSRF-Token'],
	'credentials' => true,
];
```

[!!] With `credentials` on, the listed sites can act as the logged-in visitor. List only sites you control, and never combine it with `['*']`.

For cookies to be sent across sites they must also be `SameSite=None`: see [Cookies](../cookies).

## In a controller of your own

`CORS::apply()` adds the headers. It returns `true` when the request was a preflight, which is then fully answered.

```php
class Controller_Api extends Controller {

	public function before(): void
	{
		parent::before();

		if (CORS::apply($this->request, $this->response))
		{
			// A preflight: the headers are the whole answer
			$this->request->action('preflight');
		}
	}

	public function action_preflight(): void
	{
	}
}
```

The route must let `OPTIONS` requests reach the controller.

## Other settings for one controller

```php
// A public, read-only API: every site, GET only
CORS::apply($this->request, $this->response, [
	'origins' => ['*'],
	'methods' => ['GET'],
]);
```

## What CORS is not

CORS is enforced by browsers, for scripts. It does not stop other programs from calling your API: for that you need keys or log in, and [rate limiting](throttle).
