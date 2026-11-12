# Custom Error Pages

What visitors see when a page is not found, they are not allowed, or something breaks.

## The simple way: style the production page

In production every error shows the view `kohana/error_production`. Copy it to your application and make it look like your site.

```php
<!-- application/views/kohana/error_production.php -->
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?php echo HTML::chars($title) ?> | My Shop</title>
	<link rel="stylesheet" href="/css/site.css">
</head>
<body>
	<h1><?php echo HTML::chars($title) ?></h1>

	<?php if ($status === 404): ?>
		<p>That page does not exist. Try the <a href="/">home page</a>.</p>
	<?php else: ?>
		<p>Something went wrong on our side. Please try again later.</p>
		<p>If you write to us, mention this code: <code><?php echo HTML::chars($reference) ?></code></p>
	<?php endif ?>
</body>
</html>
```

| Variable | Is |
|---|---|
| `$status` | The HTTP status: 404, 403, 500, ... |
| `$title` | A general text: "Not Found" for 4xx, "An error occurred" otherwise |
| `$reference` | The code under which the error is in the log |

[!!] Keep this view simple and self-contained. If it fails (it uses the database, and the database is the problem), the visitor gets a bare text.

In development you still get the full [error page](../errors).

## A page of its own for one status

Each status has a class: `HTTP_Exception_404`, `HTTP_Exception_403`, ... Its `get_response()` builds the answer. [Extend](../extension) the class to change it.

```php
// application/classes/HTTP/Exception/404.php
class HTTP_Exception_404 extends Kohana_HTTP_Exception_404 {

	public function get_response(): Response
	{
		if (Kohana::$environment !== Kohana::PRODUCTION)
		{
			// The full error page while developing
			return parent::get_response();
		}

		$view = View::factory('errors/404', [
			'uri' => $this->request()?->uri(),
		]);

		return Response::factory()
			->status(404)
			->body($view->render());
	}
}
```

## Sending visitors to the log in page

```php
// application/classes/HTTP/Exception/401.php
class HTTP_Exception_401 extends Kohana_HTTP_Exception_401 {

	public function get_response(): Response
	{
		return Response::factory()
			->status(302)
			->headers('location', URL::site('login'));
	}
}
```

```php
// In any controller
if ( ! Auth::instance()->logged_in())
	throw HTTP_Exception::factory(401);
```

## All statuses at once

```php
// application/classes/HTTP/Exception.php
class HTTP_Exception extends Kohana_HTTP_Exception {

	public function get_response(): Response
	{
		if (Kohana::$environment !== Kohana::PRODUCTION)
			return parent::get_response();

		// Server errors must be in the log
		if ($this->getCode() >= 500)
		{
			Kohana_Exception::log($this);
		}

		return Response::factory()
			->status($this->getCode())
			->body(View::factory('errors/http', [
				'status'    => $this->getCode(),
				'reference' => Kohana_Exception::reference($this),
			])->render());
	}
}
```

## Rules for error pages

- Send the right status. A "not found" page with status 200 confuses search engines and tools.
- Do not print the message of the exception in production: it can hold names of files, tables or people.
- Show the reference code for server errors, so that a visitor's report leads you to the log line.
