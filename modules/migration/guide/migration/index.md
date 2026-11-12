# Migration

The Migration module keeps every change to the database structure in a file, so each copy of the application (yours, a colleague's, the server) can be brought to the same state with one command.

It needs the [Database](../database) and [Minion](../minion) modules.

```php
// application/bootstrap.php
Kohana::modules([
	'database'  => MODPATH.'database',
	'minion'    => MODPATH.'minion',
	'migration' => MODPATH.'migration',
]);
```

## In four steps

**1. Make a migration.** The name says what it does.

```text
./minion migration:create --name=create_articles
✔ Created APPPATH/migrations/20261111_143000_create_articles.php
```

**2. Write the change**, and the way to undo it.

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

**3. Apply it.**

```text
./minion migration:run
applied  20261111_143000_create_articles
✔ 1 migration applied.
```

**4. Commit the file** with the code that needs the change. Everyone else runs `migration:run` after they pull.

## The commands

| Command | Does |
|---|---|
| `migration:create --name=...` | Writes an empty migration file |
| `migration:status` | Lists every migration as `applied` or `pending` |
| `migration:run` | Applies all pending migrations, oldest first |
| `migration:rollback` | Undoes the newest applied migration; `--steps=3` undoes three |
| `migration:fresh` | Drops all tables and applies everything again; `--seed` adds the seed data. Never in production |
| `migration:seed` | Runs the seed files; `--name=users` runs one |

See [Running Migrations](running) and [Fresh Database and Seeds](seeds) for the details.

## How it works

- A migration is a file in a `migrations` directory, of the application or of a module. Its name starts with the date and time it was made, and migrations are applied in that order.
- The database itself remembers which migrations were applied, in a table named `migrations`. It is created on first use.
- Each migration runs in a transaction and is only recorded when it succeeds.

[!!] The tasks only exist on the command line. Nothing in this module can be reached from the web.
