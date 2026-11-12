# REST and JSON

Two ways to build an API: a plain controller that speaks JSON, or `Controller_REST`, which maps HTTP methods to actions for you.

## JSON in a plain controller

```php
class Controller_Api_Article extends Controller {

	// GET /api/article/view/42
	public function action_view(): void
	{
		$article = ORM::factory('Article', $this->request->param('id'));

		if ( ! $article->loaded())
		{
			$this->response->json(['error' => 'Not found'], 404);
			return;
		}

		$this->response->json($article->as_array());
	}

	// POST /api/article/create   {"title": "Hello", "body": "..."}
	public function action_create(): void
	{
		$data = $this->request->json();

		$article = ORM::factory('Article')->values($data, ['title', 'body']);

		try
		{
			$article->save();
		}
		catch (ORM_Validation_Exception $e)
		{
			$this->response->json(['errors' => $e->errors('models')], 422);
			return;
		}

		$this->response->json(['id' => (int) $article->id], 201);
	}
}
```

- `$this->request->json()` reads a JSON body; one that is not valid JSON answers `400` by itself.
- `$this->response->json($data, $status)` sends one.
- Errors in an AJAX request, or one that asks for JSON, are answered as JSON too: see [Error Handling](errors).

### JSON elsewhere

`Request::json()` and `Response::json()` cover requests and responses. For any other JSON (a file, a cache entry, a call to another service) use the `Json` helper. Unlike `json_encode()` and `json_decode()`, it throws a `JsonException` on bad data instead of returning `false` or `null`.

```php
$json = Json::encode(['name' => 'Zoë', 'url' => 'a/b']);   // {"name":"Zoë","url":"a/b"}
$json = Json::encode($data, JSON_PRETTY_PRINT);            // easy to read

$data = Json::decode('{"name":"Ana"}');                    // ['name' => 'Ana']
$data = Json::decode('{"name":"Ana"}', false);             // an object: $data->name

Json::valid('{"name":"Ana"}');                             // true
```

## Controller_REST

`Controller_REST` chooses the action from the HTTP method, and formats what you put in the body.

| Method | Action |
|---|---|
| `GET` | `action_index()` |
| `POST` | `action_create()` |
| `PUT` | `action_update()` |
| `DELETE` | `action_delete()` |

```php
// application/bootstrap.php
Route::set('api', 'api/<controller>(/<id>)(.<format>)', ['id' => '\d+', 'format' => 'json|xml'])
	->defaults(['directory' => 'Api']);
```

```php
// application/classes/Controller/Api/Article.php
class Controller_Api_Article extends Controller_REST {

	// GET /api/article and GET /api/article/42
	public function action_index(): void
	{
		$id = $this->request->param('id');

		$this->response->body($id
			? ORM::factory('Article', $id)->as_array()
			: array_map(fn ($article) => $article->as_array(), ORM::factory('Article')->find_all()->as_array()));
	}

	// POST /api/article
	public function action_create(): void
	{
		$article = ORM::factory('Article')
			->values($this->_parse_params(), ['title', 'body'])
			->save();

		$this->response->status(201)->body(['id' => (int) $article->id]);
	}

	// DELETE /api/article/42
	public function action_delete(): void
	{
		ORM::factory('Article', $this->request->param('id'))->delete();

		$this->response->body(['deleted' => true]);
	}
}
```

- Give the body as an **array**; it is sent as JSON, XML or CSV, as the client asks: see below.
- `$this->_parse_params()` returns the data that was sent: the query for `GET`, the form fields or the JSON body otherwise.
- A method without an action in the controller answers `405 Method Not Allowed`.
- Responses to anything but `GET` are marked as not cacheable.

## JSON, XML or CSV

The same action answers in the format the client asks for. Your code gives an array and does not have to know.

| The client asks with | It gets | Content type |
|---|---|---|
| nothing | JSON | `application/json` |
| an address that ends in `.json`, or `Accept: application/json` | JSON | `application/json` |
| an address that ends in `.xml`, or `Accept: application/xml` | XML | `application/xml` |
| an address that ends in `.csv`, or `Accept: text/csv` | CSV | `text/csv` |

```text
GET /api/articles            JSON
GET /api/articles.csv        CSV
GET /api/articles            with "Accept: application/xml": XML
```

- The address wins: with `.xml` in it, the `Accept` header is not looked at.
- A browser opening the address gets the default, JSON. It asks for HTML first and "anything" last, which is no request for a format.
- An `Accept` header that names nothing the controller can write gets the default too.
- When the header decides, the response says so (`Vary: Accept`), so a cache keeps one copy for each format.
- The default is `REST_Format::$default_format`; the types a client can ask for are in `REST_Format::$accept`.

### How the data is written

```php
$this->response->body([
	['id' => 1, 'title' => 'First',  'tags' => ['php', 'sql']],
	['id' => 2, 'title' => 'Second', 'tags' => []],
]);
```

**XML**: keys are the names of elements, and the items of a list are named `item`. Values are written as text, so what a visitor typed can never become markup; a key that cannot be a name (`first name`, `9lives`) has the wrong characters replaced by `_`.

```xml
<root>
	<item><id>1</id><title>First</title><tags><item>php</item><item>sql</item></tags></item>
	<item><id>2</id><title>Second</title><tags/></item>
</root>
```

**CSV**: one row on each line. The first line holds the names of the values; rows need not all have the same ones. A list inside a row is written as JSON.

```text
id,title,tags
1,First,"[""php"",""sql""]"
2,Second,[]
```

[!!] A spreadsheet program runs a cell that starts with `=`, `+`, `-` or `@` as a formula. A visitor who can put such text in your data could make it act on the computer of whoever opens the file. Text like that is written with a `'` in front, which the program shows as plain text. Numbers stay numbers.

```php
// For Excel in countries where the comma is the decimal sign
REST_Format_CSV::$delimiter = ';';

// Excel only reads accents right with this mark at the start of the file
REST_Format_CSV::$bom = true;
```

A format of your own is a class `REST_Format_Name` with a `format()` method that returns the text; the address `.name` then uses it.

## Calls from other sites

A browser only lets JavaScript of another site call your API when you allow that site. `Controller_REST` does it from the `cors` config: see [Cross-Origin Requests](security/cors).

## Protecting an API

```php
public function before(): void
{
	parent::before();

	// At most 60 calls a minute for each client: answers 429 beyond that
	Throttle::check('api:'.Request::$client_ip, 60, 60);
}
```

See [Rate Limiting](security/throttle).
