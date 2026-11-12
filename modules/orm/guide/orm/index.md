# ORM

With ORM (object-relational mapping) one object is one row of a table. You read and write properties; the SQL is written for you.

```php
// application/bootstrap.php: ORM needs the database module
Kohana::modules([
	'database' => MODPATH.'database',
	'orm'      => MODPATH.'orm',
]);
```

## A model

```php
// application/classes/Model/Article.php: the table "articles"
class Model_Article extends ORM {}
```

That is a complete model. The columns are read from the table.

## Using it

```php
// Create
$article = ORM::factory('Article');
$article->title = 'Hello';
$article->body = 'First post.';
$article->save();

echo $article->id;   // the new id

// Find
$article = ORM::factory('Article', 42);

if ($article->loaded())
{
	echo $article->title;
}

// Change
$article->title = 'Hello again';
$article->save();

// Delete
$article->delete();

// Several
$articles = ORM::factory('Article')
	->where('published', '=', 1)
	->order_by('created', 'DESC')
	->limit(10)
	->find_all();

foreach ($articles as $article)
{
	echo $article->title;
}
```

## What else a model can do

| | |
|---|---|
| [Models](models) | Table and column names, automatic dates, stored arrays |
| [Finding and Saving](using) | All ways to find, create, change and delete |
| [Relationships](relationships) | An article *belongs to* an author, *has many* comments and tags |
| [Validation](validation) | Rules and filters that run on every save |
| [Behaviors](behaviors) | Slugs and ids that fill themselves |

All of it in one working example, with the tables, the controller, the form and a test: [A Model from Start to End](../kohana/tutorials/model).

## Good to know

- The database must be MySQL/MariaDB (MySQLi or PDO driver), SQLite or PostgreSQL (PDO driver). On any other database the model must list its columns: see [Models](models).
- Values are sent to the database apart from the SQL, as everywhere in the [database module](../database).
- Models can be stored in the session; they are loaded again when read.
- For queries that do not fit a model (reports, counts over many tables), use the [query builder](../database/query/builder) next to ORM.
