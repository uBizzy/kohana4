# Models

A model holds the rules of your data: how it is read, checked and saved. Controllers ask models; views show what models return.

A model is a class `Model_<Name>` in `classes/Model/`. Three kinds are common.

## A plain model

Extend `Model` and write the methods you need. Use the [query builder](../../database/query/builder) for the database.

```php
// application/classes/Model/Article.php
class Model_Article extends Model {

	/**
	 * The newest published articles.
	 */
	public function newest(int $limit = 10): array
	{
		return DB::select('id', 'title', 'created')
			->from('articles')
			->where('published', '=', 1)
			->order_by('created', 'DESC')
			->limit($limit)
			->execute()
			->as_array();
	}

	/**
	 * Adds an article and returns its id.
	 */
	public function add(string $title, string $body): int
	{
		[$id] = DB::insert('articles', ['title', 'body', 'created'])
			->values([$title, $body, time()])
			->execute();

		return (int) $id;
	}
}
```

```php
// In a controller
$articles = Model::factory('Article')->newest(5);

// The same
$articles = (new Model_Article)->newest(5);
```

## An ORM model

With the [ORM module](../../orm), one object is one row, and you write almost no SQL.

```php
// application/classes/Model/Article.php
class Model_Article extends ORM {

	protected array $_belongs_to = ['author' => ['model' => 'User']];

	public function rules(): array
	{
		return [
			'title' => [['not_empty'], ['max_length', [':value', 200]]],
		];
	}
}
```

```php
$article = ORM::factory('Article', 42);

echo $article->title;
echo $article->author->username;

$article->title = 'A new title';
$article->save();
```

## A model that is not a table

A model does not need a database. Anything with rules of its own is a model: a price calculation, a call to another service.

```php
// application/classes/Model/Weather.php
class Model_Weather extends Model {

	public function today(string $city): array
	{
		$response = Request::factory('https://api.example.org/weather')
			->query('city', $city)
			->execute();

		return json_decode($response->body(), true) ?? [];
	}
}
```

## Checking data in the model

Put validation where the data is saved, so that every controller that saves gets the same rules.

```php
class Model_Contact extends Model {

	public function send(array $data): Validation
	{
		$validation = Validation::factory($data)
			->rule('email', 'not_empty')
			->rule('email', 'email')
			->rule('message', 'min_length', [':value', 10]);

		if ($validation->check())
		{
			// ... send the message
		}

		return $validation;
	}
}
```

```php
// In the controller
$validation = Model::factory('Contact')->send($this->request->post());

if ($validation->errors())
{
	$view->errors = $validation->errors('contact');
}
```

See [Validation](../security/validation).

A complete example with ORM, from the tables to the form and a test: [A Model from Start to End](../tutorials/model).
