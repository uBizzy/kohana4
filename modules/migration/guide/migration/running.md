# Running Migrations

All commands are [Minion](../minion) tasks, run from the directory of the project.

## See where you are

```text
./minion migration:status
Status   Migration
-------  ------------------------------------------
applied  20261020_090000_create_orders
applied  20261111_143000_create_articles
pending  20261112_101500_add_published_to_articles

3 migrations, 1 pending.
```

## Apply

```text
./minion migration:run
applied  20261112_101500_add_published_to_articles
✔ 1 migration applied.
```

- Every pending migration is applied, oldest first.
- Running it again does nothing: `› Nothing to apply.`
- A migration with an older date that arrives later (from a colleague's branch) is still applied, as it is pending.

## Undo

```text
./minion migration:rollback
undone   20261112_101500_add_published_to_articles
✔ 1 migration undone.
```

```bash
# The newest three
./minion migration:rollback --steps=3
```

Migrations are undone newest first, by their names. A migration without a `down()` method stops the rollback there.

## When a migration fails

```text
./minion migration:run
applied  20261111_143000_create_articles
✖ Kohana_Exception [ 0 ]: The migration 20261112_101500_add_published_to_articles could not be applied: ...
```

- The run stops. Migrations applied before the failing one stay applied.
- The failing migration is not recorded: fix it and run again.
- The command ends with an error status, so a deploy script stops too.

[!!] **MySQL and MariaDB cannot take back a change to a table.** `CREATE TABLE`, `ALTER TABLE` and `DROP TABLE` are final the moment they run, also inside a transaction. A migration that fails after such a statement leaves it done, and running it again may fail on "table already exists". Keeping one change per migration avoids this. SQLite and PostgreSQL take back everything.

## Another database

Every command but `create` takes the name of a connection of `config/database.php`:

```bash
./minion migration:status --db=reports
./minion migration:run --db=reports
```

Each database remembers its own applied migrations. All databases see the same migration files, so this suits copies of one structure (a test database, one database per customer), not databases with different tables.

## On the server

Run the migrations as a step of every deploy, after the new code is in place and before it gets visitors:

```bash
git pull
./minion migration:run --no-interaction
```

- Make a backup first when a migration changes or removes data.
- Do not run two deploys at once: two runs at the same moment would both try the same migration.

## Settings

```php
// application/config/migration.php
return [
	// Table that remembers the applied migrations
	'table' => 'schema_migrations',
];
```

The `table_prefix` of the connection is added to this name.

## From your own code

The tasks are a thin layer over `Migration_Runner`, which you can use in a test setup or an install script.

```php
$runner = new Migration_Runner;                  // the default database
$runner = Migration_Runner::factory('reports');  // a connection by name

$runner->all();        // every migration: name => file
$runner->applied();    // names of the applied ones
$runner->pending();    // the ones not applied yet: name => file

$runner->run();        // applies the pending ones, returns their names
$runner->rollback(2);  // undoes the newest two, returns their names

$runner->fresh();      // drops all tables, applies everything (never in production)
$runner->seed();       // runs the seed files, returns their names

// Say what happens, one by one
$runner->run(function (string $name) {
	Kohana::$log->add(Log::INFO, 'Applied migration '.$name);
});
```

A failure throws a `Kohana_Exception` that names the migration; the error of the database is its `getPrevious()`.
