<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Kohana upload class
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
#[Group('kohana.core.upload')]
class Kohana_UploadTest extends Unittest_TestCase
{
	/**
	 * Provides test data for test_size()
	 *
	 * @return array
	 */
	public static function provider_size()
	{
		return [
			// $field, $bytes, $environment, $expected
			[
				'unit_test',
				5,
				['_FILES' => ['unit_test' => ['error' => UPLOAD_ERR_INI_SIZE]]],
				false
			],
			[
				'unit_test',
				5,
				['_FILES' => ['unit_test' => ['error' => UPLOAD_ERR_NO_FILE]]],
				true
			],
			[
				'unit_test',
				'6K',
				['_FILES' => [
					'unit_test' => [
						'error' => UPLOAD_ERR_OK,
						'name' => 'Unit_Test File',
						'type' => 'image/png',
						'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
						'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
						]
					]
				],
				true
			],
			[
				'unit_test',
				'1B',
				['_FILES' => [
						'unit_test' => [
							'error' => UPLOAD_ERR_OK,
							'name' => 'Unit_Test File',
							'type' => 'image/png',
							'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
							'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
						]
					]
				],
				false
			],
		];
	}

	/**
	 * Tests Upload::size
	 *
	 * @covers upload::size
	 * @param string $field the files field to test
	 * @param string $bytes valid bite size
	 * @param array $environment set the $_FILES array
	 * @param bool $expected what to expect
	 */
	#[DataProvider('provider_size')]
	public function test_size($field, $bytes, $environment, $expected)
	{
		$this->setEnvironment($environment);

		$this->assertSame($expected, Upload::size($_FILES[$field], $bytes));
	}

	/**
	 * size() should throw an exception of the supplied max size is invalid
	 *
	 * @covers upload::size
	 */
	public function test_size_throws_exception_for_invalid_size()
	{
		$this->expectException(Kohana_Exception::class);
		$this->setEnvironment([
			'_FILES' => [
				'unit_test' => [
					'error' => UPLOAD_ERR_OK,
					'name' => 'Unit_Test File',
					'type' => 'image/png',
					'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
					'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
				]
			]
		]);

		Upload::size($_FILES['unit_test'], '1DooDah');
	}

	/**
	 * Provides test data for test_valid()
	 *
	 * @return array
	 */
	public static function provider_valid()
	{
		return [
			[
				true,
				[
					'error' => UPLOAD_ERR_OK,
					'name' => 'Unit_Test File',
					'type' => 'image/png',
					'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
					'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
				]
			],
			[
				false,
				[
					'name' => 'Unit_Test File',
					'type' => 'image/png',
					'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
					'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
				]
			],
			[
				false,
				[
					'error' => UPLOAD_ERR_OK,
					'type' => 'image/png',
					'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
					'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
				]
			],
			[
				false,
				[
					'name' => 'Unit_Test File',
					'error' => UPLOAD_ERR_OK,
					'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
					'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
				]
			],
			[
				false,
				[
					'error' => UPLOAD_ERR_OK,
					'name' => 'Unit_Test File',
					'type' => 'image/png',
					'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
				]
			],
			[
				false,
				[
					'error' => UPLOAD_ERR_OK,
					'name' => 'Unit_Test File',
					'type' => 'image/png',
					'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
				]
			],

		];
	}

	/**
	 * Test Upload::valid
	 *
	 * @covers Upload::valid
	 */
	#[DataProvider('provider_valid')]
	public function test_valid($expected, $file)
	{
		$this->setEnvironment([
			'_FILES' => [
				'unit_test' => $file,
			],
		]);

		$this->assertSame($expected, Upload::valid($_FILES['unit_test']));
	}

	/**
	 * Tests Upload::type
	 *
	 * @covers Upload::type
	 */
	public function test_type()
	{
		$this->setEnvironment([
			'_FILES' => [
				'unit_test' => [
					'error' => UPLOAD_ERR_OK,
					'name' => 'github.png',
					'type' => 'image/png',
					'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
					'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
				]
			]
		]);

		$this->assertTrue(Upload::type($_FILES['unit_test'], ['jpg', 'png', 'gif']));

		$this->assertFalse(Upload::type($_FILES['unit_test'], ['docx']));
	}

	/**
	 * Provides filenames and whether Upload::save() must refuse them.
	 *
	 * @return array
	 */
	public static function provider_forbidden()
	{
		return [
			['photo.jpg', false],
			['archive.tar.gz', false],
			['no_extension', false],
			['shell.php', true],
			['shell.PHP', true],
			['shell.phtml', true],
			['shell.php.jpg', true],
			['shell.php.', true],
			['.htaccess', true],
			['../../shell.phar', true],
		];
	}

