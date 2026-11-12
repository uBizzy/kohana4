<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests URL
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
#[Group('kohana.core.url')]
class Kohana_URLTest extends Unittest_TestCase
{

	/**
	 * Sets up the environment
	 */
	// @codingStandardsIgnoreStart
	public function setUp(): void
	// @codingStandardsIgnoreEnd
	{
		parent::setUp();
		Kohana::$config->load('url')->set(
			'trusted_hosts',
			['www\.example\.com', 'sub\.example\.com', 'example\.com', 'example\.org']
		);
	}

	/**
	 * Default values for the environment, see setEnvironment
	 * @var array
	 */
	// @codingStandardsIgnoreStart
	protected array $environmentDefault =	[
		'Kohana::$base_url'	=> '/kohana/',
		'Kohana::$index_file'=> 'index.php',
		'HTTP_HOST' => 'example.com',
		'_GET'		=> [],
	];
	// @codingStandardsIgnoreEnd

	/**
	 * Provides test data for test_base()
	 *
	 * @return array
	 */
	public static function provider_base()
	{
		return [
			// $protocol, $index, $subdomain, $expected, $enviroment

			// Test with different combinations of parameters for max code coverage
			// An empty base URL gives an empty result
			[null,    false, null,  '', ['Kohana::$base_url' => '']],
			[null,    false, null,  '/', ['Kohana::$base_url' => '/']],
			[null,    false, null,  '/kohana/'],
			['http',  false, null,  'http://example.com/kohana/'],
			['http',  false, 'sub', 'http://sub.example.com/kohana/'],
			['http',  false, 'sub', 'http://sub.example.com/kohana/', ['HTTP_HOST' => 'sub.example.com']],
			['http',  false, 'sub', 'http://sub.example.com/kohana/', ['HTTP_HOST' => 'invalid.example.com']],
			[null,    true,  null,  '/kohana/index.php/'],
			['http',  true,  null,  'http://example.com/kohana/index.php/'],
			['http',  true,  'sub', 'http://sub.example.com/kohana/index.php/'],
			['https', true,  null,  'https://example.com/kohana/index.php/'],
			['https', true,  'sub', 'https://sub.example.com/kohana/index.php/'],
			['ftp',   true,  null,  'ftp://example.com/kohana/index.php/'],
			['ftp',   true,  'sub', 'ftp://sub.example.com/kohana/index.php/'],

			// Test for automatic protocol detection, protocol = true
			[true, true, null, 'cli://example.com/kohana/index.php/', ['HTTPS' => false, 'Request::$initial' => Request::factory('/')->protocol('cli')]],

			// Change base url'
			['https', false, null, 'https://example.com/kohana/', ['Kohana::$base_url' => 'omglol://example.com/kohana/']],

			// Use port in base url, issue #3307
			['http', false, null, 'http://example.com:8080/', ['Kohana::$base_url' => 'example.com:8080/']],

			// Use protocol from base url if none specified
			[null, false, null, 'http://www.example.com/', ['Kohana::$base_url' => 'http://www.example.com/']],

			[null, false, 'sub', 'http://sub.example.com/', ['Kohana::$base_url' => 'http://www.example.com/']],

			// Use HTTP_HOST before SERVER_NAME
			['http', false, null, 'http://example.com/kohana/', ['HTTP_HOST' => 'example.com', 'SERVER_NAME' => 'example.org']],

			// Use SERVER_NAME if HTTP_HOST DNX
			['http', false, null, 'http://example.org/kohana/', ['HTTP_HOST' => null, 'SERVER_NAME' => 'example.org']],
		];
	}

	/**
	 * Tests URL::base()
	 *
	 * @param mixed   $protocol    Parameter for Url::base()
	 * @param boolean $index       Parameter for Url::base()
	 * @param string  $subdomain   Parameter for Url::base()
	 * @param string  $expected    Expected url
	 * @param array   $enviroment  Array of enviroment vars to change @see Kohana_URLTest::setEnvironment()
	 */
	#[DataProvider('provider_base')]
	public function test_base($protocol, $index, $subdomain, $expected, array $enviroment = [])
	{
		$this->setEnvironment($enviroment);

		$this->assertSame(
			$expected,
			URL::base($protocol, $index, $subdomain)
		);
	}

