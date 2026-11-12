<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ORM against a real MySQL/MariaDB database: loading, saving,
 * relations, validation, uniqueness checks and serialization.
 *
 * Runs when the environment variable KOHANA_TEST_MYSQL_HOST is set (see
 * modules/database/tests/DatabaseTest.php for the other settings).
 *
 * ORMSqliteTest and ORMPdoMysqlTest run the same tests through the PDO driver.
 *
 * @package    Kohana/ORM
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.orm')]
class Kohana_ORMDatabaseTest extends Unittest_TestCase
{
	/**
	 * Returns the settings of the test database, or null when it is not
	 * available. Other test classes extend this one to run the same tests
	 * on another driver.
	 */
	protected function config(): ?array
	{
		if ( ! getenv('KOHANA_TEST_MYSQL_HOST'))
			return null;

		return [
			'type'       => 'MySQLi',
			'connection' => [
				'hostname' => getenv('KOHANA_TEST_MYSQL_HOST'),
				'port'     => getenv('KOHANA_TEST_MYSQL_PORT') ?: 3306,
				'username' => getenv('KOHANA_TEST_MYSQL_USER') ?: 'root',
				'password' => (string) getenv('KOHANA_TEST_MYSQL_PASSWORD'),
				'database' => getenv('KOHANA_TEST_MYSQL_DATABASE') ?: 'kohana_test',
			],
			'charset'      => 'utf8mb4',
			'table_prefix' => '',
		];
	}

	/**
	 * Does the database see "ANA" and "ana" as equal?
	 */
	protected function ignores_case(): bool
	{
		return true;
	}

	/**
	 * Returns the SQL of a primary key column that numbers itself.
	 */
	protected function id_column(): string
	{
		return 'id INT AUTO_INCREMENT PRIMARY KEY';
	}

	/**
	 * Connects to the test database and creates empty tables.
	 */
	public function setUp(): void
	{
		parent::setUp();

		$config = $this->config();

		if ($config === null)
		{
			$this->markTestSkipped('The database for '.static::class.' is not configured or its extension is missing.');
		}

		// A fresh connection for every test: the test classes share the name
		if (isset(Database::$instances['test_orm']))
		{
			Database::$instances['test_orm']->disconnect();
			unset(Database::$instances['test_orm']);
		}

		// Forget what ORM remembers of the tables of another driver
		(new ReflectionProperty('ORM', '_column_cache'))->setValue(null, []);
		(new ReflectionProperty('ORM', '_init_cache'))->setValue(null, []);

		$db = Database::instance('test_orm', $config);
		$id = $this->id_column();

		foreach (['kohana_orm_articles', 'kohana_orm_profiles', 'kohana_orm_books_tags', 'kohana_orm_books', 'kohana_orm_tags', 'kohana_orm_authors'] as $table)
		{
			$db->query(null, 'DROP TABLE IF EXISTS '.$table);
		}

		$db->query(null, 'CREATE TABLE kohana_orm_authors ('.$id.', name VARCHAR(100) NOT NULL, email VARCHAR(100) NULL, options TEXT NULL)');
		$db->query(null, 'CREATE TABLE kohana_orm_books ('.$id.', author_id INT NOT NULL, title VARCHAR(100) NOT NULL, created INT NULL)');
		$db->query(null, 'CREATE TABLE kohana_orm_tags ('.$id.', name VARCHAR(100) NOT NULL)');
		$db->query(null, 'CREATE TABLE kohana_orm_books_tags (book_id INT NOT NULL, tag_id INT NOT NULL)');
		$db->query(null, 'CREATE TABLE kohana_orm_articles ('.$id.', title VARCHAR(100) NULL, slug VARCHAR(100) NULL, guid VARCHAR(36) NULL)');
		$db->query(null, 'CREATE TABLE kohana_orm_profiles ('.$id.', author_id INT NOT NULL, bio VARCHAR(100) NULL, created INT NULL, updated INT NULL)');
	}

	/**
	 * Creates and returns a saved author.
	 */
	protected function author(string $name = 'Ana', ?string $email = 'ana@example.com'): ORM
	{
		return ORM::factory('Kohana_ORM_Author')->values(['name' => $name, 'email' => $email])->create();
	}

