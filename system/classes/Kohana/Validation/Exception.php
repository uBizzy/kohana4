<?php
/**
 * @package    Kohana
 * @category   Exceptions
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Validation_Exception extends Kohana_Exception {

	/**
	 * @var  Validation  Validation instance
	 */
	public Validation $array;

	/**
	 * @param  Validation   $array      Validation object
	 * @param  string       $message    error message
	 * @param  array<string, mixed>|null       $values     translation variables
	 * @param  int          $code       the exception code
	 */
	public function __construct(Validation $array, string $message = 'Failed to validate array', ?array $values = null, int|string $code = 0, ?Throwable $previous = null)
	{
		$this->array = $array;

		parent::__construct($message, $values, $code, $previous);
	}

}
