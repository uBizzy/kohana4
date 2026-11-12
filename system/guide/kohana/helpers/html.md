# HTML and Forms

`HTML` escapes text and builds tags, `Form` builds form fields, `URL` builds addresses.

## Escaping

Every value that is not HTML of your own must go through `HTML::chars()` before it is printed.

```php
echo HTML::chars('<b>"Tom" & Jerry</b>');
// &lt;b&gt;&quot;Tom&quot; &amp; Jerry&lt;/b&gt;
```

It accepts `null` and numbers, so values from the database can be passed directly. See [XSS](../security/xss).

## Links, images, files

```php
echo HTML::anchor('article/view/1', 'Read');
// <a href="/index.php/article/view/1">Read</a>

// To another site
echo HTML::anchor('https://www.php.net', 'PHP', ['rel' => 'noopener']);

// The text is HTML: escape what comes from outside
echo HTML::anchor('user/'.$user->id, HTML::chars($user->name));

// To a file in public/ (no index.php in the address)
echo HTML::file_anchor('files/terms.pdf', 'Terms');

// An email address, hidden from simple harvesters
echo HTML::mailto('ana@example.org');

echo HTML::image('img/logo.png', ['alt' => 'Logo']);
// <img src="/img/logo.png" alt="Logo" />

echo HTML::style('css/site.css');
echo HTML::script('js/site.js');

// A script with the nonce of a Content-Security-Policy
echo HTML::script('js/site.js', ['nonce' => Security::nonce()]);
```

## Attributes and tags

```php
echo '<div'.HTML::attributes(['class' => 'box', 'id' => 'main']).'>';
// <div id="main" class="box">

echo HTML::tag('span', HTML::chars($label), ['class' => 'tag']);
```

Attribute values are escaped for you.

## Addresses

```php
URL::base();                      // '/'
URL::site('article/view/1');      // '/index.php/article/view/1'
URL::site('article/view/1', true);   // with scheme and domain

// The current query string, with changes
URL::query(['page' => 2]);        // '?sort=title&page=2'

// A title for an address
URL::title('Olá Mundo: PHP 8.4!');             // 'olá-mundo-php-84'
URL::title('Olá Mundo: PHP 8.4!', '-', true);  // 'ola-mundo-php-84'
```

Prefer [`Route::url()`](../routing) for pages that have a route.

## Forms

```php
<?php echo Form::open('article/save') ?>
	<?php echo Form::csrf() ?>

	<?php echo Form::label('title', 'Title') ?>
	<?php echo Form::input('title', $article->title, ['maxlength' => 200, 'required' => true]) ?>

	<?php echo Form::label('body', 'Text') ?>
	<?php echo Form::textarea('body', $article->body, ['rows' => 8]) ?>

	<?php echo Form::label('category', 'Category') ?>
	<?php echo Form::select('category', ['' => 'Choose one', 1 => 'News', 2 => 'Guides'], $article->category_id) ?>

	<label><?php echo Form::checkbox('published', '1', (bool) $article->published) ?> Published</label>

	<?php echo Form::button('save', 'Save', ['type' => 'submit']) ?>
<?php echo Form::close() ?>
```

Values are escaped by the helper: give them as they are.

| Method | Makes |
|---|---|
| `Form::open($action, $attributes)` | `<form method="post">`; `Form::open()` posts to the current page |
| `Form::input()`, `password()`, `hidden()` | text fields |
| `Form::email()`, `url_field()`, `tel()`, `search()` | text fields the browser checks, with the right keyboard on a phone |
| `Form::number()`, `range()`, `date()`, `time()`, `color()` | fields with the browser's own picker |
| `Form::textarea()` | a text area |
| `Form::select($name, $options, $selected)` | a list; an array inside the options makes a group |
| `Form::checkbox()`, `Form::radio()` | with `$checked` as third argument |
| `Form::file()` | a file field |
| `Form::button()`, `Form::submit()` | buttons |
| `Form::label($for, $text)` | a label |
| `Form::csrf()` | the hidden token against forged forms: see [CSRF](../security/csrf) |

### Other kinds of form

```php
// A search form
echo Form::open('search', ['method' => 'get']);

// Uploads need this encoding
echo Form::open('upload', ['enctype' => 'multipart/form-data']);

// Several values
echo Form::select('tags[]', $tags, $selected, ['multiple' => true]);

// HTML5 field types
echo Form::email('email', $email, ['required' => true]);
echo Form::date('day', $day);
echo Form::number('quantity', 1, ['min' => 1, 'max' => 10]);

// Any other type
echo Form::input('week', $week, ['type' => 'week']);
```

### Showing a form again after an error

```php
// Controller
$view->values = Arr::extract($this->request->post(), ['title', 'body']);
$view->errors = $validation->errors('article');
```

```php
<!-- View -->
<?php echo Form::input('title', $values['title']) ?>
<?php if (isset($errors['title'])): ?>
	<p class="error"><?php echo HTML::chars($errors['title']) ?></p>
<?php endif ?>
```
