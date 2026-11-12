<?php
/**
 * "Expected" HTTP exception class. Used for all [HTTP_Exception]'s where a standard
 * Kohana error page should never be shown.
 *
 * Eg [HTTP_Exception_301], [HTTP_Exception_302] etc
 *
 * @package    Kohana
 * @category   Exceptions
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_HTTP_Exception_Expected extends HTTP_Exception {

	/**
	 * @var  Response   Response Object
	 */
	protected Response $_response;

	/**
	 * Creates a new translated exception.
	 *
	 *     throw new Kohana_Exception('Something went terrible wrong, :user',
	 *         array(':user' => $user));
	 *
	 * @param   string  $message    status message, custom content to display with error
	 * @param   array<string, mixed>   $variables  translation variables
	 * @return  void
	 */
	public function __construct(?string $message = null, ?array $variables = null, ?Throwable $previous = null)
	{
		parent::__construct($message, $variables, $previous);

		// Prepare our response object and set the correct status code.
		$this->_response = Response::factory()
			->status($this->_code);
	}

	/**
	 * Gets and sets headers to the [Response].
	 *
	 * @see     [Response::headers]
	 * @param   array<string, mixed>|HTTP_Header|string|null   $key
	 * @param   string  $value
	 * @return  mixed
	 */
	public function headers(HTTP_Header|array|string|null $key = null, ?string $value = null): mixed
	{
		$result = $this->_response->headers($key, $value);

		if ( ! $result instanceof Response)
			return $result;

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
		return true;
	}

	/**
	 * Generate a Response for the current Exception
	 *
	 * @uses   Kohana_Exception::response()
	 * @return Response
	 */
	public function get_response(): Response
	{
		$this->check();

		return $this->_response;
	}

}
