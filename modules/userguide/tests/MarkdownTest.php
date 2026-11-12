<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Markdown parser of the user guide.
 *
 * @package    Kohana/Userguide
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.userguide')]
class Kohana_MarkdownTest extends Unittest_TestCase
{
	// phpcs:ignore
	public function setUp(): void
	{
		parent::setUp();

		Kodoc_Markdown::$base_url = '/guide/kohana/';
		Kodoc_Markdown::$image_url = '/guide-media/kohana/';
	}

	/**
	 * Markdown and the HTML it must give.
	 */
	public static function provider_markdown(): array
	{
		return [
			'paragraph' => ["One\ntwo\n\nThree", "<p>One\ntwo</p>\n<p>Three</p>"],
			'heading' => ['## Sub title', '<h2 id="sub-title">Sub title</h2>'],
			'heading with id' => ['# Title {#top}', '<h1 id="top">Title</h1>'],
			'underlined heading' => ["Title\n=====\n\nSub\n---", "<h1 id=\"title\">Title</h1>\n<h2 id=\"sub\">Sub</h2>"],
			'bold and emphasis' => ['**bold** *em* _em_ ~~old~~', '<p><strong>bold</strong> <em>em</em> <em>em</em> <del>old</del></p>'],
			'underscore in a word' => ['some_long_name', '<p>some_long_name</p>'],
			'inline code is not parsed' => ['`**a** <b>`', '<p><code>**a** &lt;b&gt;</code></p>'],
			'html is shown as text' => ['<script>alert(1)</script>', '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>'],
			'escaped mark' => ['\*not em\*', '<p>*not em*</p>'],
			'page link' => ['[Routing](routing)', '<p><a href="/guide/kohana/routing">Routing</a></p>'],
			'page link with .md' => ['[Routing](routing.md#name)', '<p><a href="/guide/kohana/routing#name">Routing</a></p>'],
			'anchor link' => ['[Up](#top)', '<p><a href="#top">Up</a></p>'],
			'external link' => ['[PHP](https://www.php.net "Site")', '<p><a href="https://www.php.net" title="Site" rel="noopener">PHP</a></p>'],
			'autolink' => ['<https://example.com>', '<p><a href="https://example.com" rel="noopener">https://example.com</a></p>'],
			'script link is refused' => ['[x](javascript:alert(1))', '<p><a href="#">x</a>)</p>'],
			'image' => ['![Install](install.png)', '<p><img src="/guide-media/kohana/install.png" alt="Install"></p>'],
			'note' => ['[!!] Remember this', '<p class="note">Remember this</p>'],
			'not a class' => ['An [optional] part and [GET]', '<p>An [optional] part and [GET]</p>'],
			'line' => ['---', '<hr>'],
			'quote' => ["> Quoted\n> text", "<blockquote>\n<p>Quoted\ntext</p>\n</blockquote>"],
			'list' => ["- One\n- Two", "<ul>\n<li>One</li>\n<li>Two</li>\n</ul>"],
			'numbered list' => ["1. One\n2. Two", "<ol>\n<li>One</li>\n<li>Two</li>\n</ol>"],
			'list inside a list' => ["- One\n  - Inner\n- Two", "<ul>\n<li>One\n<ul>\n<li>Inner</li>\n</ul></li>\n<li>Two</li>\n</ul>"],
			'list after text' => ["Text:\n- One", "<p>Text:</p>\n<ul>\n<li>One</li>\n</ul>"],
			'fenced code' => ["```sql\nSELECT *\n```", '<pre><code class="language-sql">SELECT *</code></pre>'],
			'indented code' => ["Text\n\n    plain <code>\n\nText", "<p>Text</p>\n<pre><code>plain &lt;code&gt;</code></pre>\n<p>Text</p>"],
			'table' => [
				"| Name | Size |\n|---|--:|\n| `a|b` | 1 |",
				"<table>\n<thead>\n<tr><th>Name</th><th class=\"right\">Size</th></tr>\n</thead>\n<tbody>\n<tr><td><code>a|b</code></td><td class=\"right\">1</td></tr>\n</tbody>\n</table>",
			],
		];
	}

	#[DataProvider('provider_markdown')]
	public function test_markdown(string $markdown, string $expected): void
	{
		$this->assertSame($expected, Kodoc_Markdown::markdown($markdown));
	}

	/**
	 * PHP code is coloured with classes, never with style attributes.
	 */
	public function test_php_code_is_highlighted(): void
	{
		$html = Kodoc_Markdown::markdown("```php\n\$a = 'text'; // note\n```");

		$this->assertStringStartsWith('<pre><code class="language-php">', $html);
		$this->assertStringContainsString('<span class="hl-string">\'text\'</span>', $html);
		$this->assertStringContainsString('class="hl-comment"', $html);
		$this->assertStringNotContainsString('style=', $html);
		$this->assertStringNotContainsString('&lt;?php', $html);

		// A block without a language that looks like PHP is coloured too
		$this->assertStringContainsString('language-php', Kodoc_Markdown::markdown('    $value = Arr::get($array, 1);'));
	}

	/**
	 * Class names in brackets link to the API browser.
	 */
	public function test_api_links(): void
	{
		$api = URL::site(Route::get('docs/api')->uri(['class' => 'Arr']));

		$this->assertSame('<p><a href="'.$api.'">Arr</a></p>', Kodoc_Markdown::markdown('[Arr]'));
		$this->assertSame('<p><a href="'.$api.'#get">Arr::get()</a></p>', Kodoc_Markdown::markdown('[Arr::get()]'));
		$this->assertSame('<p><a href="'.$api.'#property:delimiter">Arr::$delimiter</a></p>', Kodoc_Markdown::markdown('[Arr::$delimiter]'));
	}

	/**
	 * The headings of a page are collected, with ids that are unique.
	 */
	public function test_toc(): void
	{
		Kodoc_Markdown::markdown("# Page\n\n## Setup\n\ntext\n\n## Setup\n\n### `code` title");

		$this->assertSame([
			['level' => 1, 'name' => 'Page', 'id' => 'page'],
			['level' => 2, 'name' => 'Setup', 'id' => 'setup'],
			['level' => 2, 'name' => 'Setup', 'id' => 'setup-2'],
			['level' => 3, 'name' => 'code title', 'id' => 'code-title'],
		], Kodoc_Markdown::toc());
	}
}
