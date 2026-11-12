<?php

// -- Environment setup --------------------------------------------------------

// Load the core Kohana class
require SYSPATH.'classes/Kohana/Core'.EXT;

if (is_file(APPPATH.'classes/Kohana'.EXT))
{
	// Application extends the core
	require APPPATH.'classes/Kohana'.EXT;
}
else
{
	// Load empty core extension
	require SYSPATH.'classes/Kohana'.EXT;
}

/**
 * Set the default time zone.
 *
 * @link https://www.php.net/manual/timezones
 */
date_default_timezone_set('America/Chicago');

/**
 * Set the default locale.
 *
 * @link https://www.php.net/manual/function.setlocale
 */
setlocale(LC_ALL, 'en_US.utf-8');

/**
 * Enable the Kohana auto-loader.
 *
 * See "Loading Classes" in the user guide.
 *
 * @link https://www.php.net/manual/function.spl-autoload-register
 */
spl_autoload_register(['Kohana', 'auto_load']);

/**
 * Optionally, you can enable a compatibility auto-loader for use with
 * older modules that have not been updated for PSR-0.
 *
 * It is recommended to not enable this unless absolutely necessary.
 */
// spl_autoload_register(array('Kohana', 'auto_load_lowercase'));

/**
 * Enable the Kohana auto-loader for unserialization.
 *
 * @link http://www.php.net/manual/function.spl-autoload-call
 * @link http://www.php.net/manual/var.configuration#unserialize-callback-func
 */
ini_set('unserialize_callback_func', 'spl_autoload_call');

/**
 * Enable composer autoload libraries
 */
if (is_file(DOCROOT.'vendor/autoload.php'))
{
	require DOCROOT.'vendor/autoload.php';
}

/**
 * Set the mb_substitute_character to "none"
 *
 * @link http://www.php.net/manual/function.mb-substitute-character.php
 */
mb_substitute_character('none');

// -- Configuration and initialization -----------------------------------------

/**
 * Set the default language
 */
I18n::lang('en-us');

if (isset($_SERVER['SERVER_PROTOCOL']))
{
	// Replace the default protocol.
	HTTP::$protocol = $_SERVER['SERVER_PROTOCOL'];
}

/**
 * Set Kohana::$environment if a 'KOHANA_ENV' environment variable has been supplied.
 *
 * Note: If you supply an invalid environment name, a PHP warning will be thrown
 * saying "Couldn't find constant Kohana::<INVALID_ENV_NAME>"
 */
if (isset($_SERVER['KOHANA_ENV']))
{
	Kohana::$environment = constant('Kohana::'.strtoupper($_SERVER['KOHANA_ENV']));
}

/**
 * Initialize Kohana, setting the default options.
 *
 * The following options are available:
 *
 * - string   base_url    path, and optionally domain, of your application   '/'
 * - string   index_file  name of your index file, usually "index.php", if set to false uses clean URLS     index.php
 * - string   charset     internal character set used for input and output   utf-8
 * - string   cache_dir   set the internal cache directory                   APPPATH/cache
 * - integer  cache_life  lifetime, in seconds, of items cached              60
 * - boolean  errors      enable or disable error handling                   true
 * - boolean  profile     enable or disable internal profiling               true
 * - boolean  caching     enable or disable internal caching                 false
 * - boolean  expose      set the X-Powered-By header                        false
 */
Kohana::init([
	'base_url'   => '/',
]);

/**
 * Attach the file write to logging. Multiple writers are supported.
 */
Kohana::$log->attach(new Log_File(APPPATH.'logs'));

/**
 * Attach a file reader to config. Multiple readers are supported.
 */
Kohana::$config->attach(new Config_File);

/**
 * Enable modules. Modules are referenced by a relative or absolute path.
 */
$modules = array(
	// 'auth'       => MODPATH.'auth',       // Basic authentication
	// 'cache'      => MODPATH.'cache',      // Caching with multiple backends
	// 'codebench'  => MODPATH.'codebench',  // Benchmarking tool
	// 'database'   => MODPATH.'database',   // Database access
	// 'encrypt'    => MODPATH.'encrypt',    // Encryption support
	// 'image'      => MODPATH.'image',      // Image manipulation
	// 'ko7'        => MODPATH.'ko7',        // Compatibility: KO7 and KO7_* class names
	// 'ko9'        => MODPATH.'ko9',        // Compatibility: KO9 and KO9_* class names
	// 'minion'     => MODPATH.'minion',     // CLI Tasks
	// 'migration'  => MODPATH.'migration',  // Database migrations (needs database and minion)
	// 'orm'        => MODPATH.'orm',        // Object Relationship Mapping
	// 'pagination' => MODPATH.'pagination', // Pagination
	// 'unittest'   => MODPATH.'unittest',   // Unit testing
	// 'userguide'  => MODPATH.'userguide',  // User guide and API documentation

	// Extra modules, installed from https://github.com/uBizzy/kohana4-modules
	// 'ub_debug'   => MODPATH.'ub_debug',   // Debug bar (development only)
);

/**
 * Initialize Modules
 */
Kohana::modules($modules);

/**
 * Cookie Salt
 * See "Cookies" in the user guide.
 *
 * If you have not defined a cookie salt in your Cookie class then
 * uncomment the line below and define a preferrably long salt.
 */
// Cookie::$salt = null;
/**
 * Cookie security settings. The defaults are safe for most sites:
 *
 * - $httponly = true:  cookies cannot be read from JavaScript
 * - $secure   = null:  automatic, cookies are HTTPS-only whenever the request is HTTPS
 * - $samesite = 'Lax': cookies are not sent on cross-site sub-requests (CSRF defence);
 *                      use 'Strict' for more protection or 'None' for cross-site use
 *
 * Warning: HSTS must also be enabled in .htaccess, otherwise a first request
 * to http://www.example.com can still reveal cookies.
 */
// Cookie::$httponly = true;
// Cookie::$secure = true;
// Cookie::$samesite = 'Lax';

/**
 * Security headers sent with every response. Three are on by default
 * (see HTTP::$security_headers); these two depend on the site:
 */
// Browsers must use HTTPS for the next year. Only for a site that is fully on HTTPS!
// HTTP::$security_headers['strict-transport-security'] = 'max-age=31536000';

// Only run scripts from this site, or inline scripts that carry the nonce
// of the request (the value of Security::nonce() in a nonce="..." attribute)
// HTTP::$security_headers['content-security-policy'] = "default-src 'self'; script-src 'self' 'nonce-{nonce}'";

/**
 * Set the routes. Each route must have a minimum of a name, a URI and a set of
 * defaults for the URI.
 */
Route::set('default', '(<controller>(/<action>(/<id>)))')
	->defaults([
		'controller' => 'welcome',
		'action'     => 'index',
	]);
