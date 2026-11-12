<?php
/**
 * Helper Class for Unit Tests.
 *
 * @package    Kohana/UnitTest
 *
 *
 * @copyright  (c) 2007-2012 Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Unittest_Helpers {

	/**
	 * Static variable used to work out whether we have an internet connection
	 * @var bool
	 */
	protected static ?bool $_has_internet = null;

	/**
	 * Backup of the environment variables
	 * @var array<string, mixed>
	 */
	protected array $_environment_backup = [];

	/**
	 * Check for internet connectivity
	 *
	 * @return bool  Whether an internet connection is available
	 */
	public static function has_internet(): bool
	{
		if ( ! isset(self::$_has_internet))
		{
			// The @ operator is used here to avoid DNS errors when there is no connection.
			$sock = @fsockopen('www.google.com', 80, $errno, $errstr, 1);

			self::$_has_internet = (bool) $sock ? true : false;
		}

		return self::$_has_internet;
	}

	/**
	 * Helper function which replaces the "/" to OS-specific delimiter
	 *
	 * @param  string $path
	 *
	 * @return string
	 */
	public static function dir_separator(string $path): string
	{
		return str_replace('/', DIRECTORY_SEPARATOR, $path);
	}

	/**
	 * Removes all cache files from the kohana cache dir (except .gitignore)
	 */
	public static function clean_cache_dir(): void
	{
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( (string) Kohana::$cache_dir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		/** @var SplFileInfo $file */
		foreach ($files as $file) {
			if ($file->getExtension() === 'gitignore') {
				continue;
			}
			$todo = ($file->isDir() ? 'rmdir' : 'unlink');
			$todo($file->getRealPath());
		}
	}

	/**
	 * Allows easy setting & backing up of enviroment config
	 *
	 * Option types are checked in the following order:
	 *
	 * - Server Var
	 * - Static Variable
	 * - Config option
	 *
	 * @param  array<string, mixed> $environment List of environment to set
	 *
	 * @return bool
	 * @throws Kohana_Exception
	 * @throws ReflectionException
	 */
	public function set_environment(array $environment): bool
	{
		if ( ! count($environment)) {
			return false;
		}

		foreach ($environment as $option => $value)
		{
			$backup_needed = ! array_key_exists($option, $this->_environment_backup);

			// Handle changing superglobals
			if (in_array($option, ['_GET', '_POST', '_SERVER', '_FILES']))
			{
				// For some reason we need to do this in order to change the superglobals
				global $$option;

				if ($backup_needed)
				{
					$this->_environment_backup[$option] = $$option;
				}

				// PHPUnit makes a backup of superglobals automatically
				$$option = $value;
			}
			// If this is a static property i.e. Html::$windowed_urls
			elseif (strpos($option, '::$') !== false)
			{
				/** @var class-string $name */
				[$name, $var] = explode('::$', $option, 2);

				$class = new ReflectionClass($name);

				if ($backup_needed)
				{
					$this->_environment_backup[$option] = $class->getStaticPropertyValue($var);
				}

				$class->setStaticPropertyValue($var, $value);
			}
			// If this is an environment variable
			elseif (isset($_SERVER[$option]) || preg_match('/^[A-Z_-]+$/', $option))
			{
				if ($backup_needed)
				{
					$this->_environment_backup[$option] = $_SERVER[$option] ?? '';
				}

				$_SERVER[$option] = $value;
			}
			// Else we assume this is a config option
			else
			{
				if ($backup_needed)
				{
					$this->_environment_backup[$option] = Kohana::$config->load($option);
				}

				[$group, $var] = explode('.', $option, 2);

				/** @var Config_Group $config */
				$config = Kohana::$config->load($group);

				$config->set($var, $value);
			}
		}
		return true;
	}

	/**
	 * Restores the environment to the original state
	 *
	 * @throws Kohana_Exception
	 * @throws ReflectionException
	 */
	public function restore_environment(): void
	{
		$this->set_environment($this->_environment_backup);
	}
}
