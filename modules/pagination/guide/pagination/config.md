# Settings and Views

## Settings

Give them to `Pagination::factory()`, or set the defaults for the application in `application/config/pagination.php`.

```php
// application/config/pagination.php
return [
	'default' => [
		'current_page'      => ['source' => 'query_string', 'key' => 'page'],
		'total_items'       => 0,
		'items_per_page'    => 20,
		'view'              => 'pagination/basic',
		'auto_hide'         => true,
		'first_page_in_url' => false,
	],
];
```

| Setting | Default | Meaning |
|---|---|---|
| `total_items` | `0` | How many items there are in all |
| `items_per_page` | `10` | Items on a page |
| `current_page` | from `?page=` | Where the page number is read from (see below) |
| `view` | `'pagination/basic'` | The view that prints the links |
| `auto_hide` | `true` | Print nothing when there is only one page |
| `first_page_in_url` | `false` | Write `?page=1` in the link to the first page |

## The page number in the address

By default the page is in the query string: `/article?page=3`.

```php
// Another name: /article?p=3
Pagination::factory([
	'total_items'  => $total,
	'current_page' => ['source' => 'query_string', 'key' => 'p'],
]);
```

In the path, as a parameter of the route: `/article/page/3`.

```php
// application/bootstrap.php
Route::set('articles', 'article(/page/<page>)', ['page' => '\d+'])
	->defaults(['controller' => 'Article', 'action' => 'index', 'page' => 1]);
```

```php
Pagination::factory([
	'total_items'  => $total,
	'current_page' => ['source' => 'route', 'key' => 'page'],
]);
```

## Several lists with their own settings

Add groups to the config file; a group only lists what differs from `default`.

```php
// application/config/pagination.php
return [
	'default' => [/* ... */],
	'admin'   => ['items_per_page' => 50, 'view' => 'pagination/floating'],
];
```

```php
$pagination = Pagination::factory(['group' => 'admin', 'total_items' => $total]);
```

## The views

Two views come with the module.

| View | Shows |
|---|---|
| `pagination/basic` | First, Previous, every page number, Next, Last |
| `pagination/floating` | The same, with only the numbers near the current page: for many pages |

```php
// For one list
echo $pagination->render('pagination/floating');
```

The words "First", "Previous", "Next" and "Last" go through [translation](../kohana/files/i18n).

## A view of your own

Copy `modules/pagination/views/pagination/basic.php` to `application/views/pagination/basic.php` and change it; or make a new view and name it in the settings. The view receives every property of the pagination, and `$page`, the pagination object itself, to build addresses.

```php
<!-- application/views/pagination/simple.php -->
<nav class="pages" aria-label="Pages">
	<?php if ($previous_page !== false): ?>
		<a href="<?php echo HTML::chars($page->url($previous_page)) ?>" rel="prev">&larr; Newer</a>
	<?php endif ?>

	<span>Page <?php echo $current_page ?> of <?php echo $total_pages ?></span>

	<?php if ($next_page !== false): ?>
		<a href="<?php echo HTML::chars($page->url($next_page)) ?>" rel="next">Older &rarr;</a>
	<?php endif ?>
</nav>
```

```php
echo $pagination->render('pagination/simple');
```
