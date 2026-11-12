# Template Controller

Most pages of a site share a layout: header, menu, footer. `Controller_Template` builds the layout once; each action only fills in its part.

## 1. The layout

```php
<!-- application/views/template.php -->
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?php echo HTML::chars($title) ?> | My Shop</title>
	<?php foreach ($styles as $style) echo HTML::style($style), "\n" ?>
</head>
<body>
	<nav><?php echo View::factory('menu') ?></nav>

	<main><?php echo $content ?></main>

	<?php foreach ($scripts as $script) echo HTML::script($script), "\n" ?>
</body>
</html>
```

## 2. A base controller

```php
// application/classes/Controller/Page.php
abstract class Controller_Page extends Controller_Template {

	// The view of the layout: views/template.php
	public string|View $template = 'template';

	public function before(): void
	{
		// Turns $this->template into the View
		parent::before();

		if ($this->auto_render)
		{
			// Values every page has
			$this->template->title = '';
			$this->template->content = '';
			$this->template->styles = ['css/site.css'];
			$this->template->scripts = [];
		}
	}
}
```

## 3. The pages

```php
// application/classes/Controller/Article.php
class Controller_Article extends Controller_Page {

	public function action_index(): void
	{
		$this->template->title = 'Articles';
		$this->template->content = View::factory('article/list', [
			'articles' => ORM::factory('Article')->find_all(),
		]);
	}

	public function action_view(): void
	{
		$article = ORM::factory('Article', $this->request->param('id'));

		if ( ! $article->loaded())
			throw HTTP_Exception::factory(404, 'Article not found');

		$this->template->title = $article->title;
		$this->template->content = View::factory('article/view', ['article' => $article]);

		// Only this page needs it
		$this->template->scripts[] = 'js/comments.js';
	}
}
```

After the action, `Controller_Template::after()` puts the layout in the response. No action calls `$this->response->body()`.

## Without the layout

For an action that answers something else (JSON, a file, a piece of HTML for JavaScript), switch the layout off:

```php
public function action_search(): void
{
	if ($this->request->is_ajax())
	{
		// Only the results, no layout
		$this->auto_render = false;

		$this->response->body(View::factory('article/results', ['articles' => $found]));
		return;
	}

	$this->template->title = 'Search';
	$this->template->content = View::factory('article/search', ['articles' => $found]);
}
```

## Another layout for some pages

```php
class Controller_Admin extends Controller_Page {

	public string|View $template = 'admin/template';
}
```

## Things to know

- `$template` must be declared `public string|View`, as in the parent class.
- `before()` and `after()` return nothing (`: void`) and must call the parent.
- Set `auto_render` to `false` in `before()` *before* `parent::before()` to skip creating the layout at all.
