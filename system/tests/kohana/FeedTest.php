<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test for feed helper
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
#[Group('kohana.core.feed')]
class Kohana_FeedTest extends Unittest_TestCase
{

	/**
	 * Sets up the environment
	 */
	// @codingStandardsIgnoreStart
	public function setUp(): void
	// @codingStandardsIgnoreEnd
	{
		parent::setUp();
		Kohana::$config->load('url')->set('trusted_hosts', ['localhost']);
	}

	/**
	 * Provides test data for test_parse()
	 *
	 * @return array
	 */
	public static function provider_parse()
	{
		return [
			// $source, $expected
			[realpath(__DIR__.'/../test_data/feeds/activity.atom'), ['Proposals (Political/Workflow) #4839 (New)', 'Proposals (Political/Workflow) #4782']],
			[realpath(__DIR__.'/../test_data/feeds/example.rss20'), ['Example entry']],
		];
	}

	/**
	 * Tests that Feed::parse gets the correct number of elements
	 *
	 * @covers feed::parse
	 * @param string  $source   URL to test
	 * @param integer $expected Count of items
	 */
	#[DataProvider('provider_parse')]
	public function test_parse($source, $expected_titles)
	{
		$titles = [];
		foreach (Feed::parse($source) as $item)
		{
			$titles[] = $item['title'];
		}

		$this->assertSame($expected_titles, $titles);
	}

	/**
	 * Provides test data for test_create()
	 *
	 * @return array
	 */
	public static function provider_create()
	{
		$info = ['pubDate' => 123, 'image' => ['link' => 'http://koseven.dev/image.png', 'url' => 'http://koseven.dev/', 'title' => 'title']];

		return [
			// $source, $expected
			[$info, ['foo' => ['foo' => 'bar', 'pubDate' => 123, 'link' => 'foo']], ['_SERVER' => ['HTTP_HOST' => 'localhost']+$_SERVER],
				[
					'tag' => 'channel',
					'descendant' => [
						'tag' => 'item',
						'child' => [
							'tag' => 'foo',
							'content' => 'bar'
						]
					]
				],
				[
					self::matcher_composer($info, 'image', 'link'),
					self::matcher_composer($info, 'image', 'url'),
					self::matcher_composer($info, 'image', 'title')
				]
			],
		];
	}

	/**
	 * Helper for handy matcher composing
	 *
	 * @param array $data
	 * @param string $tag
	 * @param string $child
	 * @return array
	 */
	private static function matcher_composer($data, $tag, $child)
	{
		return [
			'tag' => 'channel',
			'descendant' => [
				'tag' => $tag,
				'child' => [
					'tag' => $child,
					'content' => $data[$tag][$child]
				]
			]
		];
	}

	/**
	 *
	 * @covers feed::create
	 *
	 * @param string  $info     info to pass
	 * @param integer $items    items to add
	 * @param integer $matcher  output
	 */
	#[DataProvider('provider_create')]
	public function test_create($info, $items, $enviroment, $matcher_item, $matchers_image)
	{
		$this->setEnvironment($enviroment);

		$this->assertTag($matcher_item, Feed::create($info, $items), '', false);

		foreach ($matchers_image as $matcher_image)
		{
			$this->assertTag($matcher_image, Feed::create($info, $items), '', false);
		}
	}
}
