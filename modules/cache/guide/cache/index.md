# Cache

The Cache module keeps values that are expensive to make, so the next request can take them ready: the result of a slow query, an answer from another service, a computed list.

```php
// application/bootstrap.php
Kohana::modules([
	'cache' => MODPATH.'cache',
]);
```

```php
// application/config/cache.php: the simplest setup, files on disk
return [
	'file' => [
		'driver'         => 'file',
		'cache_dir'      => APPPATH.'cache',
		'default_expire' => 3600,
	],
];
```

## The pattern

Ask the cache; when the value is not there, make it and store it.

```php
$cache = Cache::instance();

$countries = $cache->get('countries');

if ($countries === null)
{
	$countries = DB::select()->from('countries')->order_by('name')->execute()->as_array('code', 'name');

	// Keep it for a day
	$cache->set('countries', $countries, Date::DAY);
}
```

## Drivers

The same code works with every driver; you choose where the values are kept.

| Driver | Kept in | Good for |
|---|---|---|
| `file` | Files on disk | One server, no extra software |
| `sqlite` | An SQLite file | One server, with tags |
| `apcu` | Memory of the PHP process | One server, very fast |
| `redis` | A Redis server | Several servers, with tags |
| `memcached` | A Memcached server | Several servers |

See [Drivers and Settings](config).

## The framework uses it too

Once the module has a configured default group, `Kohana::cache()` uses it. That covers [fragments](../kohana/fragments), [rate limiting](../kohana/security/throttle) and the framework's own cache of file locations. Without a configured group, they use files under `application/cache`.

## Rules for caching

- Cache what is **slow to make and often asked for**. Measure first: see [Profiling](../kohana/profiling).
- Every cached value needs an answer to "when is it wrong?": a lifetime, or a `delete()` where the data changes.
- Do not cache what differs for each visitor under a name they share.
- A cache can be emptied at any moment. The code must work, only slower, without it.
