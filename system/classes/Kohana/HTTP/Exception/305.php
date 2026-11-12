<?php

class Kohana_HTTP_Exception_305 extends HTTP_Exception_Expected {

	/**
	 * @var   int    HTTP 305 Use Proxy
	 */
	protected int $_code = 305;

	/**
	 * Specifies the proxy to replay this request via
	 *
	 * @param  string|null  $uri  URI of the proxy
	 */
	public function location(?string $uri = null): mixed
	{
		if ($uri === null)
			return $this->headers('Location');

		$this->headers('Location', $uri);

		return $this;
	}

	/**
	 * Validate this exception contains everything needed to continue.
	 *
	 * @throws Kohana_Exception
	 * @return bool
	 */
	public function check(): bool
	{
		if ( ! is_string($location = $this->headers('location')))
			throw new Kohana_Exception('A \'location\' must be specified for a redirect');

		if ( ! str_contains($location, '://'))
			throw new Kohana_Exception('An absolute URI to the proxy server must be specified');

		return true;
	}

}
