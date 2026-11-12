<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests HTML
 *
 * @package    Kohana
 * @category   Tests
 *
 * @author     BRMatt <matthew@sigswitch.com>
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.html')]
class Kohana_HTMLTest extends Unittest_TestCase {

	/**
	 * Sets up the environment
	 */
	// @codingStandardsIgnoreStart
	public function setUp(): void
	// @codingStandardsIgnoreEnd
	{
		parent::setUp();
		Kohana::$config->load('url')->set('trusted_hosts', ['www\.koseven\.dev']);
	}

	/**
	 * Defaults for this test
	 * @var array
	 */
	// @codingStandardsIgnoreStart
	protected array $environmentDefault = [
		'Kohana::$base_url'    => '/kohana/',
		'Kohana::$index_file'  => 'index.php',
		'HTML::$strict' => true,
		'HTTP_HOST'	=> 'www.koseven.dev',
	];
	// @codingStandardsIgnoreStart

	/**
	 * Provides test data for test_attributes()
	 *
	 * @return array
	 */
	public static function provider_attributes()
	{
		return [
			[
				['name' => 'field', 'random' => 'not_quite', 'id' => 'unique_field'],
				[],
				' id="unique_field" name="field" random="not_quite"'
			],
			[
				['invalid' => null],
				[],
				''
			],
			[
				[],
				[],
				''
			],
			[
				['name' => 'field', 'checked'],
				[],
				' name="field" checked="checked"',
			],
			[
				['id' => 'disabled_field', 'disabled'],
				['HTML::$strict' => false],
				' id="disabled_field" disabled',
			],
		];
	}

	/**
	 * Tests HTML::attributes()
	 *
	 * @param array  $attributes  Attributes to use
	 * @param array  $options     Environment options to use
	 * @param string $expected    Expected output
	 */
	#[DataProvider('provider_attributes')]
	public function test_attributes(array $attributes, array $options, $expected)
	{
		$this->setEnvironment($options);

		$this->assertSame(
			$expected,
			HTML::attributes($attributes)
		);
	}

	/**
	 * Provides test data for test_script
	 *
	 * @return array Array of test data
	 */
	public static function provider_script()
	{
		return [
			[
				'<script type="text/javascript" src="http://google.com/script.js"></script>',
				'http://google.com/script.js',
			],
			[
				'<script type="text/javascript" src="http://www.koseven.dev/kohana/index.php/my/script.js"></script>',
				'my/script.js',
				null,
				'http',
				true
			],
			[
				'<script type="text/javascript" src="https://www.koseven.dev/kohana/my/script.js"></script>',
				'my/script.js',
				null,
				'https',
				false
			],
			[
				'<script type="text/javascript" src="https://www.koseven.dev/kohana/my/script.js"></script>',
				'/my/script.js', // Test absolute paths
				null,
				'https',
				false
			],
			[
				'<script type="text/javascript" src="//google.com/script.js"></script>',
				'//google.com/script.js',
			],

		];
	}

	/**
	 * Tests HTML::script()
	 *
	 * @param string  $expected       Expected output
	 * @param string  $file           URL to script
	 * @param array   $attributes     HTML attributes for the anchor
	 * @param string  $protocol       Protocol to use
	 * @param bool    $index          Should the index file be included in url?
	 */
	#[DataProvider('provider_script')]
	public function test_script($expected, $file, ?array $attributes = null, $protocol = null, $index = false)
	{
		$this->assertSame(
			$expected,
			HTML::script($file, $attributes, $protocol, $index)
		);
	}

	/**
	 * Data provider for the style test
	 *
	 * @return array Array of test data
	 */
	public static function provider_style()
	{
		return [
			[
				'<link type="text/css" href="http://google.com/style.css" rel="stylesheet" />',
				'http://google.com/style.css',
				[],
				null,
				false
			],
			[
				'<link type="text/css" href="/kohana/my/style.css" rel="stylesheet" />',
				'my/style.css',
				[],
				null,
				false
			],
			[
				'<link type="text/css" href="https://www.koseven.dev/kohana/my/style.css" rel="stylesheet" />',
				'my/style.css',
				[],
				'https',
				false
			],
			[
				'<link type="text/css" href="https://www.koseven.dev/kohana/index.php/my/style.css" rel="stylesheet" />',
				'my/style.css',
				[],
				'https',
				true
			],
			[
				'<link type="text/css" href="https://www.koseven.dev/kohana/index.php/my/style.css" rel="stylesheet" />',
				'/my/style.css',
				[],
				'https',
				true
			],
			[
				// #4283: http://koseven.dev/issues/4283
				'<link type="text/css" href="https://www.koseven.dev/kohana/index.php/my/style.css" rel="stylesheet/less" />',
				'my/style.css',
				[
					'rel' => 'stylesheet/less'
				],
				'https',
				true
			],
			[
				'<link type="text/css" href="//google.com/style.css" rel="stylesheet" />',
				'//google.com/style.css',
				[],
				null,
				false
			],
		];
	}

