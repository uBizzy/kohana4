<?php

/**
 * The directory in which your application specific resources are located.
 * The application directory must contain the bootstrap.php file.
 */
$application = 'application';

/**
 * The directory in which your modules are located.
 */
$modules = 'modules';

/**
 * The directory in which the Kohana resources are located. The system
 * directory must contain the classes/kohana.php file.
 */
$system = 'system';

/**
 * The directory in which the Kohana public files are located. The public
 * directory contains for example the index.php and .htaccess files.
 */
$public = 'public';

/**
 * The default extension of resource files. If you change this, all resources
 * must be renamed to use the new extension.
 */
define('EXT', '.php');

/**
 * Set the path to the document root
 *
 * This assumes that this file is stored 2 levels below the DOCROOT, if you move
 * this bootstrap file somewhere else then you'll need to modify this value to
 * compensate.
 */
define('DOCROOT', dirname(__DIR__, 2).DIRECTORY_SEPARATOR);

/**
 * Set the PHP error reporting level. If you set this in php.ini, you remove this.
 * @link http://www.php.net/manual/errorfunc.configuration#ini.error-reporting
 *
 * When developing your application, it is highly recommended to enable notices
 * and warnings. Enable them by using: E_ALL
 *
 * In a production environment, it is safe to ignore notices. Disable them by
 * using: E_ALL & ~E_NOTICE
 *
 * When using a legacy application, it is recommended to disable deprecated
 * notices. Disable with: E_ALL & ~E_DEPRECATED
 */
error_reporting(E_ALL);

/**
 * End of standard configuration! Changing any of the code below should only be
 * attempted by those with a working knowledge of Kohana internals.
 */

// Make the application relative to the docroot
if ( ! is_dir($application) && is_dir(DOCROOT.$application))
{
	$application = DOCROOT.$application;
}

// Make the modules relative to the docroot
if ( ! is_dir($modules) && is_dir(DOCROOT.$modules))
{
	$modules = DOCROOT.$modules;
}

// Make the system relative to the docroot
if ( ! is_dir($system) && is_dir(DOCROOT.$system))
{
	$system = DOCROOT.$system;
}

// Make the public relative to the docroot
if ( ! is_dir($public) && is_dir(DOCROOT.$public))
{
	$public = DOCROOT.$public;
}

// Define the absolute paths for configured directories
define('APPPATH', realpath($application).DIRECTORY_SEPARATOR);
define('MODPATH', realpath($modules).DIRECTORY_SEPARATOR);
define('SYSPATH', realpath($system).DIRECTORY_SEPARATOR);
define('PUBPATH', realpath($public).DIRECTORY_SEPARATOR);

// Clean up the configuration vars
unset($application, $modules, $system, $public);

/**
 * Define the start time of the application, used for profiling.
 */
if ( ! defined('KOHANA_START_TIME'))
{
	define('KOHANA_START_TIME', microtime(true));
}

/**
 * Define the memory usage at the start of the application, used for profiling.
 */
if ( ! defined('KOHANA_START_MEMORY'))
{
	define('KOHANA_START_MEMORY', memory_get_usage());
}

// Bootstrap the application
require APPPATH.'bootstrap'.EXT;

// Disable output buffering
if (($ob_len = ob_get_length()) !== false)
{
	// flush_end on an empty buffer causes headers to be sent. Only flush if needed.
	if ($ob_len > 0)
	{
		ob_end_flush();
	}
	else
	{
		ob_end_clean();
	}
}

// Enable all modules we can find
$modules_iterator = new DirectoryIterator(MODPATH);

$modules = [];

foreach ($modules_iterator as $module)
{
	if ($module->isDir() AND ! $module->isDot())
	{
		$modules[$module->getFilename()] = MODPATH.$module->getFilename();
	}
}

// Modules kept elsewhere are tested with a bootstrap of their own, which
// names them in $unittest_modules before it includes this file
$modules += $unittest_modules ?? [];

Kohana::modules($modules);

unset($modules_iterator, $modules, $module);

// Let PHPUnit report the errors, and load the classes shared by tests
Unittest_Tests::configure_environment();
