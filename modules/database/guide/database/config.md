# Configuration

Connections are in the `database` config group. Create `application/config/database.php` with yours.

## MySQL and MariaDB

```php
// application/config/database.php
return [
	'default' => [
		'type'       => 'MySQLi',
		'connection' => [
			'hostname'   => 'localhost',
			'database'   => 'shop',
			'username'   => 'shop',
			'password'   => 'secret',
			'persistent' => false,
		],
		'table_prefix' => '',
		'charset'      => 'utf8mb4',
		'caching'      => false,
		'prepared'     => true,
	],
];
```

[!!] Use `utf8mb4` as the character set. MySQL's `utf8` cannot hold every character (emoji, some Asian characters).

## Other databases, through PDO

```php
return [
	'default' => [
		'type'       => 'PDO',
		'connection' => [
			'dsn'        => 'pgsql:host=localhost;dbname=shop',
			'username'   => 'shop',
			'password'   => 'secret',
			'persistent' => false,
		],
		'table_prefix' => '',
		'charset'      => 'utf8',
	],
];
```

```php
// SQLite: a file, or ':memory:' for tests. Foreign keys are switched on
// for every connection (SQLite itself leaves them off).
'dsn' => 'sqlite:'.APPPATH.'data/shop.sqlite',

// MySQL through PDO
'dsn' => 'mysql:host=localhost;dbname=shop;charset=utf8mb4',
```

```php
// PostgreSQL
'dsn' => 'pgsql:host=localhost;dbname=shop',
```

### Tables and columns

Both drivers can tell which tables a database has and which columns a table has. The [ORM module](../orm) uses this to learn the columns of a model.

```php
$db = Database::instance();

$db->list_tables();              // ['articles', 'users']
$db->list_tables('blog_%');      // only the names that match

$columns = $db->list_columns('users');

$columns['email']['type'];       // 'string'  ('int', 'float', 'bool' or 'string')
$columns['email']['data_type'];  // 'varchar' (as the database names it)
$columns['id']['key'];           // 'PRI' for the primary key
```

With PDO this works on SQLite, MySQL/MariaDB and PostgreSQL, and every column has the same keys on each of them:

| Key | Value |
|---|---|
| `column_name` | Name of the column |
| `data_type` | Type as the database names it, in lower case |
| `type` | `int`, `float`, `bool` or `string` |
| `is_nullable` | Can the column hold `null`? |
| `column_default` | The default as the database writes it, or `null` |
| `ordinal_position` | Position in the table, starting at 1 |
| `character_maximum_length` | Longest text, or `null` when not known |
| `numeric_precision`, `numeric_scale` | Digits in all and after the decimal point, or `null` |
| `key` | `PRI` for a column of the primary key, otherwise empty |

The MySQLi driver returns these keys and some more that only MySQL has (collation, comment, privileges).

`list_columns()` of a table that does not exist throws a `Database_Exception`, with both drivers.

[!!] On any other PDO database (Oracle, SQL Server) the two methods throw a `Kohana_Exception`. With ORM there, declare the columns in each model (`$_table_columns`).

## The settings

| Setting | Meaning |
|---|---|
| `type` | The driver: `MySQLi` or `PDO` |
| `connection` | How to reach the database (see below) |
| `table_prefix` | Put before every table name: `'shop_'` makes `users` into `shop_users` |
| `charset` | Character set of the connection |
| `caching` | Keep results of queries that ask for it with `->cached()` |
| `prepared` | Send values apart from the SQL. On by default |

### MySQLi connection

| Key | Meaning |
|---|---|
| `hostname` | Server name, optionally with `:port`, or a socket path |
| `database`, `username`, `password` | |
| `persistent` | Keep the connection open between requests |
| `ssl` | `['client_key_path' => ..., 'client_cert_path' => ..., 'ca_cert_path' => ...]` |
| `variables` | Session variables to set: `['sql_mode' => 'STRICT_ALL_TABLES']` |

### PDO connection

| Key | Meaning |
|---|---|
| `dsn` | The data source name of PDO |
| `username`, `password` | |
| `persistent` | Keep the connection open between requests |

## Several connections

Each key of the config is a connection.

```php
return [
	'default' => [/* ... */],
	'reports' => [
		'type'       => 'MySQLi',
		'connection' => ['hostname' => 'replica.internal', 'database' => 'shop', 'username' => 'reader', 'password' => 'secret'],
		'charset'    => 'utf8mb4',
	],
];
```

```php
// The default connection
$rows = DB::select()->from('orders')->execute();

// A named one
$rows = DB::select()->from('orders')->execute('reports');

// The connection itself
$db = Database::instance('reports');

// Another default
Database::$default = 'reports';
```

## Prepared statements

With `'prepared' => true` (the default) the database receives the SQL with `?` marks and the values separately. Statements used several times in a request are prepared once.

Switch it off for one connection only if a query of yours cannot be prepared; values are then quoted and put into the SQL, as older versions did.

```php
'prepared' => false,
```

```php
// What was sent last
$db = Database::instance();

echo $db->last_query;    // SELECT * FROM `users` WHERE `id` = ?
print_r($db->last_params);   // [42]
```

## Keeping passwords out of the repository

`application/config/database.php` holds a password: do not commit it. Commit an example file, and create the real one on each server.

```text
application/config/database.php            (ignored)
application/config/database.example.php    (committed, without the password)
```

## Closing and opening a connection

A connection is opened by the first query and closed at the end of the request. You can close it earlier; the object stays usable, and the next query opens a new connection with the same settings.

```php
$db = Database::instance();

$db->disconnect();

// Connects again by itself
$count = DB::select([DB::expr('COUNT(*)'), 'n'])->from('users')->execute($db)->get('n');
```

### Tasks that run a long time

A database server closes a connection that was idle for a while, and the next query fails with "server has gone away". In a [Minion](../minion) task that waits between pieces of work, open a fresh connection before each piece:

```php
while ($job = $queue->next())
{
	// The old connection may have been closed while waiting
	Database::instance()->reconnect();

	$job->run();
}
```

[!!] `reconnect()` and `disconnect()` end a transaction that is open, and temporary tables and session variables of the old connection are gone.

The settings of a connection, with the password, are kept in the object so it can connect again. They are hidden from `var_dump()`, `print_r()` and `Debug::dump()`.

