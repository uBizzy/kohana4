# Fragments

A fragment is a piece of a page that is built once and then served from the cache for a while: a menu built from the database, a list of newest articles.

```php
<?php if ( ! Fragment::load('sidebar.newest', 300)): ?>

	<ul>
	<?php foreach (ORM::factory('Article')->order_by('created', 'DESC')->limit(5)->find_all() as $article): ?>
		<li><?php echo HTML::anchor('article/view/'.$article->id, HTML::chars($article->title)) ?></li>
	<?php endforeach ?>
	</ul>

	<?php Fragment::save() ?>
<?php endif ?>
```

- `Fragment::load($name, $seconds)` prints the cached piece and returns `true`, or returns `false` when there is none (or it is too old).
- In that case the code runs, and `Fragment::save()` stores what it printed.

For five minutes, the query above does not run at all.

## Names

The name decides who shares the piece. Put in it everything the output depends on.

```php
// The same for everybody
Fragment::load('footer', 3600);

// Different for each category
Fragment::load('menu.category.'.$category->id, 600);

// Different for each visitor: usually not worth caching
Fragment::load('cart.'.$user->id, 60);
```

[!!] Never cache a piece that shows private data under a name that other visitors share.

## One piece per language

```php
// A separate copy for each language of I18n::lang()
Fragment::load('menu', 600, true);

// Or for all fragments
Fragment::$i18n = true;
```

## Forgetting a piece

When the data changes, delete the piece so that it is built again:

```php
// After saving an article
Fragment::delete('sidebar.newest');
```

## Fragments inside fragments

```php
<?php if ( ! Fragment::load('page.home', 3600)): ?>

	<h1>Welcome</h1>

	<?php if ( ! Fragment::load('home.newest', 300)): ?>
		... the list ...
		<?php Fragment::save() ?>
	<?php endif ?>

	<?php Fragment::save() ?>
<?php endif ?>
```

The outer piece holds the output of the inner one, so the shorter lifetime only counts when the outer piece is rebuilt.

## Not keeping a piece

Sometimes the output should not be kept, for example because a service it shows was down. End the piece with `Fragment::discard()` in place of `save()`: the output is shown, but built again on the next request.

```php
<?php if ( ! Fragment::load('home.prices', 300)): ?>
	<?php echo View::factory('home/prices', ['prices' => $prices]) ?>
	<?php $prices ? Fragment::save() : Fragment::discard() ?>
<?php endif ?>
```

```php
// Is the piece in the cache? Shows nothing
if ( ! Fragment::has('home.prices', 300))
{
	$prices = $shop->prices();
}

// Remove a piece, so it is built again
Fragment::delete('home.prices');
```

## Settings

```php
// Lifetime when load() is not given one, in seconds
Fragment::$lifetime = 60;
```

Fragments are kept with `Kohana::cache()`: in the [Cache module](../cache) when it is set up, otherwise in files under `application/cache`.

## Fragments or the Cache module?

| You want to keep | Use |
|---|---|
| A piece of HTML | A fragment |
| Data: arrays, objects, results | The [Cache module](../cache) |
| A whole response | `$this->check_cache()` in the controller: see [Responses](responses) |
