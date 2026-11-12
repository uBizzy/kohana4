<?php
/**
 * Makes, reads and unpacks archives: zip, tar and tar.gz, and single
 * files compressed with gzip.
 *
 *     // Everything in a directory, or a list of files
 *     Archive::create(APPPATH.'cache/export.zip', APPPATH.'data/export');
 *
 *     // What is in an archive
 *     foreach (Archive::contents($archive) as $entry) echo $entry['name'];
 *
 *     // Unpack it
 *     $files = Archive::extract($archive, APPPATH.'data/import');
 *
 * Unpacking is safe for archives that come from a visitor:
 *
 * - no file lands outside the directory it is unpacked in, whatever the
 *   names in the archive say ("../../file", "/etc/file");
 * - links, devices and the like in a tar are refused;
 * - an archive cannot unpack to more than [Archive::$max_size] bytes or
 *   [Archive::$max_files] entries (a small archive can hold a huge one);
 * - files a web server could run (see [Upload::$forbidden_extensions])
 *   are refused unless asked for;
 * - when an archive is refused, nothing of it is left behind.
 *
 * Zip needs the zip extension of PHP. Tar and gzip need nothing extra.
 *
 * @package    Kohana
 * @category   Helpers
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Archive {

	// The kinds of archive
	public const string ZIP    = 'zip';
	public const string TAR    = 'tar';
	public const string TAR_GZ = 'tar.gz';

	/**
	 * @var  int  The most bytes an archive may unpack to: 1 GB. Lower it
	 *            for archives that come from visitors.
	 */
	public static int $max_size = 1073741824;

	/**
	 * @var  int  The most entries (files and directories) an archive may hold
	 */
	public static int $max_files = 10000;

	/**
	 * Makes an archive. The kind follows from its name: ".zip", ".tar",
	 * ".tar.gz" or ".tgz". An archive that is there already is replaced.
	 *
	 *     // Everything in a directory, with its sub directories
	 *     Archive::create('backup.tar.gz', APPPATH.'data');
	 *
	 *     // Some files, under their own names or under names you give
	 *     Archive::create('report.zip', [
	 *         '/path/to/summary.pdf',
	 *         'data/2026.csv' => '/path/to/export.csv',
	 *         'images'        => '/path/to/a/directory',
	 *     ]);
	 *
	 * Links are not followed and not stored; empty directories are left out.
	 *
	 * @param   string                           $archive  path of the archive to make
	 * @param   string|array<int|string, string>  $source   a directory, or a list of files and directories
	 * @return  string  the path of the archive
	 * @throws  Kohana_Exception  when a file cannot be read or the archive cannot be written
	 */
	public static function create(string $archive, string|array $source): string
	{
		$files  = static::_files($source);
		$format = static::_format_of_name($archive);

		try
		{
			($format === static::ZIP)
				? static::_create_zip($archive, $files)
				: static::_create_tar($archive, $files, $format === static::TAR_GZ);
		}
		catch (Throwable $e)
		{
			// Half an archive is of no use to anybody
			is_file($archive) and @unlink($archive);

			throw $e;
		}

		return $archive;
	}

	/**
	 * Lists what is in an archive, without unpacking it.
	 *
	 *     // [['name' => 'data/2026.csv', 'size' => 1532, 'directory' => false], ...]
	 *     $entries = Archive::contents($archive);
	 *
	 * @param   string  $archive  path of the archive
	 * @return  array<int, array{name: string, size: int, directory: bool}>
	 * @throws  Kohana_Exception  when the file is not an archive, or holds a link or a device
	 */
	public static function contents(string $archive): array
	{
		$entries = [];

		foreach (static::_entries($archive) as $entry)
		{
			$entries[] = ['name' => $entry['name'], 'size' => $entry['size'], 'directory' => $entry['directory']];
		}

		return $entries;
	}

	/**
	 * Unpacks an archive into a directory, which is made when it is not
	 * there. Files that exist are replaced. The kind of archive is found
	 * from its content, not from its name.
	 *
	 *     $files = Archive::extract($_FILES['archive']['tmp_name'], APPPATH.'data/import');
	 *
	 * See the top of this class for what is refused. When an archive is
	 * refused, the files this call unpacked are removed again.
	 *
	 * @param   string  $archive     path of the archive
	 * @param   string  $directory   where to unpack it
	 * @param   bool    $executable  also unpack files a web server could run (.php and the like)
	 * @return  array<int, string>  the names of the files that were unpacked, as in the archive
	 * @throws  Kohana_Exception  when the archive is refused or cannot be read or written
	 */
	public static function extract(string $archive, string $directory, bool $executable = false): array
	{
		if ( ! is_dir($directory) AND ! @mkdir($directory, 0755, true) AND ! is_dir($directory))
			throw new Kohana_Exception('Unable to create the directory :dir', [':dir' => Debug::path($directory)]);

		$base  = (string) realpath($directory);
		$done  = [];
		$made  = [];
		$bytes = 0;
		$count = 0;

		try
		{
			$entries = static::_entries($archive);

			while ($entries->valid())
			{
				/** @var array{name: string, size: int, directory: bool} $entry */
				$entry = $entries->current();
				$name  = rtrim($entry['name'], '/');

				if (++$count > static::$max_files)
					throw new Kohana_Exception('The archive holds more than :max entries', [':max' => static::$max_files]);

				// What the archive says is checked first; what is really
				// written is counted while it is written
				if (($bytes += $entry['size']) > static::$max_size)
					throw new Kohana_Exception('The archive unpacks to more than :max bytes', [':max' => static::$max_size]);

				static::_check_name($name, $executable);

				$path = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);

				static::_make_directory($entry['directory'] ? $path : dirname($path), $base, $made);

				if ($entry['directory'])
				{
					$entries->next();
					continue;
				}

				$file = @fopen($path, 'wb');

				if ($file === false)
					throw new Kohana_Exception('Unable to write the file :file', [':file' => Debug::path($path)]);

				$done[] = $path;

				// The generator writes the content of the entry to the file
				$entries->send($file);

				fclose($file);
				chmod($path, 0644);
			}
		}
		catch (Throwable $e)
		{
			// Leave nothing of a refused archive behind
			array_map('unlink', array_filter($done, 'is_file'));
			array_map('rmdir', array_filter(array_reverse($made), 'is_dir'));

			throw $e;
		}

		return array_map(fn (string $path) => str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($base) + 1)), $done);
	}

	/**
	 * Compresses one file with gzip. The file itself stays as it is.
	 *
	 *     // Makes "export.csv.gz"
	 *     $compressed = Archive::gzip('export.csv');
	 *
	 * @param   string       $file    path of the file
	 * @param   string|null  $target  path of the compressed file, the same with ".gz" added by default
	 * @return  string  the path of the compressed file
	 * @throws  Kohana_Exception  when the file cannot be read or the target cannot be written
	 */
	public static function gzip(string $file, ?string $target = null): string
	{
		$target ??= $file.'.gz';

		$from = static::_open($file, 'rb');
		$to   = static::_open('compress.zlib://'.$target, 'wb');

		stream_copy_to_stream($from, $to);

		fclose($from);
		fclose($to);

		return $target;
	}

	/**
	 * Unpacks one file that was compressed with gzip. The compressed
	 * file stays as it is. No more than [Archive::$max_size] bytes are
	 * written.
	 *
	 *     // Makes "export.csv"
	 *     $file = Archive::gunzip('export.csv.gz');
	 *
	 * @param   string       $file    path of the compressed file
	 * @param   string|null  $target  path of the unpacked file, the same without ".gz" by default
	 * @return  string  the path of the unpacked file
	 * @throws  Kohana_Exception  when the file is not gzip, is too large or cannot be written
	 */
	public static function gunzip(string $file, ?string $target = null): string
	{
		if ($target === null)
		{
			if ( ! str_ends_with(strtolower($file), '.gz'))
				throw new Kohana_Exception('Give the name of the file to unpack :file to: its own name does not end in ".gz"', [':file' => Debug::path($file)]);

			$target = substr($file, 0, -3);
		}

		$header = (string) file_get_contents($file, false, null, 0, 2);

		if ($header !== "\x1f\x8b")
			throw new Kohana_Exception('The file :file is not compressed with gzip', [':file' => Debug::path($file)]);

		$from = static::_open('compress.zlib://'.$file, 'rb');
		$to   = static::_open($target, 'wb');

		try
		{
			$written = 0;

			static::_copy($from, $to, null, $written);
		}
		catch (Throwable $e)
		{
			fclose($to);
			@unlink($target);

			throw $e;
		}
		finally
		{
			fclose($from);
		}

		fclose($to);

		return $target;
	}

	/**
	 * Tells what kind of archive a file is, from its content: the name
	 * of a file proves nothing.
	 *
	 *     Archive::format($file);   // Archive::ZIP, Archive::TAR or Archive::TAR_GZ
	 *
	 * @param   string  $archive  path of the file
	 * @return  string
	 * @throws  Kohana_Exception  when the file cannot be read or is none of these
	 */
	public static function format(string $archive): string
	{
		if ( ! is_file($archive) OR ! is_readable($archive))
			throw new Kohana_Exception('Unable to read the archive :file', [':file' => Debug::path($archive)]);

		$start = (string) file_get_contents($archive, false, null, 0, 262);

		if (str_starts_with($start, "PK\x03\x04") OR str_starts_with($start, "PK\x05\x06"))
			return static::ZIP;

		if (static::_starts_as_tar($start))
			return static::TAR;

		if (str_starts_with($start, "\x1f\x8b"))
		{
			// Compressed: a tar inside?
			$inside = (string) @file_get_contents('compress.zlib://'.$archive, false, null, 0, 1024);

			if (static::_starts_as_tar($inside))
				return static::TAR_GZ;
		}

		throw new Kohana_Exception('The file :file is not a zip, tar or tar.gz archive', [':file' => Debug::path($archive)]);
	}

	/**
	 * Tells whether the first bytes of a file are those of a tar
	 * archive: the mark "ustar" in the first header, or, for an archive
	 * without files, the two empty blocks that end one.
	 *
	 * @param   string  $start  the first bytes of the file, 262 or more
	 * @return  bool
	 */
	protected static function _starts_as_tar(string $start): bool
	{
		return substr($start, 257, 5) === 'ustar' OR (strlen($start) >= 262 AND trim($start, "\x00") === '');
	}

	/**
	 * Returns the kind of archive to make, from the name it gets.
	 *
	 * @param   string  $archive  path of the archive to make
	 * @return  string
	 * @throws  Kohana_Exception  when the name ends in nothing known
	 */
	protected static function _format_of_name(string $archive): string
	{
		$name = strtolower($archive);

		return match (true) {
			str_ends_with($name, '.zip')                                     => static::ZIP,
			str_ends_with($name, '.tar')                                     => static::TAR,
			str_ends_with($name, '.tar.gz'), str_ends_with($name, '.tgz')    => static::TAR_GZ,
			default => throw new Kohana_Exception('The name of an archive ends in .zip, .tar, .tar.gz or .tgz: :file', [':file' => Debug::path($archive)]),
		};
	}

	/**
	 * Returns the files to put in an archive: their paths, by the names
	 * they get in the archive, in the order of those names.
	 *
	 * @param   string|array<int|string, string>  $source  a directory, or a list of files and directories
	 * @return  array<string, string>
	 * @throws  Kohana_Exception  when a file is not there or a name is not allowed
	 */
	protected static function _files(string|array $source): array
	{
		$files = [];

		foreach (is_array($source) ? $source : ['' => $source] as $name => $path)
		{
			// Without a name of its own, a file keeps its name and the
			// content of a directory goes to the top of the archive
			$name = is_int($name) ? (is_dir($path) ? '' : basename($path)) : trim(str_replace('\\', '/', $name), '/');

			if (is_link($path))
				continue;

			if (is_file($path))
			{
				$files[$name] = $path;
			}
			elseif (is_dir($path))
			{
				$files += static::_files_of($path, $name);
			}
			else
			{
				throw new Kohana_Exception('Unable to read :file, to put it in the archive', [':file' => Debug::path($path)]);
			}
		}

		foreach (array_keys($files) as $name)
		{
			static::_check_name($name, true);
		}

		ksort($files);

		return $files;
	}

	/**
	 * Returns the files of a directory and its sub directories, by
	 * their names in the archive. Links are left out.
	 *
	 * @param   string  $directory  path of the directory
	 * @param   string  $prefix     name of the directory in the archive, empty for its top
	 * @return  array<string, string>
	 */
	protected static function _files_of(string $directory, string $prefix): array
	{
		$files = [];
		$start = strlen(rtrim($directory, '/\\')) + 1;

		$found = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

		/** @var SplFileInfo $file */
		foreach ($found as $file)
		{
			if ($file->isFile() AND ! $file->isLink())
			{
				$name = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), $start));

				$files[ltrim($prefix.'/'.$name, '/')] = $file->getPathname();
			}
		}

		return $files;
	}

	/**
	 * Checks the name of an entry: it must stay inside the directory an
	 * archive is unpacked in, and must not be a file a web server could
	 * run unless that is allowed.
	 *
	 * @param   string  $name        name of the entry, with "/" between directories
	 * @param   bool    $executable  allow names such as "script.php"
	 * @return  void
	 * @throws  Kohana_Exception  when the name is refused
	 */
	protected static function _check_name(string $name, bool $executable): void
	{
		$name = str_replace('\\', '/', $name);

		if ($name === ''
			OR str_starts_with($name, '/')
			OR preg_match('/^[a-z]:/i', $name) === 1
			OR preg_match('/[\x00-\x1F\x7F]/', $name) === 1
			OR in_array('..', explode('/', $name), true))
			throw new Kohana_Exception('The name ":name" in the archive would leave the directory: refused', [':name' => $name]);

		if ( ! $executable AND Upload::forbidden($name))
			throw new Kohana_Exception('The archive holds a file a web server could run, ":name": refused', [':name' => $name]);
	}

	/**
	 * Makes a directory for an entry, with those above it, and makes
	 * sure it really is inside the directory the archive is unpacked in:
	 * a link that was there before could lead out of it.
	 *
	 * @param   string              $directory  path of the directory to make
	 * @param   string              $base       real path of the directory the archive is unpacked in
	 * @param   array<int, string>  $made       the directories this call made, to which new ones are added
	 * @param-out array<int, string> $made
	 * @return  void
	 * @throws  Kohana_Exception  when it cannot be made or is not inside
	 */
	protected static function _make_directory(string $directory, string $base, array & $made): void
	{
		// The ones that are missing, from the top down
		$missing = [];

		for ($path = $directory; ! is_dir($path) AND strlen($path) > strlen($base); $path = dirname($path))
		{
			array_unshift($missing, $path);
		}

		foreach ($missing as $path)
		{
			if ( ! @mkdir($path, 0755) AND ! is_dir($path))
				throw new Kohana_Exception('Unable to create the directory :dir', [':dir' => Debug::path($path)]);

			$made[] = $path;
		}

		$real = (string) realpath($directory);

		if ($real !== $base AND ! str_starts_with($real, $base.DIRECTORY_SEPARATOR))
			throw new Kohana_Exception('The directory :dir leads out of the directory the archive is unpacked in: refused', [':dir' => Debug::path($directory)]);
	}

	/**
	 * Goes through the entries of an archive of any kind. Each one is
	 * given as an array (name, size, directory). To unpack a file, send
	 * an open file to the generator: it writes the content there and
	 * moves on to the next entry.
	 *
	 * @param   string  $archive  path of the archive
	 * @return  Generator<int, array{name: string, size: int, directory: bool}, resource|null, void>
	 * @throws  Kohana_Exception  when the file is not an archive, or holds a link or a device
	 */
	protected static function _entries(string $archive): Generator
	{
		$format = static::format($archive);

		return ($format === static::ZIP)
			? static::_zip_entries($archive)
			: static::_tar_entries($archive, $format === static::TAR_GZ);
	}

	/**
	 * Opens a zip archive.
	 *
	 * @param   string  $archive  path of the archive
	 * @param   int     $flags    how to open it: ZipArchive::RDONLY, or CREATE and OVERWRITE
	 * @return  ZipArchive
	 * @throws  Kohana_Exception  without the zip extension, or when the archive cannot be opened
	 */
	protected static function _zip(string $archive, int $flags): ZipArchive
	{
		if ( ! class_exists('ZipArchive'))
			throw new Kohana_Exception('Zip archives need the zip extension of PHP');

		$zip = new ZipArchive;

		if ($zip->open($archive, $flags) !== true)
			throw new Kohana_Exception('Unable to open the zip archive :file', [':file' => Debug::path($archive)]);

		return $zip;
	}

	/**
	 * Makes a zip archive.
	 *
	 * @param   string                 $archive  path of the archive
	 * @param   array<string, string>  $files    paths by their names in the archive
	 * @return  void
	 * @throws  Kohana_Exception  when a file cannot be added or the archive cannot be written
	 */
	protected static function _create_zip(string $archive, array $files): void
	{
		$zip = static::_zip($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);

		foreach ($files as $name => $path)
		{
			if ( ! $zip->addFile($path, $name))
				throw new Kohana_Exception('Unable to add :file to the archive', [':file' => Debug::path($path)]);
		}

		// An archive without files is written too: a zip of nothing
		if ( ! $files)
		{
			$zip->setArchiveComment('');
		}

		if ( ! $zip->close())
			throw new Kohana_Exception('Unable to write the zip archive :file', [':file' => Debug::path($archive)]);

		if ( ! is_file($archive))
		{
			// The zip library writes no file for an empty archive: write the 22 bytes of one
			file_put_contents($archive, "PK\x05\x06".str_repeat("\x00", 18));
		}
	}

	/**
	 * Goes through the entries of a zip archive: see [Archive::_entries].
	 *
	 * @param   string  $archive  path of the archive
	 * @return  Generator<int, array{name: string, size: int, directory: bool}, resource|null, void>
	 * @throws  Kohana_Exception  when the archive or an entry cannot be read
	 */
	protected static function _zip_entries(string $archive): Generator
	{
		$zip = static::_zip($archive, ZipArchive::RDONLY);

		for ($index = 0; $index < $zip->numFiles; $index++)
		{
			$entry = $zip->statIndex($index);

			if ($entry === false)
				throw new Kohana_Exception('Unable to read entry :index of the zip archive', [':index' => $index]);

			$name = (string) $entry['name'];

			$target = yield ['name' => $name, 'size' => (int) $entry['size'], 'directory' => str_ends_with($name, '/')];

			if (is_resource($target))
			{
				// A link in a zip is a small file with a path in it: it is
				// written as that file, never as a link
				$content = $zip->getStream($name);

				if ($content === false)
					throw new Kohana_Exception('Unable to read ":name" from the zip archive', [':name' => $name]);

				$written = 0;

				static::_copy($content, $target, null, $written);

				fclose($content);

				// What was written is what the archive said it would be
				if ($written !== (int) $entry['size'])
					throw new Kohana_Exception('The entry ":name" of the zip archive is not of the size it claims', [':name' => $name]);
			}
		}

		$zip->close();
	}

	/**
	 * Makes a tar archive, compressed or not.
	 *
	 * @param   string                 $archive   path of the archive
	 * @param   array<string, string>  $files     paths by their names in the archive
	 * @param   bool                   $compress  compress it with gzip
	 * @return  void
	 * @throws  Kohana_Exception  when a file cannot be read or the archive cannot be written
	 */
	protected static function _create_tar(string $archive, array $files, bool $compress): void
	{
		$tar = static::_open(($compress ? 'compress.zlib://' : '').$archive, 'wb');

		foreach ($files as $name => $path)
		{
			$size = (int) filesize($path);
			$file = static::_open($path, 'rb');

			fwrite($tar, static::_tar_header($name, $size, (int) filemtime($path)));

			$written = 0;

			static::_copy($file, $tar, $size, $written);

			fclose($file);

			// The content of an entry fills whole blocks of 512 bytes
			fwrite($tar, str_repeat("\x00", (512 - $size % 512) % 512));
		}

		// Two empty blocks end the archive
		fwrite($tar, str_repeat("\x00", 1024));
		fclose($tar);
	}

	/**
	 * Returns the 512 bytes that come before the content of a file in
	 * a tar archive ("ustar" format, which every tar program reads).
	 *
	 * @param   string  $name  name of the file in the archive
	 * @param   int     $size  size of the file in bytes
	 * @param   int     $time  when the file was last changed
	 * @return  string
	 * @throws  Kohana_Exception  when the name or the file is too long for this format
	 */
	protected static function _tar_header(string $name, int $size, int $time): string
	{
		$prefix = '';

		if (strlen($name) > 100)
		{
			// A long name is cut in two at a "/": up to 155 and 100 bytes
			$cut = strrpos(substr($name, 0, 156), '/');

			if ($cut === false OR strlen($name) - $cut - 1 > 100)
				throw new Kohana_Exception('The name ":name" is too long for a tar archive', [':name' => $name]);

			$prefix = substr($name, 0, $cut);
			$name   = substr($name, $cut + 1);
		}

		if ($size > 077777777777)
			throw new Kohana_Exception('The file ":name" is too large for a tar archive (8 GB at most)', [':name' => $name]);

		$header = pack('a100a8a8a8a12a12', $name, '0000644', '0000000', '0000000', sprintf('%011o', $size), sprintf('%011o', $time))
			.'        '                                     // the checksum, counted as spaces
			.'0'                                            // a regular file
			.pack('a100a6a2a32a32a8a8a155a12', '', 'ustar', '00', '', '', '', '', $prefix, '');

		// The checksum is the sum of all bytes of the header
		$sum = array_sum(array_map('ord', str_split($header)));

		return substr_replace($header, sprintf('%06o', $sum)."\x00 ", 148, 8);
	}

	/**
	 * Goes through the entries of a tar archive: see [Archive::_entries].
	 *
	 * @param   string  $archive     path of the archive
	 * @param   bool    $compressed  is it compressed with gzip?
	 * @return  Generator<int, array{name: string, size: int, directory: bool}, resource|null, void>
	 * @throws  Kohana_Exception  when the archive is damaged, or holds a link or a device
	 */
	protected static function _tar_entries(string $archive, bool $compressed): Generator
	{
		$tar = static::_open(($compressed ? 'compress.zlib://' : '').$archive, 'rb');

		// A name too long for the header comes in an entry of its own, before the file
		$long_name = null;

		while (($header = static::_read($tar, 512)) !== '' AND trim($header, "\x00") !== '')
		{
			$entry = static::_tar_entry($header);
			$type  = $entry['type'];
			$size  = $entry['size'];

			if ($type === 'L' OR $type === 'x' OR $type === 'g')
			{
				// GNU ("L") and POSIX ("x") ways of giving the next file a long name
				$data = static::_read($tar, $size);

				static::_read($tar, (512 - $size % 512) % 512);

				if ($type === 'L')
				{
					$long_name = rtrim($data, "\x00");
				}
				elseif ($type === 'x' AND preg_match('/^\d+ path=(.*)$/m', $data, $match))
				{
					$long_name = $match[1];
				}

				continue;
			}

			$name      = $long_name ?? $entry['name'];
			$long_name = null;

			if ($type !== '0' AND $type !== '5')
				throw new Kohana_Exception('The archive holds ":name", which is a link or a device and not a file: refused', [':name' => $name]);

			$target = yield ['name' => $name, 'size' => $size, 'directory' => ($type === '5' OR str_ends_with($name, '/'))];

			$written = 0;

			// The content is read in any case: to the file, or to nowhere
			static::_copy($tar, is_resource($target) ? $target : null, $size, $written);

			if ($written !== $size)
				throw new Kohana_Exception('The tar archive ends in the middle of ":name"', [':name' => $name]);

			static::_read($tar, (512 - $size % 512) % 512);
		}

		fclose($tar);
	}

	/**
	 * Reads the header of an entry of a tar archive.
	 *
	 * @param   string  $header  the 512 bytes of the header
	 * @return  array{name: string, size: int, type: string}
	 * @throws  Kohana_Exception  when it is no header of a tar archive
	 */
	protected static function _tar_entry(string $header): array
	{
		if (strlen($header) !== 512)
			throw new Kohana_Exception('The tar archive is cut off');

		// The checksum counts its own 8 bytes as spaces
		$sum = array_sum(array_map('ord', str_split(substr_replace($header, '        ', 148, 8))));

		if ($sum !== octdec(trim(substr($header, 148, 8), " \x00")))
			throw new Kohana_Exception('The tar archive is damaged: a header does not add up');

		$name   = rtrim(substr($header, 0, 100), "\x00");
		$prefix = rtrim(substr($header, 345, 155), "\x00");
		$size   = substr($header, 124, 12);

		// A size too large for 11 digits is written in another way by some programs
		if ((ord($size[0]) & 0x80) !== 0)
			throw new Kohana_Exception('The tar archive holds a file of more than 8 GB: refused');

		// Old archives write "\0" for a regular file
		$type = ($header[156] === "\x00") ? '0' : $header[156];

		return [
			'name' => ($prefix === '') ? $name : $prefix.'/'.$name,
			'size' => (int) octdec(trim($size, " \x00")),
			'type' => $type,
		];
	}

	/**
	 * Opens a file and says so when that fails.
	 *
	 * @param   string  $path  path, or a path after "compress.zlib://"
	 * @param   string  $mode  "rb" or "wb"
	 * @return  resource
	 * @throws  Kohana_Exception  when the file cannot be opened
	 */
	protected static function _open(string $path, string $mode): mixed
	{
		$file = @fopen($path, $mode);

		if ($file === false)
		{
			throw new Kohana_Exception(($mode === 'rb') ? 'Unable to read the file :file' : 'Unable to write the file :file',
				[':file' => Debug::path(str_replace('compress.zlib://', '', $path))]);
		}

		return $file;
	}

	/**
	 * Reads a number of bytes from a file, also from a compressed one,
	 * which gives them in pieces.
	 *
	 * @param   resource  $file   open file
	 * @param   int       $bytes  how many bytes
	 * @return  string  fewer bytes than asked for when the file ends
	 */
	protected static function _read(mixed $file, int $bytes): string
	{
		$data = '';

		while (strlen($data) < $bytes AND ! feof($file))
		{
			$piece = fread($file, max(1, $bytes - strlen($data)));

			if ($piece === false OR $piece === '')
				break;

			$data .= $piece;
		}

		return $data;
	}

	/**
	 * Copies from one open file to another, piece by piece, and keeps
	 * count: all files of one call together may not be larger than
	 * [Archive::$max_size].
	 *
	 * @param   resource       $from     file to read
	 * @param   resource|null  $to       file to write, null to only read
	 * @param   int|null       $bytes    how many bytes, null for all there is
	 * @param   int            $written  set to the number of bytes that were copied
	 * @param-out int $written
	 * @return  void
	 * @throws  Kohana_Exception  when more is unpacked than is allowed
	 */
	protected static function _copy(mixed $from, mixed $to, ?int $bytes, int & $written): void
	{
		$written = 0;

		while (($bytes === null OR $written < $bytes) AND ! feof($from))
		{
			$piece = fread($from, ($bytes === null) ? 65536 : max(1, min(65536, $bytes - $written)));

			if ($piece === false OR $piece === '')
				break;

			$written += strlen($piece);

			if ($to !== null)
			{
				// What is really written counts, whatever the archive claimed
				if ($written > static::$max_size)
					throw new Kohana_Exception('The archive unpacks to more than :max bytes', [':max' => static::$max_size]);

				fwrite($to, $piece);
			}
		}
	}

}
