# Database

The database module runs SQL for you: a query builder for everyday queries, raw SQL when you need it, and the same result objects for both.

```php
// application/bootstrap.php
Kohana::modules([
	'database' => MODPATH.'database',
]);
```

## A first query

```php
$users = DB::select('id', 'name')
	->from('users')
	->where('age', '>', 30)
	->order_by('name')
	->limit(10)
	->execute();

foreach ($users as $user)
{
	echo $user['name'];
}
```

```sql
SELECT `id`, `name` FROM `users` WHERE `age` > 30 ORDER BY `name` LIMIT 10
```

## What you get

| | |
|---|---|
| [Query Builder](query/builder) | `DB::select()`, `insert()`, `update()`, `delete()`: SQL written as PHP, quoted for you |
| [Raw SQL](query/parameterized) | `DB::query()` with named parameters, for anything the builder cannot say |
| [Results](results) | Loop, count, take one row or one value, turn into arrays or objects |
| [Transactions](transactions) | Several queries that succeed or fail together |
| Drivers | MySQL and MariaDB (`MySQLi`), and any database PHP's PDO supports (`PDO`) |

## Safe by default

- **Values never become part of the SQL.** The builder and named parameters send them apart from the statement (prepared statements). You cannot forget to escape.
- **Names are quoted**: tables and columns given to the builder are quoted as identifiers.
- **Operators are checked**: `where('id', $operator, 1)` refuses an operator that is not a real one, so a value from a form cannot be used as one.

```php
// Whatever $name holds, it is only ever a value
DB::select()->from('users')->where('name', '=', $name)->execute();
```

[!!] The one way to open a hole is to glue a visitor's value into SQL text yourself: in `DB::query()`, `DB::expr()`, or as a table or column name. Use parameters for values, and a fixed list for names.

## Models

Models that use the database can extend `Model_Database`; `$this->_db` is the connection.

```php
class Model_Stats extends Model_Database {

	public function users(): int
	{
		return (int) DB::select([DB::expr('COUNT(*)'), 'total'])
			->from('users')
			->execute($this->_db)
			->get('total');
	}
}
```

For one object per row, see the [ORM module](../orm).

## Also in this module

- **Sessions in the database**: `Session::instance('database')`. See [Sessions](../kohana/sessions).
- **Settings in the database**: `Config_Database`. See [Configuration](../kohana/config).
