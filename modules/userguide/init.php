<?php
/**
 * Routes of the user guide. They are only set when the guide is enabled
 * (see config/userguide.php): by default, not in production.
 */
if ( ! Kohana::$config->load('userguide.enabled'))
	return;

// Images, style sheet and script: files in the "media/guide" directories
Route::set('docs/media', 'guide-media(/<file>)', ['file' => '.+'])
	->defaults([
		'controller' => 'Userguide',
		'action'     => 'media',
		'file'       => null,
	]);

// What the search box searches in: all pages and classes, as JSON
Route::set('docs/search', 'guide-search')
	->defaults([
		'controller' => 'Userguide',
		'action'     => 'search',
	]);

// API browser, if enabled
if (Kohana::$config->load('userguide.api_browser') === true)
{
	Route::set('docs/api', 'guide-api(/<class>)', ['class' => '[a-zA-Z0-9_]+'])
		->defaults([
			'controller' => 'Userguide',
			'action'     => 'api',
			'class'      => null,
		]);
}

// Guide pages of the modules
Route::set('docs/guide', 'guide(/<module>(/<page>))', ['page' => '.+'])
	->defaults([
		'controller' => 'Userguide',
		'action'     => 'docs',
		'module'     => '',
	]);
