# Relationships

Relationships say how tables belong together. Declare them in the model and reach related rows as properties.

```sql
authors(id, name)
profiles(id, author_id, bio)
articles(id, author_id, title)
comments(id, article_id, text)
tags(id, name)
articles_tags(article_id, tag_id)
```

## belongs_to: this row points at another

The table has the foreign key: `articles.author_id`.

```php
class Model_Article extends ORM {

	protected array $_belongs_to = [
		'author' => [],
	];
}
```

```php
$article = ORM::factory('Article', 1);

echo $article->author->name;
```

## has_many: other rows point at this one

```php
class Model_Author extends ORM {

	protected array $_has_many = [
		'articles' => [],
	];
}
```

`$author->articles` is a model ready to query, so you can add conditions:

```php
$author = ORM::factory('Author', 1);

$all = $author->articles->find_all();

$published = $author->articles
	->where('published', '=', 1)
	->order_by('created', 'DESC')
	->find_all();

$count = $author->articles->count_all();
```

## has_one: one other row points at this one

```php
class Model_Author extends ORM {

	protected array $_has_one = [
		'profile' => [],
	];
}
```

```php
echo $author->profile->bio;
```

## has_many through: many to many

Articles have many tags and tags have many articles; a table in between holds the pairs.

```php
class Model_Article extends ORM {

	protected array $_has_many = [
		'tags' => ['model' => 'Tag', 'through' => 'articles_tags'],
	];
}

class Model_Tag extends ORM {

	protected array $_has_many = [
		'articles' => ['model' => 'Article', 'through' => 'articles_tags'],
	];
}
```

```php
$article = ORM::factory('Article', 1);
$tag = ORM::factory('Tag', ['name' => 'php']);

// Connect and disconnect
$article->add('tags', $tag);
$article->add('tags', [3, 4]);     // by ids
$article->remove('tags', $tag);
$article->remove('tags');          // all of them

// Ask
$article->has('tags', $tag);       // true or false
$article->has_any('tags', [3, 4]); // at least one of them?
$article->count_relations('tags');

// Read
foreach ($article->tags->order_by('name')->find_all() as $tag)
{
	echo $tag->name;
}
```

## When names do not follow the convention

Each relationship takes options. Without them, the name of the relationship is the name of the model, and the foreign key is the model name with `_id`.

```php
class Model_Article extends ORM {

	protected array $_belongs_to = [
		// $article->writer is a Model_User, through articles.written_by
		'writer' => ['model' => 'User', 'foreign_key' => 'written_by'],
	];

	protected array $_has_many = [
		'comments' => ['model' => 'Comment', 'foreign_key' => 'article_id'],

		'tags' => [
			'model'       => 'Tag',
			'through'     => 'articles_tags',
			'foreign_key' => 'article_id',   // the column for this model in the middle table
			'far_key'     => 'tag_id',       // the column for the other model
		],
	];
}
```

| Option | Default | Meaning |
|---|---|---|
| `model` | The name of the relationship (singular for `has_many`) | The related model, without `Model_` |
| `foreign_key` | Model name + `_id` | The column that holds the id |
| `through` | | The middle table of a many-to-many relationship |
| `far_key` | Other model name + `_id` | The column of the other model in the middle table |

## Saving related rows

```php
// belongs_to: set the foreign key
$article->author_id = $author->id;
$article->save();

// has_many: create the other row with the key
$comment = ORM::factory('Comment');
$comment->article_id = $article->id;
$comment->text = 'Nice.';
$comment->save();
```

## Avoiding a query per row

Reading `$article->author` in a loop runs one query for each article. Load the authors with the articles:

```php
$articles = ORM::factory('Article')->with('author')->find_all();
```

Always, for a model:

```php
class Model_Article extends ORM {

	protected array $_belongs_to = ['author' => []];

	protected array $_load_with = ['author'];
}
```

`with()` works for `belongs_to` and `has_one`. For `has_many`, read the related rows of all models in one query yourself:

```php
$comments = ORM::factory('Comment')
	->where('article_id', 'IN', array_keys($articles->as_array('id')))
	->find_all();
```

## Deleting

Deleting a row does not delete the rows that point at it. Remove them yourself, or let the database do it with a foreign key `ON DELETE CASCADE`.

```php
public function delete(): static
{
	// The pairs in the middle table, then the comments, then the article
	$this->remove('tags');

	DB::delete('comments')->where('article_id', '=', $this->id)->execute($this->_db);

	return parent::delete();
}
```
