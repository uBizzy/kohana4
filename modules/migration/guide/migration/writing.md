# Writing Migrations

A migration is a PHP file that returns an object with an `up()` method, which makes the change, and usually a `down()` method, which undoes it. Both get the database connection.

```php
// application/migrations/20261111_143000_create_articles.php
return new class extends Migration {

	public function up(Database $db): void
	{
		$db->query(null, 'CREATE TABLE articles (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL)');
	}

	public function down(Database $db): void
	{
		$db->query(null, 'DROP TABLE articles');
	}

};
```

Make the file with `migration:create`, so the name is right:

```bash
./minion migration:create --name=create_articles
```

## The file name

`20261111_143000_create_articles.php` is the date (`20261111`), the time (`143000`) and what the migration does, in lower case letters, digits and `_`.

- The name is what the database remembers. **Never rename or change a migration that was applied anywhere else**: write a new one.
- Migrations are applied in the order of their names, so in the order they were made.
- A file with any other kind of name in a `migrations` directory is an error, not ignored: a typing mistake cannot hide a migration.

## Common changes

There is no special language for tables: you write the SQL of your database.

### Add a column

```php
return new class extends Migration {

	public function up(Database $db): void
	{
		$db->query(null, 'ALTER TABLE articles ADD COLUMN published INT NOT NULL DEFAULT 0');
	}

	public function down(Database $db): void
	{
		$db->query(null, 'ALTER TABLE articles DROP COLUMN published');
	}

};
```

### Add an index

```php
return new class extends Migration {

	public function up(Database $db): void
	{
		$db->query(null, 'CREATE INDEX articles_published ON articles (published)');
	}

	public function down(Database $db): void
	{
		$db->query(null, 'DROP INDEX articles_published ON articles');
	}

};
```

### Change data

Use the [query builder](../database/query/builder) with the connection you were given.

```php
return new class extends Migration {

	public function up(Database $db): void
	{
		$db->query(null, 'ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT \'active\'');

		// Users that never logged in become "new"
		DB::update('users')
			->set(['status' => 'new'])
			->where('logins', '=', 0)
			->execute($db);
	}

	public function down(Database $db): void
	{
		$db->query(null, 'ALTER TABLE users DROP COLUMN status');
	}

};
```

[!!] Always pass `$db` to `execute()`. Without it the query runs on the default connection, which is the wrong one when the migration is run with `--db`.

### Add starting data

```php
return new class extends Migration {

	public function up(Database $db): void
	{
		DB::insert('roles', ['name', 'description'])
			->values(['login', 'May log in'])
			->values(['admin', 'May change everything'])
			->execute($db);
	}

	public function down(Database $db): void
	{
		DB::delete('roles')->where('name', 'IN', ['login', 'admin'])->execute($db);
	}

};
```

## Table prefix

The query builder adds the `table_prefix` of the connection by itself. In SQL you write yourself, let the connection make the table name:

```php
public function up(Database $db): void
{
	// "shop_articles" when the prefix is "shop_"
	$db->query(null, 'CREATE TABLE '.$db->quote_table('articles').' (id INT PRIMARY KEY)');
}
```

## A change that cannot be undone

Some changes have no honest way back: a dropped column cannot get its data again. Leave `down()` out.

```php
return new class extends Migration {

	public function up(Database $db): void
	{
		$db->query(null, 'ALTER TABLE users DROP COLUMN old_password');
	}

};
```

A rollback that reaches this migration stops with an error and leaves it applied.

## Migrations of a module

A module can bring its own migrations, in its own `migrations` directory:

```text
modules/shop/migrations/20261020_090000_create_orders.php
application/migrations/20261111_143000_create_articles.php
```

All of them are found, and applied together in the order of their names. A file in the application replaces a module's file with the same name, as everywhere in the [cascading filesystem](../kohana/files).

Tables that not every application wants should not be in that directory: everybody who enables the module would get them. Keep such a migration elsewhere in the module and let a task copy it into the application, as `./minion auth:migration` does for the [auth tables](../auth/orm):

```php
// The third argument is the PHP code of the migration
$file = (new Migration_Runner)->create('create_auth_tables', null, $content);
```

## Advice

- **One change per migration.** When a migration fails halfway, a small one is easy to repair.
- **Write `down()` and try it**: run, roll back, run again before you commit.
- **Do not use your models** in a migration. A model changes over time; the migration must keep doing what it did on the day it was written. Use SQL and the query builder.
- **Keep to the database you use.** `AUTO_INCREMENT` is MySQL; SQLite writes `INTEGER PRIMARY KEY AUTOINCREMENT`, PostgreSQL `SERIAL`. A migration that must run on more than one asks the connection: `$db->driver()` is `'mysql'`, `'pgsql'` or `'sqlite'`.