	/**
	 * @covers Upload::forbidden
	 */
	#[DataProvider('provider_forbidden')]
	public function test_forbidden($filename, $expected)
	{
		$this->assertSame($expected, Upload::forbidden($filename));
	}

	/**
	 * Emptying the list turns the protection off.
	 *
	 * @covers Upload::forbidden
	 */
	public function test_forbidden_list_can_be_changed()
	{
		$this->setEnvironment(['Upload::$forbidden_extensions' => []]);

		$this->assertFalse(Upload::forbidden('shell.php'));
	}

	/**
	 * @var string|null  Directory of the test that saves files
	 */
	protected $directory = null;

	public function tearDown(): void
	{
		Kohana_UploadTest_Upload::$uploaded = [];

		if ($this->directory !== null)
		{
			array_map('unlink', glob($this->directory.DIRECTORY_SEPARATOR.'{,.}[!.]*', GLOB_BRACE));
			rmdir($this->directory);
		}

		parent::tearDown();
	}

	/**
	 * Makes a temporary file that counts as uploaded, and returns its
	 * entry of $_FILES. The target directory of the test is made too.
	 */
	protected function upload(string $name, string $content = 'content'): array
	{
		if ($this->directory === null)
		{
			$this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kohana_upload_'.bin2hex(random_bytes(6));
			mkdir($this->directory);
		}

		$tmp = $this->directory.DIRECTORY_SEPARATOR.'.tmp_'.bin2hex(random_bytes(6));
		file_put_contents($tmp, $content);

		Kohana_UploadTest_Upload::$uploaded[] = $tmp;

		return ['name' => $name, 'type' => 'text/plain', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
	}

	/**
	 * @covers Upload::save
	 */
	public function test_save_stores_the_file_under_a_random_name(): void
	{
		$file = $this->upload('my report.txt', 'the report');

		$first = Kohana_UploadTest_Upload::save($file, null, $this->directory);

		$this->assertIsString($first);
		$this->assertSame(realpath($this->directory), dirname($first));
		$this->assertMatchesRegularExpression('/^[0-9a-f]{16}my_report\.txt$/', basename($first));
		$this->assertSame('the report', file_get_contents($first));
		$this->assertSame(0644, fileperms($first) & 0777);
		$this->assertFileDoesNotExist($file['tmp_name']);

		// The same name again gets another prefix: nothing is overwritten
		$second = Kohana_UploadTest_Upload::save($this->upload('my report.txt'), null, $this->directory);

		$this->assertNotSame($first, $second);
	}

	/**
	 * @covers Upload::save
	 */
	public function test_save_with_a_given_name_and_settings(): void
	{
		$file = $this->upload('ignored.txt');

		$this->setEnvironment(['Upload::$remove_spaces' => false, 'Upload::$default_directory' => $this->directory]);

		$path = Kohana_UploadTest_Upload::save($file, 'my name.txt', null, 0600);

		$this->assertSame(realpath($this->directory).DIRECTORY_SEPARATOR.'my name.txt', $path);
		$this->assertSame(0600, fileperms($path) & 0777);
	}

	/**
	 * The name a visitor sends can hold directories and control
	 * characters: only the last part is used.
	 *
	 * @covers Upload::save
	 */
	public function test_save_strips_directories_and_control_characters(): void
	{
		foreach (['../../etc/passwd.txt', '..\\..\\windows\\passwd.txt', "pass\x00wd\x1F.txt", '/absolute/passwd.txt'] as $name)
		{
			$path = Kohana_UploadTest_Upload::save($this->upload($name), null, $this->directory);

			$this->assertIsString($path, $name);
			$this->assertSame(realpath($this->directory), dirname($path), $name);
			$this->assertMatchesRegularExpression('/^[0-9a-f]{16}passwd\.txt$/', basename($path), $name);
		}
	}

	/**
	 * A name given to save() may hold a sub directory, but never leads
	 * out of the upload directory. It is often the name a visitor sent.
	 *
	 * @covers Upload::save
	 */
	public function test_save_refuses_a_given_name_that_leaves_the_directory(): void
	{
		$outside = ['../evil.txt', 'sub/../../evil.txt', '..\\evil.txt', '/tmp/evil.txt', 'C:\\evil.txt', "evil\x00.txt", '..'];

		foreach ($outside as $name)
		{
			$file = $this->upload('innocent.txt');

			$this->assertFalse(Kohana_UploadTest_Upload::save($file, $name, $this->directory), $name);
			$this->assertFileExists($file['tmp_name'], 'A refused upload is not moved');
		}

		$this->assertFileDoesNotExist(dirname($this->directory).DIRECTORY_SEPARATOR.'evil.txt');

		// Dots inside a name are no climbing
		$path = Kohana_UploadTest_Upload::save($this->upload('innocent.txt'), 'report..final.txt', $this->directory);

		$this->assertSame(realpath($this->directory).DIRECTORY_SEPARATOR.'report..final.txt', $path);
	}

	/**
	 * A file the web server could run is never stored, whoever chose the name.
	 *
	 * @covers Upload::save
	 */
	public function test_save_refuses_executable_names(): void
	{
		foreach (['shell.php', 'shell.PHP', 'shell.php.jpg', 'shell.phtml', '.htaccess', 'shell.php.'] as $name)
		{
			$file = $this->upload($name);

			$this->assertFalse(Kohana_UploadTest_Upload::save($file, null, $this->directory), $name);
			$this->assertFalse(Kohana_UploadTest_Upload::save($this->upload('innocent.txt'), $name, $this->directory), $name);
			$this->assertFileExists($file['tmp_name'], 'A refused upload is not moved');
		}

		// Only the temporary files are in the directory
		$this->assertSame([], glob($this->directory.DIRECTORY_SEPARATOR.'*'));
	}

	/**
	 * Only a file that PHP received as an upload is saved: a path from
	 * anywhere else (a forged "tmp_name") is refused.
	 *
	 * @covers Upload::save
	 * @covers Upload::not_empty
	 */
	public function test_file_that_was_not_uploaded_is_refused(): void
	{
		$file = $this->upload('real.txt');

		$forged = ['tmp_name' => __FILE__] + $file;
		$list   = ['tmp_name' => [$file['tmp_name']]] + $file;

		$this->assertFalse(Kohana_UploadTest_Upload::save($forged, null, $this->directory));
		$this->assertFalse(Kohana_UploadTest_Upload::save($list, null, $this->directory));
		$this->assertFalse(Kohana_UploadTest_Upload::not_empty($forged));
		$this->assertFalse(Kohana_UploadTest_Upload::not_empty($list));
		$this->assertFalse(Kohana_UploadTest_Upload::not_empty('text'));
		$this->assertFalse(Kohana_UploadTest_Upload::not_empty(['error' => UPLOAD_ERR_NO_FILE] + $file));
		$this->assertTrue(Kohana_UploadTest_Upload::not_empty($file));

		// Without the test double nothing is an upload on the command line
		$this->assertFalse(Upload::save($file, null, $this->directory));
		$this->assertFalse(Upload::not_empty($file));
	}

	/**
	 * @covers Upload::save
	 */
	public function test_save_needs_a_writable_directory(): void
	{
		$file = $this->upload('file.txt');

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('must be writable');

		Kohana_UploadTest_Upload::save($file, null, $this->directory.DIRECTORY_SEPARATOR.'missing');
	}

	/**
	 * @covers Upload::image
	 */
	public function test_image_checks_that_it_is_an_image_and_its_size(): void
	{
		if ( ! function_exists('imagepng'))
		{
			$this->markTestSkipped('The GD extension is not available.');
		}

		ob_start();
		imagepng(imagecreatetruecolor(20, 10));
		$image = $this->upload('picture.png', ob_get_clean());

		$this->assertTrue(Kohana_UploadTest_Upload::image($image));
		$this->assertTrue(Kohana_UploadTest_Upload::image($image, 20, 10));
		$this->assertTrue(Kohana_UploadTest_Upload::image($image, 100, 100));
		$this->assertTrue(Kohana_UploadTest_Upload::image($image, 20, 10, true));
		$this->assertTrue(Kohana_UploadTest_Upload::image($image, null, 10));
		$this->assertFalse(Kohana_UploadTest_Upload::image($image, 19, 10));
		$this->assertFalse(Kohana_UploadTest_Upload::image($image, 20, 9));
		$this->assertFalse(Kohana_UploadTest_Upload::image($image, 100, 100, true));

		// A text file with the name of an image is no image
		$this->assertFalse(Kohana_UploadTest_Upload::image($this->upload('picture.png', 'not an image')));

		// And a path that was not uploaded is not even looked at
		$this->assertFalse(Kohana_UploadTest_Upload::image(['tmp_name' => __FILE__] + $image));
		$this->assertFalse(Upload::image($image));
	}
}

/**
 * Upload on the command line: the files the test made count as uploaded.
 */
class Kohana_UploadTest_Upload extends Upload
{
	/**
	 * @var array<int, string>  Paths that count as uploaded files
	 */
	public static array $uploaded = [];

	protected static function _is_uploaded(string $path): bool
	{
		return in_array($path, static::$uploaded, true);
	}

	protected static function _move(string $from, string $to): bool
	{
		return rename($from, $to);
	}
}
