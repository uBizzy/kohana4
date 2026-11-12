<?php
/**
 * The HTTP Interaction interface providing the core HTTP methods that
 * should be implemented by any HTTP request or response class.
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
interface Kohana_HTTP_Message {

	/**
	 * Gets or sets the HTTP protocol. The standard protocol to use
	 * is `HTTP/1.1`.
	 *
	 * @param   string   $protocol  Protocol to set to the request/response
	 * @return  ($protocol is null ? string : static)
	 */
	public function protocol(?string $protocol = null): string|static;

	/**
	 * Gets or sets HTTP headers to the request or response. All headers
	 * are included immediately after the HTTP protocol definition during
	 * transmission. This method provides a simple array or key/value
	 * interface to the headers.
	 *
	 * @param   array<string, mixed>|HTTP_Header|string|null   $key    Key or array of key/value pairs to set
	 * @param   string  $value  Value to set to the supplied key
	 * @return  ($key is null ? HTTP_Header : ($key is array|HTTP_Header ? static : ($value is null ? mixed : static)))
	 */
	public function headers(HTTP_Header|array|string|null $key = null, ?string $value = null): mixed;

	/**
	 * Gets or sets the HTTP body to the request or response. The body is
	 * included after the header, separated by a single empty new line.
	 *
	 * @param   string    $content  Content to set to the object
	 * @return  string
	 */
	public function body(mixed $content = null): mixed;

	/**
	 * Renders the HTTP_Interaction to a string, producing
	 *
	 *  - Protocol
	 *  - Headers
	 *  - Body
	 *
	 * @return  string
	 */
	public function render(): string;

}
