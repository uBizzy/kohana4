# Request Flow

What happens between the browser asking for a page and getting it.

1. **`public/index.php`** receives every request. It sets the paths (`APPPATH`, `MODPATH`, `SYSPATH`, `DOCROOT`) and loads `application/bootstrap.php`.
2. **`application/bootstrap.php`** prepares the framework: time zone, class loading, `Kohana::init()`, modules, routes. See [Bootstrap](bootstrap).
3. **`Request::factory()`** reads the address, the method, the headers and the client's IP address.
4. **`Request::execute()`** tries the [routes](routing) in the order they were set. The first that matches gives the controller, the action and the parameters. No match is a 404.
5. The **controller** is created and run:
   - `before()`
   - `action_<name>()`
   - `after()`
6. The controller fills `$this->response`: body, status and headers.
7. Back in `index.php`, the headers are sent and the body is printed.

```php
// The end of public/index.php: steps 3 to 7
echo Request::factory(true, [], false)
	->execute()
	->send_headers(true)
	->body();
```

## When something goes wrong

- An exception that nothing catches is turned into an [error page](errors) and written to the log.
- An `HTTP_Exception` (404, 403, 500, ...) becomes a response with that status.

```php
public function action_view(): void
{
	$article = ORM::factory('Article', $this->request->param('id'));

	if ( ! $article->loaded())
	{
		// Stops the action; the visitor gets a 404 page
		throw HTTP_Exception::factory(404, 'Article not found');
	}

	$this->response->body(View::factory('article/view', ['article' => $article]));
}
```

## Requests inside requests

A controller can run another request and use its response. This is the "H" of HMVC: pages built from parts that are pages themselves.

```php
// Inside a controller: put the output of /sidebar/latest in the page
$sidebar = Request::factory('sidebar/latest')->execute()->body();
```

The first request of a page is `Request::initial()`; the one running now is `Request::current()`.

```php
if ( ! $this->request->is_initial())
{
	// Called from another controller, not by the browser
}
```

## From the command line

`./minion <task>` skips steps 3 to 7 and runs a [Minion](../minion) task instead.