	public function test_create_find_update_delete(): void
	{
		$author = $this->author();

		$this->assertTrue($author->loaded());
		$this->assertTrue($author->saved());
		$this->assertEquals(1, $author->pk());

		// Find by primary key and by column
		$this->assertSame('Ana', ORM::factory('Kohana_ORM_Author', 1)->name);
		$this->assertSame('Ana', ORM::factory('Kohana_ORM_Author', ['email' => 'ana@example.com'])->name);
		$this->assertFalse(ORM::factory('Kohana_ORM_Author', 99)->loaded());

		// Update only what changed
		$author->name = 'Ana Maria';
		$this->assertSame(['name' => 'name'], $author->changed());
		$this->assertTrue($author->has_changed('name'));
		$this->assertFalse($author->has_changed('email'));
		$author->save();
		$this->assertSame('Ana Maria', ORM::factory('Kohana_ORM_Author', 1)->name);

		$this->assertSame(1, ORM::factory('Kohana_ORM_Author')->count_all());

		$author->delete();
		$this->assertFalse($author->loaded());
		$this->assertSame(0, ORM::factory('Kohana_ORM_Author')->count_all());
	}

	public function test_find_all_returns_models(): void
	{
		$this->author('Ana', 'ana@example.com');
		$this->author('Bruno', 'bruno@example.com');

		$authors = ORM::factory('Kohana_ORM_Author')->order_by('name', 'desc')->find_all();

		$this->assertSame(2, count($authors));
		$this->assertInstanceOf('Model_Kohana_ORM_Author', $authors[0]);
		$this->assertSame('Bruno', $authors[0]->name);
		$this->assertSame(['Bruno', 'Ana'], $authors->as_array(null, 'name'));

		// Results can be looped more than once
		$names = [];
		foreach ($authors as $author) $names[] = $author->name;
		foreach ($authors as $author) $names[] = $author->name;
		$this->assertSame(['Bruno', 'Ana', 'Bruno', 'Ana'], $names);
	}

	public function test_validation_stops_invalid_data(): void
	{
		try
		{
			ORM::factory('Kohana_ORM_Author')->values(['name' => '', 'email' => 'not-an-email'])->create();
			$this->fail('An invalid author was saved');
		}
		catch (ORM_Validation_Exception $e)
		{
			$this->assertSame(['name', 'email'], array_keys($e->errors()));
		}

		$this->assertSame(0, ORM::factory('Kohana_ORM_Author')->count_all());
	}

	/**
	 * values() only assigns the listed columns when a list is given, which
	 * is how form input must be handled.
	 */
	public function test_values_respects_expected_columns(): void
	{
		$author = ORM::factory('Kohana_ORM_Author')
			->values(['name' => 'Ana', 'email' => 'ana@example.com', 'id' => 500], ['name', 'email'])
			->create();

		$this->assertEquals(1, $author->pk());
	}

	/**
	 * values() hands the values of a relation to the related model, and
	 * only then: a column, or a value that is not a list, is left alone.
	 */
	public function test_values_reach_related_models_only(): void
	{
		$author = $this->author();
		$book   = ORM::factory('Kohana_ORM_Book')->values(['title' => 'First', 'author_id' => $author->pk()])->create();

		$book->values(
			['title' => 'Second', 'author' => ['name' => 'Bea', 'email' => 'bea@example.com']],
			['title', 'author' => ['name']]
		);

		$this->assertSame('Second', $book->title);
		$this->assertSame('Bea', $book->author->name);
		$this->assertSame('ana@example.com', $book->author->email, 'Only the expected columns of the related model are set');

		// "title" is a column, not a relation
		$book->values(['title' => ['name' => 'Cleo']], ['title' => ['name']]);
		$this->assertSame('Second', $book->title);

		// The values of a relation must be a list
		$book->values(['author' => 'Cleo'], ['author' => ['name']]);
		$this->assertSame('Bea', $book->author->name);
	}

