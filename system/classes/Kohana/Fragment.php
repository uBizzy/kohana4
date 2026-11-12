<?php
/**
 * View fragment caching. This is primarily used to cache small parts of a view
 * that rarely change. For instance, you may want to cache the footer of your
 * template because it has very little dynamic content. Or you could cache a
 * user profile page and delete the fragment when the user updates.
 *
 * For obvious reasons, fragment caching should not be applied to any
 * content that contains forms.
 *
 * [!!] Multiple language (I18n) support was added in v3.0.4.
 *
 * @package    Kohana
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 * @uses       Kohana::cache
 */
class Kohana_Fragment {

	/**
	 * @var  int  default number of seconds to cache for
	 */
	public static int $lifetime = 30;

	/**
	 * @var  bool  use multilingual fragment support?
	 */
	public static bool $i18n = false;

	/**
	 * @var  array<int, string>  list of buffer => cache key
	 */
	protected static array $_caches = [];

	/**
	 * Generate the cache key name for a fragment.
	 *
	 *     $key = Fragment::_cache_key('footer', true);
	 *
	 * @param   string  $name   fragment name
	 * @param   bool $i18n   multilingual fragment support
	 * @return  string
	 * @uses    I18n::lang
	 * @since   3.0.4
	 */
	protected static function _cache_key(string $name, ?bool $i18n = null): string
	{
		// Use the default setting
		$i18n ??= Fragment::$i18n;

		// Language prefix for cache key
		$i18n = ($i18n === true) ? I18n::lang() : '';

		// Note: $i18n and $name need to be delimited to prevent naming collisions
		return 'Fragment::cache('.$i18n.'+'.$name.')';
	}

	/**
	 * Load a fragment from cache and display it. Multiple fragments can
	 * be nested with different life times.
	 *
	 *     if ( ! Fragment::load('footer')) {
	 *         // Anything that is echo'ed here will be saved
	 *         Fragment::save();
	 *     }
	 *
	 * @param   string  $name       fragment name
	 * @param   int $lifetime   fragment cache lifetime
	 * @param   bool $i18n       multilingual fragment support
	 * @return  bool
	 */
	public static function load(string $name, ?int $lifetime = null, ?bool $i18n = null): bool
	{
		// Set the cache lifetime
		$lifetime ??= Fragment::$lifetime;

		// Get the cache key name
		$cache_key = Fragment::_cache_key($name, $i18n);

		if ($fragment = Kohana::cache($cache_key, null, $lifetime))
		{
			// Display the cached fragment now
			echo is_scalar($fragment) ? $fragment : '';

			return true;
		}
		else
		{
			// Start the output buffer
			ob_start();

			// Store the cache key by the buffer level
			Fragment::$_caches[ob_get_level()] = $cache_key;

			return false;
		}
	}

	/**
	 * Saves the currently open fragment in the cache.
	 *
	 *     Fragment::save();
	 *
	 * @return  void
	 */
	public static function save(): void
	{
		// Get the buffer level
		$level = ob_get_level();

		if (isset(Fragment::$_caches[$level]))
		{
			// Get the cache key based on the level
			$cache_key = Fragment::$_caches[$level];

			// Delete the cache key, we don't need it anymore
			unset(Fragment::$_caches[$level]);

			// Get the output buffer and display it at the same time
			$fragment = ob_get_flush();

			// Cache the fragment
			Kohana::cache($cache_key, $fragment);
		}
	}

	/**
	 * Delete a cached fragment.
	 *
	 *     Fragment::delete($key);
	 *
	 * @param   string  $name   fragment name
	 * @param   bool $i18n   multilingual fragment support
	 * @return  void
	 */
	public static function delete(string $name, ?bool $i18n = null): void
	{
		// A lifetime of 0 tells Kohana::cache() to delete the entry
		Kohana::cache(Fragment::_cache_key($name, $i18n), null, 0);
	}

	/**
	 * Tests if a fragment is in the cache, without displaying it.
	 *
	 *     if ( ! Fragment::has('footer')) ...
	 *
	 * @param   string     $name      fragment name
	 * @param   int|null   $lifetime  fragment cache lifetime
	 * @param   bool|null  $i18n      multilingual fragment support
	 * @return  bool
	 */
	public static function has(string $name, ?int $lifetime = null, ?bool $i18n = null): bool
	{
		// The same test as Fragment::load() does
		return (bool) Kohana::cache(Fragment::_cache_key($name, $i18n), null, $lifetime ?? Fragment::$lifetime);
	}

	/**
	 * Ends the currently open fragment without saving it: the content is
	 * displayed but not cached. Use it in place of [Fragment::save] when
	 * the content should not be kept, for example because a service it
	 * depends on is down.
	 *
	 *     if ( ! Fragment::load('prices'))
	 *     {
	 *         echo $prices;
	 *
	 *         $complete ? Fragment::save() : Fragment::discard();
	 *     }
	 *
	 * @return  void
	 */
	public static function discard(): void
	{
		// Get the buffer level
		$level = ob_get_level();

		if (isset(Fragment::$_caches[$level]))
		{
			// Forget the fragment and display its content
			unset(Fragment::$_caches[$level]);

			ob_end_flush();
		}
	}

}
