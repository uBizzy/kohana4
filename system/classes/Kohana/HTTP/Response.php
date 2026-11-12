<?php
/**
 * A HTTP Response specific interface that adds the methods required
 * by HTTP responses. Over and above [Kohana_HTTP_Interaction], this
 * interface provides status.
 *
 * @package    Kohana
 * @category   HTTP
 *
 * @since      3.1.0
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
interface Kohana_HTTP_Response extends HTTP_Message {

	/**
	 * Sets or gets the HTTP status from this response.
	 *
	 *      // Set the HTTP status to 404 Not Found
	 *      $response = Response::factory()
	 *              ->status(404);
	 *
	 *      // Get the current status
	 *      $status = $response->status();
	 *
	 * @param   int  $code  Status to set to this response
	 * @return  ($code is null ? int|false : static)
	 */
	public function status(?int $code = null): int|static|false;

}
