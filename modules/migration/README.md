# Migration module

Database changes kept in files, applied and undone from the command line.

Needs the `database` and `minion` modules.

```php
// application/bootstrap.php
Kohana::modules([
	'database'  => MODPATH.'database',
	'minion'    => MODPATH.'minion',
	'migration' => MODPATH.'migration',
]);
```

```
./minion migration:create --name=create_articles
./minion migration:status
./minion migration:run
./minion migration:rollback --steps=1
./minion migration:fresh --seed      # development and tests: drop all tables, start again
```

The full documentation is in `guide/migration/` and in the user guide of your application.
