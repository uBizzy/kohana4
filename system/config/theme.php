<?php
/**
 * The look of what the framework itself prints: the error pages, the
 * notices and the profiler, the user guide, Codebench, the install page
 * and the extra modules (debug bar, log reader). The pages of your
 * application are not touched.
 *
 * To change something, copy the keys you want to
 * application/config/theme.php: the rest keeps the values of this file.
 *
 *     return ['theme' => 'dracula', 'mode' => 'dark'];
 */
return [
	/**
	 * The theme: one of the names of 'themes' below.
	 *
	 *     default, catppuccin, dracula, nord, solarized, gruvbox, tokyo-night, one
	 *
	 * A name that is not there gives the default theme.
	 */
	'theme' => 'default',

	/**
	 * Which side of the theme is used:
	 *
	 * - 'auto'   follows the system of the visitor (light or dark)
	 * - 'light'  always the light colours of the theme
	 * - 'dark'   always the dark colours of the theme
	 */
	'mode' => 'auto',

	/**
	 * Show a button that switches between auto, light and dark. The
	 * choice is kept in the browser of the visitor and wins over 'mode'.
	 */
	'button' => true,

	/**
	 * Fonts of the text and of code, as written in CSS.
	 */
	'font'      => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
	'font_code' => 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',

	/**
	 * How round corners are: '0' for square ones.
	 */
	'radius' => '4px',

	/**
	 * Your own colours, on top of the theme that is chosen: only the
	 * ones you want to change.
	 *
	 *     'light' => ['link' => '#1d4ed8'],
	 */
	'light' => [],
	'dark'  => [],

	/**
	 * The themes. Each has light colours and dark colours; a colour that
	 * a theme does not name is taken from the default theme. Add a theme
	 * of your own under a new name.
	 *
	 * A colour is written as #rgb, #rrggbb (with or without
	 * transparency) or rgb(...) / hsl(...).
	 */
	'themes' => [
		// The look of the framework: pastel tones on a warm off-white, or
		// the same tones, quieter, on a warm dark grey
		'default' => [
			'light' => [
				'bg'           => '#f6f3ef',   // page background, warm off-white
				'surface'      => '#fffdfa',   // panels, tables and code
				'text'         => '#33383b',
				'muted'        => '#646b70',   // less important text
				'border'       => '#e3ddd5',
				'link'         => '#356a8f',
				'bar'          => '#cdd9e0',   // blue-grey: headers and section bars
				'bar-text'     => '#2c3e47',
				'error'        => '#efb8b3',   // red: the error itself
				'error-text'   => '#5a2622',
				'cause'        => '#f3d2b3',   // orange: causes of an error, warnings
				'cause-text'   => '#5c3a17',
				'app'          => '#d7ead3',   // green: your own code, what is current, success
				'app-text'     => '#274a2b',
				'highlight'    => '#fbf1b8',   // yellow: the line of an error, notes
				'notice'       => '#dcd3ec',   // violet: notices and deprecations
				'notice-text'  => '#3d3160',
				'chart-1'      => '#a9cfe6',   // bars of the profiler and the timeline
				'chart-2'      => '#efc7a0',
				'chart-3'      => '#b9dcb2',
				'chart-4'      => '#cdbfe6',
				'code-keyword' => '#7a4f9a',   // highlighted code
				'code-string'  => '#3f7a4d',
				'code-comment' => '#666d72',
				'glass'        => 'rgb(255 255 255 / 0.55)',   // buttons and badges on a coloured bar
				'edge'         => 'rgb(0 0 0 / 0.2)',          // their borders
				'shadow'       => 'rgb(0 0 0 / 0.14)',
			],
			'dark' => [
				'bg'           => '#1e2022',
				'surface'      => '#272a2c',
				'text'         => '#e4e0d9',
				'muted'        => '#a2a8ac',
				'border'       => '#3b3f42',
				'link'         => '#93c2e2',
				'bar'          => '#33434c',
				'bar-text'     => '#d7e4ea',
				'error'        => '#5e302d',
				'error-text'   => '#f7d6d2',
				'cause'        => '#59412a',
				'cause-text'   => '#f4dec6',
				'app'          => '#2c4331',
				'app-text'     => '#d3ebd0',
				'highlight'    => '#4a4322',
				'notice'       => '#40375a',
				'notice-text'  => '#dfd6f3',
				'chart-1'      => '#4b7c9b',
				'chart-2'      => '#a2733f',
				'chart-3'      => '#56875a',
				'chart-4'      => '#7a69a3',
				'code-keyword' => '#cba9e4',
				'code-string'  => '#a2d2ab',
				'code-comment' => '#959b9f',
				'glass'        => 'rgb(255 255 255 / 0.1)',
				'edge'         => 'rgb(255 255 255 / 0.2)',
				'shadow'       => 'rgb(0 0 0 / 0.5)',
			],
		],

		// Catppuccin: "Latte" when light, "Mocha" when dark
		'catppuccin' => [
			'light' => [
				'bg'           => '#e6e9ef',
				'surface'      => '#eff1f5',
				'text'         => '#4c4f69',
				'muted'        => '#62657d',
				'border'       => '#ccd0da',
				'link'         => '#1b5cdc',
				'bar'          => '#b0c7f5',
				'bar-text'     => '#4c4f69',
				'error'        => '#e8bbc8',
				'error-text'   => '#4c4f69',
				'cause'        => '#f4c7af',
				'cause-text'   => '#4c4f69',
				'app'          => '#bad9b8',
				'app-text'     => '#4c4f69',
				'highlight'    => '#ead3b4',
				'notice'       => '#d2bdf3',
				'notice-text'  => '#4c4f69',
				'chart-1'      => '#6494f3',
				'chart-2'      => '#f6935b',
				'chart-3'      => '#7aba70',
				'chart-4'      => '#a977ef',
				'code-keyword' => '#8136e3',
				'code-string'  => '#2d701e',
				'code-comment' => '#62657c',
			],
			'dark' => [
				'bg'           => '#181825',
				'surface'      => '#1e1e2e',
				'text'         => '#cdd6f4',
				'muted'        => '#a6adc8',
				'border'       => '#313244',
				'link'         => '#89b4fa',
				'bar'          => '#425173',
				'bar-text'     => '#cdd6f4',
				'error'        => '#664357',
				'error-text'   => '#cdd6f4',
				'cause'        => '#69514c',
				'cause-text'   => '#cdd6f4',
				'app'          => '#4c6155',
				'app-text'     => '#cdd6f4',
				'highlight'    => '#605955',
				'notice'       => '#594c72',
				'notice-text'  => '#cdd6f4',
				'chart-1'      => '#617daf',
				'chart-2'      => '#ab7d65',
				'chart-3'      => '#749c76',
				'chart-4'      => '#8c74ae',
				'code-keyword' => '#cba6f7',
				'code-string'  => '#a6e3a1',
				'code-comment' => '#8388a0',
			],
		],

		// Dracula: "Alucard" when light, "Dracula" when dark
		'dracula' => [
			'light' => [
				'bg'           => '#f5f1e0',
				'surface'      => '#fffbeb',
				'text'         => '#1f1f1f',
				'muted'        => '#6c664b',
				'border'       => '#e0dcc8',
				'link'         => '#036a96',
				'bar'          => '#b3d0d2',
				'bar-text'     => '#1f1f1f',
				'error'        => '#efc1b1',
				'error-text'   => '#1f1f1f',
				'cause'        => '#e3c7aa',
				'cause-text'   => '#1f1f1f',
				'app'          => '#b8d2a8',
				'app-text'     => '#1f1f1f',
				'highlight'    => '#dad1ab',
				'notice'       => '#d0c6e1',
				'notice-text'  => '#1f1f1f',
				'chart-1'      => '#5899b0',
				'chart-2'      => '#c0865b',
				'chart-3'      => '#639e55',
				'chart-4'      => '#9784d1',
				'code-keyword' => '#644ac9',
				'code-string'  => '#14710a',
				'code-comment' => '#6c664b',
			],
			'dark' => [
				'bg'           => '#21222c',
				'surface'      => '#282a36',
				'text'         => '#f8f8f2',
				'muted'        => '#9aa5ce',
				'border'       => '#44475a',
				'link'         => '#8be9fd',
				'bar'          => '#4a6b7a',
				'bar-text'     => '#f8f8f2',
				'error'        => '#713941',
				'error-text'   => '#f8f8f2',
				'cause'        => '#715a48',
				'cause-text'   => '#f8f8f2',
				'app'          => '#36714d',
				'app-text'     => '#f8f8f2',
				'highlight'    => '#6c7153',
				'notice'       => '#5b4e78',
				'notice-text'  => '#f8f8f2',
				'chart-1'      => '#66a3b4',
				'chart-2'      => '#b18456',
				'chart-3'      => '#40ae5f',
				'chart-4'      => '#866bb1',
				'code-keyword' => '#bd93f9',
				'code-string'  => '#50fa7b',
				'code-comment' => '#8894b8',
			],
		],

		// Nord: "Snow Storm" when light, "Polar Night" when dark
		'nord' => [
			'light' => [
				'bg'           => '#e5e9f0',
				'surface'      => '#eceff4',
				'text'         => '#2e3440',
				'muted'        => '#4c566a',
				'border'       => '#d8dee9',
				'link'         => '#4b678a',
				'bar'          => '#c1cede',
				'bar-text'     => '#2e3440',
				'error'        => '#dec4cb',
				'error-text'   => '#2e3440',
				'cause'        => '#e4d0cc',
				'cause-text'   => '#2e3440',
				'app'          => '#d6e0d5',
				'app-text'     => '#2e3440',
				'highlight'    => '#ece4d4',
				'notice'       => '#dbd2df',
				'notice-text'  => '#2e3440',
				'chart-1'      => '#8da5c4',
				'chart-2'      => '#d7a99d',
				'chart-3'      => '#bacdaf',
				'chart-4'      => '#c5aec4',
				'code-keyword' => '#755c70',
				'code-string'  => '#5a694d',
				'code-comment' => '#4c566a',
			],
			'dark' => [
				'bg'           => '#2e3440',
				'surface'      => '#3b4252',
				'text'         => '#eceff4',
				'muted'        => '#d8dee9',
				'border'       => '#4c566a',
				'link'         => '#88c0d0',
				'bar'          => '#556d7d',
				'bar-text'     => '#eceff4',
				'error'        => '#684d5a',
				'error-text'   => '#eceff4',
				'cause'        => '#6e595c',
				'cause-text'   => '#eceff4',
				'app'          => '#5e6c66',
				'app-text'     => '#eceff4',
				'highlight'    => '#6c6862',
				'notice'       => '#645c71',
				'notice-text'  => '#eceff4',
				'chart-1'      => '#688f9e',
				'chart-2'      => '#976a5f',
				'chart-3'      => '#7a8e71',
				'chart-4'      => '#856e87',
				'code-keyword' => '#c7aac2',
				'code-string'  => '#a3be8c',
				'code-comment' => '#9cb4ce',
			],
		],

		// Solarized: "Light" when light, "Dark" when dark
		'solarized' => [
			'light' => [
				'bg'           => '#eee8d5',
				'surface'      => '#fdf6e3',
				'text'         => '#54686f',
				'muted'        => '#566a71',
				'border'       => '#eee8d5',
				'link'         => '#1c689e',
				'bar'          => '#dfe7e1',
				'bar-text'     => '#54686f',
				'error'        => '#fae2d1',
				'error-text'   => '#54686f',
				'cause'        => '#f7e1ca',
				'cause-text'   => '#54686f',
				'app'          => '#eae7bf',
				'app-text'     => '#54686f',
				'highlight'    => '#f1e5bf',
				'notice'       => '#e9e3df',
				'notice-text'  => '#54686f',
				'chart-1'      => '#6cacd3',
				'chart-2'      => '#d78259',
				'chart-3'      => '#aab54b',
				'chart-4'      => '#9a9bca',
				'code-keyword' => '#5c60a7',
				'code-string'  => '#5d6b00',
				'code-comment' => '#54686f',
			],
			'dark' => [
				'bg'           => '#002b36',
				'surface'      => '#073642',
				'text'         => '#93a1a1',
				'muted'        => '#8e9d9e',
				'border'       => '#083946',
				'link'         => '#51a2db',
				'bar'          => '#083948',
				'bar-text'     => '#93a1a1',
				'error'        => '#2d353f',
				'error-text'   => '#93a1a1',
				'cause'        => '#1b383e',
				'cause-text'   => '#93a1a1',
				'app'          => '#0c3a3f',
				'app-text'     => '#93a1a1',
				'highlight'    => '#0e393f',
				'notice'       => '#0b3847',
				'notice-text'  => '#93a1a1',
				'chart-1'      => '#19699b',
				'chart-2'      => '#844021',
				'chart-3'      => '#567213',
				'chart-4'      => '#465892',
				'code-keyword' => '#989cd6',
				'code-string'  => '#91a31a',
				'code-comment' => '#909e9f',
			],
		],

		// Gruvbox: "Light" when light, "Dark" when dark
		'gruvbox' => [
			'light' => [
				'bg'           => '#ebdbb2',
				'surface'      => '#fbf1c7',
				'text'         => '#3c3836',
				'muted'        => '#665c54',
				'border'       => '#d5c4a1',
				'link'         => '#076678',
				'bar'          => '#b2c7af',
				'bar-text'     => '#3c3836',
				'error'        => '#dfa98d',
				'error-text'   => '#3c3836',
				'cause'        => '#e4ba8c',
				'cause-text'   => '#3c3836',
				'app'          => '#d4cc90',
				'app-text'     => '#3c3836',
				'highlight'    => '#e6cc91',
				'notice'       => '#dbbcad',
				'notice-text'  => '#3c3836',
				'chart-1'      => '#578f8c',
				'chart-2'      => '#c47240',
				'chart-3'      => '#a19847',
				'chart-4'      => '#af7688',
				'code-keyword' => '#8f3f71',
				'code-string'  => '#615d0b',
				'code-comment' => '#665c54',
			],
			'dark' => [
				'bg'           => '#1d2021',
				'surface'      => '#282828',
				'text'         => '#ebdbb2',
				'muted'        => '#bdae93',
				'border'       => '#3c3836',
				'link'         => '#83a598',
				'bar'          => '#47524e',
				'bar-text'     => '#ebdbb2',
				'error'        => '#70332c',
				'error-text'   => '#ebdbb2',
				'cause'        => '#714623',
				'cause-text'   => '#ebdbb2',
				'app'          => '#595a27',
				'app-text'     => '#ebdbb2',
				'highlight'    => '#6f5b2a',
				'notice'       => '#62484f',
				'notice-text'  => '#ebdbb2',
				'chart-1'      => '#5f766e',
				'chart-2'      => '#af5e1c',
				'chart-3'      => '#828524',
				'chart-4'      => '#936270',
				'code-keyword' => '#d3869b',
				'code-string'  => '#b8bb26',
				'code-comment' => '#9f907d',
			],
		],

		// Tokyo Night: "Day" when light, "Night" when dark
		'tokyo-night' => [
			'light' => [
				'bg'           => '#d0d5e3',
				'surface'      => '#e1e2e7',
				'text'         => '#3256ac',
				'muted'        => '#3457ac',
				'border'       => '#d0d5e3',
				'link'         => '#2058a3',
				'bar'          => '#ccd6e7',
				'bar-text'     => '#3256ac',
				'error'        => '#e3d0da',
				'error-text'   => '#3256ac',
				'cause'        => '#dbd2cb',
				'cause-text'   => '#3256ac',
				'app'          => '#d1d5d2',
				'app-text'     => '#3256ac',
				'highlight'    => '#d7d4d3',
				'notice'       => '#d8d1e8',
				'notice-text'  => '#3256ac',
				'chart-1'      => '#679ce7',
				'chart-2'      => '#bc864f',
				'chart-3'      => '#829774',
				'chart-4'      => '#ac81ec',
				'code-keyword' => '#723fb5',
				'code-string'  => '#465e2e',
				'code-comment' => '#3256ac',
			],
			'dark' => [
				'bg'           => '#16161e',
				'surface'      => '#1a1b26',
				'text'         => '#c0caf5',
				'muted'        => '#a9b1d6',
				'border'       => '#292e42',
				'link'         => '#7aa2f7',
				'bar'          => '#3b496d',
				'bar-text'     => '#c0caf5',
				'error'        => '#653a49',
				'error-text'   => '#c0caf5',
				'cause'        => '#68483b',
				'cause-text'   => '#c0caf5',
				'app'          => '#47583d',
				'app-text'     => '#c0caf5',
				'highlight'    => '#5d4d3c',
				'notice'       => '#51466d',
				'notice-text'  => '#c0caf5',
				'chart-1'      => '#5771ab',
				'chart-2'      => '#ad6e4c',
				'chart-3'      => '#6e8e4f',
				'chart-4'      => '#816cab',
				'code-keyword' => '#bb9af7',
				'code-string'  => '#9ece6a',
				'code-comment' => '#7b84af',
			],
		],

		// One: "One Light" when light, "One Dark" when dark
		'one' => [
			'light' => [
				'bg'           => '#f0f0f0',
				'surface'      => '#fafafa',
				'text'         => '#383a42',
				'muted'        => '#676a74',
				'border'       => '#e5e5e6',
				'link'         => '#3666ce',
				'bar'          => '#c2d3f8',
				'bar-text'     => '#383a42',
				'error'        => '#f3c9c5',
				'error-text'   => '#383a42',
				'cause'        => '#ddceaf',
				'cause-text'   => '#383a42',
				'app'          => '#c7dfc7',
				'app-text'     => '#383a42',
				'highlight'    => '#e9d7af',
				'notice'       => '#e1bae0',
				'notice-text'  => '#383a42',
				'chart-1'      => '#7ea2f1',
				'chart-2'      => '#b79855',
				'chart-3'      => '#88bd87',
				'chart-4'      => '#c06dbf',
				'code-keyword' => '#a626a4',
				'code-string'  => '#3c793b',
				'code-comment' => '#67686f',
			],
			'dark' => [
				'bg'           => '#21252b',
				'surface'      => '#282c34',
				'text'         => '#b3bac5',
				'muted'        => '#9da5b4',
				'border'       => '#3a3f4b',
				'link'         => '#61afef',
				'bar'          => '#364b61',
				'bar-text'     => '#b3bac5',
				'error'        => '#5f3f48',
				'error-text'   => '#b3bac5',
				'cause'        => '#514640',
				'cause-text'   => '#b3bac5',
				'app'          => '#3e4a42',
				'app-text'     => '#b3bac5',
				'highlight'    => '#4a4741',
				'notice'       => '#544163',
				'notice-text'  => '#b3bac5',
				'chart-1'      => '#4b7faa',
				'chart-2'      => '#937151',
				'chart-3'      => '#6e8c5e',
				'chart-4'      => '#8c5b9f',
				'code-keyword' => '#c678dd',
				'code-string'  => '#98c379',
				'code-comment' => '#9097a3',
			],
		],
	],
];
