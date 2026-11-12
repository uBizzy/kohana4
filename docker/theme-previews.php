<?php
/**
 * Draws a small preview of every theme of system/config/theme.php, as
 * the SVG images that README.md and the guide show: the light and the
 * dark side, each with a sample of text and code and the main colours.
 *
 *     php docker/theme-previews.php
 *
 * Run it again after changing the colours of a theme.
 */
define('SYSPATH', dirname(__DIR__).'/system/');

/** @var array{themes: array<string, array{light?: array<string, string>, dark?: array<string, string>}>} $config */
$config = include SYSPATH.'config/theme.php';

// The colours shown as squares under the sample
const SWATCHES = ['bg', 'surface', 'border', 'text', 'muted', 'link', 'bar', 'error', 'notice', 'highlight', 'chart-1', 'chart-2', 'chart-3', 'chart-4'];

/**
 * One side (light or dark) of a theme, drawn 350 wide and 150 high.
 *
 * @param   string                 $title  what the bar says
 * @param   array<string, string>  $c      the colours, by name
 * @param   int                    $x      where the panel starts
 */
function panel(string $title, array $c, int $x): string
{
	$svg = '<g transform="translate('.$x.' 0)">'
		.'<rect width="350" height="150" rx="6" fill="'.$c['bg'].'" stroke="'.$c['border'].'"/>'
		.'<path d="M0 6a6 6 0 0 1 6-6h338a6 6 0 0 1 6 6v22H0z" fill="'.$c['bar'].'"/>'
		.'<text x="12" y="19" font-weight="bold" fill="'.$c['bar-text'].'">'.$title.'</text>'
		.'<rect x="12" y="38" width="326" height="66" rx="4" fill="'.$c['surface'].'" stroke="'.$c['border'].'"/>'
		.'<text x="22" y="57" fill="'.$c['text'].'">Text, <tspan fill="'.$c['muted'].'">muted text</tspan> and <tspan fill="'.$c['link'].'" text-decoration="underline">a link</tspan></text>'
		.'<text x="22" y="76" font-family="ui-monospace, Menlo, Consolas, monospace" fill="'.$c['text'].'">'
		.'<tspan fill="'.$c['code-keyword'].'">return</tspan> <tspan fill="'.$c['code-string'].'">\'hello\'</tspan>; <tspan fill="'.$c['code-comment'].'">// a comment</tspan></text>'
		.'<rect x="22" y="84" width="50" height="14" rx="3" fill="'.$c['error'].'"/>'
		.'<text x="47" y="95" font-size="10" text-anchor="middle" fill="'.$c['error-text'].'">Error</text>'
		.'<rect x="78" y="84" width="50" height="14" rx="3" fill="'.$c['notice'].'"/>'
		.'<text x="103" y="95" font-size="10" text-anchor="middle" fill="'.$c['notice-text'].'">Notice</text>';

	foreach (SWATCHES as $i => $name)
	{
		$svg .= '<rect x="'.(12 + $i * 23.5).'" y="114" width="20" height="24" rx="3" fill="'.$c[$name].'" stroke="'.$c['border'].'"><title>'.$name.'</title></rect>';
	}

	return $svg.'</g>';
}

$directory = SYSPATH.'media/guide/kohana/themes/';
is_dir($directory) or mkdir($directory, 0755, true);

$default = $config['themes']['default'];

foreach ($config['themes'] as $name => $theme)
{
	// A colour that a theme does not name is the one of the default theme
	$light = ($theme['light'] ?? []) + ($default['light'] ?? []);
	$dark = ($theme['dark'] ?? []) + ($default['dark'] ?? []);

	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-1 -1 722 152" width="720" font-family="system-ui, sans-serif" font-size="12">'
		.panel($name.' · light', $light, 0)
		.panel($name.' · dark', $dark, 370)
		.'</svg>'."\n";

	file_put_contents($directory.$name.'.svg', $svg);
	echo $name.".svg\n";
}