	/**
	 * The methods that add to the query of a model, used together.
	 */
	public function test_query_methods(): void
	{
		foreach (['Ana' => 'ana@example.com', 'Bea' => 'bea@example.com', 'Cleo' => null, 'Dora' => null] as $name => $email)
		{
			$this->author($name, $email);
		}

		$names = fn (ORM $query) => $query->find_all()->as_array(null, 'name');
		$all   = fn () => ORM::factory('Kohana_ORM_Author')->order_by('name');

		$this->assertSame(['Ana', 'Cleo'], $names($all()->where('name', '=', 'Ana')->or_where('name', '=', 'Cleo')));
		$this->assertSame(['Cleo', 'Dora'], $names($all()->where('email', 'IS', null)->and_where('name', '!=', 'Ana')));

		// name = Ana OR (name = Bea AND email is there) OR (email missing AND (name = Cleo OR name = Zoe))
		$this->assertSame(['Ana', 'Bea', 'Cleo'], $names($all()
			->where('name', '=', 'Ana')
			->or_where_open()
				->where('name', '=', 'Bea')
				->and_where('email', 'IS NOT', null)
			->or_where_close()
			->or_where_open()
				->where('email', 'IS', null)
				->and_where_open()
					->where('name', '=', 'Cleo')
					->or_where('name', '=', 'Zoe')
				->and_where_close()
			->or_where_close()));

		$this->assertSame(['Bea'], $names($all()->where_open()->where('name', '=', 'Bea')->where_close()));

		// Parts of the list
		$this->assertSame(['Bea', 'Cleo'], $names($all()->limit(2)->offset(1)));
		$this->assertSame(['Dora'], $names(ORM::factory('Kohana_ORM_Author')->order_by('name', 'DESC')->limit(1)));
		$this->assertSame(2, $all()->where('email', 'IS', null)->count_all());

		// Counting and then listing with the same query, as a paged list does
		$query = $all()->where('email', 'IS', null);

		$this->assertSame(2, $query->reset(false)->count_all());
		$this->assertSame(['Cleo', 'Dora'], $names($query));

		// A value given later
		$this->assertSame(['Bea'], $names($all()->where('name', '=', DB::expr(':name'))->param(':name', 'Bea')));

		// Asked twice within the time given, the second answer comes from the cache
		$this->assertSame(['Ana'], $names($all()->where('name', '=', 'Ana')->cached(60)));
		$this->assertSame(['Ana'], $names($all()->where('name', '=', 'Ana')->cached(60)));
	}

	public function test_query_methods_for_groups_and_joins(): void
	{
		$ana = $this->author();
		$bea = $this->author('Bea', 'bea@example.com');
		$this->author('Cleo', null);

		foreach ([[$ana, 'One'], [$ana, 'Two'], [$bea, 'Three']] as list($author, $title))
		{
			ORM::factory('Kohana_ORM_Book')->values(['title' => $title, 'author_id' => $author->pk()])->create();
		}

		$authors = fn () => ORM::factory('Kohana_ORM_Author')
			->join(['kohana_orm_books', 'b'], 'LEFT')->on('b.author_id', '=', 'kohana_orm_author.id')
			->group_by('kohana_orm_author.id', 'kohana_orm_author.name', 'kohana_orm_author.email', 'kohana_orm_author.options')
			->order_by('kohana_orm_author.name');

		$names = fn (ORM $query) => $query->find_all()->as_array(null, 'name');

		$this->assertSame(['Ana', 'Bea', 'Cleo'], $names($authors()));
		$this->assertSame(['Ana'], $names($authors()->having(DB::expr('COUNT(b.id)'), '>', 1)));
		$this->assertSame(['Ana', 'Cleo'], $names($authors()
			->having(DB::expr('COUNT(b.id)'), '>', 1)
			->or_having(DB::expr('COUNT(b.id)'), '=', 0)));
		$this->assertSame(['Bea'], $names($authors()
			->having_open()
				->having(DB::expr('COUNT(b.id)'), '>', 0)
				->and_having(DB::expr('COUNT(b.id)'), '<', 2)
			->having_close()));
		$this->assertSame(['Ana', 'Cleo'], $names($authors()
			->having(DB::expr('COUNT(b.id)'), '=', 2)
			->or_having_open()
				->having(DB::expr('COUNT(b.id)'), '=', 0)
				->and_having_open()
					->having(DB::expr('COUNT(b.id)'), '<', 5)
				->and_having_close()
			->or_having_close()));

		// Every author once, however many books
		$this->assertSame(['Ana', 'Bea'], $names(ORM::factory('Kohana_ORM_Author')
			->distinct(true)
			->join(['kohana_orm_books', 'b'])->on('b.author_id', '=', 'kohana_orm_author.id')
			->order_by('kohana_orm_author.name')));

		// A join on a column with the same name in both tables
		$this->assertSame(['One', 'Two', 'Three'], ORM::factory('Kohana_ORM_Book')
			->join(['kohana_orm_books', 'same'])->using('id')
			->order_by('kohana_orm_book.id')
			->find_all()->as_array(null, 'title'));
	}

