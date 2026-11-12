<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Image::auto_orient() with photos made on the spot: one for each of
 * the eight EXIF orientations. Also checks that the steps of a chain build
 * on each other.
 *
 * @package    Kohana/Image
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.image')]
class Kohana_ImageOrientTest extends Unittest_TestCase
{
	// Size of the upright picture: wider than high, so a quarter turn shows
	protected const int WIDTH  = 80;
	protected const int HEIGHT = 40;

	// Colours of the four quarters of the upright picture: red, green, blue
	protected const array QUARTERS = [
		'top left'     => [255, 0, 0],
		'top right'    => [0, 255, 0],
		'bottom left'  => [0, 0, 255],
		'bottom right' => [255, 255, 0],
	];

	/**
	 * @var  array  Files to remove after the test
	 */
	protected array $files = [];

	public function setUp(): void
	{
		parent::setUp();

		if ( ! extension_loaded('gd') OR ! extension_loaded('exif'))
		{
			$this->markTestSkipped('The gd and exif extensions are needed.');
		}
	}

	public function tearDown(): void
	{
		array_map('unlink', array_filter($this->files, 'is_file'));

		parent::tearDown();
	}

	/**
	 * Every orientation, with every driver this machine has.
	 */
	public static function provider_orientations(): array
	{
		$drivers = ['GD'];

		if (extension_loaded('imagick'))
		{
			$drivers[] = 'Imagick';
		}

		$data = [];

		foreach ($drivers as $driver)
		{
			for ($orientation = 1; $orientation <= 8; $orientation++)
			{
				$data[$driver.', orientation '.$orientation] = [$driver, $orientation];
			}
		}

		return $data;
	}

	/**
	 * Returns the colour of the upright picture at a point.
	 */
	protected function upright_color(int $x, int $y): array
	{
		$name = ($y < self::HEIGHT / 2 ? 'top' : 'bottom').' '.($x < self::WIDTH / 2 ? 'left' : 'right');

		return self::QUARTERS[$name];
	}

	/**
	 * Writes a JPEG file that holds the picture the way a camera stores
	 * it for the given orientation, with the EXIF note that says so.
	 *
	 * The stored picture is built point by point from the definition of
	 * the orientations, not with the methods under test.
	 */
	protected function photo(int $orientation, bool $with_note = true): string
	{
		// From orientation 5 on, the stored picture lies on its side
		$turned = ($orientation >= 5);
		$width  = $turned ? self::HEIGHT : self::WIDTH;
		$height = $turned ? self::WIDTH : self::HEIGHT;

		$stored = imagecreatetruecolor($width, $height);

		$w = self::WIDTH - 1;
		$h = self::HEIGHT - 1;

		for ($x = 0; $x < $width; $x++)
		{
			for ($y = 0; $y < $height; $y++)
			{
				// Where this stored point is in the upright picture. By the EXIF
				// standard, for 6 the first row is the right side and the first
				// column the top; for 8 the first row is the left side and the
				// first column the bottom.
				[$ux, $uy] = match ($orientation) {
					1 => [$x, $y],
					2 => [$w - $x, $y],
					3 => [$w - $x, $h - $y],
					4 => [$x, $h - $y],
					5 => [$y, $x],
					6 => [$w - $y, $x],
					7 => [$w - $y, $h - $x],
					8 => [$y, $h - $x],
				};

				imagesetpixel($stored, $x, $y, imagecolorallocate($stored, ...$this->upright_color($ux, $uy)));
			}
		}

		ob_start();
		imagejpeg($stored, null, 100);
		$jpeg = ob_get_clean();

		if ($with_note)
		{
			// A tiny EXIF block with one entry: tag 0x0112 (orientation), a short number
			$tiff = "MM\x00\x2A\x00\x00\x00\x08"."\x00\x01"."\x01\x12\x00\x03\x00\x00\x00\x01".pack('n', $orientation)."\x00\x00"."\x00\x00\x00\x00";
			$exif = "Exif\x00\x00".$tiff;

			// It goes right after the two bytes that start every JPEG file
			$jpeg = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
		}

		$file = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kohana_orient_'.bin2hex(random_bytes(6)).'.jpg';

		file_put_contents($file, $jpeg);

		return $this->files[] = $file;
	}

