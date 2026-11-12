<?php
/**
 * Checks that the application can run here: PHP, extensions, directories,
 * secret keys, and the database and cache it is configured for.
 *
 *     ./minion check
 *
 * Every check is "ok", "warn" or "fail". When a check fails the command
 * ends with an error status, so a deploy script stops; warnings do not.
 * In production it also warns about settings meant for development.
 *
 * Passwords, keys and connection settings are never written.
 *
 * Add checks of your own in application/classes/Task/Check.php:
 *
 *     class Task_Check extends Kohana_Task_Check {
 *
 *         protected function _checks(): void
 *         {
 *             parent::_checks();
 *
 *             $this->_add(is_writable(DOCROOT.'uploads') ? 'ok' : 'fail', 'Uploads directory');
 *         }
 *     }
 *
 * @package    Kohana/Minion
 * @category   Tasks
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Task_Check extends Minion_Task {

	/**
	 * @var  int  A cookie salt shorter than this gets a warning
	 */
	public const int SALT_LENGTH = 32;

	/**
	 * @var  array<int, array{0: string, 1: string, 2: string}>  Results: status, what was checked, detail
	 */
	protected array $_results = [];

	/**
	 * Runs the checks, writes the results and fails when a check failed.
	 *
	 * @param   array<int|string, mixed>  $params  options of the command line
	 * @return  mixed
	 * @throws  Minion_Exception_Failed  when at least one check failed
	 */
	protected function _execute(array $params): mixed
	{
		$this->_checks();

		$colors = ['ok' => 'green', 'warn' => 'yellow', 'fail' => 'red'];
		$rows   = [];

		foreach ($this->_results as list($status, $name, $detail))
		{
			$rows[] = [Minion_CLI::color($status, $colors[$status] ?? 'red'), $name, $detail];
		}

		Minion_CLI::table($rows, ['', 'Check', 'Detail']);
		Minion_CLI::write();

		$count    = array_count_values(array_column($this->_results, 0));
		$failed   = $count['fail'] ?? 0;
		$warnings = $count['warn'] ?? 0;

		if ($warnings > 0)
		{
			Minion_CLI::warning($warnings.' '.Inflector::plural('warning', $warnings).'.');
		}

		if ($failed > 0)
			throw new Minion_Exception_Failed($failed.' of '.count($this->_results).' checks failed.');

		Minion_CLI::success('No check failed ('.count($this->_results).' checks).');

		return null;
	}

	/**
	 * Runs every check. Extend this method to add your own.
	 *
	 * @return  void
	 */
	protected function _checks(): void
	{
		$this->_check_php();
		$this->_check_directories();
		$this->_check_cookie_salt();
		$this->_check_theme();

		// Each of these only when its module is enabled
		$this->_enabled('database') and $this->_check_database();
		$this->_enabled('migration') and $this->_enabled('database') and $this->_check_migrations();
		$this->_enabled('cache') and $this->_check_cache();
		$this->_enabled('encrypt') and $this->_check_encrypt();
		$this->_enabled('image') and $this->_check_image();

		if (Kohana::$environment === Kohana::PRODUCTION)
		{
			$this->_check_production();
		}
	}

	/**
	 * Keeps the result of one check.
	 *
	 * @param   string  $status  "ok", "warn" or "fail"
	 * @param   string  $name    what was checked
	 * @param   string  $detail  what was found, or what to do
	 * @return  void
	 */
	protected function _add(string $status, string $name, string $detail = ''): void
	{
		$this->_results[] = [$status, $name, $detail];
	}

	/**
	 * Tells whether a module is enabled in the bootstrap.
	 *
	 * @param   string  $module  name of the module, as its key in Kohana::modules()
	 * @return  bool
	 */
	protected function _enabled(string $module): bool
	{
		return isset(Kohana::modules()[$module]);
	}

	/**
	 * Checks that a PHP extension is loaded.
	 *
	 * @param   string  $extension  name of the extension
	 * @param   string  $for        what needs it
	 * @return  void
	 */
	protected function _check_extension(string $extension, string $for): void
	{
		extension_loaded($extension)
			? $this->_add('ok', 'Extension '.$extension, 'for '.$for)
			: $this->_add('fail', 'Extension '.$extension, 'missing, needed for '.$for);
	}

	/**
	 * The PHP version and the extensions the framework itself needs.
	 *
	 * @return  void
	 */
	protected function _check_php(): void
	{
		$this->_add(version_compare(PHP_VERSION, '8.4', '>=') ? 'ok' : 'fail', 'PHP version', PHP_VERSION.' (8.4 or newer is needed)');

		$this->_check_extension('intl', 'the framework');
		$this->_check_extension('mbstring', 'the framework');
	}

	/**
	 * The directories the application writes to.
	 *
	 * @return  void
	 */
	protected function _check_directories(): void
	{
		$directories = [
			'Cache directory' => Kohana::$cache_dir ?? APPPATH.'cache',
			'Logs directory'  => APPPATH.'logs',
		];

		foreach ($directories as $name => $directory)
		{
			$path = Debug::path($directory);

			match (true) {
				! is_dir($directory)      => $this->_add('fail', $name, $path.' does not exist'),
				! is_writable($directory) => $this->_add('fail', $name, $path.' is not writable'),
				default                   => $this->_add('ok', $name, $path),
			};
		}
	}

	/**
	 * The theme of the framework's own pages: a value of config/theme.php
	 * that is not valid is not used, which nobody would notice. Nothing
	 * is written when the config is fine.
	 *
	 * @return  void
	 */
	protected function _check_theme(): void
	{
		foreach (Theme::problems() as $problem)
		{
			$this->_add('warn', 'Theme', $problem.' in config/theme.php: the default is used');
		}
	}

	/**
	 * The salt that signs cookies: without it no cookie or session works.
	 * Only its length is looked at, the value is never written.
	 *
	 * @return  void
	 */
	protected function _check_cookie_salt(): void
	{
		$length = strlen( (string) Cookie::$salt);

		match (true) {
			$length === 0                => $this->_add('fail', 'Cookie salt', 'Cookie::$salt is not set in the bootstrap'),
			$length < static::SALT_LENGTH => $this->_add('warn', 'Cookie salt', $length.' characters, use '.static::SALT_LENGTH.' or more'),
			default                      => $this->_add('ok', 'Cookie salt', $length.' characters'),
		};
	}

	/**
	 * The default database: its extension, then a real connection and a
	 * query. Other connections of config/database.php are not tried.
	 *
	 * @return  void
	 */
	protected function _check_database(): void
	{
		$type = Kohana::$config->load('database.'.Database::$default.'.type');

		$this->_check_extension(is_string($type) ? strtolower($type) : 'pdo', 'the database');

		try
		{
			$db = Database::instance();

			$db->query(Database::SELECT, 'SELECT 1');

			$this->_add('ok', 'Database connection', trim('"'.Database::$default.'" '.$db->driver()));
		}
		catch (Throwable $e)
		{
			// The message of the database names the user and the host, never the password
			$this->_add('fail', 'Database connection', '"'.Database::$default.'": '.$e->getMessage());
		}
	}

	/**
	 * Migrations that were not applied yet.
	 *
	 * [!!] Like migration:status, this creates the table that remembers
	 * the applied migrations when it is not there yet.
	 *
	 * @return  void
	 */
	protected function _check_migrations(): void
	{
		try
		{
			$pending = count((new Migration_Runner)->pending());

			$pending === 0
				? $this->_add('ok', 'Migrations', 'all applied')
				: $this->_add('warn', 'Migrations', $pending.' pending, run "'.Minion_Task::command().' migration:run"');
		}
		catch (Throwable $e)
		{
			$this->_add('fail', 'Migrations', $e->getMessage());
		}
	}

	/**
	 * The default cache: an entry is written, read and deleted.
	 *
	 * @return  void
	 */
	protected function _check_cache(): void
	{
		try
		{
			$cache = Cache::instance();
			$key   = 'kohana_check_'.bin2hex(random_bytes(8));

			$cache->set($key, 'ok', 60);

			$read = $cache->get($key);

			$cache->delete($key);

			$read === 'ok'
				? $this->_add('ok', 'Cache', 'written, read and deleted with '.get_class($cache))
				: $this->_add('fail', 'Cache', 'an entry written with '.get_class($cache).' could not be read again');
		}
		catch (Throwable $e)
		{
			$this->_add('fail', 'Cache', $e->getMessage());
		}
	}

	/**
	 * The key of the default encryption group. Only whether it is set is
	 * looked at, the value is never written.
	 *
	 * @return  void
	 */
	protected function _check_encrypt(): void
	{
		$group = Kohana::$config->load('encrypt.'.Encrypt::$default);
		$type  = (is_array($group) AND is_string($group['type'] ?? null)) ? strtolower($group['type']) : Encrypt::$default_engine;

		$this->_check_extension($type, 'encryption');

		(is_array($group) AND ! empty($group['key']))
			? $this->_add('ok', 'Encryption key', 'set for the "'.Encrypt::$default.'" group')
			: $this->_add('warn', 'Encryption key', 'not set in config/encrypt.php: Encrypt cannot be used');
	}

	/**
	 * The image driver that is configured, and its extension.
	 *
	 * @return  void
	 */
	protected function _check_image(): void
	{
		$driver = Kohana::$config->load('image.default_driver');

		(is_string($driver) AND $driver !== '')
			? $this->_check_extension(strtolower($driver), 'images')
			: $this->_add('warn', 'Image driver', 'not set in config/image.php: Image cannot be used');
	}

	/**
	 * Tells whether the page that checks the requirements in a browser,
	 * public/install.php, is still there.
	 *
	 * @return  bool
	 */
	protected function _install_file(): bool
	{
		return defined('PUBPATH') AND is_file(PUBPATH.'install.php');
	}

	/**
	 * Settings and files that are meant for development and should not
	 * be found on a production server. All of these are warnings.
	 *
	 * @return  void
	 */
	protected function _check_production(): void
	{
		$found = [];

		if ($this->_install_file())
		{
			$found[] = 'delete install.php from the public directory';
		}

		// The list of config/check.php: modules add themselves to it
		$modules = Kohana::$config->load('check.development_modules');

		foreach (is_array($modules) ? $modules : [] as $module)
		{
			if (is_string($module) AND $this->_enabled($module))
			{
				$found[] = 'the '.$module.' module is enabled';
			}
		}

		if (Kohana::$profiling)
		{
			$found[] = 'profiling is on: set "profile" to false in Kohana::init()';
		}

		if ( ! Kohana::$caching)
		{
			$found[] = 'internal caching is off: set "caching" to true in Kohana::init()';
		}

		foreach ($found as $detail)
		{
			$this->_add('warn', 'Production', $detail);
		}

		$found or $this->_add('ok', 'Production', 'no development setting found');
	}

}
