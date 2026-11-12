# Controllers

A controller receives a request and fills in the response. It is a class `Controller_<Name>` in `classes/Controller/`; every public method `action_<name>()` can be called from an address.

```php
// application/classes/Controller/Article.php
class Controller_Article extends Controller {

	// /article or /article/index
	public function action_index(): void
	{
		$articles = ORM::factory('Article')->order_by('created', 'DESC')->find_all();

		$this->response->body(View::factory('article/list', ['articles' => $articles]));
	}

	// /article/view/42
	public function action_view(): void
	{
		$article = ORM::factory('Article', $this->request->param('id'));

		if ( ! $article->loaded())
			throw HTTP_Exception::factory(404, 'Article not found');

		$this->response->body(View::factory('article/view', ['article' => $article]));
	}
}
```

## What a controller has

| Property | Is |
|---|---|
| `$this->request` | The [request](../requests): address, parameters, form data, headers |
| `$this->response` | The [response](../responses): body, status, headers |

## before() and after()

`before()` runs before the action and `after()` after it. Use them for what every action of the controller needs.

```php
class Controller_Account extends Controller {

	protected ?Model_User $user = null;

	public function before(): void
	{
		parent::before();

		$this->user = Auth::instance()->get_user();

		if ( ! $this->user)
		{
			// Not logged in: nothing in this controller is for you
			$this->redirect('login');
		}
	}

	public function action_index(): void
	{
		$this->response->body(View::factory('account/index', ['user' => $this->user]));
	}
}
```

[!!] Both methods return nothing: declare them `: void`, and call `parent::before()` / `parent::after()`.

## A base controller for your application

Put what all your controllers share in one class and extend it.

```php
// application/classes/Controller/Base.php
abstract class Controller_Base extends Controller {

	public function before(): void
	{
		parent::before();

		// The same for every page
		View::set_global('site_name', Kohana::$config->load('site.name'));
	}
}
```

```php
class Controller_Article extends Controller_Base { /* ... */ }
```

## Common tasks

### Form data

```php
public function action_save(): void
{
	if ($this->request->method() !== HTTP_Request::POST)
		throw HTTP_Exception::factory(405, 'Use POST');

	$title = $this->request->post('title');   // one field
	$all   = $this->request->post();          // all fields
	$page  = $this->request->query('page');   // from ?page=2
}
```

### Redirect

```php
// To an address of this site: stops the action
$this->redirect('article/view/42');

// With another status than 302
$this->redirect(Route::url('article', ['id' => 42]), 301);
```

### Errors

```php
throw HTTP_Exception::factory(404, 'No article :id', [':id' => $id]);
throw HTTP_Exception::factory(403, 'Not yours');
```

### JSON

```php
public function action_status(): void
{
	$this->response->json(['ok' => true, 'time' => time()]);
}
```

### Another content type

```php
$this->response
	->headers('content-type', 'text/csv; charset=utf-8')
	->body("id,name\n1,Ana\n");
```

## Controllers in sub-directories

`Controller_Admin_Users` is `classes/Controller/Admin/Users.php`. The route gives the directory:

```php
Route::set('admin', 'admin(/<controller>(/<action>(/<id>)))')
	->defaults(['directory' => 'Admin', 'controller' => 'Dashboard', 'action' => 'index']);
```

## Rules

- Only `action_` methods can be reached from an address. Other methods are private helpers, whatever their visibility.
- Keep controllers thin: reading the request and choosing the view. Rules about data belong in [models](models).
- Prefer `Controller_Template` when every page shares a layout: see [Template Controller](../tutorials/controller-template).
