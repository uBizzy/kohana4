# Routing

A route says which controller and action answer an address. Routes are set in `application/bootstrap.php` (or a module's `init.php`).

```php
Route::set('article', 'article/<id>')
	->defaults([
		'controller' => 'Article',
		'action'     => 'view',
	]);
```

`/article/42` runs `Controller_Article::action_view()`, and `$this->request->param('id')` is `'42'`.

[!!] Routes are tried **in the order they are set**; the first match wins. Set specific routes first and the catch-all `default` route last.

## The parts of a route

```php
Route::set($name, $uri, $regex)->defaults($defaults);
```

| Part | Meaning |
|---|---|
| name | Any name of your own; used to build addresses with `Route::url()` |
| uri | The shape of the address. `<key>` is a parameter, `( )` is optional |
| regex | Optional: what each parameter may look like |
| defaults | Values for parameters that the address does not give |

Three parameters have a meaning for the framework: `controller`, `action` and `directory` (a sub-directory of `classes/Controller/`). The others are yours, read with `$this->request->param()`.

## Examples

```php
// /blog/2026/11/a-title: only digits for year and month
Route::set('blog', 'blog/<year>/<month>/<slug>', ['year' => '\d{4}', 'month' => '\d{2}'])
	->defaults(['controller' => 'Blog', 'action' => 'post']);

// /about, /contact, /terms: a fixed list of pages, one controller
Route::set('pages', '<page>', ['page' => 'about|contact|terms'])
	->defaults(['controller' => 'Page', 'action' => 'show']);

// /admin/users/edit/5: controllers in classes/Controller/Admin/
Route::set('admin', 'admin(/<controller>(/<action>(/<id>)))')
	->defaults(['directory' => 'Admin', 'controller' => 'Dashboard', 'action' => 'index']);

// /files/2026/report.pdf: a parameter that may contain slashes
Route::set('files', 'files/<path>', ['path' => '.+'])
	->defaults(['controller' => 'File', 'action' => 'download']);

// /api/users.json or /api/users.xml
Route::set('api', 'api/<controller>(/<id>)(.<format>)', ['format' => 'json|xml'])
	->defaults(['directory' => 'Api', 'format' => 'json']);

// Everything else: /controller/action/id
Route::set('default', '(<controller>(/<action>(/<id>)))')
	->defaults(['controller' => 'Welcome', 'action' => 'index']);
```

Without a regex, a parameter matches anything except `/`, `.`, `,`, `;` and `?`.

## Reading the parameters

```php
public function action_post(): void
{
	$year = (int) $this->request->param('year');
	$slug = $this->request->param('slug');

	// A default when the address did not give it
	$format = $this->request->param('format', 'html');

	// All of them
	$params = $this->request->param();
}
```

## Building addresses

Never write addresses by hand. Build them from the route, and they follow when you change the route.

```php
// "blog/2026/11/a-title": the path
$uri = Route::get('blog')->uri(['year' => 2026, 'month' => '11', 'slug' => 'a-title']);

// "/blog/2026/11/a-title": ready for a link
$url = Route::url('blog', ['year' => 2026, 'month' => '11', 'slug' => 'a-title']);

// With the domain
$full = Route::url('blog', ['year' => 2026, 'month' => '11', 'slug' => 'a-title'], true);

// A link
echo HTML::anchor(Route::get('article')->uri(['id' => 42]), 'Read more');

// Is there a route with this name? (Route::get() throws when there is not)
$exists = Route::has('article');
```

## Filters: more than a pattern

A filter is a function that can refuse a match or change the parameters.

```php
// Only for POST requests
Route::set('upload', 'upload')
	->filter(function (Route $route, array $params, Request $request) {
		return $request->method() === HTTP_Request::POST;
	})
	->defaults(['controller' => 'Upload', 'action' => 'save']);

// "/de/about": the first part is a language, when it is one we know
Route::set('localized', '<lang>/<page>')
	->filter(function (Route $route, array $params, Request $request) {
		if ( ! in_array($params['lang'], ['en', 'de', 'pt'], true))
			return false;

		I18n::lang($params['lang']);

		return $params;
	})
	->defaults(['controller' => 'Page', 'action' => 'show']);
```

Return `false` to refuse, an array to replace the parameters, anything else to accept.

## Checking your routes

```php
// Which route matched this request?
$name = Route::name($this->request->route());

// All routes, in order
foreach (Route::all() as $name => $route)
{
	echo $name, "\n";
}
```

## In production

Compiling routes takes time on every request. Cache them:

```php
if ( ! Route::cache())
{
	Route::set('default', '(<controller>(/<action>(/<id>)))')
		->defaults(['controller' => 'Welcome', 'action' => 'index']);

	// Save them when the environment is production
	Route::cache(Kohana::$environment === Kohana::PRODUCTION);
}
```

[!!] Routes with a filter that is a closure cannot be cached. Use a static method (`'Class::method'`) as the filter if you cache routes.
