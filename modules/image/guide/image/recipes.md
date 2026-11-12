# Recipes

## A square thumbnail

```php
Image::factory($source)
	->thumbnail(200, 200)
	->sharpen(15)
	->save($target);
```

## An upload, checked and stored in three sizes

```php
public function action_upload(): void
{
	$validation = Validation::factory($_FILES)
		->rule('photo', 'Upload::not_empty')
		->rule('photo', 'Upload::valid')
		->rule('photo', 'Upload::type', [':value', ['jpg', 'jpeg', 'png', 'webp']])
		->rule('photo', 'Upload::size', [':value', '8M'])
		->rule('photo', 'Upload::image', [':value', 8000, 8000]);

	if ( ! $validation->check())
	{
		$this->response->json(['errors' => $validation->errors('upload')], 422);
		return;
	}

	// A name of our own: never the visitor's
	$name = Text::random('hexdec', 32);
	$directory = APPPATH.'uploads/';

	try
	{
		$upload = $_FILES['photo']['tmp_name'];

		// Saving again as WebP also removes anything that is not image data
		// auto_orient() turns photos from phones upright (needs the exif extension)
		Image::factory($upload)->auto_orient()->resize(1600, 1600)->save($directory.$name.'-large.webp', 82);
		Image::factory($upload)->auto_orient()->resize(600, 600)->save($directory.$name.'-medium.webp', 80);
		Image::factory($upload)->auto_orient()->thumbnail(200, 200)->save($directory.$name.'-thumb.webp', 80);
	}
	catch (Image_Exception $e)
	{
		$this->response->json(['errors' => ['photo' => 'That file is not an image we can read.']], 422);
		return;
	}

	$this->response->json(['name' => $name], 201);
}
```

## Sizes made when they are asked for

A route and a controller that make a size the first time it is requested, and keep it.

```php
// application/bootstrap.php: /photo/<name>-<width>.webp
Route::set('photo', 'photo/<name>-<width>.webp', ['name' => '[0-9a-f]{32}', 'width' => '200|400|800|1600'])
	->defaults(['controller' => 'Photo', 'action' => 'show']);
```

```php
class Controller_Photo extends Controller {

	public function action_show(): void
	{
		$name = $this->request->param('name');
		$width = (int) $this->request->param('width');

		$source = APPPATH.'uploads/'.$name.'-large.webp';
		$cached = APPPATH.'cache/photos/'.$name.'-'.$width.'.webp';

		if ( ! is_file($source))
			throw HTTP_Exception::factory(404);

		if ( ! is_file($cached))
		{
			Image::factory($source)->resize($width)->save($cached, 80);
		}

		$this->response->send_file($cached, null, ['inline' => true]);
	}
}
```

[!!] Only allow a fixed list of sizes, as the route above does. A size taken freely from the address lets anyone fill your disk and keep your server busy.

## A watermark on every photo

```php
$mark = Image::factory(APPPATH.'media/watermark.png');

foreach (glob(APPPATH.'uploads/*-large.webp') as $file)
{
	Image::factory($file)
		->watermark($mark, true, true, 40)
		->save();
}
```

## A picture for sharing (1200 x 630)

```php
Image::factory($source)
	->resize(1200, 630, Image::INVERSE)
	->crop(1200, 630)
	->save($target, 85);
```

## Turning a photo the right way up

Phones store photos sideways with a note about the rotation. Read the note and turn the image:

```php
$image = Image::factory($path);

$orientation = @exif_read_data($path)['Orientation'] ?? 1;

match ($orientation)
{
	3 => $image->rotate(180),
	6 => $image->rotate(90),
	8 => $image->rotate(-90),
	default => null,
};

$image->save();
```

Needs the `exif` extension; JPEG only.
