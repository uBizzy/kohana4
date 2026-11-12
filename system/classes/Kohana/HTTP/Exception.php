<?php

abstract class Kohana_HTTP_Exception extends Kohana_Exception {

	/**
	 * Creates an HTTP_Exception of the specified type.
	 *
	 * @param   int $code       the http status code
	 * @param   string  $message    status message, custom content to display with error
	 * @param   array<string, mixed>   $variables  translation variables
	 * @return  HTTP_Exception
	 */
	public static function factory(int $code, ?string $message = '', ?array $variables = null, ?Throwable $previous = null): HTTP_Exception
	{
		/** @var class-string<HTTP_Exception> $class  the class of this status code */
		$class = 'HTTP_Exception_'.$code;

		return new $class($message, $variables, $previous);
	}

	/**
	 * @var  int        http status code
	 */
	protected int $_code = 0;

	/**
	 * @var  Request    Request instance that triggered this exception.
	 */
	protected ?Request $_request = null;

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
	public function __construct(?string $message = '', ?array $variables = null, ?Throwable $previous = null)
	{
		parent::__construct( (string) $message, $variables, $this->_code, $previous);
	}

	/**
	 * Store the Request that triggered this exception.
	 *
	 * @param   Request   $request  Request object that triggered this exception.
	 * @return  ($request is null ? Request|null : static)
	 */
	public function request(?Request $request = null): Request|static|null
	{
		if ($request === null)
			return $this->_request;

		$this->_request = $request;

		return $this;
	}

	/**
	 * Generate a Response for the current Exception
	 *
	 * @uses   Kohana_Exception::response()
	 * @return Response
	 */
	public function get_response(): Response
	{
		if ($this->_code >= 500)
		{
			// An error of the application, not of the visitor: keep it in the log
			Kohana_Exception::log($this);
		}

		return Kohana_Exception::response($this);
	}

}
