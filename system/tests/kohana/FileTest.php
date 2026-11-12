<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Kohana File helper
 *
 * @package    Kohana
 * @category   Tests
 *
 * @author     Jeremy Bush <contractfrombelow@gmail.com>
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.file')]
class Kohana_FileTest extends Unittest_TestCase
{
	/**
	 * Provides test data for test_sanitize()
	 *
	 * @return array
	 */
	public static function provider_mime()
	{
		return [
			// $value, $result
			[Kohana::find_file('tests', 'test_data/github', 'png'), 'image/png'],
		];
	}

	/**
	 * Tests File::mime()
	 *
	 * @param boolean $input  Input for File::mime
	 * @param boolean $expected Output for File::mime
	 */
	#[DataProvider('provider_mime')]
	public function test_mime($input, $expected)
	{
		//@todo: File::mime coverage needs significant improvement or to be dropped for a composer package - it's a "horribly unreliable" method with very little testing
		$this->assertSame($expected, File::mime($input));
	}

	/**
	 * Provides test data for test_split_join()
	 *
	 * @return array
	 */
	public static function provider_split_join()
	{
		return [
			// $value, $result
			[Kohana::find_file('tests', 'test_data/github', 'png'), .01, 1],
		];
	}

	/**
	 * Tests File::mime()
	 *
	 * @param boolean $input    Input for File::split
	 * @param boolean $peices   Input for File::split
	 * @param boolean $expected Output for File::splut
	 */
	#[DataProvider('provider_split_join')]
	public function test_split_join($input, $peices, $expected)
	{
		$this->assertSame($expected, File::split($input, $peices));
		$this->assertSame($expected, File::join($input));

		foreach (glob(Kohana::find_file('tests', 'test_data/github', 'png').'.*') as $file)
		{
			unlink($file);
		}
	}

	/**
	 * The type of a file comes from what is in it, not from its name.
	 *
	 * @covers File::mime
	 */
	public function test_mime_looks_at_the_content(): void
	{
		$directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kohana_mime_'.bin2hex(random_bytes(6));
		mkdir($directory);

		try
		{
			// Text, whatever the name says
			file_put_contents($directory.'/notes', 'Just some text.');
			file_put_contents($directory.'/report.pdf', 'Just some text.');

			$this->assertSame('text/plain', File::mime($directory.'/notes'));
			$this->assertSame('text/plain', File::mime($directory.'/report.pdf'));

			// A real image under another name is still an image
			if (function_exists('imagepng'))
			{
				imagepng(imagecreatetruecolor(2, 2), $directory.'/document.txt');

				$this->assertSame('image/png', File::mime($directory.'/document.txt'));
			}

			$this->assertFalse(File::mime($directory.'/no-such-file.txt'));
		}
		finally
		{
			array_map('unlink', glob($directory.'/*'));
			rmdir($directory);
		}
	}

	/**
	 * @covers File::mimes_by_ext
	 * @covers File::exts_by_mime
	 * @covers File::ext_by_mime
	 * @covers File::mime_by_ext
	 */
	public function test_types_and_extensions(): void
	{
		$this->assertContains('image/jpeg', File::mimes_by_ext('jpg'));
		$this->assertSame([], File::mimes_by_ext('no-such-extension'));
		$this->assertSame('image/png', File::mime_by_ext('png'));
		$this->assertFalse(File::mime_by_ext('no-such-extension'));

		$this->assertContains('jpg', File::exts_by_mime('image/jpeg'));
		$this->assertContains('jpeg', File::exts_by_mime('image/jpeg'));
		$this->assertFalse(File::exts_by_mime('no/such-type'));

		// The type for files of any kind names no extension
		$this->assertFalse(File::exts_by_mime('application/octet-stream'));

		$this->assertSame('png', File::ext_by_mime('image/png'));
		$this->assertFalse(File::ext_by_mime('no/such-type'));
	}
}
