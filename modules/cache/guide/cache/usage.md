# Using the Cache

```php
$cache = Cache::instance();
```

## Store, read, delete

```php
// Store for 10 minutes. Arrays and objects are fine
$cache->set('report.sales', $report, 600);

// Store for the default lifetime of the group
$cache->set('report.sales', $report);

// Read: null when it is not there
$report = $cache->get('report.sales');

// Read with a default
$limit = $cache->get('settings.limit', 20);

// Delete one value
$cache->delete('report.sales');

// Delete everything in this group
$cache->delete_all();
```

## Read or make

```php
$report = $cache->get('report.sales');

if ($report === null)
{
	$report = Model::factory('Report')->sales();

	$cache->set('report.sales', $report, 600);
}
```

A helper of your own saves writing it each time:

```php
// application/classes/Cache.php
abstract class Cache extends Kohana_Cache {

	/**
	 * Returns the cached value, making and storing it when it is missing.
	 */
	public function remember(string $id, int $lifetime, callable $make): mixed
	{
		$value = $this->get($id);

		if ($value === null)
		{
			$value = $make();

			$this->set($id, $value, $lifetime);
		}

		return $value;
	}
}
```

```php
$report = Cache::instance()->remember('report.sales', 600, fn () => Model::factory('Report')->sales());
```

[!!] `null` means "not cached". To cache "there is nothing", store something else: `false` or an empty array.

## Names

The name is all that identifies a value. Put in it everything the value depends on.

```php
$cache->set('product.'.$id, $product);
$cache->set('products.category.'.$category_id.'.page.'.$page, $list);
$cache->set('menu.'.I18n::lang(), $menu);
```

## Forgetting when data changes

```php
class Model_Product extends ORM {

	public function save(?Validation $validation = null): static
	{
		parent::save($validation);

		// The cached copy is now wrong
		Cache::instance()->delete('product.'.$this->id);

		return $this;
	}
}
```

## Tags: forgetting many values at once

With the `sqlite` and `redis` drivers, values can carry tags, and all values of a tag can be deleted together.

```php
$cache = Cache::instance('redis');

$cache->set_with_tags('product.12', $product, 3600, ['products', 'category.3']);
$cache->set_with_tags('products.category.3.page.1', $list, 3600, ['products', 'category.3']);

// Something in category 3 changed
$cache->delete_tag('category.3');

// Everything with a tag
$values = $cache->find('products');
```

Check that the driver can do it when the code must work with any:

```php
if ($cache instanceof Cache_Tagging)
{
	$cache->delete_tag('products');
}
else
{
	$cache->delete_all();
}
```

## Counters

With the `apcu` and `memcached` drivers:

```php
$cache = Cache::instance('apcu');

$cache->set('downloads.'.$id, 0);

$cache->increment('downloads.'.$id);       // 1
$cache->increment('downloads.'.$id, 10);   // 11
$cache->decrement('downloads.'.$id);       // 10
```

## Cleaning up

The `file` and `sqlite` drivers keep expired values until they are asked for. Remove them now and then, from a [Minion](../minion) task run by cron:

```php
$cache = Cache::instance();

if ($cache instanceof Cache_GarbageCollect)
{
	$cache->garbage_collect();
}
```

## Caching whole responses of other requests

`HTTP_Cache` keeps the responses of requests, by the caching headers they carry (`Cache-Control: max-age=...`). Useful for calls to other services and for parts of a page built by sub-requests.

```php
$request = Request::factory('https://api.example.org/rates');

// Responses are kept in the default cache group, for as long as their headers allow
$request->client()->cache(HTTP_Cache::factory(Cache::instance()));

$response = $request->execute();

echo $response->headers('x-cache-status');   // MISS the first time, then HIT
```

## Is the cache doing its work?

The module counts how often a value was found and how often it was not, for the whole request.

```php
echo Cache::$stats['hits'];     // values that were found
echo Cache::$stats['misses'];   // values that were not
```

The debug bar of the `ub_debug` module shows these numbers while you develop, id by id.

## What can go wrong

| Problem | Cause |
|---|---|
| A change does not show | The old value is still cached: delete it where the data changes |
| Visitors see each other's data | The name does not include what differs between them |
| Errors about a class when reading | An object was cached and its class changed: delete the value |
| `Cache_Exception: Failed to load Kohana Cache group` | The group is not in `config/cache.php` |
