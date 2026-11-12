<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Archive: making, listing and unpacking zip, tar and tar.gz
 * archives, and what is refused when an archive is unpacked.
 *
 * @package    Kohana
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.archive')]
class Kohana_ArchiveTest extends Unittest_TestCase
{
	/**
	 * @var string  Directory of the files of one test
	 */
	protected $root;

	public function setUp(): void
	{
		parent::setUp();

		$this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kohana_archive_'.bin2hex(random_bytes(6));

		mkdir($this->root);
		mkdir($this->root.'/out');
	}

	public function tearDown(): void
	{
		$this->remove($this->root);

		parent::tearDown();
	}

	/**
	 * Removes a directory with what is in it, links included.
	 */
	protected function remove(string $directory): void
	{
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file)
		{
			($file->isDir() AND ! $file->isLink()) ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}

		rmdir($directory);
	}

	/**
	 * Writes files under the test directory and returns their directory.
	 *
	 * @param array<string, string> $files  content by name
	 */
	protected function files(array $files, string $directory = 'source'): string
	{
		foreach ($files as $name => $content)
		{
			$path = $this->root.'/'.$directory.'/'.$name;

			is_dir(dirname($path)) or mkdir(dirname($path), 0777, true);
			file_put_contents($path, $content);
		}

		return $this->root.'/'.$directory;
	}

	/**
	 * The files under a directory: content by name.
	 *
	 * @return array<string, string>
	 */
	protected function found(string $directory): array
	{
		$found = [];

		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file)
		{
			$found[substr($file->getPathname(), strlen($directory) + 1)] = file_get_contents($file->getPathname());
		}

		ksort($found);

		return $found;
	}

	/**
	 * Skips a test of zip archives where PHP has no zip extension.
	 */
	protected function needs(string $name): void
	{
		if (str_ends_with($name, '.zip') AND ! class_exists('ZipArchive'))
		{
			$this->markTestSkipped('The zip extension is not available.');
		}
	}

	public static function provider_names(): array
	{
		return [
			'zip'    => ['archive.zip', Archive::ZIP],
			'tar'    => ['archive.tar', Archive::TAR],
			'tar.gz' => ['archive.tar.gz', Archive::TAR_GZ],
			'tgz'    => ['ARCHIVE.TGZ', Archive::TAR_GZ],
		];
	}

	/**
	 * A directory goes into an archive and comes out of it the same.
	 */
	#[DataProvider('provider_names')]
	public function test_directory_round_trip(string $name, string $format): void
	{
		$this->needs($name);

		$files = [
			'readme.txt'             => 'Read me.',
			'empty.txt'              => '',
			'data/2026/report.csv'   => "a;b\n1;2\n",
			'data/olá mundo.txt'     => 'Olá, mundo!',
			'images/bytes.bin'       => random_bytes(1000),
			'large.txt'              => str_repeat('0123456789', 70000),
			'exactly-one-block.txt'  => str_repeat('x', 512),
		];

		$source  = $this->files($files);
		$archive = Archive::create($this->root.'/'.$name, $source);

		$this->assertSame($this->root.'/'.$name, $archive);
		$this->assertSame($format, Archive::format($archive));

		$contents = Archive::contents($archive);
		$sizes    = array_column($contents, 'size', 'name');

		ksort($files);

		$this->assertSame(array_keys($files), array_keys($sizes));
		$this->assertSame(array_map('strlen', $files), $sizes);
		$this->assertSame([false], array_unique(array_column($contents, 'directory')));

		$extracted = Archive::extract($archive, $this->root.'/out');

		sort($extracted);

		$this->assertSame(array_keys($files), $extracted);
		$this->assertSame($files, $this->found($this->root.'/out'));
		$this->assertSame(0644, fileperms($this->root.'/out/readme.txt') & 0777);
	}

	/**
	 * A list of files and directories, under their own names or under
	 * names that are given.
	 */
	#[DataProvider('provider_names')]
	public function test_list_of_files(string $name, string $format): void
	{
		$this->needs($name);

		$source = $this->files(['summary.txt' => 'summary', 'export.csv' => 'export', 'photos/one.jpg' => 'one', 'photos/more/two.jpg' => 'two']);

		$archive = Archive::create($this->root.'/'.$name, [
			$source.'/summary.txt',
			'data/2026.csv' => $source.'/export.csv',
			'images'        => $source.'/photos',
		]);

		$this->assertSame(
			['data/2026.csv', 'images/more/two.jpg', 'images/one.jpg', 'summary.txt'],
			array_column(Archive::contents($archive), 'name')
		);

		Archive::extract($archive, $this->root.'/out/new/deeper');

		$this->assertSame('export', file_get_contents($this->root.'/out/new/deeper/data/2026.csv'));
	}

	#[DataProvider('provider_names')]
	public function test_archive_of_nothing(string $name, string $format): void
	{
		$this->needs($name);

		mkdir($this->root.'/source');

		$archive = Archive::create($this->root.'/'.$name, $this->root.'/source');

		$this->assertSame([], Archive::contents($archive));
		$this->assertSame([], Archive::extract($archive, $this->root.'/out'));
	}

	/**
	 * Unpacking replaces a file that is there, and an archive that is
	 * made again replaces the old one.
	 */
	#[DataProvider('provider_names')]
	public function test_existing_files_are_replaced(string $name, string $format): void
	{
		$this->needs($name);

		$archive = Archive::create($this->root.'/'.$name, $this->files(['a.txt' => 'first']));

		file_put_contents($this->root.'/out/a.txt', 'old content, and longer');
		Archive::extract($archive, $this->root.'/out');

		$this->assertSame('first', file_get_contents($this->root.'/out/a.txt'));

		Archive::create($archive, $this->files(['b.txt' => 'second'], 'other'));

		$this->assertSame(['b.txt'], array_column(Archive::contents($archive), 'name'));
	}

	/**
	 * The kind of an archive is read from its content: its name can be anything.
	 */
	public function test_format_does_not_trust_the_name(): void
	{
		$tar = Archive::create($this->root.'/a.tar.gz', $this->files(['a.txt' => 'a']));

		rename($tar, $this->root.'/upload.tmp');

		$this->assertSame(Archive::TAR_GZ, Archive::format($this->root.'/upload.tmp'));
		$this->assertSame(['a.txt'], Archive::extract($this->root.'/upload.tmp', $this->root.'/out'));

		// Text with the name of an archive, and one file compressed with gzip, are no archives
		file_put_contents($this->root.'/fake.zip', 'just some text');
		Archive::gzip($this->root.'/fake.zip', $this->root.'/single.tar.gz');

		foreach (['fake.zip', 'single.tar.gz', 'missing.zip'] as $file)
		{
			try
			{
				Archive::format($this->root.'/'.$file);
				$this->fail($file.' is no archive');
			}
			catch (Kohana_Exception $e)
			{
				$this->assertMatchesRegularExpression('/is not a zip, tar or tar\.gz archive|Unable to read the archive/', $e->getMessage());
			}
		}

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('ends in .zip, .tar, .tar.gz or .tgz');

		Archive::create($this->root.'/archive.rar', $this->root.'/source');
	}

	/**
	 * Archives of this class are read by the usual programs, and
	 * theirs by this class: long names included.
	 */
	public function test_works_with_the_tar_and_unzip_programs(): void
	{
		if ( ! is_executable('/usr/bin/tar') AND ! is_executable('/bin/tar'))
		{
			$this->markTestSkipped('There is no tar program.');
		}

		$long   = str_repeat('a-long-directory-name/', 6).'and-a-file-with-a-long-name-of-its-own.txt';
		$longer = str_repeat('an-even-longer-directory-name-to-go-past-what-the-header-can-hold/', 5).'file.txt';

		$source = $this->files(['short.txt' => 'short', $long => 'long', $longer => 'longer']);

		// Ours, read by tar
		$ours = Archive::create($this->root.'/ours.tar.gz', $this->files(['short.txt' => 'short', $long => 'long'], 'two'));

		exec('tar -tzf '.escapeshellarg($ours).' 2>&1', $listed, $status);

		$this->assertSame(0, $status, implode("\n", $listed));
		$this->assertSame([$long, 'short.txt'], $listed);

		// A name that fits no header is refused when the archive is made
		try
		{
			Archive::create($this->root.'/too-long.tar', $source);
			$this->fail('A name of '.strlen($longer).' bytes does not fit a tar header');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('is too long for a tar archive', $e->getMessage());
			$this->assertFileDoesNotExist($this->root.'/too-long.tar');
		}

		// Theirs, with such names written the GNU way and the POSIX way, read by us
		foreach (['gnu', 'pax'] as $format)
		{
			$theirs = $this->root.'/'.$format.'.tar';

			exec('tar --format='.$format.' -cf '.escapeshellarg($theirs).' -C '.escapeshellarg($source).' . 2>&1', $output, $status);

			$this->assertSame(0, $status, implode("\n", $output));

			$names = array_filter(array_column(Archive::contents($theirs), 'directory', 'name'), fn ($directory) => ! $directory);

			$this->assertContains('./'.$longer, array_keys($names), $format);

			$out = $this->root.'/out/'.$format;

			Archive::extract($theirs, $out);

			$expected = ['short.txt' => 'short', $long => 'long', $longer => 'longer'];

			ksort($expected);

			$this->assertSame($expected, array_intersect_key($this->found($out), $expected), $format);
		}

		if (class_exists('ZipArchive') AND is_executable('/usr/bin/unzip'))
		{
			$zip = Archive::create($this->root.'/ours.zip', $this->files(['short.txt' => 'short'], 'three'));

			exec('unzip -l '.escapeshellarg($zip).' 2>&1', $unzip, $status);

			$this->assertSame(0, $status);
			$this->assertStringContainsString('short.txt', implode("\n", $unzip));
		}
	}

	/**
	 * Returns a tar archive with one entry of any name and type, as
	 * somebody who means harm would make it.
	 */
	protected function forged_tar(string $name, string $content = 'evil', string $type = '0'): string
	{
		$header = (new ReflectionMethod('Archive', '_tar_header'))->invoke(null, 'placeholder', strlen($content), time());

		// Put the name and the type in, and make the checksum right again
		$header = substr_replace($header, str_pad($name, 100, "\x00"), 0, 100);
		$header = substr_replace($header, $type, 156, 1);
		$header = substr_replace($header, '        ', 148, 8);
		$header = substr_replace($header, sprintf('%06o', array_sum(array_map('ord', str_split($header))))."\x00 ", 148, 8);

		$path = $this->root.'/forged_'.bin2hex(random_bytes(4)).'.tar';

		file_put_contents($path, $header.str_pad($content, (int) (ceil(strlen($content) / 512) * 512), "\x00").str_repeat("\x00", 1024));

		return $path;
	}

	public static function provider_names_that_leave(): array
	{
		return [
			'up one directory'      => ['../evil.txt'],
			'up from a directory'   => ['docs/../../evil.txt'],
			'from the root'         => ['/tmp/kohana-evil.txt'],
			'the Windows way'       => ['..\\evil.txt'],
			'a Windows drive'       => ['C:\\evil.txt'],
			'a control character'   => ["evil\x07.txt"],
			'only dots'             => ['..'],
		];
	}

	/**
	 * Whatever the names in an archive say, nothing is written outside
	 * the directory it is unpacked in, and nothing of it is left inside.
	 */
	#[DataProvider('provider_names_that_leave')]
	public function test_names_that_leave_the_directory_are_refused(string $name): void
	{
		$archives = ['tar' => $this->forged_tar($name)];

		if (class_exists('ZipArchive'))
		{
			$zip = new ZipArchive;
			$zip->open($this->root.'/forged.zip', ZipArchive::CREATE);
			$zip->addFromString('good.txt', 'good');

			// The zip library itself refuses some of these names
			$added = $zip->addFromString($name, 'evil');
			$zip->close();

			if ($added AND in_array($name, array_column(Archive::contents($this->root.'/forged.zip'), 'name'), true))
			{
				$archives['zip'] = $this->root.'/forged.zip';
			}
		}

		foreach ($archives as $kind => $archive)
		{
			try
			{
				Archive::extract($archive, $this->root.'/out');
				$this->fail('The '.$kind.' archive must be refused');
			}
			catch (Kohana_Exception $e)
			{
				$this->assertStringContainsString('would leave the directory', $e->getMessage(), $kind);
			}

			$this->assertSame([], $this->found($this->root.'/out'), 'Nothing of a refused '.$kind.' archive is left');
		}

		$this->assertFileDoesNotExist($this->root.'/evil.txt');
		$this->assertFileDoesNotExist('/tmp/kohana-evil.txt');
	}

	/**
	 * A tar archive can hold links and devices: those are refused.
	 */
	public function test_links_and_devices_are_refused(): void
	{
		foreach (['2' => 'a link', '1' => 'a hard link', '3' => 'a device', '6' => 'a pipe'] as $type => $what)
		{
			$archive = $this->forged_tar('innocent.txt', '', (string) $type);

			try
			{
				Archive::extract($archive, $this->root.'/out');
				$this->fail($what.' must be refused');
			}
			catch (Kohana_Exception $e)
			{
				$this->assertStringContainsString('is a link or a device and not a file', $e->getMessage(), $what);
			}

			try
			{
				Archive::contents($archive);
				$this->fail($what.' must be refused when listing too');
			}
			catch (Kohana_Exception $e)
			{
				$this->assertStringContainsString('refused', $e->getMessage());
			}
		}

		$this->assertSame([], $this->found($this->root.'/out'));
	}

	/**
	 * A link that was in the target directory before must not lead a
	 * file out of it.
	 */
	public function test_link_in_the_target_directory_is_not_followed(): void
	{
		mkdir($this->root.'/outside');
		symlink($this->root.'/outside', $this->root.'/out/shortcut');

		$archive = Archive::create($this->root.'/a.tar', ['shortcut/evil.txt' => $this->files(['evil.txt' => 'evil']).'/evil.txt']);

		try
		{
			Archive::extract($archive, $this->root.'/out');
			$this->fail('A file must not be written through the link');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('leads out of the directory', $e->getMessage());
		}

		$this->assertSame([], $this->found($this->root.'/outside'));
	}

	/**
	 * Files a web server could run are refused, unless asked for.
	 */
	#[DataProvider('provider_names')]
	public function test_executable_files_are_refused_unless_asked_for(string $name, string $format): void
	{
		$this->needs($name);

		$archive = Archive::create($this->root.'/'.$name, $this->files(['a.txt' => 'a', 'lib/shell.php' => '<?php', 'z.txt' => 'z']));

		try
		{
			Archive::extract($archive, $this->root.'/out');
			$this->fail('A .php file must be refused');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('a file a web server could run, "lib/shell.php"', $e->getMessage());
		}

		$this->assertSame([], $this->found($this->root.'/out'), 'The file unpacked before it is removed again');
		$this->assertDirectoryDoesNotExist($this->root.'/out/lib');

		$this->assertCount(3, Archive::extract($archive, $this->root.'/out', true));
		$this->assertSame('<?php', file_get_contents($this->root.'/out/lib/shell.php'));
	}

	/**
	 * An archive cannot unpack to more than is allowed, in bytes or in files.
	 */
	#[DataProvider('provider_names')]
	public function test_limits(string $name, string $format): void
	{
		$this->needs($name);

		// 300 000 zeros compress to almost nothing
		$archive = Archive::create($this->root.'/'.$name, $this->files(['a.txt' => 'small', 'bomb.txt' => str_repeat("\x00", 300000)]));

		$this->assertLessThan(($name === 'archive.tar') ? 400000 : 5000, filesize($archive));

		$this->setEnvironment(['Archive::$max_size' => 100000]);

		try
		{
			Archive::extract($archive, $this->root.'/out');
			$this->fail('More than the allowed size must be refused');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertSame('The archive unpacks to more than 100000 bytes', $e->getMessage());
		}

		$this->assertSame([], $this->found($this->root.'/out'));

		$this->setEnvironment(['Archive::$max_size' => 1000000, 'Archive::$max_files' => 1]);

		try
		{
			Archive::extract($archive, $this->root.'/out');
			$this->fail('More than the allowed number of files must be refused');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertSame('The archive holds more than 1 entries', $e->getMessage());
		}

		$this->assertSame([], $this->found($this->root.'/out'));

		$this->setEnvironment(['Archive::$max_files' => 2]);

		$this->assertCount(2, Archive::extract($archive, $this->root.'/out'));
	}

	/**
	 * A tar archive that is cut off or changed is noticed.
	 */
	public function test_damaged_tar_is_reported(): void
	{
		$archive = Archive::create($this->root.'/a.tar', $this->files(['a.txt' => str_repeat('a', 2000)]));
		$content = file_get_contents($archive);

		file_put_contents($this->root.'/cut.tar', substr($content, 0, 1200));

		try
		{
			Archive::extract($this->root.'/cut.tar', $this->root.'/out');
			$this->fail('A tar that is cut off must be reported');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('ends in the middle of "a.txt"', $e->getMessage());
		}

		$this->assertSame([], $this->found($this->root.'/out'));

		// One byte of the name changed: the header no longer adds up
		file_put_contents($this->root.'/changed.tar', substr_replace($content, 'b', 0, 1));

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('a header does not add up');

		Archive::contents($this->root.'/changed.tar');
	}

	public function test_missing_source_is_reported(): void
	{
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('to put it in the archive');

		Archive::create($this->root.'/a.tar', [$this->root.'/no-such-file.txt']);
	}

	/**
	 * Links are not followed when an archive is made: what they point
	 * to stays out of it.
	 */
	public function test_links_are_left_out_of_a_new_archive(): void
	{
		$source = $this->files(['real.txt' => 'real']);

		file_put_contents($this->root.'/secret.txt', 'secret');
		symlink($this->root.'/secret.txt', $source.'/link.txt');

		$archive = Archive::create($this->root.'/a.tar', $source);

		$this->assertSame(['real.txt'], array_column(Archive::contents($archive), 'name'));
		$this->assertSame([], array_column(Archive::contents(Archive::create($this->root.'/b.tar', [$source.'/link.txt'])), 'name'));
	}

	public function test_gzip_and_gunzip(): void
	{
		$content = str_repeat('Compress me. ', 10000);
		$file    = $this->files(['export.csv' => $content]).'/export.csv';

		$compressed = Archive::gzip($file);

		$this->assertSame($file.'.gz', $compressed);
		$this->assertLessThan(strlen($content) / 10, filesize($compressed));
		$this->assertSame($content, gzdecode(file_get_contents($compressed)));
		$this->assertFileExists($file, 'The file itself stays');

		unlink($file);

		$this->assertSame($file, Archive::gunzip($compressed));
		$this->assertSame($content, file_get_contents($file));

		// Names of your own
		$other = Archive::gzip($file, $this->root.'/other.bin');

		$this->assertSame($this->root.'/plain.txt', Archive::gunzip($other, $this->root.'/plain.txt'));
		$this->assertSame($content, file_get_contents($this->root.'/plain.txt'));
	}

	public function test_gunzip_refuses_what_it_should(): void
	{
		$file = $this->files(['big.txt' => str_repeat("\x00", 300000), 'text.gz' => 'not compressed'])
			.'/big.txt';

		$compressed = Archive::gzip($file);

		// More than is allowed: nothing is left
		$this->setEnvironment(['Archive::$max_size' => 100000]);

		try
		{
			Archive::gunzip($compressed, $this->root.'/out/big.txt');
			$this->fail('More than the allowed size must be refused');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('more than 100000 bytes', $e->getMessage());
			$this->assertFileDoesNotExist($this->root.'/out/big.txt');
		}

		// A name that does not say where to unpack to
		try
		{
			Archive::gunzip(Archive::gzip($file, $this->root.'/data.bin'));
			$this->fail('Without ".gz" the target must be given');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('does not end in ".gz"', $e->getMessage());
		}

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('is not compressed with gzip');

		Archive::gunzip($this->root.'/source/text.gz');
	}
}