	/**
	 * A has-one relation: the related model is the one that points here.
	 */
	public function test_has_one(): void
	{
		$author = $this->author();

		$this->assertFalse($author->profile->loaded());

		ORM::factory('Kohana_ORM_Profile')->values(['author_id' => $author->pk(), 'bio' => 'Writes'])->create();

		$author = ORM::factory('Kohana_ORM_Author', $author->pk());

		$this->assertTrue($author->profile->loaded());
		$this->assertSame('writes (bio)', $author->profile->bio);
		$this->assertTrue(isset($author->profile));
		$this->assertSame(['profile'], array_keys($author->has_one()));

		// Loaded in the same query as the author
		$with = ORM::factory('Kohana_ORM_Author')->with('profile')->where('kohana_orm_author.id', '=', $author->pk())->find();

		$this->assertSame('writes (bio)', $with->profile->bio);
		$this->assertSame('writes (bio)', $with->as_array()['profile']['bio']);
		$this->assertSame('writes (bio)', $with->as_object()->profile->bio);

		// A relation that does not exist is passed over
		$this->assertTrue(ORM::factory('Kohana_ORM_Author')->with('nothing')->with('profile')->with('profile')->find()->loaded());
	}

	/**
	 * A model that names relations in $_load_with loads them with
	 * itself, and $_sorting gives the order when none is asked.
	 */
	public function test_load_with_and_default_sorting(): void
	{
		$ana = $this->author();
		$bea = $this->author('Bea', 'bea@example.com');

		ORM::factory('Kohana_ORM_Profile')->values(['author_id' => $ana->pk(), 'bio' => 'alpha'])->create();
		ORM::factory('Kohana_ORM_Profile')->values(['author_id' => $bea->pk(), 'bio' => 'beta'])->create();

		$profiles = ORM::factory('Kohana_ORM_Profile')->find_all()->as_array();

		// Sorted by text, the last first
		$this->assertSame(['beta (bio)', 'alpha (bio)'], array_map(fn ($profile) => $profile->bio, $profiles));
		$this->assertSame('Bea', $profiles[0]->author->name);
		$this->assertSame(['author'], $profiles[0]->load_with());
		$this->assertSame(2, ORM::factory('Kohana_ORM_Profile')->count_all());

		$one = ORM::factory('Kohana_ORM_Profile')->where('kohana_orm_profile.author_id', '=', $ana->pk())->find();

		$this->assertSame('Ana', $one->author->name);
	}

	/**
	 * Filters clean a value when it is set: those for every column
	 * first, then those of the column, as functions, static methods and
	 * methods of the model.
	 */
	public function test_filters(): void
	{
		$profile = ORM::factory('Kohana_ORM_Profile');
		$profile->bio = '  A VERY Long Text About Somebody  ';

		$this->assertSame('a very long  (bio)', $profile->bio);

		// A value that is not there is cleaned as an empty text
		$profile->bio = null;

		$this->assertSame(' (bio)', $profile->bio);
	}

	public function test_created_and_updated_columns(): void
	{
		$author  = $this->author();
		$profile = ORM::factory('Kohana_ORM_Profile')->values(['author_id' => $author->pk(), 'bio' => 'one'])->create();

		$this->assertEqualsWithDelta(time(), $profile->created, 5);
		$this->assertNull($profile->updated);
		$this->assertSame('created', $profile->created_column()['column']);
		$this->assertSame('updated', $profile->updated_column()['column']);

		// Nothing changed: nothing is written
		$profile->update();
		$this->assertNull(ORM::factory('Kohana_ORM_Profile', $profile->pk())->updated);

		$profile->bio = 'two';
		$profile->update();

		$this->assertEqualsWithDelta(time(), (int) ORM::factory('Kohana_ORM_Profile', $profile->pk())->updated, 5);
	}

	/**
	 * Assigning a model to a belongs-to relation sets the foreign key.
	 */
	public function test_assigning_a_related_model(): void
	{
		$ana  = $this->author();
		$bea  = $this->author('Bea', 'bea@example.com');
		$book = ORM::factory('Kohana_ORM_Book')->values(['title' => 'First', 'author_id' => $ana->pk()])->create();

		$book->author = $bea;
		$book->save();

		$stored = ORM::factory('Kohana_ORM_Book', $book->pk());

		$this->assertEquals($bea->pk(), $stored->author_id);
		$this->assertSame('Bea', $stored->author->name);
		$this->assertSame(['author'], array_keys($stored->belongs_to()));
	}

