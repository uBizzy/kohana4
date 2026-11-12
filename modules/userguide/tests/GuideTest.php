<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Checks the guide pages of the framework and of every module against
 * the code: a guide for each module, no broken links, examples that are
 * valid PHP and that only call methods that exist.
 *
 * @package    Kohana/Userguide
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.userguide')]
#[Group('kohana.userguide.guides')]
class Kohana_GuideTest extends Unittest_TestCase
{
	/**
	 * Names used in examples for classes of an imaginary application
	 */
	protected const array EXAMPLE_PREFIXES = ['Model_', 'Controller_', 'Task_', 'My', 'Bench_', 'Foo', 'Bar'];

	/**
	 * Returns the pages of a guide: page name => path of the file.
	 */
	protected static function _pages(string $guide): array
	{
		$pages = [];

		$collect = function (array $files) use (&$collect, &$pages, $guide): void
		{
			foreach ($files as $name => $path)
			{
				if (is_array($path))
				{
					$collect($path);
				}
				elseif (str_ends_with($name, '.md'))
				{
					$pages[str_replace(DIRECTORY_SEPARATOR, '/', substr($name, strlen('guide/'.$guide.'/'), -3))] = $path;
				}
			}
		};

		$collect(Kohana::list_files('guide/'.$guide));

		return $pages;
	}

	/**
	 * The names of the guides to check.
	 *
	 * @return array<int, string>
	 */
	protected static function _guides(): array
	{
		return array_keys(Kohana::$config->load('userguide.modules'));
	}

	/**
	 * Every guide, as a data provider.
	 */
	public static function provider_guides(): array
	{
		$guides = [];

		foreach (static::_guides() as $guide)
		{
			$guides[$guide] = [$guide];
		}

		return $guides;
	}

	/**
	 * Every page of every guide, as a data provider.
	 */
	public static function provider_pages(): array
	{
		$pages = [];

		foreach (static::_guides() as $guide)
		{
			foreach (static::_pages($guide) as $page => $path)
			{
				$pages[$guide.'/'.$page] = [$guide, $page, $path];
			}
		}

		return $pages;
	}

	/**
	 * Returns the PHP examples of a page: the blocks fenced as ```php.
	 */
	protected static function _examples(string $markdown): array
	{
		preg_match_all('/^```php\n(.*?)^```/ms', $markdown, $matches);

		return $matches[1];
	}

	/**
	 * The framework and every module have a guide.
	 */
	public function test_every_module_has_a_guide(): void
	{
		$guides = Kohana::$config->load('userguide.modules');

		$this->assertArrayHasKey('kohana', $guides);

		foreach (array_keys(Kohana::modules()) as $module)
		{
			$this->assertArrayHasKey($module, $guides, 'The module "'.$module.'" has no config/userguide.php');
		}
	}

	/**
	 * A guide has a first page and a menu, a name and a description;
	 * every page is in the menu and every menu link is a page.
	 */
	#[DataProvider('provider_guides')]
	public function test_guide_is_complete(string $guide): void
	{
		$options = Kohana::$config->load('userguide.modules.'.$guide);
		$pages = static::_pages($guide);

		$this->assertNotEmpty($options['name'] ?? '', 'name');
		$this->assertNotEmpty($options['description'] ?? '', 'description');
		$this->assertArrayHasKey('index', $pages, 'guide/'.$guide.'/index.md is missing');
		$this->assertArrayHasKey('menu', $pages, 'guide/'.$guide.'/menu.md is missing');

		preg_match_all('/\]\(([^)\s#]+)\)/', file_get_contents($pages['menu']), $matches);

		$linked = array_map(fn ($link) => preg_replace('/\.md$/', '', $link), $matches[1]);

		foreach ($linked as $link)
		{
			$this->assertArrayHasKey($link, $pages, 'The menu of "'.$guide.'" links to the missing page "'.$link.'"');
		}

		foreach (array_keys($pages) as $page)
		{
			if ($page !== 'index' AND $page !== 'menu')
			{
				$this->assertContains($page, $linked, 'The page "'.$guide.'/'.$page.'" is not in the menu');
			}
		}
	}

