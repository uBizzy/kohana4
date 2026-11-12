<?php
/**
 * Redirect HTTP exception class. Used for all [HTTP_Exception]'s where the status
 * code indicates a redirect.
 *
 * Eg [HTTP_Exception_301], [HTTP_Exception_302] and most of the other 30x's
 *
 * @package    Kohana
 * @category   Exceptions
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license        https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_HTTP_Exception_Redirect extends HTTP_Exception_Expected {

	/**
	 * Specifies the URI to redirect to.
	 *
	 * @param string $uri URI of the proxy
	 *
	 * @return ($uri is null ? mixed : $this)  the URI when none is given
	 */
	public function location(?string $uri = null): mixed
	{
		if ($uri === null)
		{
			return $this->headers('Location');
		}

		if ( ! str_contains($uri, '://'))
		{
			// Make the URI into a URL
			$uri = URL::site($uri, true, !empty(Kohana::$index_file));
		}

		$this->headers('Location', $uri);

		return $this;
	}

	/**
	 * Validate this exception contains everything needed to continue.
	 *
	 * @throws Kohana_Exception
	 *
	 * @return bool
	 */
	public function check(): bool
	{
		if ($this->headers('location') === null)
		{
			throw new Kohana_Exception('A \'location\' must be specified for a redirect');
		}

		return true;
	}

}
