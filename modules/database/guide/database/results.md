# Results

A `SELECT` returns a `Database_Result`. It is used like an array of rows, but rows are only read when you ask for them.

```php
$users = DB::select()->from('users')->execute();
```

## Looping and counting

```php
echo count($users);   // number of rows

foreach ($users as $user)
{
	echo $user['name'];
}

// A row by its position
$first = $users[0];
```

## One row

```php
$user = DB::select()->from('users')->where('id', '=', 1)->execute()->current();

if ($user === null)
{
	// No such row
}

echo $user['name'];
```

## One value

```php
$total = DB::select([DB::expr('COUNT(*)'), 'total'])->from('users')->execute()->get('total');

// With a default when there is no row
$name = DB::select('name')->from('users')->where('id', '=', 99)->execute()->get('name', 'nobody');
```

## As arrays

```php
$result = DB::select()->from('users')->execute();

// A plain list of rows
$result->as_array();
// [['id' => 1, 'name' => 'Ana', ...], ['id' => 2, 'name' => 'Rui', ...]]

// Rows by a column: find a user by id without looping
$result->as_array('id');
// [1 => ['id' => 1, 'name' => 'Ana', ...], 2 => [...]]

// One column by another: ready for Form::select()
$result->as_array('id', 'name');
// [1 => 'Ana', 2 => 'Rui']

// One column as a list
$result->as_array(null, 'name');
// ['Ana', 'Rui']
```

```php
echo Form::select('user', DB::select('id', 'name')->from('users')->execute()->as_array('id', 'name'));
```

## Rows as objects

```php
// stdClass objects
$users = DB::select()->from('users')->as_object()->execute();

foreach ($users as $user)
{
	echo $user->name;
}

// Objects of a class of yours
$users = DB::select()->from('users')->as_object('Model_User')->execute();
```

## Types of the values

Values come back as the database driver gives them: with MySQLi, every value is a string (`'31'`, not `31`). Convert where the type matters:

```php
$age = (int) $user['age'];
$active = (bool) $user['active'];
```

## Using a result more than once

A result can be looped again. To keep it after other queries, or to pass it around freely, copy it into memory:

```php
$cached = $users->cached();
```

## Insert, update and delete

These do not return a result object.

```php
[$id, $rows] = DB::insert('users', ['name'])->values(['Ana'])->execute();   // new id, rows added

$rows = DB::update('users')->set(['active' => 0])->execute();   // rows changed
$rows = DB::delete('users')->where('active', '=', 0)->execute();   // rows deleted
```

## How many rows are there?

```php
// All rows of a table
$total = Database::instance()->count_records('users');

// Rows of a query, for page links: count with the same conditions
$total = DB::select([DB::expr('COUNT(*)'), 'total'])
	->from('articles')
	->where('published', '=', 1)
	->execute()
	->get('total');
```

See the [Pagination module](../pagination).
