# Requests

`$this->request` in a controller is the request being answered.

## What was asked

```php
$this->request->method();        // "GET", "POST", ...
$this->request->uri();           // "article/view/42"
$this->request->url(true);       // "https://example.org/article/view/42"
$this->request->secure();        // true over HTTPS
$this->request->is_ajax();       // true when sent by JavaScript (X-Requested-With)
$this->request->referrer();      // the page the visitor came from, or null

$this->request->controller();    // "Article"
$this->request->action();        // "view"
$this->request->param('id');     // "42", from the route
```

## Data sent with it

```php
// ?page=2&sort=title
$page = $this->request->query('page');
$all  = $this->request->query();

// Form fields
$title = $this->request->post('title');
$all   = $this->request->post();

// A header
$agent = $this->request->headers('user-agent');

// The raw body
$body = $this->request->body();
```

[!!] A value from a request can be a string, an array or `null`. Check it before use: see [Validation](security/validation).

### JSON bodies

```php
// The body is {"name": "Ana", "tags": ["a", "b"]}
$data = $this->request->json();           // the whole body, as an array
$name = $this->request->json('name');     // one key
$tags = $this->request->json('tags', []); // with a default
```

A body that is not valid JSON answers `400 Bad Request` by itself.

## Who is asking

```php
Request::$client_ip;    // "203.0.113.7"
Request::$user_agent;   // "Mozilla/5.0 ..."

// Parts of the user agent
$browser = Request::user_agent('browser');
$mobile  = Request::user_agent('mobile');
```

### Behind a proxy or load balancer

Headers such as `X-Forwarded-For` can be written by anyone, so they are only believed when the request comes from a proxy you list. Without this, `Request::$client_ip` is the address of your proxy.

```php
// application/bootstrap.php, before the request is made
Request::$trusted_proxies = ['10.0.0.0/8', '192.168.1.10'];
```

## A request inside a request

```php
// Run another controller and use its output
$menu = Request::factory('menu/main')->execute()->body();

// With data
$result = Request::factory('search/results')
	->method(HTTP_Request::POST)
	->post(['q' => 'kohana'])
	->execute();

echo $result->status();  // 200
```

## Requests to other sites

Give a full address, and the request goes out over the network.

```php
$response = Request::factory('https://api.example.org/users')
	->query('page', 2)
	->headers('accept', 'application/json')
	->execute();

if ($response->status() === 200)
{
	$users = json_decode($response->body(), true);
}
```

```php
// Sending JSON
$response = Request::factory('https://api.example.org/users')
	->method(HTTP_Request::POST)
	->headers('content-type', 'application/json')
	->body(json_encode(['name' => 'Ana']))
	->execute();
```

Safe defaults apply:

| Setting | Default | Change with |
|---|---|---|
| Allowed schemes | `http`, `https` | `Request_Client_External::$allowed_schemes` |
| Time to connect | 10 seconds | `Request_Client_External::$connect_timeout` |
| Total time | 30 seconds | `Request_Client_External::$timeout` |
| Follow redirects | no | the `follow` client setting |

```php
// For one request: follow redirects, and allow ftp addresses too
$response = Request::factory('https://example.org/old', [
		'follow'          => true,
		'allowed_schemes' => ['http', 'https', 'ftp'],
	])
	->execute();
```

[!!] When the address comes from a visitor, your server can be made to call machines of your internal network. `$request->client()->only_public_addresses()` refuses those: see [Requests to Other Servers](remote#when-the-address-comes-from-a-visitor).

Logins, time limits for one request, retries, sending and downloading files, and several requests at once are on that page too.
