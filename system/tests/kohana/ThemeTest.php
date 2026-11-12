<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the theme of the framework's own pages: the CSS of each mode,
 * that nothing but colours, fonts and lengths gets into it, and that
 * the colours that ship can be read.
 *
 * @package    Kohana
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.theme')]
class Kohana_ThemeTest extends Unittest_TestCase
{
	/**
	 * The theme as it ships.
	 *
	 * @return array<string, mixed>
	 */
	protected static function defaults(): array
	{
		return include SYSPATH.'config/theme.php';
	}

	public function test_auto_follows_the_system_and_obeys_the_button(): void
	{
		$css = Theme::build(['mode' => 'auto']);

		$this->assertStringStartsWith(':root{--k-font:', $css);
		$this->assertStringContainsString('--k-scheme:light;--k-bg:#f6f3ef;', $css);
		$this->assertStringContainsString('@media (prefers-color-scheme:dark){:root{--k-scheme:dark;--k-bg:#1e2022;', $css);
		$this->assertStringContainsString(':root[data-k-theme="light"]{--k-scheme:light;', $css);
		$this->assertStringContainsString(':root[data-k-theme="dark"]{--k-scheme:dark;', $css);

		// The choice of the button comes last, so it wins
		$this->assertGreaterThan(strpos($css, '@media'), strpos($css, ':root[data-k-theme="light"]'));
	}

	public function test_a_fixed_mode_does_not_ask_the_system(): void
	{
		$light = Theme::build(['mode' => 'light']);
		$dark  = Theme::build(['mode' => 'dark']);

		$this->assertStringNotContainsString('@media', $light);
		$this->assertStringNotContainsString('@media', $dark);
		$this->assertStringContainsString('--k-radius:4px;--k-scheme:light;', $light);
		$this->assertStringContainsString('--k-radius:4px;--k-scheme:dark;', $dark);
	}

	public function test_only_what_is_given_changes(): void
	{
		$css = Theme::build([
			'mode'   => 'light',
			'radius' => '0',
			'font'   => 'Georgia, serif',
			'light'  => ['link' => '#00f', 'glass' => 'rgb(255 255 255 / 0.3)'],
		]);

		$this->assertStringContainsString('--k-font:Georgia, serif;', $css);
		$this->assertStringContainsString('--k-radius:0;', $css);
		$this->assertStringContainsString('--k-link:#00f;', $css);
		$this->assertStringContainsString('--k-glass:rgb(255 255 255 / 0.3);', $css);
		$this->assertStringContainsString('--k-bg:#f6f3ef;', $css);
		$this->assertStringContainsString('--k-link:#93c2e2;', $css, 'the dark colours are untouched');
	}

	/**
	 * Values that must never reach the page: they would end the style,
	 * add rules of their own, or load something from elsewhere.
	 *
	 * @return array<string, array{array<string, mixed>}>
	 */
	public static function provider_bad_values(): array
	{
		return [
			'ends the style'   => [['light' => ['bg' => '#fff}</style><script>alert(1)</script>']]],
			'a second rule'    => [['light' => ['bg' => '#fff;background:url(//evil.example/x)']]],
			'a url'            => [['dark' => ['text' => 'url(https://evil.example/x)']]],
			'an expression'    => [['dark' => ['text' => 'expression(alert(1))']]],
			'a variable'       => [['light' => ['bg' => 'var(--x)']]],
			'not text'         => [['light' => ['bg' => ['#fff']]]],
			'a line end'       => [['light' => ['bg' => "#fff\n"]]],
			'font with a rule' => [['font' => 'Arial;} body{display:none']],
			'font with a tag'  => [['font' => '</style>']],
			'radius'           => [['radius' => '4px;color:red']],
			'radius too large' => [['radius' => '9999px']],
			'mode'             => [['mode' => 'pink']],
			'mode not text'    => [['mode' => true]],
		];
	}

	/**
	 * @param array<string, mixed> $config
	 */
	#[DataProvider('provider_bad_values')]
	public function test_a_bad_value_is_left_out_and_the_default_used(array $config): void
	{
		$this->assertSame(Theme::build([]), Theme::build($config));
	}

	public function test_problems_are_named(): void
	{
		$config = Kohana::$config->load('theme');
		$before = $config->as_array();

		try
		{
			$this->assertSame([], Theme::problems());

			$config->exchangeArray(['mode' => 'pink', 'light' => ['bg' => 'red;', 'backgrund' => '#fff']] + $before);

			$this->assertSame(
				['"mode" is not valid', 'the colour "bg" of "light" is not valid', 'there is no colour "backgrund" ("light")'],
				Theme::problems()
			);
			$this->assertSame('auto', Theme::config()['mode']);
			$this->assertStringContainsString('--k-bg:#f6f3ef;', Theme::css());
		}
		finally
		{
			$config->exchangeArray($before);
		}
	}

