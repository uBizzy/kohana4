<?php
/**
 * Wrapper for configuration arrays. Multiple configuration readers can be
 * attached to allow loading configuration from files, database, etc.
 *
 * Configuration directives cascade across config sources in the same way that
 * files cascade across the filesystem.
 *
 * Directives from sources high in the sources list will override ones from those
 * below them.
 *
 * @package    Kohana
 * @category   Configuration
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Config {

	/**
	 * @var  array<int, Kohana_Config_Source>  Configuration readers
	 */
	protected array $_sources = [];

	/**
	 * @var  array<string, Config_Group>  Array of config groups
	 */
	protected array $_groups = [];

	/**
	 * Attach a configuration reader. By default, the reader will be added as
	 * the first used reader. However, if the reader should be used only when
	 * all other readers fail, use `false` for the second parameter.
	 *
	 *     $config->attach($reader);        // Try first
	 *     $config->attach($reader, false); // Try last
	 *
	 * @param   Kohana_Config_Source    $source instance
	 * @param   bool                 $first  add the reader as the first used object
	 * @return  $this
	 */
	public function attach(Kohana_Config_Source $source, bool $first = true): static
	{
		if ($first === true)
		{
			// Place the log reader at the top of the stack
			array_unshift($this->_sources, $source);
		}
		else
		{
			// Place the reader at the bottom of the stack
			$this->_sources[] = $source;
		}

		// Clear any cached _groups
		$this->_groups = [];

		return $this;
	}

	/**
	 * Detach a configuration reader.
	 *
	 *     $config->detach($reader);
	 *
	 * @param   Kohana_Config_Source    $source instance
	 * @return  $this
	 */
	public function detach(Kohana_Config_Source $source): static
	{
		if (($key = array_search($source, $this->_sources)) !== false)
		{
			// Remove the writer
			unset($this->_sources[$key]);
		}

		return $this;
	}

	/**
	 * Load a configuration group. Searches all the config sources, merging all the
	 * directives found into a single config group.  Any changes made to the config
	 * in this group will be mirrored across all writable sources.
	 *
	 *     $array = $config->load($name);
	 *
	 * See [Kohana_Config_Group] for more info
	 *
	 * @param   string  $group  configuration group name, or a path to one value such as "database.default"
	 * @return  mixed   the group (a [Config_Group]), or the value when a path was given
	 * @throws  Kohana_Exception
	 */
	public function load(string $group): mixed
	{
		if ( ! count($this->_sources))
		{
			throw new Kohana_Exception('No configuration sources attached');
		}

		if ($group === '')
		{
			throw new Kohana_Exception("Need to specify a config group");
		}

		if (str_contains($group, '.'))
		{
			// Split the config group and path
			list($group, $path) = explode('.', $group, 2);
		}

		if (isset($this->_groups[$group]))
		{
			if (isset($path))
			{
				return Arr::path($this->_groups[$group], $path, null, '.');
			}
			return $this->_groups[$group];
		}

		$config = [];

		// We search from the "lowest" source and work our way up
		$sources = array_reverse($this->_sources);

		foreach ($sources as $source)
		{
			if ($source instanceof Kohana_Config_Reader)
			{
				if ($source_config = $source->load($group))
				{
					$config = Arr::merge($config, $source_config);
				}
			}
		}

		$this->_groups[$group] = new Config_Group($this, $group, $config);

		if (isset($path))
		{
			return Arr::path($config, $path, null, '.');
		}

		return $this->_groups[$group];
	}

	/**
	 * Copy one configuration group to all of the other writers.
	 *
	 *     $config->copy($name);
	 *
	 * @param   string  $group  configuration group name
	 * @return  $this
	 */
	public function copy(string $group): static
	{
		// Load the configuration group
		$config = $this->load($group);

		if ( ! $config instanceof Config_Group)
			return $this;

		foreach ($config->as_array() as $key => $value)
		{
			$this->_write_config($group, $key, $value);
		}

		return $this;
	}

	/**
	 * Callback used by the config group to store changes made to configuration
	 *
	 * @param string    $group  Group name
	 * @param string    $key    Variable name
	 * @param mixed     $value  The new value
	 * @return static Chainable instance
	 */
	public function _write_config(string $group, string|int $key, mixed $value): static
	{
		foreach ($this->_sources as $source)
		{
			if ( ! ($source instanceof Kohana_Config_Writer))
			{
				continue;
			}

			// Copy each value in the config
			$source->write($group, $key, $value);
		}

		return $this;
	}

}
