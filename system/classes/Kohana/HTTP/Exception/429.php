<?php

class Kohana_HTTP_Exception_429 extends HTTP_Exception_Expected {

	/**
	 * @var   int    HTTP 429 Too Many Requests
	 */
	protected int $_code = 429;

	/**
	 * Gets or sets the Retry-After header: the number of seconds the
	 * client should wait before trying again.
	 *
	 * @param   int|null  $seconds  seconds to wait; null to read the header
	 * @return  mixed     the header value when reading, this exception when setting
	 */
	public function retry_after(?int $seconds = null): mixed
	{
		if ($seconds === null)
			return $this->headers('retry-after');

		$this->headers('retry-after', (string) $seconds);

		return $this;
	}

}
