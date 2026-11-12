# Responses

`$this->response` in a controller is what the visitor will get. An action fills it in; the framework sends it.

## Body, status, headers

```php
$this->response->body('Hello');
$this->response->body(View::factory('page'));   // a view is rendered

$this->response->status(201);

$this->response->headers('content-type', 'text/plain; charset=utf-8');
$this->response->headers('cache-control', 'public, max-age=3600');

// Chained
$this->response
	->status(404)
	->headers('content-type', 'text/plain')
	->body('Not here');
```

## JSON

```php
$this->response->json(['id' => 42, 'title' => 'Hello']);

// With a status
$this->response->json(['error' => 'Title is needed'], 422);
```

Sets the content type and encodes the data.

## CSV and XML

```php
// A table, shown as text
$this->response->csv($rows);

// The same, offered as a file to save
$this->response->csv($rows, 'orders-2026.csv');
```

```php
// <root><id>42</id><title>Hello</title></root>
$this->response->xml(['id' => 42, 'title' => 'Hello']);
```

Both set the content type. How rows and data are written, and why text that looks like a formula gets a `'` in a CSV, is explained under [JSON, XML or CSV](rest#json-xml-or-csv).

## Redirects

```php
// In a controller. Nothing after this line runs
$this->redirect('login');
$this->redirect('article/view/42', 301);

// Anywhere
HTTP::redirect('login');
```

## Sending a file

```php
// A download
$this->response->send_file(APPPATH.'files/report.pdf');

// With another name for the visitor
$this->response->send_file(APPPATH.'files/2026-11.pdf', 'report.pdf');

// Shown in the browser instead of saved
$this->response->send_file($path, null, ['inline' => true]);

// What is in the body, as a file
$this->response->body($csv);
$this->response->send_file(true, 'export.csv', ['mime_type' => 'text/csv']);
```

| Option | Meaning |
|---|---|
| `inline` | Show in the browser, do not offer to save |
| `mime_type` | The content type, when it should not be taken from the file name |
| `resumable` | Allow the download to be continued after a break |
| `delete` | Delete the file after it was sent |

[!!] Never build the path from what a visitor sent without checking it. Keep downloads in one directory and look the file up by an id.

## Cookies

```php
// Sent with this response
$this->response->cookie('theme', 'dark');

// Or directly: see the Cookies page
Cookie::set('theme', 'dark');
```

## Let the browser keep a page

`check_cache()` answers `304 Not Modified`, without a body, when the browser already has this version.

```php
public function action_view(): void
{
	$article = ORM::factory('Article', $this->request->param('id'));

	// The version changes when the article does
	$this->check_cache(hash('sha256', $article->id.$article->updated));

	$this->response->body(View::factory('article/view', ['article' => $article]));
}
```

## Headers on every response

Three security headers are added to every response. Change them in the bootstrap: see [Security Headers](security/headers).

```php
HTTP::$security_headers['x-frame-options'] = 'DENY';
```

## Status codes as exceptions

To stop an action with an error status, throw:

```php
throw HTTP_Exception::factory(404, 'No such article');
throw HTTP_Exception::factory(403, 'Not allowed');
throw HTTP_Exception::factory(503, 'Back in a minute');
```

See [Custom Error Pages](tutorials/error-pages) to choose what the visitor sees.
