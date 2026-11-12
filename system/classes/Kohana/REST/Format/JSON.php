<?php
/**
 * Class for formatting REST-Response bodies as JSON
 *
 * @package        Kohana\REST
 *
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license        https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_REST_Format_JSON extends REST_Format {

	/**
	 * Format function
	 *
	 * @throws REST_Exception
	 *
	 * @return string
	 */
	public function format(): string
	{
		try
		{
			$body = json_encode($this->_body,
				JSON_THROW_ON_ERROR
				|JSON_FORCE_OBJECT
				|JSON_NUMERIC_CHECK
				|JSON_PRESERVE_ZERO_FRACTION
				|JSON_UNESCAPED_UNICODE
			);
		}
		catch(Exception $e)
		{
			throw new REST_Exception($e->getMessage(), null, $e->getCode(), $e);
		}

		return $body;
	}

}