	/**
	 * Checks that a rendered image is the upright picture: the middle of
	 * each quarter has the right colour.
	 */
	protected function assert_upright(string $rendered, string $message): void
	{
		$image = imagecreatefromstring($rendered);

		$this->assertSame([self::WIDTH, self::HEIGHT], [imagesx($image), imagesy($image)], $message.': size');

		foreach (self::QUARTERS as $name => $expected)
		{
			$x = (int) (self::WIDTH * (str_contains($name, 'left') ? 0.25 : 0.75));
			$y = (int) (self::HEIGHT * (str_contains($name, 'top') ? 0.25 : 0.75));

			$color = imagecolorsforindex($image, imagecolorat($image, $x, $y));

			// JPEG changes colours a little
			$this->assertEqualsWithDelta($expected, [$color['red'], $color['green'], $color['blue']], 40, $message.': '.$name);
		}
	}

	#[DataProvider('provider_orientations')]
	public function test_auto_orient_turns_the_photo_upright(string $driver, int $orientation): void
	{
		$file = $this->photo($orientation);

		// The test photo really carries the note
		$this->assertSame($orientation, exif_read_data($file)['Orientation']);

		$image = Image::factory($file, $driver)->auto_orient();

		$this->assertSame([self::WIDTH, self::HEIGHT], [$image->width, $image->height]);
		$this->assert_upright($image->render('png'), 'Orientation '.$orientation);

		// Asking twice does not turn it twice
		$this->assert_upright($image->auto_orient()->render('png'), 'Second call');
	}

	/**
	 * The saved file is upright and no longer says it needs turning.
	 */
	#[DataProvider('provider_orientations')]
	public function test_saved_photo_needs_no_more_turning(string $driver, int $orientation): void
	{
		$file   = $this->photo($orientation);
		$target = $this->files[] = $file.'.saved.jpg';

		Image::factory($file, $driver)->auto_orient()->save($target, 100);

		$this->assert_upright(file_get_contents($target), 'Saved file');
		$this->assertSame(1, (int) (@exif_read_data($target)['Orientation'] ?? 1));
	}

	public function test_photo_without_note_is_left_alone(): void
	{
		// Stored on its side, but nothing says so: it must stay as it is
		$image = Image::factory($this->photo(6, false), 'GD')->auto_orient();

		$this->assertSame([self::HEIGHT, self::WIDTH], [$image->width, $image->height]);
	}

	public function test_other_image_types_are_left_alone(): void
	{
		$image = Image::factory(__DIR__.'/../test_data/test_image.png', 'GD');
		$size  = [$image->width, $image->height];

		$this->assertSame($size, [$image->auto_orient()->width, $image->height]);
	}

	/**
	 * Every step of a chain works on the result of the step before, and
	 * what is rendered or saved is the result of all of them.
	 */
	public function test_steps_build_on_each_other(): void
	{
		// Turned upright (80 x 40), cut to the left half (40 x 40), halved (20 x 20)
		$image = Image::factory($this->photo(6), 'GD')
			->auto_orient()
			->crop(40, 40, 0, 0)
			->resize(20, 20);

		$this->assertSame([20, 20], [$image->width, $image->height]);

		$rendered = imagecreatefromstring($image->render('png'));

		$this->assertSame([20, 20], [imagesx($rendered), imagesy($rendered)]);

		// The left half is red above blue
		$top    = imagecolorsforindex($rendered, imagecolorat($rendered, 10, 4));
		$bottom = imagecolorsforindex($rendered, imagecolorat($rendered, 10, 15));

		$this->assertEqualsWithDelta([255, 0, 0], [$top['red'], $top['green'], $top['blue']], 40);
		$this->assertEqualsWithDelta([0, 0, 255], [$bottom['red'], $bottom['green'], $bottom['blue']], 40);
	}
}
