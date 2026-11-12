# Configuration

Settings live in PHP files that return an array, in the `config/` directories. The file name is the name of the **group**.

```php
// application/config/site.php
return [
	'name'  => 'My Shop',
	'email' => 'hello@example.org',
	'social' => [
		'mastodon' => 'https://example.social/@shop',
	],
];
```

## Reading

```php
// The whole group
$site = Kohana::$config->load('site');

echo $site->get('name');                  // My Shop
echo $site->get('phone', 'not listed');   // a default when the key is missing
echo $site['email'];                      // as an array

// One value, with a path: group.key.key
$url = Kohana::$config->load('site.social.mastodon');

// As a plain array
$array = Kohana::$config->load('site')->as_array();
```

## Files are merged

A group can have a file in the system, in modules and in the application. All are read and merged; the application's values win, key by key. Copy only what you change.

```php
// modules/database/config/database.php has the full structure.
// application/config/database.php changes the connection:
return [
	'default' => [
		'connection' => [
			'hostname' => 'db.internal',
			'database' => 'shop',
			'username' => 'shop',
			'password' => 'secret',
		],
	],
];
```

[!!] Files with passwords do not belong in version control. Keep `application/config/database.php` out of the repository and create it on each server.

## Different values per environment

Config files are PHP, so they can decide:

```php
// application/config/site.php
return [
	'debug_bar' => Kohana::$environment !== Kohana::PRODUCTION,
	'mail_host' => (Kohana::$environment === Kohana::PRODUCTION) ? 'smtp.example.org' : 'localhost',
];
```

## Changing a value while running

```php
$site = Kohana::$config->load('site');

$site->set('name', 'Another name');
$site['email'] = 'other@example.org';
```

The change lasts for the request. It is saved only if a config **writer** is attached (see below).

## Settings in the database

With the database module, settings can be kept in a table and changed without deploying. Values from the database win over the files.

```sql
CREATE TABLE config (
	group_name VARCHAR(128) NOT NULL,
	config_key VARCHAR(128) NOT NULL,
	config_value TEXT,
	PRIMARY KEY (group_name, config_key)
);
```

```php
// application/bootstrap.php, after Kohana::modules()
Kohana::$config->attach(new Config_File);
Kohana::$config->attach(new Config_Database);

// Now this is written to the table
Kohana::$config->load('site')->set('name', 'Renamed');
```

## What belongs where

| Kind of value | Put it in |
|---|---|
| Settings of your application | a config group of your own (`config/site.php`) |
| Settings of a module | the module's group, overridden in `application/config/` |
| Passwords and keys | a config file that is not in version control |
| Fixed values of one class | a constant of the class |
| Texts shown to people | [messages](files/messages) or [translations](files/i18n) |
