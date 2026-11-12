<?php
/**
 * KO9 compatibility module.
 *
 * Lets code written for Konine keep using the "KO9" names: the KO9
 * class, the KO9_* classes and interfaces, and the KO9_START_* constants.
 *
 * It works with class aliases, not with extra classes: KO9_Arr IS
 * Kohana_Arr under a second name. That has three advantages over a file
 * with "class KO9_Arr extends Kohana_Arr {}" for every class:
 *
 * - it costs nothing until a KO9 name is actually used;
 * - it covers every class of the framework and of all modules, also the
 *   ones added later, without anything to keep up to date;
 * - type checks work both ways: an object the framework creates passes
 *   "instanceof KO9_Request" and a "KO9_Request $request" parameter.
 *
 * Enable it in bootstrap.php:
 *
 *     'ko9' => MODPATH.'ko9',
 *
 * @package    Kohana/KO9
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */

/**
 * Autoloader for the KO9 names. It is registered after the Kohana
 * autoloader, so a class that really exists under a KO9 name (one of the
 * application's own, for example) is always loaded from its file first.
 *
 * The Kohana name is found by replacing each "KO9" part of the name:
 *
 *     KO9                  => Kohana
 *     KO9_Arr              => Kohana_Arr
 *     KO9_KO9_Exception    => Kohana_Kohana_Exception
 */
spl_autoload_register(function (string $class): void
{
	// Quick exit for every name this module is not about
	if ( ! str_starts_with($class, 'KO9'))
		return;

	$parts = explode('_', $class);

	foreach ($parts as $i => $part)
	{
		if ($part === 'KO9')
		{
			$parts[$i] = 'Kohana';
		}
	}

	$kohana_class = implode('_', $parts);

	// class_exists() and interface_exists() load the Kohana class if needed
	if ($kohana_class !== $class AND (class_exists($kohana_class) OR interface_exists($kohana_class)))
	{
		class_alias($kohana_class, $class);
	}
});

/**
 * The start time and memory constants under their KO9 names.
 */
if ( ! defined('KO9_START_TIME'))
{
	define('KO9_START_TIME', KOHANA_START_TIME);
}

if ( ! defined('KO9_START_MEMORY'))
{
	define('KO9_START_MEMORY', KOHANA_START_MEMORY);
}

/**
 * Set Kohana::$environment from the 'KONINE_ENV' environment variable, when
 * the server still uses that name and 'KOHANA_ENV' is not set.
 */
$env = $_SERVER['KONINE_ENV'] ?? null;

if (is_string($env) AND ! isset($_SERVER['KOHANA_ENV']) AND defined('Kohana::'.strtoupper($env)))
{
	/** @var int $environment */
	$environment = constant('Kohana::'.strtoupper($env));

	Kohana::$environment = $environment;
}

unset($env, $environment);

/**
 * An application that has its own error page in views/ko9/error.php keeps it.
 */
if (Kohana::find_file('views', 'ko9/error'))
{
	Kohana_Exception::$error_view = 'ko9/error';
}