	/**
	 * Provides test data for test_site()
	 *
	 * @return array
	 */
	public static function provider_site()
	{
		return [
			['', null,		'/kohana/index.php/'],
			['', 'http',			'http://example.com/kohana/index.php/'],

			['my/site', null, '/kohana/index.php/my/site'],
			['my/site', 'http',  'http://example.com/kohana/index.php/my/site'],

			// @ticket #3110
			['my/site/page:5', null, '/kohana/index.php/my/site/page:5'],
			['my/site/page:5', 'http', 'http://example.com/kohana/index.php/my/site/page:5'],

			['my/site?var=asd&kohana=awesome', null,  '/kohana/index.php/my/site?var=asd&kohana=awesome'],
			['my/site?var=asd&kohana=awesome', 'http',  'http://example.com/kohana/index.php/my/site?var=asd&kohana=awesome'],

			['?kohana=awesome&life=good', null, '/kohana/index.php/?kohana=awesome&life=good'],
			['?kohana=awesome&life=good', 'http', 'http://example.com/kohana/index.php/?kohana=awesome&life=good'],

			['?kohana=awesome&life=good#fact', null, '/kohana/index.php/?kohana=awesome&life=good#fact'],
			['?kohana=awesome&life=good#fact', 'http', 'http://example.com/kohana/index.php/?kohana=awesome&life=good#fact'],

			['some/long/route/goes/here?kohana=awesome&life=good#fact', null, '/kohana/index.php/some/long/route/goes/here?kohana=awesome&life=good#fact'],
			['some/long/route/goes/here?kohana=awesome&life=good#fact', 'http', 'http://example.com/kohana/index.php/some/long/route/goes/here?kohana=awesome&life=good#fact'],

			['/route/goes/here?kohana=awesome&life=good#fact', 'https', 'https://example.com/kohana/index.php/route/goes/here?kohana=awesome&life=good#fact'],
			['/route/goes/here?kohana=awesome&life=good#fact', 'ftp', 'ftp://example.com/kohana/index.php/route/goes/here?kohana=awesome&life=good#fact'],
		];
	}

	/**
	 * Tests URL::site()
	 *
	 * @param string          $uri         URI to use
	 * @param boolean|string  $protocol    Protocol to use
	 * @param string          $expected    Expected result
	 * @param array           $enviroment  Array of enviroment vars to set
	 */
	#[DataProvider('provider_site')]
	public function test_site($uri, $protocol, $expected, array $enviroment = [])
	{
		$this->setEnvironment($enviroment);

		$this->assertSame(
			$expected,
			URL::site($uri, $protocol)
		);
	}

	/**
	 * Provides test data for test_site_url_encode_uri()
	 * See issue #2680
	 *
	 * @return array
	 */
	public static function provider_site_url_encode_uri()
	{
		$provider = [
			['test', 'encode'],
			['test', 'éñçø∂ë∂'],
			['†éß†', 'encode'],
			['†éß†', 'éñçø∂ë∂', 'µåñ¥'],
		];

		foreach ($provider as $i => $params)
		{
			// Every non-ASCII character except for forward slash should be encoded...
			$expected = implode('/', array_map('rawurlencode', $params));

			// ... from a URI that is not encoded
			$uri = implode('/', $params);

			$provider[$i] = ["/kohana/index.php/{$expected}", $uri];
		}

		return $provider;
	}

	/**
	 * Tests URL::site for proper URL encoding when working with non-ASCII characters.
	 */
	#[DataProvider('provider_site_url_encode_uri')]
	public function test_site_url_encode_uri($expected, $uri)
	{
		$this->assertSame($expected, URL::site($uri, false));
	}

	/**
	 * Provides test data for test_title()
	 * @return array
	 */
	public static function provider_title()
	{
		return [
			// Tests that..
			// Title is converted to lowercase
			['we-shall-not-be-moved', 'WE SHALL NOT BE MOVED', '-'],
			// Excessive white space is removed and replaced with 1 char
			['thissssss-is-it', 'THISSSSSS         IS       IT  ', '-'],
			// separator is either - (dash) or _ (underscore) & others are converted to underscores
			['some-title', 'some title', '-'],
			['some_title', 'some title', '_'],
			['some!title', 'some title', '!'],
			['some:title', 'some title', ':'],
			// Numbers are preserved
			['99-ways-to-beat-apple', '99 Ways to beat apple', '-'],
			// ... with lots of spaces & caps
			['99_ways_to_beat_apple', '99    ways   TO beat      APPLE', '_'],
			['99-ways-to-beat-apple', '99    ways   TO beat      APPLE', '-'],
			// Invalid characters are removed
			['each-gbp-is-now-worth-32-usd', 'Each GBP(£) is now worth 32 USD($)', '-'],
			// ... inc. separator
			['is-it-reusable-or-re-usable', 'Is it reusable or re-usable?', '-'],
			// Doing some crazy UTF8 tests
			['espana-wins', 'España-wins', '-', true],
		];
	}

