# A Model from Start to End

This tutorial builds one complete feature: articles, each written by a user and found by its tags. It uses every part a model touches, in the order you would write them:

1. the tables, as a migration;
2. the models, with their rules and relationships;
3. data to try it with;
4. a controller that lists, shows, saves and deletes;
5. the views, with the form and its errors;
6. a test.

Every file below is complete and was run as shown.

## What you need

```php
// application/bootstrap.php
Kohana::modules([
	'auth'       => MODPATH.'auth',
	'database'   => MODPATH.'database',
	'migration'  => MODPATH.'migration',
	'minion'     => MODPATH.'minion',
	'orm'        => MODPATH.'orm',
	'pagination' => MODPATH.'pagination',
]);
```

A database connection in `application/config/database.php` (see [Database](../../database/config)), and the users of the auth module stored in it:

```php
// application/config/auth.php
return [
	'driver' => 'Bcrypt',
];
```

## 1. The tables

The authors are the users of the [auth module](../../auth); its tables come as a migration. Then make an empty migration for the articles:

```bash
./minion auth:migration
```

```bash
./minion migration:create --name=create_articles
```

Fill in the second file:

```php
// application/migrations/20261112_033900_create_articles.php
return new class extends Migration {

	/**
	 * Makes the tables of the articles, the tags, and which article has which tag.
	 */
	public function up(Database $db): void
	{
		$db->query(null, 'CREATE TABLE articles ('
			.'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, '
			.'user_id INT UNSIGNED NOT NULL, '
			.'title VARCHAR(200) NOT NULL, '
			.'body TEXT NOT NULL, '
			.'published INT NOT NULL DEFAULT 0, '
			.'created INT NOT NULL, '
			.'FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE)');

		$db->query(null, 'CREATE TABLE tags ('
			.'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, '
			.'name VARCHAR(50) NOT NULL UNIQUE)');

		// Deleting an article or a tag deletes its pairs
		$db->query(null, 'CREATE TABLE articles_tags ('
			.'article_id INT UNSIGNED NOT NULL, '
			.'tag_id INT UNSIGNED NOT NULL, '
			.'PRIMARY KEY (article_id, tag_id), '
			.'FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE, '
			.'FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE)');

		$db->query(null, 'CREATE INDEX articles_created ON articles (created)');
	}

	/**
	 * Removes the tables, with all articles in them.
	 */
	public function down(Database $db): void
	{
		foreach (['articles_tags', 'tags', 'articles'] as $table)
		{
			$db->query(null, 'DROP TABLE '.$db->quote_table($table));
		}
	}

};
```

```bash
./minion migration:run
```

```text
applied  20261112_033850_create_auth_tables
applied  20261112_033900_create_articles
✔ 2 migrations applied.
```

- `articles.user_id` points at the user who wrote the article.
- `articles_tags` holds one row for each pair of an article and a tag.
- The foreign keys let the database clean up: deleting an article deletes its pairs.

[!!] The SQL is that of MySQL and MariaDB. On SQLite the id column is `id INTEGER PRIMARY KEY AUTOINCREMENT` and the other numbers are `INTEGER`; on PostgreSQL it is `id SERIAL PRIMARY KEY` and `INTEGER`. See [Writing Migrations](../../migration/writing).

## 2. The models

A model is named after its table: `Model_Tag` reads the table `tags`, `Model_Article` the table `articles`. Nothing else has to be said for a model to work; what follows is what makes it yours.

### The tag

```php
// application/classes/Model/Tag.php
/**
 * A word that articles are found by.
 */
class Model_Tag extends ORM {

	protected array $_has_many = [
		'articles' => ['model' => 'Article', 'through' => 'articles_tags'],
	];

	protected array $_sorting = ['name' => 'ASC'];

	public function rules(): array
	{
		return [
			'name' => [
				['not_empty'],
				['max_length', [':value', 50]],
			],
		];
	}

	/**
	 * Turns what was typed ("PHP, kohana , php") into clean names
	 * (['php', 'kohana']): lower case, no empty or double ones.
	 *
	 * @return  string[]
	 */
	public static function names(string $text): array
	{
		$names = [];

		foreach (explode(',', $text) as $name)
		{
			$name = mb_substr(mb_strtolower(trim($name)), 0, 50);

			if ($name !== '')
			{
				$names[$name] = $name;
			}
		}

		return array_values($names);
	}

}
```

- `$_has_many` with `through` is a many-to-many relationship: see [Relationships](../../orm/relationships).
- `names()` is plain PHP and needs no database: logic like this belongs in the model, where every controller and task finds it, and where it is easy to test.

### The article