	public function test_the_logo_is_an_svg_element_that_a_strict_policy_allows(): void
	{
		$logo = Theme::logo();

		$this->assertStringStartsWith('<svg class="k-logo"', $logo);
		$this->assertStringEndsWith('</svg>', $logo);
		$this->assertStringContainsString('aria-label="Kohana 4"', $logo);
		$this->assertStringNotContainsString('style=', $logo);
		$this->assertStringNotContainsString('<script', $logo);
		$this->assertStringNotContainsString('<image', $logo, 'a vector, without a picture inside');
	}

	public function test_the_favicon_carries_its_picture(): void
	{
		$link = Theme::favicon();

		$this->assertStringStartsWith('<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,', $link);
		$this->assertSame(1, preg_match('/base64,([A-Za-z0-9+\/=]+)">$/', $link, $matches));
		$this->assertStringContainsString('<svg', (string) base64_decode($matches[1]));
	}

	public function test_the_button_can_be_switched_off(): void
	{
		$config = Kohana::$config->load('theme');

		try
		{
			$button = Theme::button('extra-class');

			$this->assertStringContainsString('data-k-theme-button', $button);
			$this->assertStringContainsString('class="k-theme-button extra-class"', $button);
			$this->assertStringContainsString(' hidden', $button, 'without JavaScript the button does nothing: it is not shown');
			$this->assertStringNotContainsString('onclick', $button);
			$this->assertStringNotContainsString('style=', $button);

			$config->set('button', false);

			$this->assertSame('', Theme::button());
		}
		finally
		{
			$config->set('button', true);
		}
	}

	public function test_the_script_is_plain_javascript(): void
	{
		$script = Theme::script();

		$this->assertStringContainsString('data-k-theme', $script);
		$this->assertStringNotContainsString('</script', $script);
		$this->assertStringNotContainsString('<?', $script);
	}

	public function test_every_theme_names_colours_that_exist(): void
	{
		$themes  = static::defaults()['themes'];
		$default = $themes['default'];

		$this->assertSame(['default', 'catppuccin', 'dracula', 'nord', 'solarized', 'gruvbox', 'tokyo-night', 'one'], array_keys($themes));
		$this->assertSame(array_keys($themes), Theme::names());
		$this->assertSame(array_keys($default['light']), array_keys($default['dark']));

		foreach ($themes as $name => $theme)
		{
			$this->assertSame(['light', 'dark'], array_keys($theme), $name);

			foreach ($theme as $palette => $colors)
			{
				$this->assertSame([], array_diff_key($colors, $default[$palette]), $name.' '.$palette.' names a colour that is not there');

				foreach ($colors as $color => $value)
				{
					$this->assertMatchesRegularExpression(Theme::COLOR, $value, $name.' '.$palette.' '.$color);
				}
			}
		}
	}

	public function test_a_theme_is_chosen_by_its_name(): void
	{
		$css = Theme::build(['theme' => 'dracula', 'mode' => 'dark']);

		$this->assertStringContainsString('--k-scheme:dark;--k-bg:#21222c;', $css);
		$this->assertStringContainsString(':root[data-k-theme="light"]{--k-scheme:light;--k-bg:#f5f1e0;', $css);

		// What the theme does not say comes from the default theme
		$this->assertStringContainsString('--k-shadow:rgb(0 0 0 / 0.5);', $css);

		$this->assertStringContainsString('--k-bg:#e5e9f0;', Theme::build(['theme' => 'nord', 'mode' => 'light']));
	}

	public function test_a_theme_that_is_not_there_gives_the_default(): void
	{
		$default = Theme::build([]);

		$this->assertSame($default, Theme::build(['theme' => 'default']));
		$this->assertSame($default, Theme::build(['theme' => 'no-such-theme']));
		$this->assertSame($default, Theme::build(['theme' => null]));
		$this->assertSame($default, Theme::build(['theme' => ['dracula']]));
		$this->assertSame($default, Theme::build(['theme' => 'dracula</style>']));
		$this->assertSame($default, Theme::build(['themes' => 'not a list']));
	}