	/**
	 * A has-many relation marked with "update" takes a list of ids: what
	 * is new is added, what is missing is removed.
	 */
	public function test_assigning_a_list_of_ids(): void
	{
		$book = ORM::factory('Kohana_ORM_Book')->values(['title' => 'First', 'author_id' => $this->author()->pk()])->create();
		$ids  = [];

		foreach (['php', 'sql', 'css'] as $name)
		{
			$ids[$name] = ORM::factory('Kohana_ORM_Tag')->values(['name' => $name])->create()->pk();
		}

		$tags = fn () => ORM::factory('Kohana_ORM_Book', $book->pk())->tags->order_by('name')->find_all()->as_array(null, 'name');

		$book->tag_ids = [$ids['php'], $ids['sql']];
		$this->assertSame(['php', 'sql'], $tags());

		$book->tag_ids = [$ids['sql'], $ids['css']];
		$this->assertSame(['css', 'sql'], $tags());

		$book->tag_ids = [];
		$this->assertSame([], $tags());

		// Without "update" a to-many relation cannot be assigned
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('is a to many relation');

		$book->tags = [$ids['php']];
	}

	public function test_properties_that_do_not_exist(): void
	{
		$author = $this->author();

		$this->assertFalse(isset($author->nothing));
		$this->assertTrue(isset($author->name));
		$this->assertTrue(isset($author->books));

		unset($author->email);
		$this->assertFalse(isset($author->email));

		try
		{
			$author->nothing;
			$this->fail('Reading a property that does not exist must fail');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('The nothing property does not exist', $e->getMessage());
		}

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('property does not exist');

		$author->nothing = 1;
	}

	/**
	 * A model says when it is used in the wrong state.
	 */
	public function test_wrong_state_is_reported(): void
	{
		$author = $this->author();
		$new    = ORM::factory('Kohana_ORM_Author');

		$wrong = [
			'already loaded'  => fn () => $author->create(),
			'is not loaded'   => fn () => $new->update(),
			'not loaded'      => fn () => $new->delete(),
			'find() cannot'   => fn () => $author->find(),
			'find_all() cannot' => fn () => $author->find_all(),
		];

		foreach ($wrong as $message => $call)
		{
			try
			{
				$call();
				$this->fail('Expected an error with "'.$message.'"');
			}
			catch (Kohana_Exception $e)
			{
				$this->assertStringContainsString($message, $e->getMessage());
			}
		}
	}

	public function test_what_a_model_tells_about_itself(): void
	{
		$author = $this->author();

		$this->assertSame((string) $author->pk(), (string) $author);
		$this->assertSame('', (string) ORM::factory('Kohana_ORM_Author'));
		$this->assertSame('id', $author->primary_key());
		$this->assertSame('kohana_orm_authors', $author->object_plural());
		$this->assertSame(['books'], array_keys($author->has_many()));
		$this->assertSame('Ana', $author->object()['name']);
		$this->assertSame('Ana', $author->original_values()['name']);
		$this->assertStringContainsString('kohana_orm_authors', (string) $author->last_query());
		$this->assertFalse($author->table_column_type('nothing'));
		$this->assertInstanceOf(Validation::class, $author->validation());

		// Loading again brings back what the table holds
		$author->name = 'Changed';
		$this->assertSame('Ana', $author->reload()->name);

		// A new model that is loaded again is an empty one
		$this->assertFalse(ORM::factory('Kohana_ORM_Author')->reload()->loaded());

		// The older way of keeping a model as text
		$copy = ORM::factory('Kohana_ORM_Author');
		$copy->unserialize($author->serialize());

		$this->assertSame('Ana', $copy->name);
		$this->assertTrue($copy->loaded());
	}