	/**
	 * A page starts with its title, and its links lead somewhere.
	 */
	#[DataProvider('provider_pages')]
	public function test_page_links(string $guide, string $page, string $path): void
	{
		$markdown = file_get_contents($path);
		if ($page !== 'menu')
		{
			$this->assertMatchesRegularExpression('/\A# \S/', $markdown, 'The page must start with "# Title"');
		}

		// Code is not text: links are only looked for outside it
		$text = preg_replace(['/^```.*?^```/ms', '/`[^`\n]+`/'], '', $markdown);

		// [text](page): pages of this guide, or ../other/page of another guide
		preg_match_all('/(?<!!)\[[^\]]*\]\(([^)\s]+)[^)]*\)/', $text, $matches);

		foreach ($matches[1] as $link)
		{
			if (preg_match('~^(\w+:|#|/)~', $link))
				continue;

			// Links are relative to the file of the page: work out guide and page
			$parts = [];

			foreach (explode('/', $guide.'/'.dirname($page).'/'.preg_replace('/(\.md)?(#.*)?$/', '', $link)) as $part)
			{
				if ($part === '..')
				{
					array_pop($parts);
				}
				elseif ($part !== '.' AND $part !== '')
				{
					$parts[] = $part;
				}
			}

			$target_guide = (string) array_shift($parts);
			$target_page = $parts ? implode('/', $parts) : 'index';

			$this->assertArrayHasKey($target_page, static::_pages($target_guide), 'Broken link "'.$link.'"');
		}

		// ![text](image): a file in media/guide/<guide>/
		preg_match_all('/!\[[^\]]*\]\(([^)\s]+)[^)]*\)/', $text, $matches);

		foreach ($matches[1] as $image)
		{
			if ( ! preg_match('~^(\w+:)?//~', $image))
			{
				$info = pathinfo($image);

				$this->assertNotFalse(
					Kohana::find_file('media/guide/'.$guide, ($info['dirname'] === '.' ? '' : $info['dirname'].'/').$info['filename'], $info['extension'] ?? ''),
					'Missing image "'.$image.'"'
				);
			}
		}

		// [Class], [Class::method()] and [Class::$property] must exist
		preg_match_all('/\[([A-Z]\w*)(?:::(\$?\w+))?(?:\(\))?\](?![(\[])/', $text, $matches, PREG_SET_ORDER);

		foreach ($matches as $match)
		{
			if ( ! class_exists($match[1]) AND ! interface_exists($match[1]))
				continue;

			$member = $match[2] ?? '';

			if ($member !== '')
			{
				$exists = ($member[0] === '$')
					? property_exists($match[1], substr($member, 1))
					: (method_exists($match[1], $member) OR defined($match[1].'::'.$member));

				$this->assertTrue($exists, 'API link to "'.$match[0].'", which does not exist');
			}
		}
	}

	/**
	 * The PHP examples of a page are valid PHP, and the static methods,
	 * constants and static properties of framework classes they use exist.
	 */
	#[DataProvider('provider_pages')]
	public function test_page_examples(string $guide, string $page, string $path): void
	{
		$markdown = file_get_contents($path);

		// Code is always fenced, with its language: ```php, ```sql, ```bash, ```text
		$this->assertDoesNotMatchRegularExpression('/^```\s*$\n(?!\Z)(?=(?:.*\n)*?```)/m', preg_replace('/^```\w+\n.*?^```\s*$/ms', '', $markdown), 'A code block has no language');

		// Classes and methods that the page itself declares in its examples
		preg_match_all('/\b(?:class|interface)\s+(\w+)/', $markdown, $declared);
		preg_match_all('/\bfunction\s+(\w+)\s*\(/', $markdown, $declared_methods);

		foreach (static::_examples($markdown) as $code)
		{
			$source = str_contains($code, '<?php') ? $code : "<?php\n".$code;

			try
			{
				token_get_all($source, TOKEN_PARSE);
			}
			catch (ParseError $e)
			{
				// A piece of a class (methods) or of an array (settings)?
				$valid = false;

				foreach (["<?php\nclass Example {\n%s\n}", "<?php\n\$example = [\n%s\n];"] as $wrapper)
				{
					try
					{
						token_get_all(sprintf($wrapper, $code), TOKEN_PARSE);
						$valid = true;
						break;
					}
					catch (ParseError $inner)
					{
						// Try the next form
					}
				}

				if ( ! $valid)
				{
					$this->fail('An example is not valid PHP ('.$e->getMessage().', line '.($e->getLine() - 1)."):\n".$code);
				}
			}

			// Class::method(   Class::CONSTANT   Class::$property
			preg_match_all('/\b([A-Z]\w*)::(\$?\w+)(\s*\()?/', $code, $uses, PREG_SET_ORDER);

			foreach ($uses as [$all, $class, $member])
			{
				$example = (in_array($class, $declared[1], true)
					OR array_filter(static::EXAMPLE_PREFIXES, fn ($prefix) => str_starts_with($class, $prefix)));

				if ($example OR $member === 'class' OR in_array($member, $declared_methods[1], true) OR ( ! class_exists($class) AND ! interface_exists($class)))
					continue;

				$exists = match (true)
				{
					$member[0] === '$' => property_exists($class, substr($member, 1)),
					str_ends_with($all, '(') => method_exists($class, $member) OR method_exists($class, '__callStatic'),
					default => defined($class.'::'.$member) OR method_exists($class, $member),
				};

				$this->assertTrue($exists, 'An example uses '.$class.'::'.$member.', which does not exist');
			}
		}
	}

	/**
	 * The guides speak of Kohana. The old names only appear where the
	 * guide is about them.
	 */
	#[DataProvider('provider_pages')]
	public function test_page_names(string $guide, string $page, string $path): void
	{
		if (in_array($guide, ['ko7', 'ko9'], true) OR in_array($guide.'/'.$page, ['kohana/upgrading', 'kohana/index'], true))
		{
			$this->assertTrue(true);
			return;
		}

		$this->assertDoesNotMatchRegularExpression('/koseven|\bKO[79]_/i', file_get_contents($path));
	}
}
