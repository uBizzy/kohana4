# Finding and Saving

## Finding one

```php
// By primary key
$article = ORM::factory('Article', 42);

// By other columns
$user = ORM::factory('User', ['email' => 'ana@example.org']);

// With conditions
$article = ORM::factory('Article')
	->where('slug', '=', $slug)
	->where('published', '=', 1)
	->find();
```

A row that is not found gives an empty model, not an error. Always check:

```php
if ( ! $article->loaded())
	throw HTTP_Exception::factory(404, 'Article not found');
```

## Finding several

`find_all()` returns a result to loop over. All methods of the [query builder](../database/query/builder) can be used before it.

```php
$articles = ORM::factory('Article')
	->where('published', '=', 1)
	->where('created', '>', strtotime('-30 days'))
	->order_by('created', 'DESC')
	->limit(20)
	->offset(40)
	->find_all();

echo count($articles);

foreach ($articles as $article)
{
	echo $article->title;
}
```

```php
// As arrays
$list = ORM::factory('Article')->find_all()->as_array();           // list of models
$titles = ORM::factory('Article')->find_all()->as_array('id', 'title');   // [1 => 'Hello', 2 => ...]

// For a select box
echo Form::select('category', ORM::factory('Category')->order_by('name')->find_all()->as_array('id', 'name'));
```

## Counting

```php
$total = ORM::factory('Article')->where('published', '=', 1)->count_all();
```

## Reading values

```php
echo $article->title;
echo $article->author->name;   // a related model: see Relationships

// All columns
$data = $article->as_array();

// The primary key, whatever its name
$id = $article->pk();
```

[!!] With MySQLi, values read from the database are strings: `'1'`, not `1`. Compare with care (`(int) $article->author_id === (int) $user->id`).

## Creating

```php
$article = ORM::factory('Article');
$article->title = 'Hello';
$article->body = 'First post.';
$article->save();

echo $article->id;
```

### From a form

`values()` sets several columns. **Always give the list of columns the form may set**, or a visitor can add fields of their own (`is_admin=1`).

```php
$article = ORM::factory('Article')
	->values($this->request->post(), ['title', 'body', 'category_id'])
	->save();
```

## Changing

```php
$article = ORM::factory('Article', 42);

$article->title = 'A better title';
$article->save();
```

Only columns that changed are written.

```php
$article->changed();          // ['title' => 'title']: what was changed since loading
$article->changed('title');   // true
$article->saved();            // true when nothing is waiting to be saved
```

`save()` creates a new row or updates the loaded one. `create()` and `update()` do only the one thing.

## Deleting

```php
ORM::factory('Article', 42)->delete();
```

To delete many rows without loading them, use the query builder:

```php
DB::delete('articles')->where('created', '<', $limit)->execute();
```

## When saving fails

`save()` checks the [rules](validation) of the model first. When a value breaks a rule, nothing is saved and an `ORM_Validation_Exception` is thrown.

```php
try
{
	$article->values($this->request->post(), ['title', 'body'])->save();

	$this->redirect('article/view/'.$article->id);
}
catch (ORM_Validation_Exception $e)
{
	// ['title' => 'Title must not be empty']
	$errors = $e->errors('models');
}
```

## Loading related rows in the same query

```php
// One query with a join, not one query per article for its author
$articles = ORM::factory('Article')->with('author')->find_all();

foreach ($articles as $article)
{
	echo $article->author->name;
}
```

Works for `belongs_to` and `has_one` relationships.

## Several saves as one

```php
Database::instance()->transaction(function () use ($order, $lines) {
	$order->save();

	foreach ($lines as $line)
	{
		$line->order_id = $order->id;
		$line->save();
	}
});
```

If any save fails, none is kept. See [Transactions](../database/transactions).

## Other useful methods

| Method | Does |
|---|---|
| `$model->reload()` | Reads the row again from the database |
| `$model->clear()` | Empties the model |
| `$model->as_array()` | The columns as an array |
| `$model->last_query()` | The last SQL of this model: for debugging |
| `$model->unique('email', $value)` | `true` when no other row has that value. The database decides what is equal: MySQL ignores upper and lower case by default, SQLite and PostgreSQL do not |
| `$model->unique('code', $value, true)` | The same, counting only values that are exactly equal, on every database |