```php
// application/classes/Model/Article.php
/**
 * An article: written by a user, found by its tags.
 */
class Model_Article extends ORM {

	// articles.user_id points at the user who wrote it
	protected array $_belongs_to = [
		'author' => ['model' => 'User', 'foreign_key' => 'user_id'],
	];

	// The table articles_tags holds the pairs
	protected array $_has_many = [
		'tags' => ['model' => 'Tag', 'through' => 'articles_tags'],
	];

	// Filled with the time when the row is created
	protected ?array $_created_column = ['column' => 'created', 'format' => true];

	// The newest first, unless a query says otherwise
	protected array $_sorting = ['created' => 'DESC', 'id' => 'DESC'];

	public function rules(): array
	{
		return [
			'title' => [
				['not_empty'],
				['max_length', [':value', 200]],
			],
			'body' => [
				['not_empty'],
			],
			'user_id' => [
				['not_empty'],
				[[$this, 'author_exists'], [':value']],
			],
		];
	}

	public function filters(): array
	{
		return [
			'title' => [
				['trim'],
			],
		];
	}

	public function labels(): array
	{
		return [
			'title'   => 'Title',
			'body'    => 'Text',
			'user_id' => 'Author',
		];
	}

	/**
	 * A rule: is there a user with this id?
	 */
	public function author_exists(mixed $id): bool
	{
		return ORM::factory('User', $id)->loaded();
	}

	/**
	 * Returns the tags as they are typed in the form: "kohana, php".
	 */
	public function tag_names(): string
	{
		return implode(', ', $this->tags->find_all()->as_array(null, 'name'));
	}

	/**
	 * Gives the article exactly the tags that were typed. A tag that does
	 * not exist yet is created. The article must be saved first.
	 */
	public function tag(string $text): static
	{
		$this->remove('tags');

		foreach (Model_Tag::names($text) as $name)
		{
			$tag = ORM::factory('Tag', ['name' => $name]);

			if ( ! $tag->loaded())
			{
				$tag->name = $name;
				$tag->save();
			}

			$this->add('tags', $tag);
		}

		return $this;
	}

}
```

- **`rules()`** run at every `save()`: no controller can store an article without a title. `author_exists` is a method of the model used as a rule.
- **`filters()`** change a value when it is set: the title loses the spaces around it before the rules see it.
- **`labels()`** are the names of the columns in error texts.
- **`tag()`** and **`tag_names()`** hide how tags are stored. The controller only knows the text of the form.

More about each: [Models](../../orm/models), [Validation](../../orm/validation).

### The error texts

A rule without a text of its own gets a general one ("Text must not be empty"). Your own texts go in a messages file named after the model:

```php
// application/messages/models/article.php
return [
	'title' => [
		'not_empty' => 'Give the article a title.',
	],
	'user_id' => [
		'author_exists' => 'Choose one of the authors.',
	],
];
```

## 3. Data to try it with

A [seed](../../migration/seeds) adds a user and 25 articles, enough for three pages:

```php
// application/seeds/articles.php
return function (Database $db): void
{
	list($user) = DB::insert('users', ['username', 'email', 'password'])
		->values(['ana', 'ana@example.com', Auth::instance()->hash('correct horse battery')])
		->execute($db);

	for ($i = 1; $i <= 25; $i++)
	{
		DB::insert('articles', ['user_id', 'title', 'body', 'published', 'created'])
			->values([$user, 'Article number '.$i, 'The text of article number '.$i.'.', 1, time() - $i * 3600])
			->execute($db);
	}
};
```

```bash
./minion migration:seed
```

## 4. The controller