	public function test_your_own_theme_and_your_own_colours(): void
	{
		$config = [
			'theme'  => 'mine',
			'mode'   => 'light',
			'themes' => [
				'mine' => [
					'light' => ['bg' => '#ffffff', 'link' => '#ff0000', 'text' => 'not a colour'],
				],
			],
		];

		$css = Theme::build($config);

		$this->assertStringContainsString('--k-scheme:light;--k-bg:#ffffff;', $css);
		$this->assertStringContainsString('--k-link:#ff0000;', $css);

		// A colour that is not valid, and a side the theme does not have: the default theme
		$this->assertStringContainsString('--k-text:#33383b;', $css);
		$this->assertStringContainsString(':root[data-k-theme="dark"]{--k-scheme:dark;--k-bg:#1e2022;', $css);

		// Your own colours come on top of any theme
		$css = Theme::build(['light' => ['link' => '#00ff00']] + $config);

		$this->assertStringContainsString('--k-link:#00ff00;', $css);
		$this->assertStringContainsString('--k-bg:#ffffff;', $css);

		// A theme that ships can be changed in part under its own name
		$css = Theme::build(['theme' => 'nord', 'mode' => 'dark', 'themes' => ['nord' => ['dark' => ['link' => '#abcdef']]]]);

		$this->assertStringContainsString('--k-link:#abcdef;', $css);
		$this->assertStringContainsString('--k-scheme:dark;--k-bg:#2e3440;', $css);
	}

	public function test_problems_of_themes_are_named(): void
	{
		$config = Kohana::$config->load('theme');
		$before = $config->as_array();

		try
		{
			$config->exchangeArray(['theme' => 'dracla'] + $before);

			$this->assertSame(['there is no theme "dracla"'], Theme::problems());
			$this->assertSame('default', Theme::config()['theme']);

			$config->exchangeArray(['theme' => 'mine', 'themes' => ['mine' => ['dark' => ['bg' => 'black', 'bgg' => '#000']]]] + $before);

			$this->assertSame(
				['the colour "bg" of the theme "mine" ("dark") is not valid', 'there is no colour "bgg" (the theme "mine" ("dark"))'],
				Theme::problems()
			);
			$this->assertSame('mine', Theme::config()['theme']);
			$this->assertContains('mine', Theme::names());
		}
		finally
		{
			$config->exchangeArray($before);
		}
	}

	/**
	 * Text colours and the backgrounds they are written on, in both
	 * sides of every theme.
	 *
	 * @return array<string, array{string, string, string, string}>
	 */
	public static function provider_pairs(): array
	{
		$pairs = [
			['text', 'bg'], ['text', 'surface'], ['text', 'highlight'], ['text', 'border'],
			['muted', 'bg'], ['muted', 'surface'],
			['link', 'bg'], ['link', 'surface'],
			['bar-text', 'bar'], ['error-text', 'error'], ['cause-text', 'cause'],
			['app-text', 'app'], ['notice-text', 'notice'],
			['code-keyword', 'bg'], ['code-string', 'bg'], ['code-comment', 'bg'],
			['code-keyword', 'surface'], ['code-string', 'surface'], ['code-comment', 'surface'],
		];

		$data = [];

		foreach (array_keys(static::defaults()['themes']) as $theme)
		{
			foreach (['light', 'dark'] as $palette)
			{
				foreach ($pairs as list($text, $background))
				{
					$data[$theme.' '.$palette.': '.$text.' on '.$background] = [$theme, $palette, $text, $background];
				}
			}
		}

		return $data;
	}

	/**
	 * Every text can be read on its background: a contrast of 4.5 to 1,
	 * the "AA" level of the accessibility guidelines (WCAG).
	 */
	#[DataProvider('provider_pairs')]
	public function test_text_can_be_read(string $theme, string $palette, string $text, string $background): void
	{
		$config = Kohana::$config->load('theme');
		$before = $config->as_array();

		try
		{
			$config->exchangeArray(['theme' => $theme, 'light' => [], 'dark' => []] + $before);

			$this->assertSame([], Theme::problems());
			$this->assertSame($theme, Theme::config()['theme']);

			$colors = Theme::config()[$palette];
		}
		finally
		{
			$config->exchangeArray($before);
		}

		$light = max($this->luminance($colors[$text]), $this->luminance($colors[$background]));
		$dark  = min($this->luminance($colors[$text]), $this->luminance($colors[$background]));

		$this->assertGreaterThanOrEqual(4.5, ($light + 0.05) / ($dark + 0.05));
	}

	/**
	 * How bright a colour written as #rrggbb is, from 0 to 1 (WCAG).
	 */
	protected function luminance(string $hex): float
	{
		$this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hex);

		$parts = [];

		foreach (str_split(substr($hex, 1), 2) as $part)
		{
			$value   = hexdec($part) / 255;
			$parts[] = ($value <= 0.03928) ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
		}

		return 0.2126 * $parts[0] + 0.7152 * $parts[1] + 0.0722 * $parts[2];
	}
}
