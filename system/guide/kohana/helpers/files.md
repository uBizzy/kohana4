# Files and Uploads

## Receiving an upload

An uploaded file arrives in `$_FILES`. Check it, then save it.

```php
<!-- The form -->
<?php echo Form::open('profile/photo', ['enctype' => 'multipart/form-data']) ?>
	<?php echo Form::csrf() ?>
	<?php echo Form::file('photo') ?>
	<?php echo Form::submit('send', 'Upload') ?>
<?php echo Form::close() ?>
```

```php
// The controller
public function action_photo(): void
{
	$validation = Validation::factory($_FILES)
		->rule('photo', 'Upload::not_empty')
		->rule('photo', 'Upload::valid')
		->rule('photo', 'Upload::type', [':value', ['jpg', 'jpeg', 'png', 'webp']])
		->rule('photo', 'Upload::size', [':value', '2M'])
		->rule('photo', 'Upload::image', [':value', 4000, 4000]);

	if ( ! $validation->check())
	{
		$this->response->body(View::factory('profile/photo', ['errors' => $validation->errors('upload')]));
		return;
	}

	// Saved with a random prefix before its name; returns the full path
	$path = Upload::save($_FILES['photo'], null, APPPATH.'uploads');

	// ...
}
```

| Rule | Checks |
|---|---|
| `Upload::not_empty` | A file was sent |
| `Upload::valid` | The upload arrived whole |
| `Upload::type` | The extension is one of the list |
| `Upload::size` | Not larger than `'2M'`, `'500K'`, `'1GiB'` |
| `Upload::image` | It really is an image, and not larger than width x height |

### Keeping uploads safe

- `Upload::save()` **refuses files a web server could run**: `.php`, `.phtml`, `.phar`, `.htaccess`, `.cgi` and others, also in the middle of a name (`photo.php.jpg`). The list is `Upload::$forbidden_extensions`.
- Save uploads **outside `public/`** and send them through a controller, or in a directory where the server runs no scripts.
- Do not trust the visitor's file name. Without a name of yours, `Upload::save()` puts a random prefix before it, so nobody can guess or overwrite a file; give your own name (an id) when you can.
- Check the type with `Upload::image` for images: an extension can lie.

```php
Upload::forbidden('photo.php.jpg');   // true
Upload::forbidden('photo.jpg');       // false

// You really need to accept .py files
Upload::$forbidden_extensions = array_diff(Upload::$forbidden_extensions, ['py']);
```

### Settings

```php
// application/bootstrap.php
Upload::$default_directory = APPPATH.'uploads';   // used when save() gets no directory
Upload::$remove_spaces = true;                    // "my file.jpg" is saved as "my_file.jpg"
```

## Sending a file

```php
// In a controller: a download with the name the visitor sees
$this->response->send_file(APPPATH.'uploads/'.$document->stored_name, $document->title.'.pdf');
```

See [Responses](../responses).

## Types of files

```php
File::mime('/path/to/photo.jpg');   // 'image/jpeg', read from the content of the file
File::mime_by_ext('pdf');           // 'application/pdf'
File::ext_by_mime('image/png');     // 'png'
File::exts_by_mime('image/jpeg');   // ['jpe', 'jpeg', 'jpg']
```

The table is `config/mimes.php`. Add types in `application/config/mimes.php`.

## Large files in pieces

```php
// Cut a file into pieces of 10 MB: file.zip.001, file.zip.002, ...
$pieces = File::split('/path/file.zip', 10);

// Put them together again
File::join('/path/file.zip');
```

## RSS feeds

```php
// Read: the 5 newest items of a feed
$items = Feed::parse('https://example.org/feed.xml', 5);

foreach ($items as $item)
{
	echo HTML::anchor($item['link'], HTML::chars($item['title']));
}
```

```php
// Write
public function action_feed(): void
{
	$info = ['title' => 'My Shop: news', 'link' => URL::site('news', true), 'description' => 'What is new'];

	$items = [];

	foreach (ORM::factory('Article')->order_by('created', 'DESC')->limit(20)->find_all() as $article)
	{
		$items[] = [
			'title'       => $article->title,
			'link'        => URL::site('article/view/'.$article->id, true),
			'description' => $article->summary,
			'pubDate'     => $article->created,
		];
	}

	$this->response
		->headers('content-type', 'application/rss+xml; charset=utf-8')
		->body(Feed::create($info, $items));
}
```
