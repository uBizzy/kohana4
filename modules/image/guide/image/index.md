# Image

The Image module resizes, crops, rotates and converts images.

```php
// application/bootstrap.php
Kohana::modules([
	'image' => MODPATH.'image',
]);
```

## Choose a driver

```php
// application/config/image.php
return [
	'default_driver' => 'GD',   // or 'Imagick'
];
```

| Driver | Needs | Notes |
|---|---|---|
| `GD` | the `gd` extension | On almost every server. JPEG, PNG, GIF, WebP, BMP, and AVIF when GD was built with it |
| `Imagick` | the `imagick` extension | More formats, among them HEIC, the format of iPhone photos; better quality on large reductions |

See [Formats](using#formats) for what each driver reads and writes.

There is no default: without this setting, `Image::factory()` throws an exception.

## A first image

```php
Image::factory(APPPATH.'uploads/photo.jpg')
	->resize(800, 600)
	->save(APPPATH.'uploads/photo-800.jpg', 85);
```

```php
$image = Image::factory($path);

echo $image->width;    // 800
echo $image->height;   // 600
echo $image->mime;     // image/jpeg
```

A file that is not an image throws an `Image_Exception`.

## What it can do

| Method | Does |
|---|---|
| `resize($width, $height, $master)` | Makes the image smaller or larger |
| `crop($width, $height, $x, $y)` | Cuts a part out |
| `thumbnail($width, $height)` | Fills exactly this size and cuts off the rest |
| `rotate($degrees)` | Turns it |
| `auto_orient()` | Turns a photo from a phone upright |
| `flip($direction)` | Mirrors it |
| `sharpen($amount)` | Makes it crisper |
| `watermark($image, $x, $y, $opacity)` | Puts another image on top |
| `background($color, $opacity)` | Fills transparent parts |
| `reflection($height, $opacity, $fade_in)` | Adds a mirror image below |
| `save($file, $quality)` | Writes a file; the extension decides the format |
| `render($type, $quality)` | Returns the image data |
| `Image::from_string($data)` | Loads an image from data in place of a file |

See [Changing Images](using) and the [Recipes](recipes).

[!!] Images from visitors must be checked before they get here: see [Files and Uploads](../kohana/helpers/files). Large images need a lot of memory: a 6000 x 4000 photo takes about 100 MB while it is worked on.

## Limits

- **Size.** An image of more than 50 million pixels is refused with an `Image_Exception`, before it is loaded. Change it with `Image::$max_pixels`: see [Formats](using#a-limit-on-size).
- **Animation.** Of an animated GIF or WebP only the first picture is kept.
- **Encoders.** ImageMagick can often read a format it cannot write: on many servers it reads AVIF without being able to save it. Saving then throws an `Image_Exception` that says so.
