# Query Builder

The builder writes SQL from method calls. Names are quoted, values are sent safely, and the same code works on every supported database.

Every query ends with `->execute()`.

## Select

```php
// All columns
$rows = DB::select()->from('users')->execute();

// Some columns
$rows = DB::select('id', 'name')->from('users')->execute();

// With another name: [column, alias]
$rows = DB::select(['name', 'n'])->from(['users', 'u'])->execute();
// SELECT `name` AS `n` FROM `users` AS `u`

// Without repeats
$rows = DB::select('country')->distinct(true)->from('users')->execute();
```

### Where

```php
DB::select()->from('users')
	->where('active', '=', 1)
	->where('age', '>=', 18)          // AND
	->or_where('role', '=', 'admin')  // OR
	->execute();
```

| You write | SQL |
|---|---|
| `->where('id', '=', 5)` | `id = 5` |
| `->where('id', 'IN', [1, 2])` | `id IN (1, 2)` |
| `->where('age', 'BETWEEN', [18, 65])` | `age BETWEEN 18 AND 65` |
| `->where('name', 'LIKE', 'A%')` | `name LIKE 'A%'` |
| `->where('email', '=', null)` | `email IS NULL` |
| `->where('email', 'IS NOT', null)` | `email IS NOT NULL` |
| `->where('id', 'IN', DB::select('user_id')->from('posts'))` | `id IN (SELECT user_id FROM posts)` |

Brackets:

```php
DB::select()->from('users')
	->where('active', '=', 1)
	->and_where_open()
		->where('age', '<', 18)
		->or_where('age', '>', 65)
	->and_where_close()
	->execute();
// WHERE active = 1 AND (age < 18 OR age > 65)
```

[!!] The operator must be a real SQL operator: anything else throws a `Database_Exception`. To allow one of your database that is not in the list, add it to `Database_Query_Builder::$operators`.

### Searching text from a visitor

In `LIKE`, `%` and `_` are wildcards. Escape them in what the visitor typed:

```php
$term = addcslashes($this->request->query('q') ?? '', '%_\\');

DB::select()->from('articles')->where('title', 'LIKE', '%'.$term.'%')->execute();
```

### Order, limit, offset

```php
DB::select()->from('articles')
	->order_by('created', 'DESC')
	->order_by('title')
	->limit(20)
	->offset(40)    // page 3
	->execute();
```

A column to sort by that comes from the address must be one of a list you fix:

```php
$sort = in_array($this->request->query('sort'), ['title', 'created'], true)
	? $this->request->query('sort')
	: 'created';
```

### Joins

```php
DB::select('users.name', 'posts.title')
	->from('users')
	->join('posts', 'LEFT')
	->on('posts.user_id', '=', 'users.id')
	->execute();
// ... FROM users LEFT JOIN posts ON (posts.user_id = users.id)
```

The join type is `'LEFT'`, `'RIGHT'`, `'INNER'`, ... or nothing for a plain `JOIN`. Both sides of `on()` are column names.

### Counting and grouping

```php
// One number
$total = DB::select([DB::expr('COUNT(*)'), 'total'])
	->from('users')
	->where('active', '=', 1)
	->execute()
	->get('total');

// Per group
$rows = DB::select('country', [DB::expr('COUNT(*)'), 'total'])
	->from('users')
	->group_by('country')
	->having('total', '>', 10)
	->order_by('total', 'DESC')
	->execute();
```

## Insert

```php
[$id, $rows] = DB::insert('users', ['name', 'email'])
	->values(['Ana', 'ana@example.org'])
	->execute();

// $id is the new id, $rows the number of rows added
```

`$id` is the number the database gave the new row. For a table that does not number its rows it is `0`. On PostgreSQL, where numbers come from sequences, it is then the last number given in the connection, which can belong to an earlier insert: only use `$id` for tables with a `SERIAL` or identity column.

```php
// Several rows in one query
DB::insert('users', ['name', 'email'])
	->values(['Ana', 'ana@example.org'], ['Rui', 'rui@example.org'])
	->execute();

// From another query
DB::insert('archive', ['id', 'title'])
	->select(DB::select('id', 'title')->from('articles')->where('created', '<', $limit))
	->execute();
```

## Update

```php
$rows = DB::update('users')
	->set(['name' => 'Ana Maria', 'updated' => time()])
	->where('id', '=', 1)
	->execute();

// $rows is the number of rows changed
```

```php
// One column
DB::update('users')->value('active', 0)->where('last_login', '<', $limit)->execute();

// A value computed by the database
DB::update('articles')->set(['views' => DB::expr('views + 1')])->where('id', '=', $id)->execute();
```

[!!] An update or delete without `where()` changes every row.

## Delete

```php
$rows = DB::delete('users')->where('id', '=', 99)->execute();
```

## Expressions

`DB::expr()` puts text into the SQL as it is: for functions and calculations. It is **not** quoted, so never put a visitor's value in it; give values as parameters.

```php
DB::select([DB::expr('COUNT(*)'), 'total']);
DB::select([DB::expr('DATE(created)'), 'day']);

// With a value
DB::expr('price * :rate', [':rate' => $rate]);
```

## Looking at the SQL

```php
echo DB::select('id')->from('users')->where('age', '>', 30)->compile();
// SELECT `id` FROM `users` WHERE `age` > 30

// After running: what was sent to the database
echo Database::instance()->last_query;
```

## Building a query in steps

A builder is an object; add to it as you go.

```php
$query = DB::select()->from('articles')->where('published', '=', 1);

if ($category)
{
	$query->where('category_id', '=', $category);
}

if ($search !== '')
{
	$query->where('title', 'LIKE', '%'.addcslashes($search, '%_\\').'%');
}

$articles = $query->order_by('created', 'DESC')->limit(20)->execute();
```

## Keeping a result for a while

```php
// The same query is not run again for 60 seconds
$countries = DB::select()->from('countries')->cached(60)->execute();
```

Needs `'caching' => true` on the connection.