	/**
	 * The Slug behavior makes a text for addresses from the title, and
	 * numbers it when another record has the same one.
	 */
	public function test_slug_behavior(): void
	{
		$make = fn (?string $title) => ORM::factory('Kohana_ORM_Article')->values(['title' => $title])->create();

		$first = $make('Hello World');

		$this->assertSame('hello-world', $first->slug);
		$this->assertSame('hello-world-0', $make('Hello World')->slug);
		$this->assertSame('hello-world-1', $make('Hello, World!')->slug);
		$this->assertSame('ola-mundo-acao', $make('Olá Mundo: Ação')->slug);
		$this->assertSame('kohana-orm-article', $make(null)->slug, 'Without a title the name of the model is used');

		// Saved again, a record keeps its slug; a new title gives a new one
		$first->save();
		$this->assertSame('hello-world', ORM::factory('Kohana_ORM_Article', $first->pk())->slug);

		$first->title = 'Good Bye';
		$first->save();
		$this->assertSame('good-bye', ORM::factory('Kohana_ORM_Article', $first->pk())->slug);

		// A record is found by its slug, or by its number
		$found = ORM::factory('Kohana_ORM_Article', 'good-bye');

		$this->assertTrue($found->loaded());
		$this->assertEquals($first->pk(), $found->pk());
		$this->assertSame('Good Bye', ORM::factory('Kohana_ORM_Article', (string) $first->pk())->title);
		$this->assertFalse(ORM::factory('Kohana_ORM_Article', 'no-such-slug')->loaded());
	}

	/**
	 * The Guid behavior gives a record an id that never changes, by
	 * which it can be found.
	 */
	public function test_guid_behavior(): void
	{
		foreach (['Kohana_ORM_Ticket', 'Kohana_ORM_Ticket_Verified'] as $model)
		{
			$ticket = ORM::factory($model)->values(['title' => 'Broken'])->create();
			$guid   = $ticket->guid;

			$this->assertTrue(UUID::valid($guid), $model);

			$ticket->title = 'Repaired';
			$ticket->save();

			$this->assertSame($guid, ORM::factory($model, $ticket->pk())->guid, 'A record keeps its guid when it is saved again ('.$model.')');

			$found = ORM::factory($model, $guid);

			$this->assertTrue($found->loaded(), $model);
			$this->assertSame('Repaired', $found->title);
			$this->assertNotSame($guid, ORM::factory($model)->values(['title' => 'Other'])->create()->guid);
		}

		$this->assertFalse(ORM::factory('Kohana_ORM_Ticket', UUID::v4())->loaded());

		// Text that is no guid is refused before the database is asked
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('Invalid UUID');

		ORM::factory('Kohana_ORM_Ticket', "x' OR '1'='1");
	}

	/**
	 * A behavior can be a function of your own: it is told what happens.
	 */
	public function test_behavior_of_your_own(): void
	{
		Model_Kohana_ORM_Watched::$events = [];

		$article = ORM::factory('Kohana_ORM_Watched')->values(['title' => 'Seen'])->create();
		$article->title = 'Seen again';
		$article->save();

		$this->assertSame([['construct', null], ['create'], ['update']], Model_Kohana_ORM_Watched::$events);

		// A behavior that answers false stops the loading of the record
		Model_Kohana_ORM_Watched::$stop = true;

		try
		{
			$this->assertFalse(ORM::factory('Kohana_ORM_Watched', $article->pk())->loaded());
		}
		finally
		{
			Model_Kohana_ORM_Watched::$stop = false;
		}

		$this->assertTrue(ORM::factory('Kohana_ORM_Watched', $article->pk())->loaded());

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('function does not exists');

		ORM_Behavior::factory(0, ['Model_Kohana_ORM_Watched', 'no_such_method']);
	}

	public function test_belongs_to_and_has_many(): void
	{
		$author = $this->author();

		$book = ORM::factory('Kohana_ORM_Book')->values(['title' => 'First', 'author_id' => $author->pk()])->create();
		ORM::factory('Kohana_ORM_Book')->values(['title' => 'Second', 'author_id' => $author->pk()])->create();

		// The created column was filled in automatically
		$this->assertEqualsWithDelta(time(), (int) $book->created, 5);

		$this->assertSame('Ana', ORM::factory('Kohana_ORM_Book', 1)->author->name);
		$this->assertSame(['First', 'Second'], $author->books->order_by('id')->find_all()->as_array(null, 'title'));
		$this->assertSame(2, $author->books->count_all());

		// Eager loading with one query
		$loaded = ORM::factory('Kohana_ORM_Book')->with('author')->where('kohana_orm_book.id', '=', 1)->find();
		$this->assertSame('Ana', $loaded->author->name);
	}

