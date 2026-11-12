<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for internal methods of userguide controller
 *
 * @package    Kohana/Userguide
 * @category   Tests
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.userguide')]
#[Group('kohana.userguide.controller')]
class Userguide_ControllerTest extends Unittest_TestCase
{

	public static function provider_file_finds_markdown_files()
	{
		return [
			['userguide'.DIRECTORY_SEPARATOR.'writing', 'guide'.DIRECTORY_SEPARATOR.'userguide'.DIRECTORY_SEPARATOR.'writing.md'],
			['userguide'.DIRECTORY_SEPARATOR.'writing.md', 'guide'.DIRECTORY_SEPARATOR.'userguide'.DIRECTORY_SEPARATOR.'writing.md'],
			['userguide'.DIRECTORY_SEPARATOR.'writing.markdown', 'guide'.DIRECTORY_SEPARATOR.'userguide'.DIRECTORY_SEPARATOR.'writing.md'],
			['userguide'.DIRECTORY_SEPARATOR.'does_not_exist.md', false]
		];
	}

	/**
	 * @param  string  $page           Page name passed in the URL
	 * @param  string  $expected_file  Expected result from Controller_Userguide::file
	 */
	#[DataProvider('provider_file_finds_markdown_files')]
	public function test_file_finds_markdown_files($page, $expected_file)
	{
		$controller = $this->createStub('Controller_Userguide');

		$cache_reflection = new ReflectionClass('Controller_Userguide');
		$file_method = $cache_reflection->getMethod('file');

		$path = $file_method->invoke($controller, $page);

		// Only verify trailing segments to avoid problems if file overwritten in CFS
		$expected_len = strlen($expected_file);
		$file = substr($path, -$expected_len, $expected_len);

		$this->assertEquals($expected_file, $file);
	}

	/**
	 * Runs a request of the guide. The guide routes are given to the
	 * request, because the test bootstrap sets the default route first.
	 */
	protected function _get(string $uri): Response
	{
		$routes = array_filter(Route::all(), fn ($name) => str_starts_with($name, 'docs/'), ARRAY_FILTER_USE_KEY);

		return Request::factory($uri, [], false, $routes)->execute();
	}

	/**
	 * The pages of the guide.
	 */
	public static function provider_pages(): array
	{
		return [
			['guide', 200, 'kodoc-guides'],
			['guide/kohana', 200, 'id="kodoc-topics"'],
			['guide/userguide/markdown', 200, '<h1'],
			['guide/kohana/index', 404, 'Not found'],
			['guide/kohana/no-such-page', 404, 'Not found'],
			['guide/no-such-module', 404, 'Not found'],
			['guide-api', 200, 'class-list'],
			['guide-api/Arr', 200, 'id="get"'],
			['guide-api/No_Such_Class', 404, 'Not found'],
		];
	}

	#[DataProvider('provider_pages')]
	public function test_pages(string $uri, int $status, string $contains): void
	{
		$response = $this->_get($uri);

		$this->assertSame($status, $response->status());
		$this->assertStringContainsString($contains, $response->body());

		// Nothing from other sites, no inline scripts or styles
		$this->assertStringNotContainsString('disqus', $response->body());
		$this->assertStringNotContainsString(' style="', $response->body());
		$this->assertStringNotContainsString(' onclick', $response->body());
	}

	/**
	 * The API browser shows the types declared in the code, and hides
	 * the values of properties named like a secret.
	 */
	public function test_api_shows_real_types_and_hides_secrets(): void
	{
		$body = $this->_get('guide-api/Cookie')->body();

		$this->assertStringContainsString('<code>?string</code> $salt', preg_replace('/\s+/', ' ', $body));
		$this->assertStringContainsString('<pre>***</pre>', $body);
	}

	/**
	 * The search list holds the pages of the guides and the classes.
	 */
	public function test_search_list(): void
	{
		$response = $this->_get('guide-search');

		$this->assertStringContainsString('application/json', (string) $response->headers('content-type'));

		$entries = json_decode($response->body(), true);
		$titles = array_column($entries, 'section', 'title');

		$this->assertSame('API', $titles['Arr']);
		$this->assertContains('Kohana', $titles);

		foreach ($entries as $entry)
		{
			$this->assertSame(['url', 'title', 'section', 'headings', 'text'], array_keys($entry));
		}
	}

	/**
	 * Media: only known kinds of file, only from the media/guide directories.
	 */
	public function test_media(): void
	{
		$css = $this->_get('guide-media/guide.css');

		$this->assertSame(200, $css->status());
		$this->assertSame('text/css', (string) $css->headers('content-type'));

		$this->assertSame(404, $this->_get('guide-media/guide.php')->status());
		$this->assertSame(404, $this->_get('guide-media/missing.css')->status());
	}

}
