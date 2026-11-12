<?php
/**
 * File-based configuration reader. Multiple configuration directories can be
 * used by attaching multiple instances of this class to [Kohana_Config].
 *
 * @package    Kohana
 * @category   Configuration
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2020-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Config_File_Reader implements Kohana_Config_Reader {

	/**
	 * @var string The directory where config files are located
	 */
	protected string $_directory = 'config';

	/**
	 * @var array<string, array<int|string, mixed>> Cached configurations
	 */
	protected static array $_cache = [];

	/**
	 * Creates a new file reader using the given directory as a config source.
	 *
	 * @param string $directory Configuration directory to search
	 */
	public function __construct(?string $directory = null)
	{
		if ($directory !== null)
		{
			$this->_directory = trim($directory, '\/');
		}
	}

	/**
	 * Load and merge all of the configuration files in this group.
	 *
	 * @param string $group Configuration group name
	 * @return array<int|string, mixed> Configuration
	 * @throws Kohana_Exception YAML extension/package not loaded
	 */
	public function load(string $group): array
	{
		// @codeCoverageIgnoreStart
		// Check cache
		$cache_key = $this->_directory.' '.$group;
		if (Kohana::$caching && isset(static::$_cache[$cache_key]))
		{
			return static::$_cache[$cache_key];
		}
		// @codeCoverageIgnoreEnd

		if (Kohana::$profiling)
		{
			// Start a new benchmark
			$benchmark = Profiler::start('Config ('.$this->_directory.')', $group);
		}

		$config = [];
		// Loop through paths. Notice: reverse paths, so system and modules files get overwritten by application files
		foreach (array_reverse(Kohana::include_paths()) as $path)
		{
			// Build path
			$file = $path.$this->_directory.DIRECTORY_SEPARATOR.$group;
			$value = false;
			// Try ".php", ".json" and ".yaml" extensions and parse contents with PHP support
			if (file_exists($file.'.php'))
			{
				$value = Kohana::load($file.'.php');
			}
			elseif (file_exists($file.'.json'))
			{
				$value = json_decode($this->read_from_ob($file.'.json'), true);
			}
			elseif (file_exists($file.'.yaml'))
			{
				// @codeCoverageIgnoreStart
				if ( ! function_exists('yaml_parse'))
				{
					throw new Kohana_Exception('YAML extension/package is required in order to parse YAML files');
				}
				// @codeCoverageIgnoreEnd
				$value = yaml_parse($this->read_from_ob($file.'.yaml'));
			}
			// Merge configurations
			if (is_array($value))
			{
				$config = Arr::merge($config, $value);
			}
		}

		// @codeCoverageIgnoreStart
		if (Kohana::$caching)
		{
			static::$_cache[$cache_key] = $config;
		}
		// @codeCoverageIgnoreEnd

		if (isset($benchmark))
		{
			// Stop the benchmark
			Profiler::stop($benchmark);
		}

		return $config;
	}

	/**
	 * Read contents from file with output buffering. Used to support `<?php` `?>` tags and code inside configurations.
	 *
	 * @param string $path Path to File
	 * @return string
	 * @codeCoverageIgnore
	 */
	protected function read_from_ob(string $path): string
	{
		// Start output buffer
		ob_start();
		Kohana::load($path);
		// Return contents of buffer
		return (string) ob_get_clean();
	}
}