	public function test_has_many_through(): void
	{
		$author = $this->author();
		$book   = ORM::factory('Kohana_ORM_Book')->values(['title' => 'First', 'author_id' => $author->pk()])->create();
		$php    = ORM::factory('Kohana_ORM_Tag')->values(['name' => 'php'])->create();
		$sql    = ORM::factory('Kohana_ORM_Tag')->values(['name' => 'sql'])->create();

		$this->assertFalse($book->has('tags'));

		$book->add('tags', $php);
		$book->add('tags', [$sql->pk()]);

		$this->assertTrue($book->has('tags', $php));
		$this->assertTrue($book->has('tags', [$php->pk(), $sql->pk()]));
		$this->assertSame(2, $book->count_relations('tags'));
		$this->assertSame(['php', 'sql'], $book->tags->order_by('name')->find_all()->as_array(null, 'name'));

		$book->remove('tags', $php);
		$this->assertFalse($book->has('tags', $php));
		$this->assertTrue($book->has_any('tags', [$php->pk(), $sql->pk()]));

		$book->remove('tags');
		$this->assertFalse($book->has('tags'));
	}

	/**
	 * Provides values for the uniqueness check, with and without exact case matching.
	 *
	 * @return array of $value, $match_case, $expected_unique
	 */
	public static function provider_unique(): array
	{
		return [
			'existing value'                    => ['ana@example.com', false, false],
			'existing value, other case'        => ['ANA@example.com', false, false],
			'other case counts as new'          => ['ANA@example.com', true, true],
			'same case'                         => ['ana@example.com', true, false],
			'new value'                         => ['new@example.com', false, true],
			'injection attempt is only a value' => ["x' OR '1'='1", true, true],
			'injection attempt, loose matching' => ["x' OR '1'='1", false, true],
		];
	}

	/**
	 * unique() is used as a validation rule on values typed by visitors.
	 */
	#[DataProvider('provider_unique')]
	public function test_unique($value, $match_case, $expected): void
	{
		if ($value === 'ANA@example.com' AND ! $match_case AND ! $this->ignores_case())
		{
			$this->markTestSkipped('This database does not ignore case when it compares.');
		}

		$this->author();

		$this->assertSame($expected, ORM::factory('Kohana_ORM_Author')->unique('email', $value, $match_case));
		$this->assertSame(1, ORM::factory('Kohana_ORM_Author')->count_all());
	}

	/**
	 * The record being edited does not count as a duplicate of itself.
	 */
	public function test_unique_ignores_the_model_itself(): void
	{
		$author = $this->author();

		$this->assertTrue($author->unique('email', 'ana@example.com'));
		$this->assertTrue($author->unique('email', 'ana@example.com', true));
	}

	/**
	 * Models are stored in sessions, so they must survive serialize().
	 */
	public function test_serialize_and_unserialize(): void
	{
		$author = $this->author();
		$author->name = 'Changed but not saved';

		$copy = unserialize(serialize($author));

		$this->assertInstanceOf('Model_Kohana_ORM_Author', $copy);
		$this->assertTrue($copy->loaded());
		$this->assertEquals(1, $copy->pk());

		// The model is reloaded from the database when it wakes up
		$this->assertSame('Ana', $copy->name);
		$this->assertSame('ana@example.com', $copy->email);
	}

	/**
	 * Serialized data can only restore the state of the model, never
	 * other properties such as its table name.
	 */
	public function test_unserialize_ignores_unknown_properties(): void
	{
		$author = $this->author();

		$data = $author->__serialize();
		$data['_table_name'] = 'kohana_orm_tags';

		$copy = ORM::factory('Kohana_ORM_Author');
		$copy->__unserialize($data);

		$this->assertSame('kohana_orm_authors', $copy->table_name());
		$this->assertSame('Ana', $copy->name);
	}

	public function test_serialized_columns_and_as_array(): void
	{
		$author = $this->author();
		$author->options = ['theme' => 'dark', 'sizes' => [1, 2]];
		$author->save();

		$loaded = ORM::factory('Kohana_ORM_Author', 1);

		$this->assertSame(['theme' => 'dark', 'sizes' => [1, 2]], $loaded->options);
		$this->assertSame('Ana', $loaded->as_array()['name']);
		$this->assertSame(1, $loaded->as_object()->id);
	}
}

/**
 * Test model: an author has many books.
 */
class Model_Kohana_ORM_Author extends ORM
{
	protected ?string $_db_group = 'test_orm';
	protected ?string $_table_name = 'kohana_orm_authors';
	protected array $_serialize_columns = ['options'];
	protected array $_has_many = [
		'books' => ['model' => 'Kohana_ORM_Book', 'foreign_key' => 'author_id'],
	];
	protected array $_has_one = [
		'profile' => ['model' => 'Kohana_ORM_Profile', 'foreign_key' => 'author_id'],
	];