	/**
	 * Tests URL::title()
	 *
	 * @param string $title        Input to convert
	 * @param string $separator    Seperate to replace invalid characters with
	 * @param string $expected     Expected result
	 */
	#[DataProvider('provider_title')]
	public function test_title($expected, $title, $separator, $ascii_only = false)
	{
		$this->assertSame(
			$expected,
			URL::title($title, $separator, $ascii_only)
		);
	}

	/**
	 * Provides test data for URL::query()
	 * @return array
	 */
	public static function provider_query()
	{
		return [
			[[], '', null],
			[['_GET' => ['test' => 'data']], '?test=data', null],
			[[], '?test=data', ['test' => 'data']],
			[['_GET' => ['more' => 'data']], '?more=data&test=data', ['test' => 'data']],
			[['_GET' => ['sort' => 'down']], '?test=data', ['test' => 'data'], false],

			// http://koseven.dev/issues/3362
			[[], '', ['key' => null]],
			[[], '?key=0', ['key' => false]],
			[[], '?key=1', ['key' => true]],
			[['_GET' => ['sort' => 'down']], '?sort=down&key=1', ['key' => true]],
			[['_GET' => ['sort' => 'down']], '?sort=down&key=0', ['key' => false]],

			// @issue 4240
			[['_GET' => ['foo' => ['a' => 100]]], '?foo%5Ba%5D=100&foo%5Bb%5D=bar', ['foo' => ['b' => 'bar']]],
			[['_GET' => ['a' => 'a']], '?a=b', ['a' => 'b']],
		];
	}

	/**
	 * Tests URL::query()
	 *
	 * @param array $enviroment Set environment
	 * @param string $expected Expected result
	 * @param array $params Query string
	 * @param boolean $use_get Combine with GET parameters
	 */
	#[DataProvider('provider_query')]
	public function test_query($enviroment, $expected, $params, $use_get = true)
	{
		$this->setEnvironment($enviroment);

		$this->assertSame(
			$expected,
			URL::query($params, $use_get)
		);
	}

	/**
	 * Provides test data for URL::is_trusted_host()
	 * @return array
	 */
	public static function provider_is_trusted_host()
	{
		return [
			// data set #0
			[
				'givenhost',
				[
					'list-of-trusted-hosts',
				],
				false
			],
			// data set #1
			[
				'givenhost',
				[
					'givenhost',
					'example\.com',
				],
				true
			],
			// data set #2
			[
				'www.koseven.dev',
				[
					'.*\.koseven\.dev',
				],
				true
			],
			// data set #3
			[
				'koseven.dev',
				[
					'.*\.koseven\.dev',
				],
				false // because we are requesting a subdomain
			],
			// The port is not part of the name
			['localhost:8000', ['localhost'], true],
			['www.example.com:8443', ['.*\.example\.com'], true],
			['[::1]:8000', ['\[::1\]'], true],
			// An entry with a port trusts that port only
			['localhost:8000', ['localhost:8000'], true],
			['localhost:9000', ['localhost:8000'], false],
			['localhost', ['localhost:8000'], false],
			// What follows the name must be a port, and the name must still match
			['evil.com:8000', ['localhost'], false],
			['localhost:8000.evil.com', ['localhost'], false],
			['localhost:evil', ['localhost'], false],
			['localhost:80:90', ['localhost'], false],
		];
	}

	/**
	 * Tests URL::is_trusted_hosts()
	 *
	 * @param string $host the given host
	 * @param array $trusted_hosts list of trusted hosts
	 * @param boolean $expected true if host is trusted, false otherwise
	 */
	#[DataProvider('provider_is_trusted_host')]
	public function test_is_trusted_host($host, $trusted_hosts, $expected)
	{
		$this->assertSame(
			$expected,
			URL::is_trusted_host($host, $trusted_hosts)
		);
	}

	/**
	 * Tests if invalid host throws "Invalid host" exception
	 */
	public function test_if_invalid_host_throws_exception()
	{
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('Invalid host <invalid>');

		// set the global HTTP_HOST to <invalid>
		$_SERVER['HTTP_HOST'] = '<invalid>';
		// trigger exception
		URL::base('https');
	}

	/**
	 * Tests if untrusted host throws "Untrusted host" exception
	 */
	public function test_if_untrusted_host_throws_exception()
	{
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('Untrusted host untrusted.com');

		// set the global HTTP_HOST to a valid but untrusted host
		$_SERVER['HTTP_HOST'] = 'untrusted.com';
		// trigger exception
		URL::base('https');
	}

}
