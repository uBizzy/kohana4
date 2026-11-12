<?php
/**
 * Support for image manipulation using [GD](http://php.net/GD).
 *
 * @package        Kohana/Image/Driver
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license        https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Image_GD extends Image {

	/**
	 * GD rotate function
	 */
	const string IMAGEROTATE = 'imagerotate';

	/**
	 * GD convolution function
	 */
	const string IMAGECONVOLUTION = 'imageconvolution';

	/**
	 * GD filter function
	 */
	const string IMAGEFILTER = 'imagefilter';

	/**
	 * GD layer-effect function
	 */
	const string IMAGELAYEREFFECT = 'imagelayereffect';

	/**
	 * Holds available GD functions
	 * @var array<string, bool>
	 */
	protected static array $_available_functions = [];

	/**
	 * The image, once it has been loaded: see [Image_GD::_load_image]
	 * @var GdImage|null
	 */
	protected ?GdImage $_image = null;

	/**
	 * GD has different function names for different image types.
	 * This one holds the current function, which is needed to open the image.
	 *
	 * @var string
	 */
	protected ?string $_create_function = null;

	/**
	 * Checks if GD is enabled and verify that key methods exist, some of which require GD to
	 * be bundled with PHP.
	 *
	 * @return  bool
	 */
	public static function check(): bool
	{
		// Check if gd is loaded correctly
		if ( ! extension_loaded('gd'))
		{
			return false; // @codeCoverageIgnore
		}

		// Check available functions
		$functions = [
			Image_GD::IMAGEROTATE,
			Image_GD::IMAGECONVOLUTION,
			Image_GD::IMAGEFILTER,
			Image_GD::IMAGELAYEREFFECT
		];

		foreach ($functions as $function)
		{
			Image_GD::$_available_functions[$function] = function_exists($function);
		}

		return true;
	}

	/**
	 * Kohana_Image_GD constructor.
	 *
	 * @param string $file Path to image file
	 *
	 * @throws  Image_Exception
	 */
	public function __construct(string $file)
	{
		// Call parent constructor
		parent::__construct($file);

		// Set the image creation function name
		$ext = image_type_to_extension($this->type, false);

		// jpe and jpg are both valid extensions for jpeg
		if (in_array($ext, [
			'jpe',
			'jpg',
		]))
		{
			$ext = 'jpeg';
		}

		$create = 'imagecreatefrom' . strtolower( (string) $ext);

		// Check if imagecreate function exists
		// @codeCoverageIgnoreStart
		if ( ! function_exists($create))
		{
			throw new Image_Exception(
				'Installed GD does not support ":type" images',
				[':type' => $ext]
			);
		}
		// @codeCoverageIgnoreEnd

		// Save function for future use: the image is only loaded when it is first worked on
		$this->_create_function = $create;
	}

	/**
	 * Returns the image, loading it from its file the first time. Every
	 * step works on the result of the step before.
	 *
	 * @return  GdImage
	 * @throws  Image_Exception  when GD cannot read the file
	 */
	protected function _load_image(): GdImage
	{
		if ($this->_image === null)
		{
			// The function that opens this type of image, e.g. imagecreatefromjpeg
			// The function that opens this type of image, e.g. imagecreatefromjpeg
			$create = $this->_create_function;

			$image = is_callable($create) ? $create($this->file) : false;

			if ( ! $image instanceof GdImage)
				throw new Image_Exception('The image :file could not be read', [':file' => Debug::path($this->file)]);

			// Preserve transparency when saving
			imagesavealpha($image, true);

			$this->_image = $image;
		}

		return $this->_image;
	}

	/**
	 * Resize function for GD
	 *
	 * @param int $width	Width to resize to
	 * @param int $height	Height to resize to
	 *
	 * @return bool
	 */
	protected function _do_resize(int $width, int $height): bool
	{
		// Pre-Size width and height
		$pre_width = $this->width;
		$pre_height = $this->height;

		// Loads image if not yet loaded
		$this->_load_image();

		// Test if we can do a resize without re-sampling to speed up the final resize
		if ($width > ($this->width / 2) && $height > ($this->height / 2))
		{
			// Create the temporary image to copy to
			$image = $this->_create($pre_width, $pre_height);

			if (imagecopyresized($image, $this->_load_image(), 0, 0, 0, 0, $pre_width, $pre_height, $this->width, $this->height))
			{
				// Swap the new image for the old one
				$this->_image = $image;
			}
		}

		// Create the temporary image to copy to
		$image = $this->_create($width, $height);

		// Execute the resize
		if ($success = imagecopyresampled($image, $this->_load_image(), 0, 0, 0, 0, $width, $height, $pre_width, $pre_height))
		{
			// Swap the new image for the old one
			$this->_image = $image;

			// Reset the width and height
			$this->width = imagesx($image);
			$this->height = imagesy($image);
		}

		return $success;
	}

	/**
	 * Crop function for GD
	 *
	 * @param int $width    New width
	 * @param int $height   New height
	 * @param int $offset_x Offset from the left
	 * @param int $offset_y Offset from the top
	 *
	 * @return  bool
	 */
	protected function _do_crop(int $width, int $height, int $offset_x, int $offset_y): bool
	{
		// Support for negative offsets
		$dest_width = $width;
		$dest_height = $height;
		if ($offset_x < 0)
		{
			$dest_width = $width + $offset_x;
			$offset_x = 0;
		}
		if ($offset_y < 0)
		{
			$dest_height = $height + $offset_y;
			$offset_y = 0;
		}

		// Create the temporary image to copy to
		$image = $this->_create($dest_width, $dest_height);

		// Loads image if not yet loaded
		$this->_load_image();

		// Execute the crop
		if ($success = imagecopyresampled($image, $this->_load_image(), 0, 0, $offset_x, $offset_y, $width, $height, $width, $height))
		{
			// Swap the new image for the old one
			$this->_image = $image;

			// Reset the width and height
			$this->width = imagesx($image);
			$this->height = imagesy($image);
		}

		return $success;
	}

	/**
	 * Rotation function for GD
	 *
	 * @param int $degrees	Degrees to rotate
	 *
	 * @throws Image_Exception
	 *
	 * @return bool
	 */
	protected function _do_rotate(int $degrees): bool
	{
		// Check if function is available in GD
		if (empty(Image_GD::$_available_functions[Image_GD::IMAGEROTATE]))
		{
			throw new Image_Exception('This method requires "imagerotate", which is only available in the bundled version of GD'); // @codeCoverageIgnore
		}

		// Loads image if not yet loaded
		$this->_load_image();

		// Transparent black will be used as the background for the uncovered region
		$transparent = imagecolorallocatealpha($this->_load_image(), 0, 0, 0, 127);

		// Rotate, setting the transparent color
		$image = imagerotate($this->_load_image(), 360 - $degrees, (int) $transparent);

		if ($image === false)
			throw new Image_Exception('The image could not be rotated');

		// Save the alpha of the rotated image
		imagesavealpha($image, true);

		// Get the width and height of the rotated image
		$width = imagesx($image);
		$height = imagesy($image);

		if ($success = imagecopymerge($this->_load_image(), $image, 0, 0, 0, 0, $width, $height, 100))
		{
			// Swap the new image for the old one
			$this->_image = $image;

			// Reset the width and height
			$this->width = $width;
			$this->height = $height;
		}

		return $success;
	}

	/**
	 * Flip function for GD
	 *
	 * @param int $direction Direction to flip
	 *
	 * @return bool
	 */
	protected function _do_flip(int $direction): bool
	{
		// Flip was successful
		$success = false;

		// Create the flipped image
		$flipped = $this->_create($this->width, $this->height);

		// Loads image if not yet loaded
		$this->_load_image();

		if ($direction === Image::HORIZONTAL)
		{
			for ($x = 0; $x < $this->width; $x++)
			{
				// Flip each row from top to bottom
				$success = imagecopy($flipped, $this->_load_image(), $x, 0, $this->width - $x - 1, 0, 1, $this->height);
			}
		}
		else
		{
			for ($y = 0; $y < $this->height; $y++)
			{
				// Flip each column from left to right
				$success = imagecopy($flipped, $this->_load_image(), 0, $y, 0, $this->height - $y - 1, $this->width, 1);
			}
		}

		// Swap the new image for the old one
		$this->_image = $flipped;

		// Reset the width and height
		$this->width = imagesx($flipped);
		$this->height = imagesy($flipped);

		return $success;
	}

	/**
	 * Sharpen function for GD
	 *
	 * @param int $amount	Amount to sharpen the iamge
	 *
	 * @throws Image_Exception
	 *
	 * @return bool
	 */
	protected function _do_sharpen(int $amount): bool
	{
		// Check if function is available in GD
		if (empty(Image_GD::$_available_functions[Image_GD::IMAGECONVOLUTION]))
		{
			throw new Image_Exception('This method requires "imageconvolution", which is only available in the bundled version of GD'); // @codeCoverageIgnore
		}

		// Loads image if not yet loaded
		$this->_load_image();

		// Amount should be in the range of 18-10
		$amount = round(abs(-18 + ($amount * 0.08)), 2);

		// Gaussian blur matrix
		$matrix = [[-1, -1, -1], [-1, $amount, -1], [-1, -1, -1],];

		// Perform the sharpen
		if ($success = imageconvolution($this->_load_image(), $matrix, $amount - 8, 0))
		{
			// Reset the width and height
			$this->width = imagesx($this->_load_image());
			$this->height = imagesy($this->_load_image());
		}

		return $success;
	}

	/**
	 * Reflection function for GD
	 *
	 * @param int  $height	 Reflection height
	 * @param int  $opacity	 Reflection opacity
	 * @param bool $fade_in	 true to fade out, false to fade in
	 *
	 * @throws Image_Exception
	 *
	 * @return bool
	 */
	protected function _do_reflection(int $height, int $opacity, bool $fade_in): bool
	{
		// Check if function is available in GD
		if (empty(Image_GD::$_available_functions[Image_GD::IMAGEFILTER]))
		{
			throw new Image_Exception('This method requires "imagefilter", which is only available in the bundled version of GD'); // @codeCoverageIgnore
		}

		// Loads image if not yet loaded
		$this->_load_image();

		// Convert an opacity range of 0-100 to 127-0
		$opacity = max(0, min(127, (int) round(abs(($opacity * 127 / 100) - 127))));

		if ($opacity < 127)
		{
			// Calculate the opacity stepping
			$stepping = (127 - $opacity) / $height;
		}
		else
		{
			// Avoid a "divide by zero" error
			$stepping = 127 / $height;
		}

		// Create the reflection image
		$reflection = $this->_create($this->width, $this->height + $height);

		// Copy the image to the reflection
		$success = imagecopy($reflection, $this->_load_image(), 0, 0, 0, 0, $this->width, $this->height);

		for ($offset = 0; $height >= $offset; $offset++)
		{
			// Read the next line down
			$src_y = $this->height - $offset - 1;

			// Place the line at the bottom of the reflection
			$dst_y = $this->height + $offset;

			if ($fade_in === true)
			{
				// Start with the most transparent line first
				$dst_opacity = round($opacity + ($stepping * ($height - $offset)));
			}
			else
			{
				// Start with the most opaque line first
				$dst_opacity = round($opacity + ($stepping * $offset));
			}

			// Create a single line of the image
			$line = $this->_create($this->width, 1);

			// Copy a single line from the current image into the line
			imagecopy($line, $this->_load_image(), 0, 0, 0, $src_y, $this->width, 1);

			// Colorize the line to add the correct alpha level
			imagefilter($line, IMG_FILTER_COLORIZE, 0, 0, 0, $dst_opacity);

			// Copy a the line into the reflection
			imagecopy($reflection, $line, 0, $dst_y, 0, 0, $this->width, 1);
		}

		// Swap the new image for the old one
		$this->_image = $reflection;

		// Reset the width and height
		$this->width = imagesx($reflection);
		$this->height = imagesy($reflection);

		return $success;
	}

	/**
	 * Watermark function for GD
	 *
	 * @param Image $watermark	Watermark (Image)
	 * @param int   $offset_x	Offset from the left
	 * @param int   $offset_y	Offset from thr top
	 * @param int   $opacity	Opacity of watermark
	 *
	 * @throws Image_Exception
	 *
	 * @return bool
	 */
	protected function _do_watermark(Image $watermark, int $offset_x, int $offset_y, int $opacity): bool
	{
		// Check if function is available in GD
		if (empty(Image_GD::$_available_functions[Image_GD::IMAGELAYEREFFECT]))
		{
			throw new Image_Exception('This method requires "imagelayereffect", which is only available in the bundled version of GD'); // @codeCoverageIgnore
		}

		// Loads image if not yet loaded
		$this->_load_image();

		// Create the watermark image resource
		$overlay = imagecreatefromstring($watermark->render());

		if ($overlay === false)
			throw new Image_Exception('The watermark image could not be read');

		imagesavealpha($overlay, true);

		// Get the width and height of the watermark
		$width = imagesx($overlay);
		$height = imagesy($overlay);

		if ($opacity < 100)
		{
			// Convert an opacity range of 0-100 to 127-0
			$opacity = max(0, min(127, (int) round(abs(($opacity * 127 / 100) - 127))));

			// Allocate transparent gray
			$color = imagecolorallocatealpha($overlay, 127, 127, 127, $opacity);

			// The transparent image will overlay the watermark
			imagelayereffect($overlay, IMG_EFFECT_OVERLAY);

			// Fill the background with the transparent color
			imagefilledrectangle($overlay, 0, 0, $width, $height, (int) $color);
		}

		// Alpha blending must be enabled on the background!
		imagealphablending($this->_load_image(), true);

		// PHP frees the overlay by itself when this method ends
		return imagecopy($this->_load_image(), $overlay, $offset_x, $offset_y, 0, 0, $width, $height);
	}

	/**
	 * Background function for GD
	 *
	 * @param int $r			Red
	 * @param int $g			Green
	 * @param int $b			Blue
	 * @param int $opacity		Opacity of background
	 *
	 * @return bool
	 */
	protected function _do_background(int $r, int $g, int $b, int $opacity): bool
	{
		// Loads image if not yet loaded
		$this->_load_image();

		// Convert an opacity range of 0-100 to 127-0
		$opacity = max(0, min(127, (int) round(abs(($opacity * 127 / 100) - 127))));

		// Create a new background
		$background = $this->_create($this->width, $this->height);

		// Allocate the color
		$color = imagecolorallocatealpha($background, max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)), $opacity);

		// Fill the image with the colour
		imagefilledrectangle($background, 0, 0, $this->width, $this->height, (int) $color);

		// Alpha blending must be enabled on the background!
		imagealphablending($background, true);

		// Copy the image onto a white background to remove all transparency
		if ($success = imagecopy($background, $this->_load_image(), 0, 0, 0, 0, $this->width, $this->height))
		{
			// Swap the new image for the old one
			$this->_image = $background;
		}

		return $success;
	}

	/**
	 * Execute a save.
	 *
	 * @param string  $file    New image filename
	 * @param int $quality Image quality
	 *
	 * @throws Image_Exception
	 *
	 * @return  bool
	 */
	protected function _do_save(string $file, int $quality): bool
	{
		// Loads image if not yet loaded
		$this->_load_image();

		// Get the extension of the file
		$extension = pathinfo($file, PATHINFO_EXTENSION);

		// Get the save function and IMAGETYPE
		/** @var callable $save  a GD function such as imagejpeg, checked in _save_function() */
		[$save, $type] = $this->_save_function($extension, $quality);

		// Save the image to a file
		$status = isset($quality) ? $save($this->_load_image(), $file, $quality) : $save($this->_load_image(), $file);

		if ($status === true AND $type !== $this->type)
		{
			// Reset the image type and mime type
			$this->type = $type;
			$this->mime = image_type_to_mime_type($type);
		}

		return (bool) $status;
	}

	/**
	 * Render function for GD
	 *
	 * @param string $type		Image type: png, jpg, gif, etc
	 * @param int    $quality	Image quality
	 *
	 * @throws Image_Exception
	 *
	 * @return string
	 */
	protected function _do_render(string $type, int $quality): string
	{
		// Loads image if not yet loaded
		$this->_load_image();

		// Get the save function and IMAGETYPE
		/** @var callable $save  a GD function such as imagejpeg, checked in _save_function() */
		[$save, $type] = $this->_save_function($type, $quality);

		// Capture the output
		ob_start();

		// Render the image
		$status = isset($quality) ? $save($this->_load_image(), null, $quality) : $save($this->_load_image(), null);

		if ($status === true && $type !== $this->type)
		{
			// Reset the image type and mime type
			$this->type = $type;
			$this->mime = image_type_to_mime_type($type);
		}

		return (string) ob_get_clean();
	}

	/**
	 * Get the GD saving function and image type for this extension.
	 * Also normalizes the quality setting.
	 *
	 * @param string  $extension Image type: png, jpg, etc
	 * @param int $quality   Image quality
	 *
	 * @throws  Image_Exception
	 *
	 * @return  array{string, int}
	 */
	protected function _save_function(?string $extension, ?int & $quality): array
	{
		// Grep extension
		if ( ! $extension)
		{
			// Use the current image type
			$extension = (string) image_type_to_extension($this->type, false);
		}

		// Make lowercase for comparison
		$extension = strtolower($extension);

		// jpe and jpg are both valid extensions for jpeg
		if (in_array($extension, [
			'jpe',
			'jpg',
		]))
		{
			$extension = 'jpeg';
		}

		// The extension becomes part of a function name (imagejpeg, imagepng,
		// ...), so only the image formats GD can write are accepted
		if ( ! in_array($extension, ['jpeg', 'png', 'gif', 'webp', 'bmp', 'wbmp', 'avif'], true))
		{
			throw new Image_Exception(
				'Installed GD does not support ":type" images',
				[':type' => $extension]
			);
		}

		$save = 'image' . $extension;

		// Check if image (save) function exists
		// @codeCoverageIgnoreStart
		if ( ! function_exists($save))
		{
			throw new Image_Exception(
				'Installed GD does not support ":type" images',
				[':type' => $extension]
			);
		}
		// @codeCoverageIgnoreEnd

		$type = static::extension_to_image_type($extension);

		// GIFs do not a quality setting
		if ($extension === 'gif')
		{
			$quality = null;
		}

		// Use a compression level of 9 (does not affect quality!)
		elseif ($extension === 'png')
		{
			$quality = 9;
		}

		return [$save, $type];
	}

	/**
	 * Create an empty image with the given width and height.
	 *
	 * @param int $width  Image width
	 * @param int $height Image height
	 *
	 * @return  GdImage
	 */
	protected function _create(int $width, int $height): GdImage
	{
		// Create an empty image
		$image = imagecreatetruecolor(max(1, $width), max(1, $height));

		if ($image === false)
			throw new Image_Exception('An image of :width x :height pixels could not be created', [':width' => $width, ':height' => $height]);

		// Do not apply alpha blending
		imagealphablending($image, false);

		// Save alpha levels
		imagesavealpha($image, true);

		return $image;
	}

	/**
	 * Return if image type is supported by GD
	 *
	 * @param int $type IMAGETYPE_* Constant
	 *
	 * @return bool
	 */
	protected function _is_supported_type(int $type): bool
	{
		// Format Extension
		$ext = strtoupper(substr( (string) image_type_to_extension($type), 1));

		// Detect formats not following the convention
		if ($ext === 'WEBP')
		{
			$ext = 'WebP';
		}
		elseif ($ext === 'GIF')
		{
			$ext = 'GIF Create';
		}

		$ext .= ' Support';

		// Check if supported
		return (bool) (gd_info()[$ext] ?? false);
	}
}
