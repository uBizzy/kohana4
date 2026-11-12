# Drivers and Settings

Each key of `application/config/cache.php` is a **group**: a driver with its settings. The module ships with every group commented out; define the ones you use.

```php
// application/config/cache.php
return [
	'file' => [
		'driver'         => 'file',
		'cache_dir'      => APPPATH.'cache',
		'default_expire' => 3600,
	],
];
```

```php
// The default group
$cache = Cache::instance();

// A named group
$cache = Cache::instance('redis');

// Which group is the default: in the bootstrap
Cache::$default = 'redis';
```

`default_expire` is the lifetime, in seconds, of values stored without one.

## File

```php
'file' => [
	'driver'           => 'file',
	'cache_dir'        => APPPATH.'cache',
	'default_expire'   => 3600,
	'ignore_on_delete' => ['.gitignore', '.git'],   // kept by delete_all()
],
```

Needs nothing installed. Slower than memory, and each server has its own files. Old files are removed by `garbage_collect()`: run it from a scheduled task.

## SQLite

```php
'sqlite' => [
	'driver'         => 'sqlite',
	'default_expire' => 3600,
	'database'       => APPPATH.'cache/kohana-cache.sql3',
	'schema'         => 'CREATE TABLE caches(id VARCHAR(127) PRIMARY KEY, tags VARCHAR(255), expiration INTEGER, cache TEXT)',
],
```

Needs the `pdo_sqlite` extension. One file, with [tags](usage).

## APCu

```php
'apcu' => [
	'driver'         => 'apcu',
	'default_expire' => 3600,
],
```

Needs the `apcu` extension. Very fast; the values live in the memory of the web server and are lost when it restarts. Not shared between servers, and not with command line tasks.

## Redis

```php
'redis' => [
	'driver'         => 'redis',
	'default_expire' => 3600,
	'cache_prefix'   => 'shop',
	'tag_prefix'     => '_tag',
	'servers'        => [
		'local' => [
			'host'       => 'localhost',   // or 'unix:///var/run/redis/redis.sock'
			'port'       => 6379,
			'persistent' => false,
			'prefix'     => '',
			'password'   => '',
		],
	],
],
```

Needs the `redis` extension. Shared between servers, with [tags](usage). Give each application its own `cache_prefix` when several use one Redis server.

## Memcached

```php
'memcached' => [
	'driver'         => 'memcached',
	'default_expire' => 3600,
	'servers'        => [
		['host' => 'localhost', 'port' => 11211, 'weight' => 1],
	],
	'options' => [],
],
```

Needs the `memcached` extension. Shared between servers. A key can be at most 250 characters and a value 1 MB.

## What each driver can do

| | `file` | `sqlite` | `apcu` | `redis` | `memcached` |
|---|---|---|---|---|---|
| Shared between servers | no | no | no | yes | yes |
| Survives a restart | yes | yes | no | depends on Redis | no |
| Tags | no | yes | no | yes | no |
| Counters (`increment`) | no | no | yes | no | yes |
| Needs cleaning up | yes | yes | no | no | no |

## Different caches for different things

```php
return [
	// Fast and local: things that may differ per server
	'apcu'  => ['driver' => 'apcu', 'default_expire' => 300],

	// Shared: what all servers must agree on
	'redis' => ['driver' => 'redis', 'default_expire' => 3600, 'servers' => ['main' => ['host' => 'cache.internal', 'port' => 6379]]],
];
```

```php
$settings = Cache::instance('apcu')->get('settings');
$stock = Cache::instance('redis')->get('stock.'.$product_id);
```

## In development

Caching hides changes. Use short lifetimes, or empty the cache when something looks stale:

```php
Cache::instance()->delete_all();
```
