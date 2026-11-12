# Requests to Other Servers

A request with a full address goes out over the network. This page is about what such a request can do: logins, time limits, files, several requests at once, and how to stay safe when the address comes from a visitor.

```php
$request = Request::factory('https://api.example.com/reports/7');

$request->client()
	->bearer($token)
	->timeout(5)
	->retry(2);

$response = $request->execute();

if ($response->status() === 200)
{
	$report = json_decode($response->body(), true);
}
```

The settings are methods of the request's client, `$request->client()`, and can be chained. [Requests](requests#requests-to-other-sites) has the basics: sending values, headers and JSON.

[!!] Everything on this page needs the **curl** extension, except `timeout()`, `auth()` and `bearer()`. Without curl the framework uses PHP's streams for simple requests, and stops with a clear message when one of the other methods is used.

## Logging in

```php
// A token, as most APIs ask
$request->client()->bearer($token);

// A name and a password ("Basic" authentication): over https only
$request->client()->auth('ana', 'correct horse');
```

Both set the `Authorization` header. When an answer sends the client to another server, the login stays behind: it only goes along to the same host.

## Time limits

```php
$request->client()
	->connect_timeout(3)   // seconds to reach the server
	->timeout(10);         // seconds for the whole request
```

Without these, the limits of the application apply: `Request_Client_External::$connect_timeout` (10) and `Request_Client_External::$timeout` (30). A request that takes too long throws a `Request_Exception`.

## Trying again

```php
// Up to three more attempts, half a second apart
$request->client()->retry(3, 0.5);
```

A request is sent again when the server could not be reached or answered with 502, 503 or 504. Only requests that can be repeated without harm are: `GET`, `HEAD`, `PUT`, `DELETE` and `OPTIONS`. A `POST` is never sent twice, because it could do its work twice.

## Sending files

```php
$request = Request::factory('https://example.com/photos')
	->method(HTTP_Request::POST)
	->post('title', 'Lisboa');

$request->client()
	->upload('photo', '/path/to/photo.jpg')
	->upload('thumb', '/path/to/small.jpg', 'thumb.jpg', 'image/jpeg');

$response = $request->execute();
```

The files are sent as a form upload, together with the values of `post()`. The name and the type of a file are found from the file unless you give them.

To send one file as the body of the request itself, as many APIs take files with `PUT`:

```php
$request = Request::factory('https://example.com/files/report.pdf')->method(HTTP_Request::PUT);
$request->client()->body_from('/path/to/report.pdf');
```

The file is read from the disk while it is sent, so a large one never has to fit in memory.

## Downloading to a file

```php
$request = Request::factory('https://example.com/exports/2026.zip');
$request->client()->save_to(APPPATH.'cache/2026.zip');

$response = $request->execute();
```

The answer is written to the file as it arrives, so a large download never has to fit in memory. The body of the response stays empty; its status and headers are there as always.

When the request fails, or is answered with an error status (400 and up), no file is left: an error page is not the file you asked for.

## Several requests at once

```php
$responses = Request_Client_Curl::all([
	'users'  => Request::factory('https://api.example.com/users'),
	'orders' => Request::factory('https://api.example.com/orders'),
	'stock'  => Request::factory('https://api.example.com/stock'),
], $errors);

$users = json_decode($responses['users']->body(), true);
```

- The requests are sent together: the time it takes is that of the slowest one, not of all three added up.
- The responses come back under the keys of the requests.
- A request that fails has no response: its exception is in `$errors`, under the same key. The others are not affected.
- The settings of each request's client apply, except `retry()`. Answers that send the client elsewhere are returned as they are, not followed.

## When the address comes from a visitor

A form that takes an address ("import from this link", "fetch this avatar") lets a visitor choose where **your server** makes a request. Without a check they can point it at machines only your server can reach: the database, an admin page, the settings service of a cloud provider.

```php
$request = Request::factory($url_from_the_form);

$request->client()
	->only_public_addresses()
	->timeout(5);

try
{
	$response = $request->execute();
}
catch (Request_Exception $e)
{
	// Refused, or failed: tell the visitor the address cannot be used
}
```

With `only_public_addresses()`:

- this machine, private networks (`10.x`, `172.16.x`, `192.168.x`), link-local addresses (`169.254.x`) and their IPv6 counterparts are refused;
- a name is looked up once, and the request goes to the address that was checked, so a name cannot answer "public" first and "internal" a moment later;
- when the answer sends the client to another address, that address is checked the same way.

Only `http` and `https` addresses are requested in any case: `Request_Client_External::$allowed_schemes`.

```php
// The same check for an address you have by other means
Request_Client_External::is_public_address('10.0.0.5');   // false
```

## Certificates

The certificate of an `https` server is always checked against the authorities your system trusts. A server with any other certificate is refused.

```php
// A server with a certificate of your own authority
$request->client()->verify(APPPATH.'certificates/authority.pem');

// A server that knows its clients by certificate
$request->client()->certificate(
	APPPATH.'certificates/client.pem',
	APPPATH.'certificates/client.key',
	$password_of_the_key
);
```

[!!] `verify(false)` switches the check off. Anybody between you and the server can then read and change everything, logins included. Use it for a test server only, never for a real one.

## Through a proxy

```php
$request->client()->proxy('http://proxy.example.com:8080');

// A proxy that asks for a login
$request->client()->proxy('http://proxy.example.com:8080', 'ana', $password);
```

## Any other option of curl

```php
$request->client()->options([
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
	CURLOPT_ENCODING     => '',   // accept compressed answers
]);
```

What the request itself says (method, body, headers, cookies) and the methods of this page come first; an option given here applies where they say nothing. A key that is no option of curl is reported.

## All settings

| Method | Does | Without curl |
|---|---|---|
| `bearer($token)` | Logs in with a token | works |
| `auth($user, $password)` | Logs in with name and password | works |
| `timeout($seconds)` | Limit for the whole request | works |
| `connect_timeout($seconds)` | Limit for reaching the server | ignored |
| `retry($times, $pause)` | Tries again after a failure, 502, 503 or 504 | error |
| `upload($field, $file)` | Sends a file as a form upload | error |
| `body_from($file)` | Sends a file as the body of the request | error |
| `save_to($path)` | Writes the answer to a file | error |
| `only_public_addresses()` | Refuses addresses inside your network | error |
| `verify($file)` | Which server certificates are trusted | error |
| `certificate($file, $key)` | A certificate of your own | error |
| `proxy($address)` | Goes through a proxy | error |
| `follow(true)` | Follows answers that send the client elsewhere | works |
