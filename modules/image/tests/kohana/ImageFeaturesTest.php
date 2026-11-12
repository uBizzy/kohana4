<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the features added to the Image module in 4.0: AVIF, HEIC
 * detection, the pixel limit, images from data, thumbnails, the default
 * quality and the configured driver. Runs with the GD driver.
 *
 * @package    Kohana/Image
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.image')]
class Kohana_ImageFeaturesTest extends Unittest_TestCase
{
	// A JPEG photo of 150 x 150 pixels
	protected const string JPEG = __DIR__.'/../test_data/test_image.jpg';

	/**
	 * @var  array  Files to remove after the test
	 */
	protected array $files = [];

	public function setUp(): void
	{
		parent::setUp();

		if ( ! extension_loaded('gd'))
		{
			$this->markTestSkipped('The gd extension is needed.');
		}
	}

	public function tearDown(): void
	{
		array_map('unlink', array_filter($this->files, 'is_file'));

		parent::tearDown();
	}

	/**
	 * Returns the path of a temporary file that is removed after the test.
	 */
	protected function temp(string $extension): string
	{
		return $this->files[] = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kohana_imgtest_'.bin2hex(random_bytes(6)).'.'.$extension;
	}

	/**
	 * Returns the temporary files Image::from_string() has left behind.
	 */
	protected function leftovers(): array
	{
		return glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'kohana_image_*') ?: [];
	}

	public function test_factory_uses_the_configured_driver(): void
	{
		$config = Kohana::$config->load('image');
		$before = $config->get('default_driver');

		try
		{
			$config->set('default_driver', 'GD');

			$this->assertInstanceOf('Image_GD', Image::factory(self::JPEG));

			$config->set('default_driver', null);

			$this->expectException(Image_Exception::class);

			Image::factory(self::JPEG);
		}
		finally
		{
			$config->set('default_driver', $before);
		}
	}

	public function test_avif_is_written_and_read(): void
	{
		if (empty(gd_info()['AVIF Support']))
		{
			$this->markTestSkipped('This GD was built without AVIF support.');
		}

		$file = $this->temp('avif');

		$this->assertTrue(Image::factory(self::JPEG, 'GD')->resize(80, 80)->save($file));

		$image = Image::factory($file, 'GD');

		$this->assertSame([80, 80], [$image->width, $image->height]);
		$this->assertSame(IMAGETYPE_AVIF, $image->type);
		$this->assertSame('image/avif', $image->mime);

		// And on to another format
		$png = imagecreatefromstring($image->resize(40, 40)->render('png'));

		$this->assertSame([40, 40], [imagesx($png), imagesy($png)]);
	}

	public function test_images_over_the_pixel_limit_are_refused(): void
	{
		$limit = Image::$max_pixels;

		try
		{
			// The test image has 150 x 150 = 22500 pixels
			Image::$max_pixels = 22500;
			$this->assertSame(150, Image::factory(self::JPEG, 'GD')->width);

			// No limit at all
			Image::$max_pixels = 0;
			$this->assertSame(150, Image::factory(self::JPEG, 'GD')->width);

			Image::$max_pixels = 22499;

			$this->expectException(Image_Exception::class);
			$this->expectExceptionMessage('too large');

			Image::factory(self::JPEG, 'GD');
		}
		finally
		{
			Image::$max_pixels = $limit;
		}
	}

	public function test_from_string(): void
	{
		$before = $this->leftovers();

		$image = Image::from_string(file_get_contents(self::JPEG), 'GD');

		$this->assertSame([150, 150], [$image->width, $image->height]);
		$this->assertSame('image/jpeg', $image->mime);

		$rendered = imagecreatefromstring($image->resize(30, 30)->render('png'));
		$this->assertSame([30, 30], [imagesx($rendered), imagesy($rendered)]);

		// It can be saved under a name
		$file = $this->temp('png');
		$this->assertTrue($image->save($file));
		$this->assertSame(30, getimagesize($file)[0]);

		// Without a name there is nowhere to save it
		try
		{
			$image->save();

			$this->fail('No exception was thrown');
		}
		catch (Image_Exception $e)
		{
			$this->assertStringContainsString('give save() a file name', $e->getMessage());
		}

		// The temporary file goes away with the image
		unset($image);
		$this->assertSame($before, $this->leftovers());
	}

	public function test_from_string_refuses_what_is_not_an_image(): void
	{
		$before = $this->leftovers();

		try
		{
			Image::from_string('<?php echo "not an image";', 'GD');

			$this->fail('No exception was thrown');
		}
		catch (Image_Exception $e)
		{
			$this->assertStringContainsString('not an image', $e->getMessage());
		}

		// Nothing is left behind
		$this->assertSame($before, $this->leftovers());
	}

	public function test_thumbnail_has_exactly_the_given_size(): void
	{
		foreach ([[60, 30], [30, 60], [50, 50], [150, 150]] as [$width, $height])
		{
			$image = Image::factory(self::JPEG, 'GD')->thumbnail($width, $height);

			$this->assertSame([$width, $height], [$image->width, $image->height]);

			$rendered = imagecreatefromstring($image->render('png'));
			$this->assertSame([$width, $height], [imagesx($rendered), imagesy($rendered)]);
		}
	}

	public function test_default_quality_gives_smaller_files(): void
	{
		$default = $this->temp('jpg');
		$best    = $this->temp('jpg');

		Image::factory(self::JPEG, 'GD')->save($default);
		Image::factory(self::JPEG, 'GD')->save($best, 100);

		$this->assertLessThan(filesize($best), filesize($default));
		$this->assertLessThan(strlen(Image::factory(self::JPEG, 'GD')->render('jpg', 100)), strlen(Image::factory(self::JPEG, 'GD')->render('jpg')));
	}

	public function test_heic_files_are_recognised(): void
	{
		// The first bytes of a photo taken with an iPhone
		$heic = $this->temp('heic');
		file_put_contents($heic, "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic".str_repeat("\x00", 64));

		$this->assertTrue(Image::is_heif($heic));
		$this->assertFalse(Image::is_heif(self::JPEG));
		$this->assertFalse(Image::is_heif(__FILE__));
		$this->assertFalse(Image::is_heif('/no/such/file'));
	}

	public function test_gd_says_that_heic_needs_imagick(): void
	{
		$heic = $this->temp('heic');
		file_put_contents($heic, "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic".str_repeat("\x00", 64));

		$this->expectException(Image_Exception::class);
		$this->expectExceptionMessage('need the Imagick driver');

		Image::factory($heic, 'GD');
	}

	/**
	 * Skips the test when the Imagick driver cannot be used here.
	 */
	protected function need_imagick(string $format): void
	{
		if ( ! extension_loaded('imagick') OR empty(Imagick::queryFormats($format)))
		{
			$this->markTestSkipped('The imagick extension with '.$format.' support is needed.');
		}
	}

	/**
	 * A real HEIC photo (80 x 40) is opened by Imagick and saved in
	 * formats that browsers show.
	 */
	public function test_imagick_reads_heic(): void
	{
		$this->need_imagick('HEIC');

		$heic = __DIR__.'/../test_data/test_image.heic';

		$this->assertTrue(Image::is_heif($heic));

		$image = Image::factory($heic, 'Imagick');

		$this->assertSame([80, 40], [$image->width, $image->height]);
		$this->assertSame('image/heic', $image->mime);

		// Rendered as PNG: a real picture of the right size
		$png = imagecreatefromstring($image->render('png'));
		$this->assertSame([80, 40], [imagesx($png), imagesy($png)]);

		// Resized and saved as JPEG
		$file = $this->temp('jpg');
		$this->assertTrue(Image::factory($heic, 'Imagick')->resize(40, 20)->save($file));
		$this->assertSame([40, 20, IMAGETYPE_JPEG], array_slice(getimagesize($file), 0, 3));
	}

	/**
	 * A HEIC image has no format of its own to be saved in.
	 */
	public function test_heic_must_be_saved_in_another_format(): void
	{
		$this->need_imagick('HEIC');

		$image = Image::factory(__DIR__.'/../test_data/test_image.heic', 'Imagick');

		try
		{
			$image->render();

			$this->fail('No exception was thrown');
		}
		catch (Image_Exception $e)
		{
			$this->assertStringContainsString('can only be saved in another format', $e->getMessage());
		}

		$this->expectException(Image_Exception::class);

		$image->save($this->temp('heic'));
	}

	/**
	 * The pixel limit also counts for HEIC, which PHP cannot measure itself.
	 */
	public function test_pixel_limit_counts_for_heic(): void
	{
		$this->need_imagick('HEIC');

		$limit = Image::$max_pixels;

		try
		{
			// The photo has 80 x 40 = 3200 pixels
			Image::$max_pixels = 3199;

			$this->expectException(Image_Exception::class);
			$this->expectExceptionMessage('too large');

			Image::factory(__DIR__.'/../test_data/test_image.heic', 'Imagick');
		}
		finally
		{
			Image::$max_pixels = $limit;
		}
	}

	/**
	 * Imagick writes the format that was asked for, or says that it cannot:
	 * it never writes another format under the name.
	 */
	public function test_imagick_writes_avif_or_says_it_cannot(): void
	{
		$this->need_imagick('AVIF');

		$file = $this->temp('avif');

		try
		{
			Image::factory(self::JPEG, 'Imagick')->resize(80, 80)->save($file);
		}
		catch (Image_Exception $e)
		{
			// An ImageMagick that reads AVIF but has no encoder for it
			$this->assertStringContainsString('cannot write "avif"', $e->getMessage());

			return;
		}

		$this->assertSame('image/avif', (new finfo(FILEINFO_MIME_TYPE))->file($file));

		$image = Image::factory($file, 'Imagick');

		$this->assertSame([80, 80], [$image->width, $image->height]);
		$this->assertSame('image/avif', $image->mime);
		$this->assertSame('image/avif', (new finfo(FILEINFO_MIME_TYPE))->buffer($image->render()));
	}

	public function test_imagick_from_string_and_thumbnail(): void
	{
		$this->need_imagick('JPEG');

		$image = Image::from_string(file_get_contents(self::JPEG), 'Imagick')->thumbnail(60, 30);

		$this->assertSame([60, 30], [$image->width, $image->height]);

		$png = imagecreatefromstring($image->render('png'));
		$this->assertSame([60, 30], [imagesx($png), imagesy($png)]);
	}
}
