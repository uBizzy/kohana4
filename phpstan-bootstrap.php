<?php
/**
 * Constants that public/index.php defines when the application runs.
 * PHPStan loads this file so that it knows them while analysing the code;
 * the file is not used by the framework itself.
 */
define('EXT', '.php');
define('DOCROOT', __DIR__.DIRECTORY_SEPARATOR);
define('APPPATH', DOCROOT.'application'.DIRECTORY_SEPARATOR);
define('MODPATH', DOCROOT.'modules'.DIRECTORY_SEPARATOR);
define('SYSPATH', DOCROOT.'system'.DIRECTORY_SEPARATOR);
define('PUBPATH', DOCROOT.'public'.DIRECTORY_SEPARATOR);
define('KOHANA_START_TIME', microtime(true));
define('KOHANA_START_MEMORY', memory_get_usage());
