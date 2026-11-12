# Kohana

Kohana is a small PHP framework built around three ideas:

- **Model, View, Controller**: a request is matched by a route, run by a controller, and answered with a view.
- **Cascading filesystem**: your application can replace any file of the framework by putting a file with the same name in `application/`.
- **Plain PHP**: no code generation, no template language, no configuration format to learn.

This is version 4.0. It needs PHP 8.4 or later; every method declares its types.

## Where to start

| You want to | Read |
|---|---|
| Install the framework | [Installation](install) |
| See a first page working | [Hello World](tutorials/hello-world) |
| Move an application from 3.3 or Koseven | [Upgrading to 4.0](upgrading) |
| Understand what happens on each request | [Request Flow](flow) |
| Look up a class or a method | The API Browser, in the header of this page |

## A request in ten lines

```php
// application/bootstrap.php: which address runs which controller
Route::set('article', 'article/<id>', ['id' => '\d+'])
	->defaults(['controller' => 'Article', 'action' => 'view']);

// application/classes/Controller/Article.php
class Controller_Article extends Controller {

	public function action_view(): void
	{
		$id = (int) $this->request->param('id');

		$this->response->body(View::factory('article/view', ['id' => $id]));
	}
}
```

`/article/42` now shows the view `application/views/article/view.php`.

## The modules

The framework ships with modules that you switch on in the bootstrap. Each has its own guide.

| Module | For |
|---|---|
| [Database](../database) | Queries, query builder, prepared statements, transactions |
| [ORM](../orm) | Models that map to database tables |
| [Auth](../auth) | Log in, roles, password hashing |
| [Cache](../cache) | File, SQLite, Redis, Memcached and APCu caches |
| [Encrypt](../encrypt) | Encrypting data with OpenSSL or Sodium |
| [Image](../image) | Resize, crop and convert images |
| [Minion](../minion) | Tasks run from the command line |
| [Pagination](../pagination) | Page links for lists |
| [Unittest](../unittest) | Running tests with PHPUnit |
| [Userguide](../userguide) | This guide and the API browser |
| [Codebench](../codebench) | Comparing the speed of pieces of code |
| [KO7](../ko7) and [KO9](../ko9) | Running applications written with the old class names |
