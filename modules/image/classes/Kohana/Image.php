<?php
/**
 * Image manipulation support.
 * Allows images to be resized, cropped, etc.
 *
 * @package        Kohana/Image
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license        https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Image {

	/**
	 * Resize image by given values, regardless of original dimensions
	 * e.g Image is 100x200, you resize to 50x80, image will be 50x80
	 */
	public const int NONE = 0x01;

	/**
	 * Automatically choose resize direction with the highest reduction ratio *keeping* original dimensions
	 * e.g Image is 100x200, you resize to 70x50, highest ratio = 4 - image will be 25x50
	 */
	public const int AUTO = 0x04;

	/**
	 * Automatically choose resize direction with the lowest reduction ratio *keeping* original dimensions
	 * e.g Image is 100x200, you resize to 70x50, lowest ratio = 1,4285 image will be 70x140
	 */
	public const int INVERSE = 0x05;

	/**
	 * FLIP horizontal
	 */
	public const int HORIZONTAL = 0x11;

	/**
	 * Flip vertical
	 */
	public const int VERTICAL = 0x12;

	/**
	 * True if driver dependencies are satisfied, false if not
	 * @var bool
	 */
	protected static bool $_checked = false;

	/**
	 * @var  int  Largest image that is loaded, in pixels (width x height);
	 *            0 for no limit. The default allows photos of 50 megapixels.
	 */
	public static int $max_pixels = 50000000;

	/**
	 * @var  bool  Is the file a temporary one, made by [Image::from_string]?
	 */
	protected bool $_temporary = false;

	/**
	 * Path to Image File
	 * @var  string
	 */
	public string $file;

	/**
	 * Image Width
	 * @var  int
	 */
	public int $width;

	/**
	 * Image Height
	 * @var  int
	 */
	public int $height;

	/**
	 * Image Type (IMAGETYPE_* constants)
	 * @var  int
	 */
	public int $type;

	/**
	 * Image mime type
	 * @var  string
	 */
	public string $mime;

	/**
	 * Loads an image and prepares it for manipulation.
	 *
	 * @param string $file	  Path to the Image file
	 * @param string $driver  Driver to use (overrides driver which is set in config)
	 *
	 * @throws Image_Exception
	 * @throws Kohana_Exception
	 *
	 * @return Image
	 */
	public static function factory(string $file, ?string $driver = null): Image
	{
		// Use the driver of the configuration file when none is given
		$driver ??= Kohana::$config->load('image.default_driver');

		if ( ! is_string($driver) OR $driver === '')
			throw new Image_Exception('Please specify a driver in your image configuration.');

		// Set the class name
		$class = 'Image_'.$driver;

		if ( ! class_exists($class) OR ! is_subclass_of($class, 'Image'))
			throw new Image_Exception('Driver: ":driver" is not a valid Image Driver.', [':driver' => $driver]);

		// Check that the extension the driver needs is there, before it is used
		// @codeCoverageIgnoreStart
		if ( ! ($class::$_checked = $class::check()))
			throw new Image_Exception('Dependencies for driver :driver not satisfied. Please check docs.', [':driver' => $driver]);
		// @codeCoverageIgnoreEnd

		return new $class($file);
	}

	/**
	 * Loads an image from its data, for images that do not come from a
	 * file: a database column, an API, a download.
	 *
	 *     $image = Image::from_string($response->body());
	 *     $image->resize(300, 300)->save($target);
	 *
	 * The image has no file of its own, so [Image::save] needs a file name.
	 *
	 * @param   string       $data    content of an image file
	 * @param   string|null  $driver  driver type: GD, Imagick, etc
	 * @return  Image
	 * @throws  Image_Exception  when the data is not an image the driver can read
	 */
	public static function from_string(string $data, ?string $driver = null): Image
	{
		// The drivers work on files: keep the data in a temporary one,
		// which is removed together with the image object
		$file = tempnam(sys_get_temp_dir(), 'kohana_image_');

		try
		{
			file_put_contents($file, $data);

			$image = Image::factory($file, $driver);
		}
		catch (Throwable $e)
		{
			@unlink($file);

			throw $e;
		}

		$image->_temporary = true;

		return $image;
	}

	/**
	 * Tests if a file is a HEIC/HEIF image, the format of iPhone photos.
	 * PHP itself does not know this format before version 8.5.
	 *
	 *     if (Image::is_heif($upload['tmp_name'])) ...
	 *
	 * @param   string  $file  path of the file
	 * @return  bool
	 */
	public static function is_heif(string $file): bool
	{
		// Bytes 4 to 12 of the file name the format: "ftyp" and a brand
		$start = (string) @file_get_contents($file, false, null, 4, 8);

		return in_array($start, ['ftypheic', 'ftypheix', 'ftyphevc', 'ftyphevx', 'ftypheim', 'ftypheis', 'ftypmif1', 'ftypmsf1'], true);
	}

	/**
	 * Loads information about the image. Will throw an exception if the image
	 * does not exist or is not an image.
	 *
	 * @param string $file Image file path
	 *
	 * @return  void
	 *@throws  Image_Exception
	 *
	 */
	public function __construct(string $file)
	{
		// Get the real path to the file
		$path = ($file === '') ? false : realpath($file);

		if ($path === false OR ! is_file($path) OR ! is_readable($path))
			throw new Image_Exception('Not an image or invalid image: :file', [':file' => Debug::path($file)]);

		if (Image::is_heif($path))
		{
			// PHP cannot measure this format: the driver has to
			[$width, $height] = $this->_heif_size($path);

			$type = IMAGETYPE_UNKNOWN;
			$mime = 'image/heic';
		}
		else
		{
			// Get the image information
			$info = @getimagesize($path);

			if ($info === false)
				throw new Image_Exception('The file is not an image :file', [':file' => Debug::path($file)]);

			[$width, $height, $type] = $info;

			$mime = image_type_to_mime_type($type);

			// Check if image type is supported by our driver
			if ( ! $this->_is_supported_type($type))
				throw new Image_Exception('Image extension ":ext", is unknown by driver ":driver".', [
					':ext' => image_type_to_extension($type),
					':driver' => get_class($this)
				]);
		}

		// A small file can claim to be a huge image, and loading it would
		// use up all the memory: refuse it before anything is loaded
		if (Image::$max_pixels > 0 AND $width * $height > Image::$max_pixels)
			throw new Image_Exception('The image :file is too large: :width x :height pixels, the limit is :max pixels', [
				':file'   => Debug::path($file),
				':width'  => $width,
				':height' => $height,
				':max'    => Image::$max_pixels,
			]);

		// Store the image information
		$this->file = $path;
		$this->width = $width;
		$this->height = $height;
		// Type https://www.php.net/manual/en/function.image-type-to-mime-type.php
		$this->type = $type;
		$this->mime = $mime;
	}

	/**
	 * Removes the temporary file of an image made by [Image::from_string].
	 */
	public function __destruct()
	{
		if ($this->_temporary)
		{
			@unlink($this->file);
		}
	}

	/**
	 * Returns the width and height of a HEIC/HEIF image. Only drivers that
	 * can read this format replace this method.
	 *
	 * @param   string  $file  path of the file
	 * @return  array{int, int}  list of width and height
	 * @throws  Image_Exception  when the driver cannot read HEIC images
	 */
	protected function _heif_size(string $file): array
	{
		throw new Image_Exception('HEIC images cannot be read by the :driver driver, they need the Imagick driver', [
			':driver' => get_class($this),
		]);
	}

	/**
	 * Returns the file extension of the current image type, to save or
	 * render an image in the format it has.
	 *
	 * @return  string
	 * @throws  Image_Exception  when the image has a format that can only be read (HEIC)
	 */
	protected function _current_extension(): string
	{
		$extension = image_type_to_extension($this->type, false);

		if ( ! $extension)
			throw new Image_Exception('This image (:mime) can only be saved in another format: give a file name or extension such as "jpg" or "webp"', [
				':mime' => $this->mime,
			]);

		return $extension;
	}

	/**
	 * Resizes the image so it fills the given size, then cuts off what
	 * sticks out, around the middle. The result always has exactly this
	 * size and is not distorted.
	 *
	 *     // A square thumbnail of any photo
	 *     $image->thumbnail(200, 200);
	 *
	 * @param   int  $width   width of the thumbnail
	 * @param   int  $height  height of the thumbnail
	 * @return  static
	 */
	public function thumbnail(int $width, int $height): static
	{
		return $this->resize($width, $height, Image::INVERSE)->crop($width, $height);
	}

	/**
	 * Resize the image to the given size. Either the width or the height can
	 * be omitted and the image will be resized proportionally.
	 *
	 * @param ?int $width  New width
	 * @param ?int $height New height
	 * @param ?int $master Master dimension
	 *
	 * @throws Image_Exception
	 *
	 * @return  static
	 */
	public function resize(?int $width = null, ?int $height = null, ?int $master = null): static
	{
		// Check if at least width or height was set
		if ($width === null && $height === null)
		{
			throw new Image_Exception('Please specify at least a width or a height.');
		}

		// Choose the master dimension automatically
		if ($master === null)
		{
			$master = Image::AUTO;
		}
		elseif ( ! in_array($master, [Image::AUTO, Image::INVERSE, Image::NONE], true))
		{
			throw new Image_Exception('Invalid Master dimension, please specify a correct one');
		}

		// Set width if not set
		if ($width === null && $master !== Image::NONE)
		{
			$width = $this->width * $height / $this->height;
		}

		// Set height if not set
		if ($height === null && $master !== Image::NONE)
		{
			$height = $this->height * $width / $this->width;
		}

		// Resize to exact given width/height
		if ($master === Image::NONE)
		{
			// A side that is not given keeps its size
			$width ??= $this->width;
			$height ??= $this->height;
		}

		// Resize with the greatest / lowest reduction ratio
		elseif (($this->width / $width) > ($this->height / $height) === ($master === Image::AUTO))
		{
			$height = $this->height * $width / $this->width;
		}
		else
		{
			$width = $this->width * $height / $this->height;
		}

		// Convert the width and height to integers, minimum value is 1px
		$width = (int) max(round( (float) $width), 1);
		$height = (int) max(round( (float) $height), 1);

		// Call driver resize function
		$this->_do_resize($width, $height);

		return $this;
	}

	/**
	 * Crop an image to the given size. Either the width or the height can be
	 * omitted and the current width or height will be used.
	 *
	 * If no offset is specified, the center of the axis will be used.
	 * If an offset of true is specified, the bottom of the axis will be used.
	 *
	 * @param int $width    New width
	 * @param int $height   New height
	 * @param bool|int|null   $offset_x Offset from the left - Set to true to start from right
	 * @param bool|int|null   $offset_y Offset from the top - Set to true to start from bottom
	 *
	 * @return  static
	 */
	public function crop(int $width, int $height, int|bool|null $offset_x = null, int|bool|null $offset_y = null): static
	{
		if ($width > $this->width)
		{
			// Use the current width
			$width = $this->width;
		}

		if ($height > $this->height)
		{
			// Use the current height
			$height = $this->height;
		}

		if ($offset_x === null)
		{
			// Center the X offset
			$offset_x = round(($this->width - $width) / 2);
		}
		elseif ($offset_x === true)
		{
			// Right the X offset
			$offset_x = $this->width - $width;
		}
		elseif ($offset_x < 0)
		{
			// Set the X offset from the right
			$offset_x = $this->width - $width + $offset_x;
		}

		if ($offset_y === null)
		{
			// Center the Y offset
			$offset_y = round(($this->height - $height) / 2);
		}
		elseif ($offset_y === true)
		{
			// Bottom the Y offset
			$offset_y = $this->height - $height;
		}
		elseif ($offset_y < 0)
		{
			// Set the Y offset from the bottom
			$offset_y = $this->height - $height + $offset_y;
		}

		// Determine the maximum possible width and height
		$max_width = $this->width - $offset_x;
		$max_height = $this->height - $offset_y;

		if ($width > $max_width)
		{
			// Use the maximum available width
			$width = $max_width;
		}

		if ($height > $max_height)
		{
			// Use the maximum available height
			$height = $max_height;
		}

		// Call image driver crop
		$this->_do_crop( (int) $width, (int) $height, (int) $offset_x, (int) $offset_y);

		return $this;
	}

	/**
	 * Rotate the image by a given amount.
	 *
	 * @param int $degrees degrees to rotate
	 *
	 * @return  static
	 */
	public function rotate(int $degrees): static
	{
		// Make the degrees an integer
		$degrees = (int)$degrees;

		// Don't rotate if 360 degree
		if ($degrees % 360 === 0)
		{
			return $this;
		}

		if ($degrees > 180)
		{
			do
			{
				// Keep subtracting full circles until the degrees have normalized
				$degrees -= 360;
			} while ($degrees > 180);
		}

		if ($degrees < -180)
		{
			do
			{
				// Keep adding full circles until the degrees have normalized
				$degrees += 360;
			} while ($degrees < -180);
		}

		// Call Image driver rotation function
		$this->_do_rotate($degrees);

		return $this;
	}

	/**
	 * @var  bool  Was the image already turned upright by [Image::auto_orient]?
	 */
	protected bool $_oriented = false;

	/**
	 * Turns a photo upright. Cameras and phones often store a photo
	 * sideways, with a note (the EXIF orientation) that says how to turn
	 * it. This method reads the note and turns the image accordingly.
	 *
	 *     // Call it first: turning swaps the width and the height
	 *     $image->auto_orient()->resize(1200, 1200)->save();
	 *
	 * Only JPEG files have this note. Any other image, and a photo that is
	 * already upright, is left as it is.
	 *
	 * [!!] Needs the "exif" extension of PHP.
	 *
	 * @return  static
	 * @throws  Image_Exception  when the exif extension is missing
	 */
	public function auto_orient(): static
	{
		if ( ! function_exists('exif_read_data'))
			throw new Image_Exception('Image::auto_orient() needs the "exif" extension of PHP');

		// The note is read from the file, so turning twice would be wrong
		if ($this->_oriented OR $this->type !== IMAGETYPE_JPEG)
			return $this;

		$this->_oriented = true;

		// A photo without the note, or with a damaged one, is left alone
		$exif = @exif_read_data($this->file);

		// 1 is upright; 2 to 8 are the seven ways a photo can be turned or mirrored
		$orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;

		switch (is_numeric($orientation) ? (int) $orientation : 1)
		{
			case 2: $this->flip(Image::HORIZONTAL); break;
			case 3: $this->rotate(180); break;
			case 4: $this->flip(Image::VERTICAL); break;
			case 5: $this->rotate(90)->flip(Image::HORIZONTAL); break;
			case 6: $this->rotate(90); break;
			case 7: $this->rotate(-90)->flip(Image::HORIZONTAL); break;
			case 8: $this->rotate(-90); break;
			default: return $this;
		}

		// The image is upright now: the note must not be used a second time
		$this->_clear_orientation();

		return $this;
	}

	/**
	 * Removes the orientation note from the image, for drivers that keep
	 * such notes when they save. Called after [Image::auto_orient] turned
	 * the image.
	 *
	 * @return  void
	 */
	protected function _clear_orientation(): void
	{
		// Nothing to do by default
	}

	/**
	 * Flip the image along the horizontal or vertical axis.
	 *
	 * @param int $direction Flip direction (Image::HORIZONTAL, Image::VERTICAL)
	 *
	 * @return static
	 */
	public function flip(int $direction): static
	{
		if ($direction !== Image::HORIZONTAL)
		{
			// Flip vertically
			$direction = Image::VERTICAL;
		}

		// Call image driver flip function
		$this->_do_flip($direction);

		return $this;
	}

	/**
	 * Sharpen the image by a given amount.
	 *
	 * @param int $amount amount to sharpen (1-100)
	 *
	 * @return  static
	 */
	public function sharpen(int $amount): static
	{
		// The amount must be in the range of 1 to 100
		$amount = min(max($amount, 1), 100);

		// Call image driver sharpen function
		$this->_do_sharpen($amount);

		return $this;
	}

	/**
	 * Add a reflection to an image. The most opaque part of the reflection
	 * will be equal to the opacity setting and fade out to full transparent.
	 * Alpha transparency is preserved.
	 *
	 * @param int $height  Reflection height
	 * @param int $opacity Reflection opacity: 0-100
	 * @param bool $fade_in true to fade in, false to fade out
	 *
	 * @return  static
	 */
	public function reflection(?int $height = null, int $opacity = 100, bool $fade_in = false): static
	{
		if ($height === null || $height > $this->height)
		{
			// Use the current height
			$height = $this->height;
		}

		// The opacity must be in the range of 0 to 100
		$opacity = min(max($opacity, 0), 100);

		// Call image driver reflection function
		$this->_do_reflection($height, $opacity, $fade_in);

		return $this;
	}

	/**
	 * Add a watermark to an image with a specified opacity. Alpha transparency
	 * will be preserved.
	 *
	 * @param Image   $watermark watermark Image instance
	 * @param int $offset_x  Offset from the left
	 * @param int $offset_y  Offset from the top
	 * @param int $opacity   Opacity of watermark: 1-100
	 *
	 * @throws  Image_Exception
	 *
	 * @return  static
	 */
	public function watermark(Image $watermark, int|bool|null $offset_x = null, int|bool|null $offset_y = null, int $opacity = 100): static
	{
		// If watermark is to big, resize it to fit image
		if ($watermark->height > $this->height || $watermark->width > $this->width)
		{
			$watermark->resize($this->width, $this->height, self::AUTO);
		}

		if ($offset_x === null)
		{
			// Center the X offset
			$offset_x = round(($this->width - $watermark->width) / 2);
		}
		elseif ($offset_x === true)
		{
			// Right the X offset
			$offset_x = $this->width - $watermark->width;
		}
		elseif ($offset_x < 0)
		{
			// Set the X offset from the right
			$offset_x = $this->width - $watermark->width + $offset_x;
		}

		if ($offset_y === null)
		{
			// Center the Y offset
			$offset_y = round(($this->height - $watermark->height) / 2);
		}
		elseif ($offset_y === true)
		{
			// Bottom the Y offset
			$offset_y = $this->height - $watermark->height;
		}
		elseif ($offset_y < 0)
		{
			// Set the Y offset from the bottom
			$offset_y = $this->height - $watermark->height + $offset_y;
		}

		// The opacity must be in the range of 1 to 100
		$opacity = min(max($opacity, 1), 100);

		// Call image driver watermark function
		$this->_do_watermark($watermark, (int) $offset_x, (int) $offset_y, $opacity);

		return $this;
	}

	/**
	 * Set the background color of an image. This is only useful for images
	 * with alpha transparency.
	 *
	 * @param string  $color   Hexadecimal color value
	 * @param int $opacity Background opacity: 0-100
	 *
	 * @return  $this
	 */
	public function background(string $color, int $opacity = 100): static
	{
		if (strpos($color, '#') === 0)
		{
			// Remove the pound
			$color = substr($color, 1);
		}

		if (strlen($color) === 3)
		{
			// Convert shorthand into longhand hex notation
			$color = (string) preg_replace('/./', '$0$0', $color);
		}

		// Convert the hex into RGB values
		[$r, $g, $b] = array_map('intval', array_map('hexdec', str_split($color, 2)));

		// The opacity must be in the range of 0 to 100
		$opacity = min(max($opacity, 0), 100);

		// Call image driver background function
		$this->_do_background($r, $g, $b, $opacity);

		return $this;
	}

	/**
	 * Save the image. If the filename is omitted, the original image will
	 * be overwritten.
	 *
	 * @param string  $file    new image path
	 * @param int $quality quality of image: 1-100
	 *
	 * @throws  Image_Exception
	 *
	 * @return  bool
	 */
	public function save(?string $file = null, int $quality = 85): bool
	{
		if ($file === null)
		{
			if ($this->_temporary)
				throw new Image_Exception('An image made by Image::from_string() has no file of its own: give save() a file name');

			// Overwrite the file
			$file = $this->file; //@codeCoverageIgnore
		}

		// Get File Extension
		$extension = pathinfo($file, PATHINFO_EXTENSION);

		if ( ! $extension)
		{
			// Use the current image type
			$extension = $this->_current_extension();
		}

		// Check if it is supported. A format PHP has no type for (HEIC,
		// or a mistyped extension) is never written.
		// @codeCoverageIgnoreStart
		$type = self::extension_to_image_type($extension);

		if ($type === IMAGETYPE_UNKNOWN OR ! $this->_is_supported_type($type))
		{
			throw new Image_Exception('Image extension ":ext", is unknown by driver ":driver".', [
				':ext' => $extension,
				':driver' => get_class($this)
			]);
		}

		if (is_file($file))
		{
			if ( ! is_writable($file))
			{
				throw new Image_Exception('File must be writable: :file', [':file' => Debug::path($file)]);
			}
		}
		// @codeCoverageIgnoreEnd
		else
		{
			// Get the directory of the file
			$directory = realpath(pathinfo($file, PATHINFO_DIRNAME));

			// Check if is the directory is writable (realpath() gives false when there is none)
			if ($directory === false || ! is_dir($directory) || ! is_writable($directory))
			{
				throw new Image_Exception('Directory must be writable: :directory', [
					':directory' => Debug::path(pathinfo($file, PATHINFO_DIRNAME))
				]);
			}
		}

		// The quality must be in the range of 1 to 100
		$quality = min(max($quality, 1), 100);

		// Call image driver save function
		return $this->_do_save($file, $quality);
	}

	/**
	 * Render the image and return the binary string.
	 *
	 * @param string  $extension    Image type to return: png, jpg, gif, etc
	 * @param int $quality		Quality of image: 1-100
	 *
	 * @return  string
	 */
	public function render(?string $extension = null, int $quality = 85): string
	{
		if ($extension === null)
		{
			// Use the current image type
			$extension = $this->_current_extension();
		}
		elseif (strpos($extension, '.') === 0)
		{
			// remove "." from image extension
			$extension = substr($extension, 1);
		}

		if (self::extension_to_image_type($extension) === IMAGETYPE_UNKNOWN)
			throw new Image_Exception('Image extension ":ext", is unknown by driver ":driver".', [
				':ext' => $extension,
				':driver' => get_class($this)
			]);

		// The quality must be in the range of 1 to 100
		$quality = min(max($quality, 1), 100);

		// Call image driver render function
		return $this->_do_render($extension, $quality);
	}

	/**
	 * Render the current image.
	 *
	 *     echo $image;
	 *
	 * [!!] The output of this function is binary and must be rendered with the
	 * appropriate Content-Type header or it will not be displayed correctly!
	 *
	 * @codeCoverageIgnore We can ignore this, since it just calls render()
	 *
	 * @return  string
	 */
	public function __toString(): string
	{
		// __toString() must not throw an exception so we catch and only log it
		try
		{
			// Render the current image
			return $this->render();
		}
		catch (Exception $e)
		{
			if (is_object(Kohana::$log))
			{
				// Get the text of the exception
				$error = Image_Exception::text($e);

				// Add this exception to the log
				Kohana::$log->add(Log::ERROR, $error);
			}

			// Showing any kind of error will be "inside" image data
			return '';
		}
	}

	/**
	 *
	 * Convert Image Extension to Image Type Constant
	 *
	 * @param string $extension	 Image Extension String
	 *
	 * @return int
	 */
	public static function extension_to_image_type(string $extension): int
	{
		// Convert to lowercase
		$extension = strtolower($extension);

		// Remove dot form extension if there
		if (strpos($extension, '.') === 0)
		{
			$extension = substr($extension, 1);
		}

		// jpe and jpg are both valid extensions for jpeg
		if (in_array($extension, [
			'jpe',
			'jpg',
		]))
		{
			$extension = 'jpeg';
		}

		// Convert to imagetype
		$type = 'IMAGETYPE_' . strtoupper($extension);

		// Check if constant exists return if so, else Unknown image type
		$value = defined($type) ? constant($type) : IMAGETYPE_UNKNOWN;

		return is_int($value) ? $value : IMAGETYPE_UNKNOWN;
	}

	/**
	 * Check if ImageType is supported
	 *
	 * @param int $type	ImageType Constant
	 *
	 * @return bool
	 */
	abstract protected function _is_supported_type(int $type): bool;

	/**
	 * Resize Image
	 *
	 * @param int $width  New width
	 * @param int $height New height
	 *
	 * @return  bool  True on successful resize, false otherwise
	 */
	abstract protected function _do_resize(int $width, int $height): bool;

	/**
	 * Crop image.
	 *
	 * @param int $width    New width
	 * @param int $height   New height
	 * @param int $offset_x Offset from the left
	 * @param int $offset_y Offset from the top
	 *
	 * @return  bool  True on successful crop, false otherwise
	 */
	abstract protected function _do_crop(int $width, int $height, int $offset_x, int $offset_y): bool;

	/**
	 * Rotate image
	 *
	 * @param int $degrees Degrees to rotate
	 *
	 * @return  bool  True on successful rotation, false otherwise
	 */
	abstract protected function _do_rotate(int $degrees): bool;

	/**
	 * Flip the Image
	 *
	 * @param int $direction Direction to flip
	 *
	 * @return  bool True on successful flip, false otherwise
	 */
	abstract protected function _do_flip(int $direction): bool;

	/**
	 * Sharpen Image
	 *
	 * @param int $amount Amount to sharpen (sigma)
	 *
	 * @return  bool True on successful sharpen, false otherwise
	 */
	abstract protected function _do_sharpen(int $amount): bool;

	/**
	 * Create an image reflection effect
	 *
	 * @param int $height  Reflection height
	 * @param int $opacity Reflection opacity
	 * @param bool $fade_in true to fade out, false to fade in
	 *
	 * @return  bool True if reflection applied successful, false otherwise
	 */
	abstract protected function _do_reflection(int $height, int $opacity, bool $fade_in): bool;

	/**
	 * Watermark image.
	 *
	 * @param Image   $image    The Watermark (has to be an Image)
	 * @param int $offset_x Offset from the left
	 * @param int $offset_y Offset from the top
	 * @param int $opacity  Opacity of watermark
	 *
	 * @return  bool True if Watermark applied successful, false otherwise
	 */
	abstract protected function _do_watermark(Image $image, int $offset_x, int $offset_y, int $opacity): bool;

	/**
	 * Change / Add image background (RGB)
	 *
	 * @param int $r       Red
	 * @param int $g       Green
	 * @param int $b       Blue
	 * @param int $opacity Opacity of background
	 *
	 * @return  bool True if background applied successful, false otherwise
	 */
	abstract protected function _do_background(int $r, int $g, int $b, int $opacity): bool;

	/**
	 * Save the image
	 *
	 * @param string  $file    New image filename
	 * @param int $quality Quality
	 *
	 * @return  bool True if saved successful, false otherwise
	 */
	abstract protected function _do_save(string $file, int $quality): bool;

	/**
	 * Render the Image
	 *
	 * @param string  $type    Image type: png, jpg, gif, etc
	 * @param int $quality Image Quality
	 *
	 * @return  string
	 */
	abstract protected function _do_render(string $type, int $quality): string;

	/**
	 * Check if all requirements for the driver to work properly are met.
	 *
	 * @return bool
	 */
	abstract public static function check(): bool;
}
