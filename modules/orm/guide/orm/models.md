# Models

A model is a class `Model_<Name>` that extends `ORM`. Names follow a convention, and every part of it can be changed.

## The convention

| Class | Table | Primary key |
|---|---|---|
| `Model_Article` | `articles` | `id` |
| `Model_Category` | `categories` | `id` |
| `Model_Blog_Post` | `blog_posts` | `id` |

A foreign key is the name of the other model followed by `_id`: `author_id`, `article_id`.

## Changing it

Properties must be declared with the same types as in `ORM`.

```php
class Model_Article extends ORM {

	// Another table
	protected ?string $_table_name = 'news_items';

	// Another primary key
	protected string $_primary_key = 'item_id';

	// Another database connection (a key of config/database.php)
	protected ?string $_db_group = 'reports';

	// Order of find_all() when none is given
	protected array $_sorting = ['created' => 'DESC'];
}
```

For table names that are not plural (`article`), in every model:

```php
protected bool $_table_names_plural = false;
```

## Dates that fill themselves

```php
class Model_Article extends ORM {

	// Set when the row is created
	protected ?array $_created_column = ['column' => 'created', 'format' => true];

	// Set at every save
	protected ?array $_updated_column = ['column' => 'updated', 'format' => 'Y-m-d H:i:s'];
}
```

`format` is `true` for a timestamp (an integer column), or a format of `date()` for a date column.

## Arrays in a column

A column listed here is stored as JSON and given back as an array.

```php
class Model_User extends ORM {

	protected array $_serialize_columns = ['settings'];
}
```

```php
$user->settings = ['theme' => 'dark', 'newsletter' => true];
$user->save();

echo $user->settings['theme'];   // dark
```

## Columns that must not leave the model

`as_array()` leaves these out, so they do not end up in JSON by accident.

```php
class Model_User extends ORM {

	protected ?array $_private_columns = ['password', 'reset_token'];
}
```

## Listing the columns yourself

ORM asks the database which columns a table has. To save that query, or when the database cannot answer (PDO with a database other than SQLite, MySQL or PostgreSQL), list them:

```php
class Model_Article extends ORM {

	protected ?array $_table_columns = [
		'id'      => [],
		'title'   => [],
		'body'    => [],
		'created' => [],
	];
}
```

## Methods of your own

A model is the place for everything about its data.

```php
class Model_Article extends ORM {

	protected array $_belongs_to = ['author' => ['model' => 'User']];

	/**
	 * The newest published articles.
	 */
	public function newest(int $limit = 10): Database_Result
	{
		return $this
			->where('published', '=', 1)
			->order_by('created', 'DESC')
			->limit($limit)
			->find_all();
	}

	/**
	 * May this user change the article?
	 */
	public function editable_by(Model_User $user): bool
	{
		return $this->author_id === $user->id OR $user->has('roles', ORM::factory('Role', ['name' => 'admin']));
	}

	/**
	 * A short version of the text.
	 */
	public function summary(int $words = 40): string
	{
		return Text::limit_words(strip_tags((string) $this->body), $words);
	}
}
```

```php
foreach (ORM::factory('Article')->newest(5) as $article)
{
	echo HTML::chars($article->summary());
}
```

## All settings

| Property | Type | Meaning |
|---|---|---|
| `$_table_name` | `?string` | Name of the table |
| `$_primary_key` | `string` | Name of the primary key column |
| `$_table_names_plural` | `bool` | Are table names plural? |
| `$_db_group` | `?string` | Database connection |
| `$_sorting` | `array` | Default order: `['column' => 'ASC']` |
| `$_created_column`, `$_updated_column` | `?array` | Dates that fill themselves |
| `$_serialize_columns` | `array` | Columns stored as JSON |
| `$_private_columns` | `?array` | Columns left out of `as_array()` |
| `$_table_columns` | `?array` | The columns, when not read from the database |
| `$_belongs_to`, `$_has_one`, `$_has_many` | `array` | [Relationships](relationships) |
| `$_load_with` | `array` | Relationships loaded with every find |