	/**
	 * Tests HTML::style()
	 *
	 * @param string  $expected     The expected output
	 * @param string  $file         The file to link to
	 * @param array   $attributes   Any extra attributes for the link
	 * @param string  $protocol     Protocol to use
	 * @param bool    $index        Whether the index file should be added to the link
	 */
	#[DataProvider('provider_style')]
	public function test_style($expected, $file, ?array $attributes = null, $protocol = null, $index = false)
	{
		$this->assertSame(
			$expected,
			HTML::style($file, $attributes, $protocol, $index)
		);
	}

	/**
	 * Provides test data for test_anchor
	 *
	 * @return array Test data
	 */
	public static function provider_anchor()
	{
		return [
			// a fragment-only anchor
			[
				'<a href="#go-to-section-kohana">Kohana</a>',
				[],
				'#go-to-section-kohana',
				'Kohana',
			],
			// a query-only anchor
			[
				'<a href="?cat=a">Category A</a>',
				[],
				'?cat=a',
				'Category A',
			],
			[
				'<a href="http://koseven.dev/">Kohana</a>',
				[],
				'http://koseven.dev/',
				'Kohana',
			],
			[
				'<a href="http://google.com" target="_blank">GOOGLE</a>',
				[],
				'http://google.com',
				'GOOGLE',
				['target' => '_blank'],
				'http',
			],
			[
				'<a href="//google.com/">GOOGLE</a>',
				[],
				'//google.com/',
				'GOOGLE',
			],
			[
				'<a href="https://www.koseven.dev/kohana/users/example">Kohana</a>',
				[],
				'users/example',
				'Kohana',
				null,
				'https',
				false,
			],
			[
				'<a href="https://www.koseven.dev/kohana/index.php/users/example">Kohana</a>',
				[],
				'users/example',
				'Kohana',
				null,
				'https',
				true,
			],
			[
				'<a href="https://www.koseven.dev/kohana/index.php/users/example">Kohana</a>',
				[],
				'users/example',
				'Kohana',
				null,
				'https',
			],
			[
				'<a href="https://www.koseven.dev/kohana/index.php/users/example">Kohana</a>',
				[],
				'users/example',
				'Kohana',
				null,
				'https',
				true,
			],
			[
				'<a href="https://www.koseven.dev/kohana/users/example">Kohana</a>',
				[],
				'users/example',
				'Kohana',
				null,
				'https',
				false,
			],
			[
				'<a href="https://www.koseven.dev/kohana/users/example">Kohana</a>',
				[],
				'/users/example',
				'Kohana',
				null,
				'https',
				false,
			],
		];
	}

	/**
	 * Tests HTML::anchor
	 */
	#[DataProvider('provider_anchor')]
	public function test_anchor($expected, array $options, $uri, $title = null, ?array $attributes = null, $protocol = null, $index = true)
	{
		// $this->setEnvironment($options);

		$this->assertSame(
			$expected,
			HTML::anchor($uri, $title, $attributes, $protocol, $index)
		);
	}

	/**
	 * Data provider for test_file_anchor
	 *
	 * @return array
	 */
	public static function provider_file_anchor()
	{
		return [
			[
				'<a href="/kohana/mypic.png">My picture file</a>',
				[],
				'mypic.png',
				'My picture file',
			],
			[
				'<a href="https://www.koseven.dev/kohana/index.php/mypic.png" attr="value">My picture file</a>',
				['attr' => 'value'],
				'mypic.png',
				'My picture file',
				'https',
				true
			],
			[
				'<a href="ftp://www.koseven.dev/kohana/mypic.png">My picture file</a>',
				[],
				'mypic.png',
				'My picture file',
				'ftp',
				false
			],
			[
				'<a href="ftp://www.koseven.dev/kohana/mypic.png">My picture file</a>',
				[],
				'/mypic.png',
				'My picture file',
				'ftp',
				false
			],
		];
	}

	/**
	 * Test for HTML::file_anchor()
	 *
	 * @covers HTML::file_anchor
	 */
	#[DataProvider('provider_file_anchor')]
	public function test_file_anchor($expected, array $attributes, $file, $title = null, $protocol = null, $index = false)
	{
		$this->assertSame(
			$expected,
			HTML::file_anchor($file, $title, $attributes, $protocol, $index)
		);
	}


	/**
	 * Provides test data for test_image
	 *
	 * @return array Array of test data
	 */
	public static function provider_image()
	{
		return [
			[
				'<img src="http://google.com/image.png" />',
				'http://google.com/image.png',
			],
			[
				'<img src="//google.com/image.png" />',
				'//google.com/image.png',
			],
			[
				'<img src="/kohana/img/image.png" />',
				'img/image.png',
			],
			[
				'<img src="https://www.koseven.dev/kohana/index.php/img/image.png" alt="..." />',
				'img/image.png',
				['alt' => '...',],
				'https',
				true
			],
		];
	}

	/**
	 * Tests HTML::image()
	 *
	 * @param string  $expected       Expected output
	 * @param string  $file           file name
	 * @param array   $attributes     HTML attributes for the image
	 * @param string  $protocol       Protocol to use
	 * @param bool    $index          Should the index file be included in url?
	 */
	#[DataProvider('provider_image')]
	public function test_image($expected, $file, ?array $attributes = null, $protocol = null, $index = false)
	{
		$this->assertSame(
			$expected,
			HTML::image($file, $attributes, $protocol, $index)
		);
	}

}