```php
// application/classes/Controller/Article.php
/**
 * Lists, shows, creates, changes and deletes articles.
 */
class Controller_Article extends Controller {

	/**
	 * Before every action: a request that changes something must carry
	 * the token of the form.
	 */
	public function before(): void
	{
		parent::before();

		if ($this->request->method() === HTTP_Request::POST AND ! Security::check($this->request->post('csrf')))
			throw HTTP_Exception::factory(403, 'The form has expired. Go back and try again.');
	}

	/**
	 * The list, ten on a page: /article
	 */
	public function action_index(): void
	{
		$pagination = Pagination::factory([
			'total_items'    => ORM::factory('Article')->count_all(),
			'items_per_page' => 10,
		]);

		$articles = ORM::factory('Article')
			->with('author')
			->limit($pagination->items_per_page)
			->offset($pagination->offset)
			->find_all();

		$this->response->body(View::factory('article/list', [
			'articles'   => $articles,
			'pagination' => $pagination,
		]));
	}

	/**
	 * One article: /article/view/3
	 */
	public function action_view(): void
	{
		$this->response->body(View::factory('article/view', ['article' => $this->_article()]));
	}

	/**
	 * The empty form, and saving it: /article/create
	 */
	public function action_create(): void
	{
		$this->_form(ORM::factory('Article'));
	}

	/**
	 * The filled form, and saving it: /article/edit/3
	 */
	public function action_edit(): void
	{
		$this->_form($this->_article());
	}

	/**
	 * Deletes an article. Only as POST, from the button of the page.
	 */
	public function action_delete(): void
	{
		if ($this->request->method() !== HTTP_Request::POST)
			throw HTTP_Exception::factory(405, 'Articles are deleted with the button')->allowed('POST');

		$this->_article()->delete();

		$this->redirect('article');
	}

	/**
	 * Returns the article of the address, or answers "404 Not Found".
	 */
	protected function _article(): Model_Article
	{
		$article = ORM::factory('Article', (int) $this->request->param('id'));

		if ( ! $article->loaded())
			throw HTTP_Exception::factory(404, 'Article not found');

		return $article;
	}

	/**
	 * Shows the form of an article. When it was sent, saves the article
	 * and goes to its page, or shows the form again with what is wrong.
	 */
	protected function _form(Model_Article $article): void
	{
		$post   = $this->request->post();
		$tags   = (string) Arr::get($post, 'tags', $article->tag_names());
		$errors = [];

		if ($this->request->method() === HTTP_Request::POST)
		{
			try
			{
				// Only these columns are taken from the form
				$article->values($post, ['title', 'body', 'user_id']);

				// A box that is not ticked is not sent at all
				$article->published = (int) isset($post['published']);

				$article->save()->tag($tags);

				$this->redirect('article/view/'.$article->id);
			}
			catch (ORM_Validation_Exception $e)
			{
				$errors = $e->errors('models');
			}
		}

		$this->response->body(View::factory('article/form', [
			'article' => $article,
			'tags'    => $tags,
			'errors'  => $errors,
			'authors' => ORM::factory('User')->order_by('username')->find_all()->as_array('id', 'username'),
		]));
	}

}
```

What to notice:

- **`before()`** checks the [CSRF token](../security/csrf) of every `POST`, so no action can forget it.
- **`_article()`** is the one place that turns an address into an article. `(int)` makes `/article/view/abc` a plain "not found".
- **`_form()`** serves both creating and changing: the only difference is whether the model was loaded.
- **`values($post, [...])`** takes the listed columns only. Without the list, a visitor could add `created` or `id` to the form and set them.
- **Deleting is a `POST`.** A link (`GET`) that deletes would be followed by search engines and browser previews.
- **`with('author')`** loads the authors together with the articles: one query, not eleven.
- After saving, the browser is **redirected**: reloading the page does not send the form again.

[!!] `redirect()` builds a full address, and refuses a host name it was not told about. List yours in `application/config/url.php`: see [Installation](../install).

In a real application the author is not chosen in the form: it is the user who is logged in, `Auth::instance()->get_user()`.

## 5. The views

```php
<!-- application/views/article/list.php -->
<h1>Articles</h1>

<p><?php echo HTML::anchor('article/create', 'Write an article') ?></p>

<ul>
<?php foreach ($articles as $article): ?>
	<li>
		<?php echo HTML::anchor('article/view/'.$article->id, HTML::chars($article->title)) ?>
		by <?php echo HTML::chars($article->author->username) ?>,
		<?php echo date('Y-m-d', $article->created) ?>
	</li>
<?php endforeach ?>
</ul>

<?php echo $pagination ?>
```

```php
<!-- application/views/article/view.php -->
<h1><?php echo HTML::chars($article->title) ?></h1>

<p>
	By <?php echo HTML::chars($article->author->username) ?>,
	<?php echo date('Y-m-d H:i', $article->created) ?>.
	<?php echo $article->published ? 'Published.' : 'Not published yet.' ?>
</p>

<p><?php echo nl2br(HTML::chars($article->body)) ?></p>

<p>Tags: <?php echo HTML::chars($article->tag_names()) ?></p>

<p>
	<?php echo HTML::anchor('article', 'All articles') ?> |
	<?php echo HTML::anchor('article/edit/'.$article->id, 'Change') ?>
</p>

<?php echo Form::open('article/delete/'.$article->id) ?>
	<?php echo Form::csrf() ?>
	<?php echo Form::submit('delete', 'Delete') ?>
<?php echo Form::close() ?>
```

