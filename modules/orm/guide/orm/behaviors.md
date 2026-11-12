# Behaviors

A behavior fills columns by itself when a model is created or changed. Two come with the module; you can write your own.

```php
class Model_Article extends ORM {

	public function behaviors(): array
	{
		return [
			'Slug' => ['source' => 'title', 'column' => 'slug'],
			'Guid' => ['column' => 'guid'],
		];
	}
}
```

## Slug

Makes a text for addresses from another column, and keeps it unique.

```php
$article = ORM::factory('Article');
$article->title = 'Olá Mundo!';
$article->save();

echo $article->slug;   // ola-mundo
```

A second article with the same title gets a number at the end, so slugs stay unique. The slug is made again when the title changes.

| Option | Default | Meaning |
|---|---|---|
| `source` | `'name'` | The column the slug is made from |
| `column` | `'slug'` | The column the slug is stored in |

With this behavior, a model can be found by its slug as well as by its number:

```php
$article = ORM::factory('Article', 'ola-mundo');
$article = ORM::factory('Article', 42);
```

[!!] If old addresses must keep working, do not let the slug change with the title: set it once in your own code instead of using the behavior.

## Guid

Gives each new row a UUID: an id that cannot be guessed and is unique across databases.

```php
$order = ORM::factory('Order')->values(['total' => 100])->save();

echo $order->guid;   // 3f1c2a9e-...
```

With this behavior, a model can be found by its UUID as well as by its number:

```php
$order = ORM::factory('Order', '3f1c2a9e-5b7d-4c1a-9e2f-8a6b4d2c1e0f');
```

| Option | Default | Meaning |
|---|---|---|
| `column` | `'guid'` | The column the UUID is stored in |

Use it for ids shown in addresses (`/order/3f1c2a9e-...`), where a number would let visitors count your orders or try the next one.

## Writing a behavior

A behavior is a class `ORM_Behavior_<Name>` with three methods, all optional.

```php
// application/classes/ORM/Behavior/Author.php
class ORM_Behavior_Author extends ORM_Behavior {

	protected string $_column = 'author_id';

	protected function __construct(mixed $config)
	{
		parent::__construct($config);

		$this->_column = Arr::get((array) $config, 'column', $this->_column);
	}

	/**
	 * A new row belongs to the logged-in user.
	 */
	public function on_create(ORM $model): void
	{
		if ($user = Auth::instance()->get_user())
		{
			$model->set($this->_column, $user->id);
		}
	}
}
```

```php
class Model_Article extends ORM {

	public function behaviors(): array
	{
		return ['Author' => ['column' => 'author_id']];
	}
}
```

| Method | Called |
|---|---|
| `on_construct(ORM $model, mixed $id): bool` | When a model is made. Return `false` when the behavior loaded the row itself |
| `on_create(ORM $model): void` | Before a new row is saved |
| `on_update(ORM $model): void` | Before a loaded row is saved |

## For simple cases

A date that fills itself needs no behavior: see `$_created_column` and `$_updated_column` in [Models](models). For one model only, override `create()` or `update()`:

```php
public function create(?Validation $validation = null): static
{
	$this->token = Text::random('hexdec', 32);

	return parent::create($validation);
}
```
