# A Fresh Database and Seed Data

Two things for development and tests: start again from an empty database, and fill it with data to work with.

## Start again

```text
./minion migration:fresh
Drop all tables of the "default" database and lose their data? [y/N]: y
applied  20261020_090000_create_orders
applied  20261111_143000_create_articles
✔ Tables dropped, 2 migrations applied.
```

Every table is dropped and all migrations are applied again. Use it when your database is in a state no migration can repair, or to try the whole list of migrations from nothing.

[!!] **All data is lost.** The task asks before it does anything, and it never runs when `Kohana::$environment` is `Kohana::PRODUCTION`: there is no option to force it.

- With `--no-interaction` nothing is asked, for scripts and CI.
- Only tables whose names start with the `table_prefix` of the connection are dropped. Applications that share one database by prefix keep their tables. Without a prefix, all tables go.
- Tables that point to each other are no problem. Views are not dropped.
- Works on MySQL/MariaDB, PostgreSQL and SQLite.

## Seed data

A seed is a file in a `seeds` directory that returns a function. The function gets the database connection and adds rows.

```php
// application/seeds/users.php
return function (Database $db): void
{
	DB::insert('users', ['username', 'email', 'password'])
		->values(['ana', 'ana@example.com', Auth::instance()->hash('correct horse')])
		->values(['bea', 'bea@example.com', Auth::instance()->hash('battery staple')])
		->execute($db);
};
```

```text
./minion migration:seed
seeded   articles
seeded   users
✔ 2 seeds run.
```

```bash
# Only one of them
./minion migration:seed --name=users
```

```bash
# An empty database, all migrations, then all seeds
./minion migration:fresh --seed
```

- Seeds run in the order of their names. When one needs the rows of another, number them: `10_users.php`, `20_articles.php`.
- Each seed runs in a transaction: when it fails, none of its rows stay. The seeds before it stay.
- A seed is not remembered: running it twice adds the rows twice (or fails on a unique column). Run seeds on a fresh database.
- Always pass `$db` to `execute()`, as in a migration.
- Modules can bring seeds in their own `seeds` directory. A file in the application replaces a module's file with the same name.

### Seed or migration?

| Data | Where |
|---|---|
| The application cannot work without it (the roles `login` and `admin`, a list of countries) | A [migration](writing#add-starting-data): it reaches every copy, the server included |
| Examples to develop and test with (ten users, fifty articles) | A seed: it never needs to reach the server |

## In tests

`Migration_Runner` does the same from PHP, so a test can start from a known database:

```php
public function setUp(): void
{
	parent::setUp();

	$runner = Migration_Runner::factory('test');

	$runner->fresh();          // empty tables, all migrations applied
	$runner->seed('users');    // one seed, or seed() for all of them
}
```

Give the tests their own connection in `config/database.php`, with their own database: `fresh()` drops whatever it finds there.