```php
<!-- application/views/article/form.php -->
<h1><?php echo $article->loaded() ? 'Change the article' : 'Write an article' ?></h1>

<?php echo Form::open() ?>
	<?php echo Form::csrf() ?>

	<p>
		<?php echo Form::label('title', 'Title') ?>
		<?php echo Form::input('title', $article->title, ['id' => 'title']) ?>
		<?php if (isset($errors['title'])): ?>
			<strong class="error"><?php echo HTML::chars($errors['title']) ?></strong>
		<?php endif ?>
	</p>

	<p>
		<?php echo Form::label('body', 'Text') ?>
		<?php echo Form::textarea('body', $article->body, ['id' => 'body']) ?>
		<?php if (isset($errors['body'])): ?>
			<strong class="error"><?php echo HTML::chars($errors['body']) ?></strong>
		<?php endif ?>
	</p>

	<p>
		<?php echo Form::label('user_id', 'Author') ?>
		<?php echo Form::select('user_id', $authors, $article->user_id, ['id' => 'user_id']) ?>
		<?php if (isset($errors['user_id'])): ?>
			<strong class="error"><?php echo HTML::chars($errors['user_id']) ?></strong>
		<?php endif ?>
	</p>

	<p>
		<?php echo Form::label('tags', 'Tags, with commas between them') ?>
		<?php echo Form::input('tags', $tags, ['id' => 'tags']) ?>
	</p>

	<p>
		<?php echo Form::checkbox('published', 1, (bool) $article->published, ['id' => 'published']) ?>
		<?php echo Form::label('published', 'Published') ?>
	</p>

	<?php echo Form::submit('save', 'Save') ?>
<?php echo Form::close() ?>
```

- **Everything that was typed by somebody is printed with `HTML::chars()`**: titles, texts, tags, user names, error texts. `Form::input()` and the other form helpers do it themselves for their values. See [XSS](../security/xss).
- The form shows the model: after a failed save, the model holds what was typed, so nothing is lost.
- `Form::open()` without an address posts to the address of the page: the same view serves `/article/create` and `/article/edit/3`.

These views are fragments, without `<html>` and a layout. For a layout around every page see [Template Controller](controller-template).

Open `/article` (or `/index.php/article` without [clean URLs](clean-urls)).

## 6. A test

The test uses the database of the application. Each test starts a transaction and undoes it at the end, so no row of a test stays.

```php
// application/tests/ArticleTest.php
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the article model on the database of the application. Every
 * test runs in a transaction that is undone: no row stays.
 */
#[Group('application')]
class ArticleTest extends Unittest_TestCase
{
	public function setUp(): void
	{
		parent::setUp();

		Database::instance()->begin();
	}

	public function tearDown(): void
	{
		Database::instance()->rollback();

		parent::tearDown();
	}

	/**
	 * Returns the id of a user to write articles.
	 */
	protected function user(): int
	{
		list($id) = DB::insert('users', ['username', 'email', 'password'])
			->values(['test-writer', 'writer@example.com', 'not a real hash'])
			->execute();

		return (int) $id;
	}

	public function test_names_are_cleaned(): void
	{
		$this->assertSame(['php', 'kohana'], Model_Tag::names(' PHP, kohana ,, php'));
	}

	public function test_an_article_needs_a_title_and_an_author(): void
	{
		try
		{
			ORM::factory('Article')->values(['title' => '  ', 'body' => 'Text', 'user_id' => 0])->save();

			$this->fail('An article without a title was saved');
		}
		catch (ORM_Validation_Exception $e)
		{
			$errors = $e->errors('models');
		}

		$this->assertSame('Give the article a title.', $errors['title']);
		$this->assertArrayHasKey('user_id', $errors);
		$this->assertArrayNotHasKey('body', $errors);
	}

	public function test_tags_are_replaced(): void
	{
		$article = ORM::factory('Article')
			->values(['title' => 'First', 'body' => 'Text', 'user_id' => $this->user()])
			->save();

		$article->tag('php, kohana');
		$this->assertSame('kohana, php', $article->tag_names());

		$article->tag('php, orm');
		$this->assertSame('orm, php', $article->tag_names());

		// "php" was made once and is used again
		$this->assertSame(1, ORM::factory('Tag')->where('name', '=', 'php')->count_all());
	}
}
```

Tell PHPUnit about the directory, once:

```xml
<!-- phpunit.xml, inside <testsuites> -->
<testsuite name="application">
	<directory>application/tests</directory>
</testsuite>
```

```bash
vendor/bin/phpunit --testsuite application
```

```text
OK (3 tests, 7 assertions)
```

[!!] The tables must be able to undo a transaction: InnoDB on MySQL and MariaDB (the default), any table on PostgreSQL and SQLite. More in the guide of the [Unittest module](../../unittest).

## Where to go from here

- **Only the author may change an article**: compare `$article->user_id` with the logged in user in `_article()`, and answer 403. See [Auth](../../auth).
- **A page per tag**: `ORM::factory('Tag', ['name' => $name])->articles->find_all()`.
- **The same data as JSON**, for an app or JavaScript: [REST and JSON](../rest).
- **Slugs in the address** (`/article/my-first-article`): the Slug [behavior](../../orm/behaviors) and a [route](../routing).
