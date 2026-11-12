<?php
/**
 * KO7 compatibility module.
 *
 * Lets code written for Koseven keep using the "KO7" names: the KO7
 * class, the KO7_* classes and interfaces, and the KO7_START_* constants.
 *
 * It works with class aliases, not with extra classes: KO7_Arr IS
 * Kohana_Arr under a second name. That has three advantages over a file
 * with "class KO7_Arr extends Kohana_Arr {}" for every class:
 *
 * - it costs nothing until a KO7 name is actually used;
 * - it covers every class of the framework and of all modules, also the
 *   ones added later, without anything to keep up to date;
 * - type checks work both ways: an object the framework creates passes
 *   "instanceof KO7_Request" and a "KO7_Request $request" parameter.
 *
 * Enable it in bootstrap.php:
 *
 *     'ko7' => MODPATH.'ko7',
 *
 * @package    Kohana/KO7
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */

/**
 * Autoloader for the KO7 names. It is registered after the Kohana
 * autoloader, so a class that really exists under a KO7 name (one of the
 * application's own, for example) is always loaded from its file first.
 *
 * The Kohana name is found by replacing each "KO7" part of the name:
 *
 *     KO7                  => Kohana
 *     KO7_Arr              => Kohana_Arr
 *     KO7_KO7_Exception    => Kohana_Kohana_Exception
 */
spl_autoload_register(function (string $class): void
{
	// Quick exit for every name this module is not about
	if ( ! str_starts_with($class, 'KO7'))
		return;

	$parts = explode('_', $class);

	foreach ($parts as $i => $part)
	{
		if ($part === 'KO7')
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
 * The start time and memory constants under their KO7 names.
 */
if ( ! defined('KO7_START_TIME'))
{
	define('KO7_START_TIME', KOHANA_START_TIME);
}

if ( ! defined('KO7_START_MEMORY'))
{
	define('KO7_START_MEMORY', KOHANA_START_MEMORY);
}

/**
 * Set Kohana::$environment from the 'KOSEVEN_ENV' environment variable, when
 * the server still uses that name and 'KOHANA_ENV' is not set.
 */
$env = $_SERVER['KOSEVEN_ENV'] ?? null;

if (is_string($env) AND ! isset($_SERVER['KOHANA_ENV']) AND defined('Kohana::'.strtoupper($env)))
{
	/** @var int $environment */
	$environment = constant('Kohana::'.strtoupper($env));

	Kohana::$environment = $environment;
}

unset($env, $environment);

/**
 * An application that has its own error page in views/ko7/error.php keeps it.
 */
if (Kohana::find_file('views', 'ko7/error'))
{
	Kohana_Exception::$error_view = 'ko7/error';
}
