<?php
/**
 * Route of the benchmarks. Codebench runs code on request, so the route is
 * not set in production.
 */
if (Kohana::$environment === Kohana::PRODUCTION)
	return;

// codebench            the list of benchmarks
// codebench/<name>     runs classes/Bench/<Name>.php
Route::set('codebench', 'codebench(/<class>)', ['class' => '[A-Za-z0-9_]+'])
	->defaults([
		'controller' => 'Codebench',
		'action'     => 'index',
		'class'      => null,
	]);
