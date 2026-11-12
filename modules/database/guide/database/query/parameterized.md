# Raw SQL

For queries the builder cannot express, write the SQL yourself and give the values as named parameters.

```php
$users = DB::query(Database::SELECT, 'SELECT * FROM users WHERE age > :age AND country = :country')
	->param(':age', 30)
	->param(':country', 'PT')
	->execute();
```

The values are sent to the database apart from the SQL: whatever they hold, they can never become part of the statement.

[!!] Never build the SQL text from a visitor's value: `'... WHERE name = "'.$name.'"'` is how databases get broken into. Always a parameter.

## The type of query

The first argument says what the query is, which decides what `execute()` returns.

| Type | `execute()` returns |
|---|---|
| `Database::SELECT` | A [result](../results) to loop over |
| `Database::INSERT` | `[new id, number of rows]` |
| `Database::UPDATE` | The number of rows changed |
| `Database::DELETE` | The number of rows deleted |
| `null` | Nothing useful: for `CREATE TABLE` and the like |

```php
[$id, $rows] = DB::query(Database::INSERT, 'INSERT INTO users (name) VALUES (:name)')
	->param(':name', 'Eva')
	->execute();

$changed = DB::query(Database::UPDATE, 'UPDATE users SET active = 0 WHERE last_login < :limit')
	->param(':limit', time() - Date::YEAR)
	->execute();

DB::query(null, 'TRUNCATE TABLE sessions')->execute();
```

## Giving parameters

```php
// One by one
$query->param(':age', 30);

// Several
$query->parameters([':age' => 30, ':country' => 'PT']);

// By reference: the value the variable has when the query runs
$query->bind(':age', $age);
$age = 40;
$query->execute();   // uses 40
```

A list becomes a list in the SQL:

```php
DB::query(Database::SELECT, 'SELECT * FROM users WHERE id IN :ids')
	->param(':ids', [1, 2, 3])
	->execute();
// ... WHERE id IN (1, 2, 3)
```

## Running one query many times

```php
$query = DB::query(Database::INSERT, 'INSERT INTO log (user_id, action) VALUES (:user, :action)')
	->bind(':user', $user_id)
	->bind(':action', $action);

foreach ($events as [$user_id, $action])
{
	$query->execute();
}
```

The statement is prepared once and reused.

## Names of tables and columns

A parameter is always a value. A table or column name that changes must come from a list of your own:

```php
$columns = ['title', 'created', 'views'];
$sort = in_array($sort, $columns, true) ? $sort : 'created';

DB::query(Database::SELECT, 'SELECT * FROM articles ORDER BY '.$sort.' DESC')->execute();
```

Or let the connection quote it:

```php
$db = Database::instance();

$sql = 'SELECT * FROM '.$db->quote_table('articles').' ORDER BY '.$db->quote_column($sort);
```

## On another connection

```php
$rows = DB::query(Database::SELECT, 'SELECT * FROM orders')->execute('reports');
```

## Mixing with the builder

`DB::expr()` puts raw SQL inside a built query, with parameters of its own:

```php
DB::select('id', [DB::expr('price * :rate', [':rate' => 1.23]), 'gross'])
	->from('products')
	->execute();
```
