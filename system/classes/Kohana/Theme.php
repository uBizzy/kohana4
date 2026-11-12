<?php
/**
 * The colours and fonts of what the framework itself prints (error
 * pages, profiler, user guide, extra modules), light and dark, from
 * config/theme.php.
 *
 * The config names a theme ("default", "dracula", "nord", ...). Each
 * theme has a light and a dark side; what a theme does not say, and a
 * theme that does not exist, falls back to the default theme.
 *
 * A page of the framework puts three things in its HTML:
 *
 *     <style nonce="...">< ?php echo Theme::css() ? > ...</style>
 *     <script nonce="...">< ?php echo Theme::script() ? ></script>
 *     < ?php echo Theme::button() ? >
 *
 * The CSS only sets variables (`--k-bg`, `--k-text`, ...) that the style
 * sheets of the pages use; it changes nothing by itself, so it is safe
 * inside a page of an application (debug bar, notices).
 *
 * This class is used by the error pages: it never throws. A value of the
 * config that is not valid is left out, the default is used in its
 * place, and [Theme::problems] names it (shown by `./minion check`).
 *
 * @package    Kohana
 * @category   Helpers
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Theme {

	/**
	 * @var  string  What a colour of the config looks like
	 */
	public const string COLOR = '/^(#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})|(rgb|hsl)a?\([0-9a-z.,%\/ -]{1,60}\))$/iD';

	/**
	 * @var  string  What a list of fonts looks like
	 */
	public const string FONT = '/^[a-z0-9 ,"\'_-]{1,200}$/iD';

	/**
	 * @var  string  What a length looks like: 0, 4px, 0.5rem
	 */
	public const string LENGTH = '/^(0|[0-9]{1,3}(\.[0-9]{1,3})?(px|em|rem))$/D';

	/**
	 * Returns the CSS that sets the variables of the theme, for the
	 * mode of the config.
	 *
	 * @return  string
	 */
	public static function css(): string
	{
		return static::build(static::config());
	}

	/**
	 * Returns the CSS of a theme. It needs nothing of the framework, so
	 * the install page can use it before the framework has started.
	 *
	 * @param   array<int|string, mixed>  $config  as config/theme.php; missing or invalid parts fall back to the defaults
	 * @return  string
	 */
	public static function build(array $config): string
	{
		$config = static::_checked($config)[0];
		$mode   = $config['mode'];

		$shared = '--k-font:'.$config['font'].';--k-font-code:'.$config['font_code'].';--k-radius:'.$config['radius'].';';
		$light  = static::_variables($config['light'], 'light');
		$dark   = static::_variables($config['dark'], 'dark');

		// What applies when the visitor chose nothing
		$css = ':root{'.$shared.(($mode === 'dark') ? $dark : $light).'}';

		if ($mode === 'auto')
		{
			$css .= '@media (prefers-color-scheme:dark){:root{'.$dark.'}}';
		}

		// What the visitor chose with the button, kept by Theme::script().
		// After the rules above, so it wins over them.
		return $css.':root[data-k-theme="light"]{'.$light.'}:root[data-k-theme="dark"]{'.$dark.'}';
	}

	/**
	 * Returns the JavaScript that applies the choice of the visitor
	 * before the page is drawn, and makes the button work.
	 *
	 * @return  string
	 */
	public static function script(): string
	{
		return (string) file_get_contents(SYSPATH.'views'.DIRECTORY_SEPARATOR.'kohana'.DIRECTORY_SEPARATOR.'theme.js');
	}

	/**
	 * Returns the button that switches between auto, light and dark, or
	 * an empty string when the config switches it off. It stays hidden
	 * without JavaScript.
	 *
	 * @param   string  $class  class names for the button, besides "k-theme-button"
	 * @return  string  HTML
	 */
	public static function button(string $class = ''): string
	{
		if ( ! static::config()['button'])
			return '';

		return '<button type="button" class="'.htmlspecialchars(trim('k-theme-button '.$class), ENT_QUOTES, 'UTF-8')
			.'" data-k-theme-button hidden title="Colours: follow the system, light or dark">Theme</button>';
	}

	/**
	 * Returns the logo of the framework as an SVG element, to print in a
	 * page. Its size is set with CSS:
	 *
	 *     .k-logo { height: 1.6rem; width: auto; }
	 *
	 * The picture is system/views/kohana/logo.svg.
	 *
	 * @return  string  HTML
	 */
	public static function logo(): string
	{
		return trim((string) file_get_contents(SYSPATH.'views'.DIRECTORY_SEPARATOR.'kohana'.DIRECTORY_SEPARATOR.'logo.svg'));
	}

	/**
	 * Returns the `<link>` element of the small icon of the framework (the
	 * "k" with the 4), for the `<head>` of a page. The picture is inside
	 * the element, so no file has to be served: a Content-Security-Policy
	 * must allow `data:` for images to show it.
	 *
	 * The picture is system/views/kohana/favicon.svg.
	 *
	 * @return  string  HTML
	 */
	public static function favicon(): string
	{
		$icon = (string) file_get_contents(SYSPATH.'views'.DIRECTORY_SEPARATOR.'kohana'.DIRECTORY_SEPARATOR.'favicon.svg');

		return '<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,'.base64_encode($icon).'">';
	}

	/**
	 * Returns the names of the themes there are: those that ship, and
	 * those of the config of the application.
	 *
	 * @return  string[]
	 */
	public static function names(): array
	{
		$names = [];

		foreach ([static::_defaults(), static::_loaded()] as $config)
		{
			foreach (array_keys(is_array($config['themes'] ?? null) ? $config['themes'] : []) as $name)
			{
				$names[ (string) $name] = (string) $name;
			}
		}

		return array_values($names);
	}

	/**
	 * Returns what is wrong in config/theme.php, in words.
	 *
	 * @return  string[]  empty when all is well
	 */
	public static function problems(): array
	{
		return static::_checked(static::_loaded())[1];
	}

	/**
	 * Returns the theme that is used: its name, and the colours of
	 * both sides as they end up, with the default theme in the place of
	 * everything missing or not valid.
	 *
	 * @return  array{theme: string, mode: string, button: bool, font: string, font_code: string, radius: string, light: array<string, string>, dark: array<string, string>}
	 */
	public static function config(): array
	{
		return static::_checked(static::_loaded())[0];
	}

	/**
	 * Returns config/theme.php as it is, of the application and the
	 * framework together.
	 *
	 * @return  array<int|string, mixed>  empty when the config cannot be read
	 */
	protected static function _loaded(): array
	{
		try
		{
			$config = Kohana::$config?->load('theme');

			return ($config instanceof Config_Group) ? $config->as_array() : [];
		}
		catch (Throwable $e)
		{
			// The error page must be shown whatever the config is like
			return [];
		}
	}

	/**
	 * Returns the defaults: config/theme.php of the framework itself.
	 *
	 * @return  array<int|string, mixed>
	 */
	protected static function _defaults(): array
	{
		/** @var array<int|string, mixed>|null $defaults */
		static $defaults = null;

		// A plain include: the install page uses this class before the framework has started
		return $defaults ??= (array) include SYSPATH.'config/theme.php';
	}

	/**
	 * Returns one side ("light" or "dark") of a theme of a config, as
	 * far as it is an array.
	 *
	 * @param   array<int|string, mixed>  $config   as config/theme.php
	 * @param   string                    $theme    name of the theme
	 * @param   string                    $palette  "light" or "dark"
	 * @return  array<int|string, mixed>
	 */
	protected static function _palette(array $config, string $theme, string $palette): array
	{
		$themes = is_array($config['themes'] ?? null) ? $config['themes'] : [];
		$sides  = is_array($themes[$theme] ?? null) ? $themes[$theme] : [];

		return is_array($sides[$palette] ?? null) ? $sides[$palette] : [];
	}

	/**
	 * Checks a theme.
	 *
	 * @param   array<int|string, mixed>  $config  as config/theme.php
	 * @return  array{array{theme: string, mode: string, button: bool, font: string, font_code: string, radius: string, light: array<string, string>, dark: array<string, string>}, string[]}  the theme to use, and what was wrong
	 */
	protected static function _checked(array $config): array
	{
		$defaults = static::_defaults();
		$problems = [];

		// Returns a text of the config when it fits, the default when not
		$text = function (string $key, string $pattern) use ($config, $defaults, & $problems): string
		{
			$value = $config[$key] ?? $defaults[$key];

			if (is_string($value) AND preg_match($pattern, $value))
				return $value;

			$problems[] = '"'.$key.'" is not valid';

			return is_string($defaults[$key]) ? $defaults[$key] : '';
		};

		// The theme that is asked for, when there is one of that name:
		// in the config, or among those that ship
		$name = $config['theme'] ?? 'default';

		if ( ! is_string($name) OR (static::_palette($config, $name, 'light') + static::_palette($config, $name, 'dark') + static::_palette($defaults, $name, 'light')) === [])
		{
			$problems[] = 'there is no theme "'.(is_string($name) ? $name : get_debug_type($name)).'"';
			$name = 'default';
		}

		$theme = [
			'theme'     => $name,
			'mode'      => $text('mode', '/^(auto|light|dark)$/D'),
			'button'    => (bool) ($config['button'] ?? $defaults['button']),
			'font'      => $text('font', static::FONT),
			'font_code' => $text('font_code', static::FONT),
			'radius'    => $text('radius', static::LENGTH),
			'light'     => [],
			'dark'      => [],
		];

		foreach (['light', 'dark'] as $palette)
		{
			$fallback = static::_palette($defaults, 'default', $palette);

			// Where a colour is looked for, in this order: your own
			// colours, the theme, the same theme as it ships, the default theme
			$sources = [
				'"'.$palette.'"'                   => is_array($config[$palette] ?? null) ? $config[$palette] : [],
				'the theme "'.$name.'" ("'.$palette.'")' => static::_palette($config, $name, $palette) + static::_palette($defaults, $name, $palette),
			];

			foreach ($fallback as $color => $default)
			{
				$theme[$palette][ (string) $color] = is_string($default) ? $default : '';

				foreach (array_reverse($sources) as $where => $colors)
				{
					if ( ! isset($colors[$color]))
						continue;

					if (is_string($colors[$color]) AND preg_match(static::COLOR, $colors[$color]))
					{
						$theme[$palette][ (string) $color] = $colors[$color];
					}
					else
					{
						$problems[] = 'the colour "'.$color.'" of '.$where.' is not valid';
					}
				}
			}

			foreach ($sources as $where => $colors)
			{
				foreach (array_diff_key($colors, $fallback) as $color => $value)
				{
					// A typing mistake in a name would otherwise change nothing, silently
					$problems[] = 'there is no colour "'.$color.'" ('.$where.')';
				}
			}
		}

		return [$theme, $problems];
	}

	/**
	 * Returns the CSS variables of one palette.
	 *
	 * @param   array<string, string>  $colors  name => colour, already checked
	 * @param   string                 $scheme  "light" or "dark": told to the browser for its own parts (scrollbars, form fields)
	 * @return  string
	 */
	protected static function _variables(array $colors, string $scheme): string
	{
		$css = '--k-scheme:'.$scheme.';';

		foreach ($colors as $name => $color)
		{
			$css .= '--k-'.$name.':'.$color.';';
		}

		return $css;
	}

}
