# Pagination

The Pagination module splits a long list over pages and makes the links between them.

```php
// application/bootstrap.php
Kohana::modules([
	'pagination' => MODPATH.'pagination',
]);
```

## In three steps

```php
public function action_index(): void
{
	// 1. How many items are there?
	$total = ORM::factory('Article')->where('published', '=', 1)->count_all();

	// 2. The page links: reads ?page= from the address
	$pagination = Pagination::factory([
		'total_items'    => $total,
		'items_per_page' => 20,
	]);

	// 3. The items of this page
	$articles = ORM::factory('Article')
		->where('published', '=', 1)
		->order_by('created', 'DESC')
		->limit($pagination->items_per_page)
		->offset($pagination->offset)
		->find_all();

	$this->response->body(View::factory('article/list', [
		'articles'   => $articles,
		'pagination' => $pagination,
	]));
}
```

```php
<!-- views/article/list.php -->
<?php foreach ($articles as $article): ?>
	<h2><?php echo HTML::chars($article->title) ?></h2>
<?php endforeach ?>

<?php echo $pagination ?>
```

`/article?page=3` shows items 41 to 60, and the links keep the other parts of the query (`?sort=title&page=3`).

A page number that is too large, too small or not a number gives the nearest real page, so a hand-written address cannot break the query.

## With the query builder

```php
$total = DB::select([DB::expr('COUNT(*)'), 'total'])->from('orders')->execute()->get('total');

$pagination = Pagination::factory(['total_items' => $total]);

$orders = DB::select()->from('orders')
	->order_by('id', 'DESC')
	->limit($pagination->items_per_page)
	->offset($pagination->offset)
	->execute();
```

## What it knows

| Property | Is |
|---|---|
| `$pagination->current_page` | The page shown, from 1 |
| `$pagination->total_pages` | How many pages there are |
| `$pagination->total_items` | How many items there are |
| `$pagination->items_per_page` | Items on a page |
| `$pagination->offset` | Items before this page: for `offset()` |
| `$pagination->current_first_item`, `current_last_item` | "Items 41 to 60" |
| `$pagination->previous_page`, `next_page` | Page numbers, or `false` at the ends |
| `$pagination->first_page`, `last_page` | Page numbers, or `false` when already there |

```php
<p>
	<?php echo __('Items :first to :last of :total', [
		':first' => $pagination->current_first_item,
		':last'  => $pagination->current_last_item,
		':total' => $pagination->total_items,
	]) ?>
</p>
```

## Links of your own

```php
<?php if ($pagination->next_page !== false): ?>
	<a href="<?php echo HTML::chars($pagination->url($pagination->next_page)) ?>" rel="next">Older articles</a>
<?php endif ?>
```

## Good to know

- With one page only, nothing is printed (`auto_hide`).
- Always sort the list (`order_by()`), or the database may return rows in another order on each page.
- For very long lists, `offset()` gets slow on deep pages. There, page by "after id X" instead of by number.
