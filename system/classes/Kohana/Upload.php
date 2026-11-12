<?php
/**
 * Upload helper class for working with uploaded files and [Validation].
 *
 *     $array = Validation::factory($_FILES);
 *
 * [!!] Remember to define your form with "enctype=multipart/form-data" or file
 * uploading will not work!
 *
 * The following configuration properties can be set:
 *
 * - [Upload::$remove_spaces]
 * - [Upload::$default_directory]
 *
 * @package    Kohana
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Upload {

	/**
	 * @var  bool  remove spaces in uploaded files
	 */
	public static bool $remove_spaces = true;

	/**
	 * @var  string  default upload directory
	 */
	public static string $default_directory = 'upload';

	/**
	 * @var  array<int, string>  File extensions (lowercase) that [Upload::save] refuses to
	 *              save, because a web server may execute such files or use
	 *              them as configuration. Remove an entry, or empty the list,
	 *              only if the upload directory cannot be reached from the web.
	 */
	public static array $forbidden_extensions = [
		'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phtml', 'pht', 'phar', 'phps',
		'htaccess', 'htpasswd', 'cgi', 'pl', 'py', 'sh', 'asp', 'aspx', 'jsp',
	];

	/**
	 * Save an uploaded file to a new location. If no filename is provided,
	 * the original filename will be used, with a unique prefix added.
	 *
	 * This method should be used after validating the $_FILES array:
	 *
	 *     if ($array->check())
	 *     {
	 *         // Upload is valid, save it
	 *         Upload::save($array['file']);
	 *     }
	 *
	 * @param   array<string, mixed>   $file       uploaded file data
	 * @param   string  $filename   new filename
	 * @param   string  $directory  new directory
	 * @param   int $chmod      chmod mask
	 * @return  string|false  full path to the new file on success; false on failure,
	 *                        when the extension is in Upload::$forbidden_extensions,
	 *                        or when the given filename leads out of the directory
	 */
	public static function save(array $file, ?string $filename = null, ?string $directory = null, int|false $chmod = 0644): string|false
	{
		if ( ! is_string($file['tmp_name'] ?? null) OR ! static::_is_uploaded($file['tmp_name']))
		{
			// Ignore corrupted uploads
			return false;
		}

		if ($filename === null)
		{
			// Use the client's filename with a random prefix. basename()
			// strips any directory part (Windows "\" included) and control
			// characters have no place in a file name.
			$filename = basename(str_replace('\\', '/', is_string($file['name'] ?? null) ? $file['name'] : ''));
			$filename = bin2hex(random_bytes(8)).preg_replace('/[\x00-\x1F\x7F]/', '', $filename);
		}

		elseif (static::_leaves_directory($filename))
		{
			// A name you pass yourself may hold a sub directory, but it
			// never leads out of the upload directory
			return false;
		}

		if (Upload::forbidden($filename))
		{
			// Never store a file the web server could execute
			return false;
		}

		if (Upload::$remove_spaces === true)
		{
			// Remove spaces from the filename
			$filename = preg_replace('/\s+/u', '_', $filename);
		}

		// Use the pre-configured upload directory by default
		$directory ??= Upload::$default_directory;

		if ( ! is_dir($directory) OR ! is_writable( (string) realpath($directory)))
		{
			throw new Kohana_Exception('Directory :dir must be writable',
				[':dir' => Debug::path($directory)]);
		}

		// Make the filename into a complete path
		$filename = realpath($directory).DIRECTORY_SEPARATOR.$filename;

		if (static::_move($file['tmp_name'], $filename))
		{
			if ($chmod !== false)
			{
				// Set permissions on filename
				chmod($filename, $chmod);
			}

			// Return new file path
			return $filename;
		}

		return false;
	}

	/**
	 * Tells whether a filename has an extension listed in
	 * [Upload::$forbidden_extensions].
	 *
	 * Every extension of the name is checked, not only the last one, because
	 * some server configurations execute "shell.php.jpg" as PHP:
	 *
	 *     Upload::forbidden('photo.jpg');      // false
	 *     Upload::forbidden('shell.PHP');      // true
	 *     Upload::forbidden('shell.php.jpg');  // true
	 *     Upload::forbidden('.htaccess');      // true
	 *
	 * @param   string  $filename  filename to check
	 * @return  bool
	 */
	public static function forbidden(string $filename): bool
	{
		// Everything after the first dot of the name, as lowercase parts
		$extensions = array_slice(explode('.', strtolower(basename($filename))), 1);

		return (bool) array_intersect($extensions, Upload::$forbidden_extensions);
	}

	/**
	 * Tests if upload data is valid, even if no file was uploaded. If you
	 * _do_ require a file to be uploaded, add the [Upload::not_empty] rule
	 * before this rule.
	 *
	 *     $array->rule('file', 'Upload::valid')
	 *
	 * @param   mixed   $file   $_FILES item (visitor input: anything else is not valid)
	 * @return  bool
	 */
	public static function valid(mixed $file): bool
	{
		// An upload is always an array with these five keys
		return (is_array($file)
			AND isset($file['error'])
			AND isset($file['name'])
			AND isset($file['type'])
			AND isset($file['tmp_name'])
			AND isset($file['size']));
	}

	/**
	 * Tests if a successful upload has been made.
	 *
	 *     $array->rule('file', 'Upload::not_empty');
	 *
	 * @param   mixed   $file   $_FILES item (visitor input: anything else is not valid)
	 * @return  bool
	 * @phpstan-assert-if-true array{error: int, tmp_name: string} $file
	 */
	public static function not_empty(mixed $file): bool
	{
		return (is_array($file)
			AND isset($file['error'])
			AND is_string($file['tmp_name'] ?? null)
			AND $file['error'] === UPLOAD_ERR_OK
			AND static::_is_uploaded($file['tmp_name']));
	}

	/**
	 * Test if an uploaded file is an allowed file type, by extension.
	 *
	 *     $array->rule('file', 'Upload::type', array(':value', array('jpg', 'png', 'gif')));
	 *
	 * @param   mixed   $file       $_FILES item (visitor input: anything else is not valid)
	 * @param   array<int, string>   $allowed    allowed file extensions
	 * @return  bool
	 */
	public static function type(mixed $file, array $allowed): bool
	{
		// Anything that is not an upload fails
		if ( ! is_array($file) OR ! isset($file['error'], $file['name']))
			return false;

		// A failed or missing upload is not this rule's concern
		if ($file['error'] !== UPLOAD_ERR_OK)
			return true;

		$ext = strtolower(pathinfo(is_string($file['name']) ? $file['name'] : '', PATHINFO_EXTENSION));

		return in_array($ext, $allowed, true);
	}

	/**
	 * Validation rule to test if an uploaded file is allowed by file size.
	 * File sizes are defined as: SB, where S is the size (1, 8.5, 300, etc.)
	 * and B is the byte unit (K, MiB, GB, etc.). All valid byte units are
	 * defined in Num::$byte_units
	 *
	 *     $array->rule('file', 'Upload::size', array(':value', '1M'))
	 *     $array->rule('file', 'Upload::size', array(':value', '2.5KiB'))
	 *
	 * @param   mixed   $file   $_FILES item (visitor input: anything else is not valid)
	 * @param   string  $size   maximum file size allowed
	 * @return  bool
	 */
	public static function size(mixed $file, string|int|float $size): bool
	{
		// Anything that is not an upload fails
		if ( ! is_array($file) OR ! isset($file['error']))
			return false;

		if ($file['error'] === UPLOAD_ERR_INI_SIZE)
		{
			// Upload is larger than PHP allowed size (upload_max_filesize)
			return false;
		}

		if ($file['error'] !== UPLOAD_ERR_OK)
		{
			// The upload failed, no size to check
			return true;
		}

		// Convert the provided size to bytes for comparison
		return (($file['size'] ?? 0) <= Num::bytes($size));
	}

	/**
	 * Validation rule to test if an upload is an image and, optionally, is the correct size.
	 *
	 *     // The "image" file must be an image
	 *     $array->rule('image', 'Upload::image')
	 *
	 *     // The "photo" file has a maximum size of 640x480 pixels
	 *     $array->rule('photo', 'Upload::image', array(':value', 640, 480));
	 *
	 *     // The "image" file must be exactly 100x100 pixels
	 *     $array->rule('image', 'Upload::image', array(':value', 100, 100, true));
	 *
	 *
	 * @param   mixed   $file       $_FILES item (visitor input: anything else is not valid)
	 * @param   int $max_width  maximum width of image
	 * @param   int $max_height maximum height of image
	 * @param   bool $exact      match width and height exactly?
	 * @return  bool
	 */
	public static function image(mixed $file, ?int $max_width = null, ?int $max_height = null, bool $exact = false): bool
	{
		if ( ! static::not_empty($file))
			return false;

		try
		{
			// Get the width and height from the uploaded image
			$size = getimagesize($file['tmp_name']);
		}
		catch (Throwable $e)
		{
			// The file could not be read as an image
			$size = false;
		}

		if (empty($size[0]) OR empty($size[1]))
		{
			// Cannot get image size, cannot validate
			return false;
		}

		list($width, $height) = $size;

		// No limit means "as large as the image is"
		$max_width  = $max_width ?: $width;
		$max_height = $max_height ?: $height;

		if ($exact)
		{
			// Check if dimensions match exactly
			return ($width === $max_width AND $height === $max_height);
		}

		// Check if size is within maximum dimensions
		return ($width <= $max_width AND $height <= $max_height);
	}

	/**
	 * Tells whether a file name would put the file outside the directory
	 * it is saved in: it climbs with "..", starts at the root of the disk,
	 * or holds a control character. Such a name is often the one a visitor
	 * sent, passed on as it came.
	 *
	 * @param   string  $filename  name, with or without sub directories
	 * @return  bool
	 */
	protected static function _leaves_directory(string $filename): bool
	{
		// "\" is a separator on Windows; it is read as one everywhere
		$name = str_replace('\\', '/', $filename);

		return str_starts_with($name, '/')
			OR preg_match('/^[a-z]:/i', $name) === 1
			OR preg_match('/[\x00-\x1F\x7F]/', $name) === 1
			OR in_array('..', explode('/', $name), true);
	}

	/**
	 * Proxy for the native is_uploaded_file function, so that uploads can
	 * be tested on the command line, where PHP receives none.
	 *
	 * @param   string  $path  path of the temporary file
	 * @return  bool
	 * @see     is_uploaded_file
	 */
	protected static function _is_uploaded(string $path): bool
	{
		return is_uploaded_file($path);
	}

	/**
	 * Proxy for the native move_uploaded_file function, for the same reason.
	 *
	 * @param   string  $from  path of the temporary file
	 * @param   string  $to    where the file goes
	 * @return  bool
	 * @see     move_uploaded_file
	 */
	protected static function _move(string $from, string $to): bool
	{
		return move_uploaded_file($from, $to);
	}

}
