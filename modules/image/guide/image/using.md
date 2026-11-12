# Changing Images

Methods can be chained. Nothing is written until `save()` or `render()`.

```php
$image = Image::factory(APPPATH.'uploads/photo.jpg');   // 800 x 600
```

## Resize

The image keeps its proportions unless you say otherwise.

```php
// To a width: 400 x 300
$image->resize(400);

// To a height: 267 x 200
$image->resize(null, 200);

// To fit inside a box: 200 x 150
$image->resize(200, 200);

// To cover a box: 267 x 200
$image->resize(200, 200, Image::INVERSE);

// Exactly, stretching if needed: 200 x 200
$image->resize(200, 200, Image::NONE);
```

| `$master` | The result |
|---|---|
| `Image::AUTO` (default) | Fits inside the box |
| `Image::INVERSE` | Covers the box; one side may be larger |
| `Image::NONE` | Exactly the box; proportions are not kept |

## Crop

```php
// The middle 200 x 200
$image->crop(200, 200);

// From the top left corner
$image->crop(200, 200, 0, 0);

// 20 pixels from the left, 50 from the top
$image->crop(200, 200, 20, 50);

// From the bottom right corner
$image->crop(200, 200, true, true);
```

The offsets: a number of pixels, `null` for the middle, `true` for the far side.

## Thumbnail

`thumbnail()` gives an image of exactly the size you ask for: it resizes until the box is filled, then cuts off what sticks out, around the middle. Nothing is distorted.

```php
// 1600 x 900 becomes 200 x 200: the middle square of the photo
$image->thumbnail(200, 200);

// A wide banner from any picture
$image->thumbnail(1200, 300);
```

## Rotate and flip

```php
$image->rotate(90);      // clockwise; 800 x 600 becomes 600 x 800
$image->rotate(-90);     // the other way

$image->flip(Image::HORIZONTAL);
$image->flip(Image::VERTICAL);
```

## Turn a photo upright

Phones and cameras often store a photo on its side, with a note (the EXIF orientation) that tells a viewer how to turn it. A resized copy loses the note and shows up sideways. `auto_orient()` reads the note and turns the image.

```php
// First in the chain: turning swaps the width and the height
Image::factory($photo)
	->auto_orient()
	->resize(1200, 1200)
	->save($target, 85);
```

- Only JPEG files have this note. Other images, and photos that are already upright, are left as they are.
- All eight positions are handled: the three turns and the mirrored ones.
- Calling it twice turns the image once.

[!!] `auto_orient()` needs the `exif` extension of PHP and throws an `Image_Exception` without it.

## Sharpen

Resizing makes an image softer. A little sharpening afterwards helps.

```php
$image->resize(300)->sharpen(20);   // 1 to 100
```

## Watermark

```php
$mark = Image::factory(APPPATH.'media/watermark.png');

// Bottom right corner, half transparent
$image->watermark($mark, true, true, 50);
```

## Background

For formats without transparency, fill the transparent parts first.

```php
// A transparent PNG saved as JPEG, on white
Image::factory('logo.png')
	->background('#ffffff')
	->save('logo.jpg');
```

## Save

The extension decides the format. The quality, 1 to 100, counts for JPEG, WebP and AVIF; it is 85 when you give none, a good balance of size and looks.

```php
// Over the original
$image->save();

// As another file
$image->save(APPPATH.'uploads/photo-small.jpg', 85);

// As another format
$image->save(APPPATH.'uploads/photo.webp', 80);
```

## Formats

| Format | GD | Imagick |
|---|---|---|
| JPEG, PNG, GIF, WebP, BMP | read and write | read and write |
| AVIF | read and write, when GD was built with AVIF support | read and write, when ImageMagick was built with it |
| HEIC / HEIF (iPhone photos) | no | read only, when ImageMagick was built with HEIC support |

With Imagick, reading and writing a format are two things: a server can read AVIF and still lack the encoder to write it. Saving then throws an `Image_Exception` ("This ImageMagick cannot write ..."); it never writes another format under the name you gave.

```php
// Smaller files than JPEG and WebP at the same quality
$image->save(APPPATH.'uploads/photo.avif', 60);
```

A HEIC photo is opened like any other image and saved in a format browsers show:

```php
// Needs the Imagick driver
Image::factory($upload, 'Imagick')
	->resize(1600, 1600)
	->save(APPPATH.'uploads/photo.jpg');
```

```php
// Is this file a HEIC photo? Works with any driver
if (Image::is_heif($upload))
{
	// ...
}
```

- With the GD driver a HEIC file throws an `Image_Exception` that says it needs Imagick.
- A HEIC image cannot be saved as HEIC: give `save()` a file name with another extension.

### An image from data

When the image is not a file (a database column, the answer of another service), load it from its data:

```php
$response = Request::factory('https://example.com/logo.png')->execute();

Image::from_string($response->body())
	->resize(200, 200)
	->save(APPPATH.'uploads/logo.png');
```

Such an image has no file of its own, so `save()` needs a file name.

### A limit on size

A file of a few kilobytes can claim to be an image of 50000 x 50000 pixels, and loading it would use all the memory of the server. Images over `Image::$max_pixels` (width x height) are refused with an `Image_Exception` before they are loaded.

```php
// application/bootstrap.php

// 50 million pixels by default; lower it when you only expect small images
Image::$max_pixels = 12000000;

// No limit
Image::$max_pixels = 0;
```

## Send to the browser

```php
public function action_thumb(): void
{
	$image = Image::factory(APPPATH.'uploads/'.$photo->file)->resize(300, 300);

	$this->response
		->headers('content-type', 'image/webp')
		->headers('cache-control', 'public, max-age=86400')
		->body($image->render('webp', 80));
}
```

## Good values

| For | Use |
|---|---|
| Photos on the web | WebP at 80, or JPEG at 85 |
| Logos, drawings, screenshots | PNG |
| Thumbnails | `resize(..., Image::INVERSE)` then `crop()` |
| Anything a visitor uploaded | Save it again: it removes hidden data and anything that is not image |