	public function rules(): array
	{
		return [
			'name'  => [['not_empty'], ['max_length', [':value', 100]]],
			'email' => [['email']],
		];
	}
}

/**
 * Test model: a book belongs to an author and has many tags through a pivot table.
 */
class Model_Kohana_ORM_Book extends ORM
{
	protected ?string $_db_group = 'test_orm';
	protected ?string $_table_name = 'kohana_orm_books';
	protected ?array $_created_column = ['column' => 'created', 'format' => true];
	protected array $_belongs_to = [
		'author' => ['model' => 'Kohana_ORM_Author', 'foreign_key' => 'author_id'],
	];
	protected array $_has_many = [
		'tags' => ['model' => 'Kohana_ORM_Tag', 'through' => 'kohana_orm_books_tags', 'foreign_key' => 'book_id', 'far_key' => 'tag_id'],

		// The same tags, as a list of ids that can be assigned
		'tag_ids' => ['model' => 'Kohana_ORM_Tag', 'through' => 'kohana_orm_books_tags', 'foreign_key' => 'book_id', 'far_key' => 'tag_id', 'update' => true],
	];
}

/**
 * Test model: the profile of an author. It is loaded together with its
 * author, sorted by its text, keeps the time of its changes and cleans
 * what is set.
 */
class Model_Kohana_ORM_Profile extends ORM
{
	protected ?string $_db_group = 'test_orm';
	protected ?string $_table_name = 'kohana_orm_profiles';
	protected ?array $_created_column = ['column' => 'created', 'format' => true];
	protected ?array $_updated_column = ['column' => 'updated', 'format' => true];
	protected array $_sorting = ['bio' => 'DESC'];
	protected array $_load_with = ['author'];
	protected array $_belongs_to = [
		'author' => ['model' => 'Kohana_ORM_Author', 'foreign_key' => 'author_id'],
	];

	public function filters(): array
	{
		return [
			// Every column
			true  => [['trim']],
			'bio' => [
				['strtolower'],
				['Model_Kohana_ORM_Profile::shorten', [':value', 12]],
				[[$this, 'mark'], [':value', ':field']],
			],
		];
	}

	public static function shorten(string $text, int $length): string
	{
		return substr($text, 0, $length);
	}

	public function mark(string $text, string $field): string
	{
		return $text.' ('.$field.')';
	}
}

/**
 * Test model: a tag.
 */
class Model_Kohana_ORM_Tag extends ORM
{
	protected ?string $_db_group = 'test_orm';
	protected ?string $_table_name = 'kohana_orm_tags';
}

/**
 * Test model: an article with a slug made from its title.
 */
class Model_Kohana_ORM_Article extends ORM
{
	protected ?string $_db_group = 'test_orm';
	protected ?string $_table_name = 'kohana_orm_articles';
	protected array $_behaviors = ['Slug' => ['source' => 'title']];
}

/**
 * Test model: the same table, with a guid for each record.
 */
class Model_Kohana_ORM_Ticket extends ORM
{
	protected ?string $_db_group = 'test_orm';
	protected ?string $_table_name = 'kohana_orm_articles';
	protected array $_behaviors = ['Guid' => []];
}

/**
 * Test model: the same, with a check that the guid is not in use.
 */
class Model_Kohana_ORM_Ticket_Verified extends Model_Kohana_ORM_Ticket
{
	protected array $_behaviors = ['Guid' => ['verify' => true]];
}

/**
 * Test model: the same table, with a behavior that is a function.
 */
class Model_Kohana_ORM_Watched extends ORM
{
	/**
	 * @var array  What the behavior was told
	 */
	public static array $events = [];

	/**
	 * @var bool  Makes the behavior stop the loading of records
	 */
	public static bool $stop = false;

	protected ?string $_db_group = 'test_orm';
	protected ?string $_table_name = 'kohana_orm_articles';
	protected array $_behaviors = [['Model_Kohana_ORM_Watched', 'watch']];

	public static function watch(string $event, mixed $id = null): ?bool
	{
		if ($event === 'construct')
		{
			if (static::$stop)
				return false;

			static::$events[] = [$event, $id];

			return null;
		}

		static::$events[] = [$event];

		return null;
	}
}
