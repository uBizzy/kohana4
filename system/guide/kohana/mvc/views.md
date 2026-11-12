# Views

A view is a PHP file that produces output, usually HTML. Views are in the `views/` directories; `View::factory('article/view')` is `views/article/view.php`.

```php
<!-- application/views/article/view.php -->
<article>
	<h1><?php echo HTML::chars($article->title) ?></h1>
	<p>By <?php echo HTML::chars($author) ?></p>
</article>
```

```php
// In a controller
$view = View::factory('article/view');
$view->article = $article;
$view->author = 'Ana';

$this->response->body($view);
```

[!!] Print every value that is not HTML of your own with `HTML::chars()`. See [XSS](../security/xss).

## Giving values to a view

```php
// One by one
$view = View::factory('article/view');
$view->article = $article;

// In one go
$view = View::factory('article/view', ['article' => $article, 'author' => 'Ana']);

// With set(), which can be chained
$view = View::factory('article/view')
	->set('article', $article)
	->set('author', 'Ana');

// By reference: the view sees the value the variable has when it is rendered
$view->bind('errors', $errors);
$errors = $validation->errors('article');
```

## Values for every view

```php
// Seen by all views, in this request
View::set_global('site_name', 'My Shop');
View::set_global(['user' => $user, 'year' => date('Y')]);
```

## Views inside views

```php
<!-- views/article/list.php -->
<?php foreach ($articles as $article): ?>
	<?php echo View::factory('article/teaser', ['article' => $article]) ?>
<?php endforeach ?>
```

A view can also be a value of another view. This is how a layout works:

```php
$page = View::factory('layout');
$page->title = 'Articles';
$page->content = View::factory('article/list', ['articles' => $articles]);

$this->response->body($page);
```

```php
<!-- views/layout.php -->
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?php echo HTML::chars($title) ?></title>
</head>
<body>
	<?php echo $content ?>
</body>
</html>
```

See [Template Controller](../tutorials/controller-template) to do this once for all pages.

## Writing view files

Use the alternative syntax of PHP, which reads well between HTML:

```php
<?php if ($articles): ?>
	<ul>
	<?php foreach ($articles as $article): ?>
		<li><?php echo HTML::anchor('article/view/'.$article->id, HTML::chars($article->title)) ?></li>
	<?php endforeach ?>
	</ul>
<?php else: ?>
	<p><?php echo __('No articles yet.') ?></p>
<?php endif ?>
```

- Keep logic out of views: loops and conditions to show data, nothing more.
- No database queries in views. Give the view what it needs.

## Rendering to a string

A view becomes a string when it is printed or given to the response. To get the string yourself:

```php
$html = View::factory('mail/welcome', ['user' => $user])->render();
```

## Errors in views

An error inside a view is reported with the file and line of the view itself. A value the view uses but was not given is an "Undefined variable" error on that line. A view file that does not exist throws a `View_Exception`.
