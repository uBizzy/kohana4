<?php
/**
 * File helper class.
 *
 * @package    Kohana
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_File {

	/**
	 * Attempt to get the mime type from a file. This method is horribly
	 * unreliable, due to PHP being horribly unreliable when it comes to
	 * determining the mime type of a file.
	 *
	 *     $mime = File::mime($file);
	 *
	 * @param   string  $filename   file name or path
	 * @return  string  mime type on success
	 */
	public static function mime(string $filename): string|false
	{
		// Get the complete path to the file
		$filename = realpath($filename);

		if ($filename === false)
		{
			// The file does not exist
			return false;
		}

		// Get the extension from the filename
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		if (preg_match('/^(?:jpe?g|png|[gt]if|bmp|swf)$/', $extension))
		{
			// Use getimagesize() to find the mime type on images
			$file = getimagesize($filename);

			if (isset($file['mime']))
				return $file['mime'];
		}

		if (class_exists('finfo', false))
		{
			// Detect the type from the file contents (fileinfo extension)
			$mime = (new finfo(FILEINFO_MIME_TYPE))->file($filename);

			if ($mime !== false)
				return $mime;
		}

		if ($extension !== '')
		{
			// Fall back to the extension
			return File::mime_by_ext($extension);
		}

		// Unable to find the mime-type
		return false;
	}

	/**
	 * Return the mime type of an extension.
	 *
	 *     $mime = File::mime_by_ext('png'); // "image/png"
	 *
	 * @param   string  $extension  php, pdf, txt, etc
	 * @return  string  mime type on success
	 */
	public static function mime_by_ext(string $extension): string|false
	{
		// Extension has to be lowercase
		$extension = strtolower($extension);

		$mimes = File::_mimes();

		return $mimes[$extension][0] ?? false;
	}

	/**
	 * The mime types of config/mimes.php.
	 *
	 * @return  array<string, array<int, string>>  extension => mime types
	 */
	protected static function _mimes(): array
	{
		/** @var Config_Group $config */
		$config = Kohana::$config->load('mimes');

		/** @var array<string, array<int, string>> $mimes */
		$mimes = $config->as_array();

		return $mimes;
	}

	/**
	 * Lookup MIME types for a file
	 *
	 * @see Kohana_File::mime_by_ext()
	 * @param   string  $extension  Extension to lookup
	 * @return  array<int, string>  Array of MIMEs associated with the specified extension
	 */
	public static function mimes_by_ext(string $extension): array
	{
		return File::_mimes()[$extension] ?? [];
	}

	/**
	 * Lookup file extensions by MIME type
	 *
	 * @param   string  $type File MIME type
	 * @return  array<int, string>|false   File extensions matching MIME type or false if none
	 */
	public static function exts_by_mime(string $type): array|false
	{
		/** @var array<string, array<int, string>> $types */
		static $types = [];

		// Fill the static array
		if (empty($types))
		{
			foreach (File::_mimes() as $ext => $mimes)
			{
				foreach ($mimes as $mime)
				{
					if ($mime == 'application/octet-stream')
					{
						// octet-stream is a generic binary
						continue;
					}

					if ( ! isset($types[$mime]))
					{
						$types[$mime] = [ (string) $ext];
					}
					elseif ( ! in_array($ext, $types[$mime]))
					{
						$types[$mime][] = (string) $ext;
					}
				}
			}
		}

		return $types[$type] ?? false;
	}

	/**
	 * Lookup a single file extension by MIME type.
	 *
	 * @param   string  $type  MIME type to lookup
	 * @return  string|false   First file extension matching or false
	 */
	public static function ext_by_mime(string $type): string|false
	{
		$exts = File::exts_by_mime($type);

		if ($exts === false)
		{
			return false;
		}

		return current($exts);
	}

	/**
	 * Split a file into pieces matching a specific size. Used when you need to
	 * split large files into smaller pieces for easy transmission.
	 *
	 *     $count = File::split($file);
	 *
	 * @param   string  $filename   file to be split
	 * @param   int $piece_size size, in MB, for each piece to be
	 * @return  int The number of pieces that were created
	 */
	public static function split(string $filename, int|float $piece_size = 10): int
	{
		// Open the input file
		$file = fopen($filename, 'rb');

		if ($file === false)
			throw new Kohana_Exception('Unable to open file :file', [':file' => Debug::path($filename)]);

		// Change the piece size to bytes
		$piece_size = floor($piece_size * 1024 * 1024);

		// Write files in 8k blocks
		$block_size = 1024 * 8;

		// Total number of pieces
		$pieces = 0;

		while ( ! feof($file))
		{
			// Create another piece
			$pieces += 1;

			// Create a new file piece
			$piece = str_pad( (string) $pieces, 3, '0', STR_PAD_LEFT);
			$piece = fopen($filename.'.'.$piece, 'wb+');

			if ($piece === false)
				throw new Kohana_Exception('Unable to open file :file', [':file' => Debug::path($filename)]);

			// Number of bytes read
			$read = 0;

			do
			{
				// Transfer the data in blocks
				File::_write($piece, (string) fread($file, $block_size), $filename);

				// Another block has been read
				$read += $block_size;
			}
			while ($read < $piece_size);

			// Close the piece
			fclose($piece);
		}

		// Close the file
		fclose($file);

		return $pieces;
	}

	/**
	 * Writes a block of data to an open file and makes sure all of it was
	 * written. A full disk makes fwrite() fail or write only a part, which
	 * would otherwise leave a corrupt file without any error.
	 *
	 * @param   resource  $handle    open file
	 * @param   string    $data      data to write
	 * @param   string    $filename  file name, for the error message
	 * @return  void
	 * @throws  Kohana_Exception
	 */
	protected static function _write(mixed $handle, string $data, string $filename): void
	{
		if (fwrite($handle, $data) !== strlen($data))
			throw new Kohana_Exception('Unable to write to file :file', [':file' => Debug::path($filename)]);
	}

	/**
	 * Join a split file into a whole file. Does the reverse of [File::split].
	 *
	 *     $count = File::join($file);
	 *
	 * @param   string  $filename   split filename, without .000 extension
	 * @return  int The number of pieces that were joined.
	 */
	public static function join(string $filename): int
	{
		// Open the file
		$file = fopen($filename, 'wb+');

		if ($file === false)
			throw new Kohana_Exception('Unable to open file :file', [':file' => Debug::path($filename)]);

		// Read files in 8k blocks
		$block_size = 1024 * 8;

		// Total number of pieces
		$pieces = 0;

		while (is_file($piece = $filename.'.'.str_pad( (string) ($pieces + 1), 3, '0', STR_PAD_LEFT)))
		{
			// Read another piece
			$pieces += 1;

			// Open the piece for reading
			$piece = fopen($piece, 'rb');

			if ($piece === false)
				throw new Kohana_Exception('Unable to open file :file', [':file' => Debug::path($filename)]);

			while ( ! feof($piece))
			{
				// Transfer the data in blocks
				File::_write($file, (string) fread($piece, $block_size), $filename);
			}

			// Close the piece
			fclose($piece);
		}

		// Close the joined file
		fclose($file);

		return $pieces;
	}

}
