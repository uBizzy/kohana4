<?php
/**
 * The bootstrap of the application: runs once for every request, before
 * the request is handled. It sets up the environment, starts the
 * framework, enables the modules and defines the routes.
 *
 * See "Bootstrap" and "Installation" in the user guide.
 */

// -- Environment setup --------------------------------------------------------

// The core of the framework, and the class of the application that extends it
require SYSPATH.'classes/Kohana/Core'.EXT;

if (is_file(APPPATH.'classes/Kohana'.EXT))
{
	// The application extends the core
	require APPPATH.'classes/Kohana'.EXT;
}
else
{
	// The empty extension of the framework
	require SYSPATH.'classes/Kohana'.EXT;
}

/**
 * The time zone of the site, as in "Europe/Lisbon".
 *
 * @link https://www.php.net/manual/timezones
 */
date_default_timezone_set('UTC');

/**
 * The locale of the site.
 *
 * @link https://www.php.net/manual/function.setlocale
 */
setlocale(LC_ALL, 'en_US.utf-8');

/**
 * Classes are loaded by their name: Model_Article is classes/Model/Article.php.
 * See "Loading Classes" in the user guide.
 */
spl_autoload_register(['Kohana', 'auto_load']);

// Classes are also loaded when an object is read from a session or a cache
ini_set('unserialize_callback_func', 'spl_autoload_call');

// The libraries of Composer, when the project has any
if (is_file(DOCROOT.'vendor/autoload.php'))
{
	require DOCROOT.'vendor/autoload.php';
}

// A character that cannot be converted is left out, not replaced by "?"
mb_substitute_character('none');

// -- Configuration and initialization -----------------------------------------

/**
 * The language of the site: the texts of application/i18n/.
 */
I18n::lang('en-us');

if (isset($_SERVER['SERVER_PROTOCOL']))
{
	// Answer with the protocol of the request
	HTTP::$protocol = $_SERVER['SERVER_PROTOCOL'];
}

/**
 * The environment: development unless the server says otherwise.
 *
 *     SetEnv KOHANA_ENV production            (Apache)
 *     fastcgi_param KOHANA_ENV production;    (nginx)
 *
 * In production a visitor never sees an error, only a reference code;
 * the guide and the other development tools are off.
 */
if (isset($_SERVER['KOHANA_ENV']))
{
	Kohana::$environment = constant('Kohana::'.strtoupper($_SERVER['KOHANA_ENV']));
}

/**
 * Starts the framework. The settings, with their defaults:
 *
 * - string       base_url    path of the site, and optionally its domain          '/'
 * - string|false index_file  name of the front file; false for clean URLs         'index.php'
 * - string       charset     character set of input and output                    'utf-8'
 * - string       cache_dir   directory of the internal cache                      APPPATH/cache
 * - int          cache_life  seconds an internal cache entry is kept              60
 * - bool         errors      show errors with the pages of the framework          true
 * - bool         profile     measure the request                                  true
 * - bool         caching     remember where files are                             false
 * - bool         expose      send the X-Powered-By header                         false
 *
 * In production the framework remembers where files are, which saves
 * looking for them at every request, and does not measure the request.
 * Empty application/cache after a deploy that adds or moves files.
 */
Kohana::init([
	'base_url' => '/',
	'caching'  => Kohana::$environment === Kohana::PRODUCTION,
	'profile'  => Kohana::$environment !== Kohana::PRODUCTION,
]);

/**
 * Where the log goes. Several writers can be attached.
 */
Kohana::$log->attach(new Log_File(APPPATH.'logs'));

/**
 * Where settings are read from: the config/ directories.
 */
Kohana::$config->attach(new Config_File);

/**
 * The modules of the application. A module that is not enabled costs nothing.
 */
Kohana::modules([
	// 'auth'       => MODPATH.'auth',       // Logins
	// 'cache'      => MODPATH.'cache',      // Caches: file, SQLite, APCu, Memcached, Redis
	// 'codebench'  => MODPATH.'codebench',  // Compares the speed of code (development)
	// 'database'   => MODPATH.'database',   // Database connections and the query builder
	// 'encrypt'    => MODPATH.'encrypt',    // Encryption
	// 'image'      => MODPATH.'image',      // Resizing and converting images
	// 'ko7'        => MODPATH.'ko7',        // The KO7_* class names of Koseven
	// 'ko9'        => MODPATH.'ko9',        // The KO9_* class names
	// 'minion'     => MODPATH.'minion',     // Tasks for the command line
	// 'migration'  => MODPATH.'migration',  // Database changes as files (needs database and minion)
	// 'orm'        => MODPATH.'orm',        // Models
	// 'pagination' => MODPATH.'pagination', // Long lists over several pages
	// 'unittest'   => MODPATH.'unittest',   // PHPUnit (development)
	// 'userguide'  => MODPATH.'userguide',  // The guide and the API browser (development)

	// Extra modules, installed from https://github.com/uBizzy/kohana4-modules
	// 'ub_debug'   => MODPATH.'ub_debug',   // Debug bar (development)
]);

// -- Security -----------------------------------------------------------------

/**
 * The key that signs cookies. It is a secret, so it is not written here:
 * set "cookie_salt" in application/config/app.php (a copy of
 * system/config/app.php). Without it no cookie can be set.
 */
Cookie::$salt = Kohana::$config->load('app')->get('cookie_salt');

/**
 * Cookies are safe by default: not readable from JavaScript ($httponly),
 * HTTPS-only whenever the request is HTTPS ($secure), and not sent with
 * requests that start on another site ($samesite = 'Lax').
 *
 * Once the whole site is on HTTPS:
 */
// Cookie::$secure = true;

/**
 * The Content-Security-Policy: what a page may load and run. With this
 * one, everything comes from the site itself, and a script or style that
 * is written in the page must carry the nonce of the request:
 *
 *     <script nonce="<?php echo HTML::chars(Security::nonce()) ?>">
 *
 * A script that an attacker manages to get into a page does not run.
 * Add what the site really uses (a CDN, a font service) to the lists,
 * and see "Security headers" in the user guide.
 */
HTTP::$security_headers['content-security-policy'] =
	"default-src 'self'; script-src 'self' 'nonce-{nonce}'; style-src 'self' 'nonce-{nonce}'; img-src 'self' data:";

/**
 * Browsers must use HTTPS for the next year. Only for a site that is
 * fully on HTTPS, and set in the web server too (see
 * public/example.htaccess): files it serves itself do not pass here.
 */
// HTTP::$security_headers['strict-transport-security'] = 'max-age=31536000';

// -- Routes -------------------------------------------------------------------

/**
 * Which controller and action answer an address. The first route that
 * matches is used: put your own before this one. See "Routing" in the
 * user guide.
 */
Route::set('default', '(<controller>(/<action>(/<id>)))')
	->defaults([
		'controller' => 'welcome',
		'action'     => 'index',
	]);
