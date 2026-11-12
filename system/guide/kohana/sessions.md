# Sessions

A session keeps data for one visitor between requests: who is logged in, what is in the cart.

```php
$session = Session::instance();

$session->set('cart', [12, 57]);

$cart = $session->get('cart', []);   // [] when there is none

$session->delete('cart');
```

## Reading and writing

```php
$session = Session::instance();

// Set, get, delete
$session->set('user_id', 10);
$id = $session->get('user_id');
$session->delete('user_id', 'cart');   // several at once

// Read once: for a message shown on the next page
$session->set('notice', 'Saved.');
$notice = $session->get_once('notice');   // "Saved.", and it is gone

// Everything
$data = $session->as_array();

// Throw the whole session away (log out)
$session->destroy();
```

The session is saved by itself at the end of the request.

## After a log in

Give the session a new id when the visitor's rights change. This stops an attacker who planted a session id from using it.

```php
$session->regenerate();
$session->set('user_id', $user->id);
```

The Auth module does this for you.

## Where sessions are kept

| Type | Kept in | Use it when |
|---|---|---|
| `native` | PHP's own session files (the default) | One server |
| `database` | A table (needs the database module) | Several servers, or you want to see who is online |
| `cookie` | A signed cookie in the browser | Little data (under 4 KB), no server storage |

[!!] A browser drops a cookie over 4 KB without any error. When a `cookie` session grows past that, it is not sent: in development you get a `Session_Exception`, on other environments the error is written to the log.

```php
// The default type
$session = Session::instance();

// A named type
$session = Session::instance('database');

// Change the default, in the bootstrap
Session::$default = 'database';
```

## Settings

```php
// application/config/session.php
return [
	'native' => [
		'name'     => 'session',   // name of the cookie
		'lifetime' => 43200,       // seconds; 0 ends with the browser
	],
	'cookie' => [
		'name'      => 'data',
		'lifetime'  => 43200,
		'encrypted' => true,       // needs the encrypt module
	],
	'database' => [
		'name'     => 'session',
		'lifetime' => 43200,
		'group'    => 'default',   // database connection
		'table'    => 'sessions',
		'gc'       => 500,         // old sessions are deleted once in 500 requests
	],
];
```

Table for database sessions:

```sql
CREATE TABLE sessions (
	session_id VARCHAR(24) NOT NULL,
	last_active INT UNSIGNED NOT NULL,
	contents TEXT NOT NULL,
	PRIMARY KEY (session_id),
	INDEX (last_active)
);
```

## Good to know

- The session cookie follows the [cookie settings](cookies): `HttpOnly`, `SameSite=Lax`, and `Secure` over HTTPS.
- A stored session that cannot be read (damaged, or written by an old version) is thrown away and a new one starts. Nothing to handle.
- Cookie sessions are signed but **readable** by the visitor unless `encrypted` is on. Do not put secrets in them.
- Objects can be stored; their classes are loaded when the session is read.
