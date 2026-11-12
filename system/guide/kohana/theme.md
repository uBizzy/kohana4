# Light and Dark Theme

Everything the framework itself prints has a light and a dark look: the error pages, the notices and the profiler, this guide, Codebench, the install page, and the extra modules (debug bar, log reader). The pages of your application are not touched.

There are several themes to choose from, each with a light and a dark side. By default a page follows the system of the visitor, and a small **Theme** button switches between *auto*, *light* and *dark*. The choice is kept in the browser.

## Choosing a theme

Eight themes ship. Each has a light and a dark side.

```php
// application/config/theme.php
return [
	'theme' => 'dracula',
];
```

| `theme` | Light side | Dark side |
|---|---|---|
| `default` | Pastel tones on a warm off-white | The same tones, quieter, on a warm dark grey |
| `catppuccin` | Latte | Mocha |
| `dracula` | Alucard | Dracula |
| `nord` | Snow Storm | Polar Night |
| `solarized` | Solarized Light | Solarized Dark |
| `gruvbox` | Gruvbox Light | Gruvbox Dark |
| `tokyo-night` | Day | Night |
| `one` | One Light | One Dark |

- Without a `theme`, and with a name that does not exist, the pages have the `default` theme.
- The named themes follow the colours of their projects. Where a colour could not be read well enough on its background, it was made a little darker or lighter: every text in every theme has a contrast of at least 4.5 to 1.

### What they look like

Each picture shows the light and the dark side of a theme: text, code, the error and notice colours, and its main colours.

![The default theme, light and dark](themes/default.svg)

![The catppuccin theme, light and dark](themes/catppuccin.svg)

![The dracula theme, light and dark](themes/dracula.svg)

![The nord theme, light and dark](themes/nord.svg)

![The solarized theme, light and dark](themes/solarized.svg)

![The gruvbox theme, light and dark](themes/gruvbox.svg)

![The tokyo-night theme, light and dark](themes/tokyo-night.svg)

![The one theme, light and dark](themes/one.svg)

## Light, dark, or as the system says

```php
// application/config/theme.php
return [
	'theme'  => 'nord',
	'mode'   => 'dark',   // 'auto' (the default), 'light' or 'dark'
	'button' => false,    // no button: the mode above is what everybody gets
];
```

| `mode` | The pages have |
|---|---|
| `auto` | The light or the dark side of the theme, as the system of the visitor says |
| `light` | Always the light side |
| `dark` | Always the dark side |

With the button shown, a visitor can still choose otherwise; the mode is what they get before they do. The button changes the side, not the theme.

## Your own colours

A theme is a list of named colours for each side. To change some of them, whatever the theme, name them under `light` and `dark`:

```php
// application/config/theme.php
return [
	'theme' => 'catppuccin',
	'light' => [
		'link' => '#1d4ed8',
	],
	'dark' => [
		'bg'      => '#000000',
		'surface' => '#111111',
	],
];
```

| Colour | Used for |
|---|---|
| `bg`, `surface` | The page, and what lies on it: panels, tables, code |
| `text`, `muted` | Text, and less important text |
| `border` | Lines between things |
| `link` | Links |
| `bar`, `bar-text` | Headers and the bars of sections |
| `error`, `error-text` | The error itself |
| `cause`, `cause-text` | Causes of an error, warnings |
| `app`, `app-text` | Your own code in a trace, what is current, success |
| `highlight` | The line of an error, notes |
| `notice`, `notice-text` | Notices and deprecations |
| `chart-1` to `chart-4` | Bars of the profiler and of the timeline |
| `code-keyword`, `code-string`, `code-comment` | Highlighted code |
| `glass`, `edge` | Buttons and badges on a coloured bar, and their borders |
| `shadow` | Shadows |

All of them, with the values of every theme, are in `system/config/theme.php`.

A colour is written as `#rgb`, `#rrggbb` (with or without transparency) or `rgb(...)` / `hsl(...)`.

[!!] Keep text readable: each `...-text` colour is written on the colour of the same name, `text` and `muted` on `bg` and `surface`.

## A theme of your own

Add it under a name of its own, and choose it:

```php
// application/config/theme.php
return [
	'theme'  => 'company',
	'themes' => [
		'company' => [
			'light' => [
				'bg'       => '#f4f6f8',
				'surface'  => '#ffffff',
				'link'     => '#0057b8',
				'bar'      => '#0057b8',
				'bar-text' => '#ffffff',
			],
			'dark' => [
				'bg'       => '#0b1620',
				'surface'  => '#12202d',
				'link'     => '#7cb8ff',
			],
		],
	],
];
```

- A colour your theme does not name is that of the `default` theme. So is a side it does not have.
- A theme that ships can be changed the same way, under its own name.

## Fonts and corners

```php
// application/config/theme.php
return [
	'font'      => 'Georgia, serif',
	'font_code' => '"JetBrains Mono", monospace',
	'radius'    => '0',   // square corners; '4px' is the default
];
```

A font must be installed on the computer of the visitor: the framework loads none.

## When a value is wrong

A value that is not a colour, a list of fonts or a length is **not used**: the default takes its place, so a mistake in the config can never break the error page. `./minion check` names such values:

```text
warn  Theme  the colour "bg" of "light" is not valid in config/theme.php: the default is used
warn  Theme  there is no theme "dracla" in config/theme.php: the default is used
```

## In a module of your own

A page of a module can use the same colours, and follow the same button.

```php
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Reports</title>
	<style nonce="<?php echo HTML::chars(Security::nonce()) ?>">
		<?php echo Theme::css() ?>

		html { color-scheme: var(--k-scheme); }
		body { background: var(--k-bg); color: var(--k-text); font: 15px/1.5 var(--k-font); }
		a { color: var(--k-link); }
		.failed { background: var(--k-error); color: var(--k-error-text); border-radius: var(--k-radius); }
	</style>
	<script nonce="<?php echo HTML::chars(Security::nonce()) ?>"><?php echo Theme::script() ?></script>
</head>
<body>
	<?php echo Theme::button() ?>
</body>
</html>
```

| Method | Returns |
|---|---|
| `Theme::css()` | The CSS that sets the variables: `--k-bg`, `--k-text`, ... one for each colour, and `--k-font`, `--k-font-code`, `--k-radius`, `--k-scheme` |
| `Theme::script()` | The JavaScript that applies the choice of the visitor and makes the button work |
| `Theme::button()` | The button, or nothing when it is switched off |
| `Theme::logo()` | The logo of the framework, as an SVG element; give `.k-logo` a height with CSS |
| `Theme::favicon()` | The `<link>` element of the small icon, for the `<head>`; the picture is inside it |
| `Theme::config()` | The theme in use: its name, and the colours of both sides as they end up |
| `Theme::names()` | The names of the themes there are |
| `Theme::problems()` | What is wrong in the config, in words |

- The CSS only sets variables: it changes nothing by itself, so it does no harm inside a page of your application.
- Nothing here uses inline event handlers or `style` attributes: it works under a strict [Content-Security-Policy](security/headers).
- The choice of the visitor is kept in the `localStorage` of the browser under `k-theme`, and marked on the page as `<html data-k-theme="dark">`. No cookie is set.
