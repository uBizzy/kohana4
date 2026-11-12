# Archives

`Archive` makes, reads and unpacks archives: zip, tar and tar.gz. It also compresses single files with gzip.

```php
// Everything in a directory
Archive::create(APPPATH.'cache/export.zip', APPPATH.'data/export');

// What is in an archive
foreach (Archive::contents($archive) as $entry)
{
	echo $entry['name'], ': ', $entry['size'], ' bytes';
}

// Unpack it
$files = Archive::extract($archive, APPPATH.'data/import');
```

| Kind | Name ends in | Needs |
|---|---|---|
| Zip | `.zip` | the `zip` extension of PHP |
| Tar | `.tar` | nothing |
| Tar, compressed | `.tar.gz`, `.tgz` | nothing |

## Making an archive

The kind follows from the name you give it.

```php
// A directory, with its sub directories
Archive::create('backup.tar.gz', APPPATH.'data');
```

```php
// Some files, under their own names or under names you give
Archive::create('report.zip', [
	'/path/to/summary.pdf',                        // "summary.pdf"
	'data/2026.csv' => '/path/to/export.csv',      // under another name
	'images'        => '/path/to/a/directory',     // a directory, as "images/..."
]);
```

- An archive of that name that exists is replaced.
- Links are not followed and not stored: what a link points to stays out of the archive.
- Empty directories are left out.
- In a tar archive a name can be 255 bytes long at most, and a file 8 GB.

## Looking inside

```php
$entries = Archive::contents($archive);

// [
//     ['name' => 'data/2026.csv', 'size' => 1532, 'directory' => false],
//     ...
// ]
```

Nothing is unpacked. The kind of archive is found from its **content**, so the name of the file does not matter: an upload in a temporary file works.

```php
Archive::format($file);   // Archive::ZIP, Archive::TAR or Archive::TAR_GZ
```

A file that is none of these throws a `Kohana_Exception`.

## Unpacking

```php
$files = Archive::extract($archive, APPPATH.'data/import');

// ['data/2026.csv', 'summary.pdf', ...]: the names of the files, as in the archive
```

The directory is made when it is not there. Files that exist are replaced. Every file is written as an ordinary file, readable and not executable, whatever the archive says about it.

### An archive from a visitor

An archive is a small program for the one who unpacks it: it says which files to write, and where. `Archive::extract()` does not take its word for it.

| An archive that… | is… |
|---|---|
| names a file outside the directory (`../../config.php`, `/etc/passwd`) | refused |
| holds a link, a device or a pipe (tar) | refused |
| would write through a link that was already in the target directory | refused |
| unpacks to more than `Archive::$max_size` bytes (1 GB) | refused |
| holds more than `Archive::$max_files` entries (10 000) | refused |
| holds a file a web server could run (`.php`, `.phtml`, `.htaccess`…) | refused, unless you allow it |
| is damaged or cut off | refused |

When an archive is refused, the files this call had already unpacked are removed again: nothing of it is left behind. A `Kohana_Exception` says what was wrong.

```php
// For uploads, allow much less than the default
Archive::$max_size  = 20 * 1024 * 1024;   // 20 MB
Archive::$max_files = 500;

try
{
	$files = Archive::extract($_FILES['archive']['tmp_name'], $directory);
}
catch (Kohana_Exception $e)
{
	// Tell the visitor the archive cannot be used
}
```

A small archive can unpack to a huge one ("zip bomb"). The size is checked twice: what the archive claims before anything is written, and what is really written while it is written.

[!!] Files a web server could run are the ones of [Upload::$forbidden_extensions](files#receiving-an-upload). Unpack them only from an archive you made yourself, with the third argument: `Archive::extract($archive, $directory, true)`.

## One file

```php
// Makes "export.csv.gz"; the file itself stays
$compressed = Archive::gzip('export.csv');

// And back: makes "export.csv"
$file = Archive::gunzip('export.csv.gz');
```

```php
// Names of your own
Archive::gzip('export.csv', 'export-2026.gz');
Archive::gunzip('export-2026.gz', 'export.csv');
```

`gunzip()` writes no more than `Archive::$max_size` bytes, and leaves no file when that is passed.

For text in memory, PHP's own `gzencode()` and `gzdecode()` do the same without a file.
