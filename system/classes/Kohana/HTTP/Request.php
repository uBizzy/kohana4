<?php
/**
 * A HTTP Request specific interface that adds the methods required
 * by HTTP requests. Over and above [Kohana_HTTP_Interaction], this
 * interface provides method, uri, get and post methods.
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
interface Kohana_HTTP_Request extends HTTP_Message {

	// HTTP Methods
	const string GET       = 'GET';
	const string POST      = 'POST';
	const string PATCH     = 'PATCH';
	const string PUT       = 'PUT';
	const string DELETE    = 'DELETE';
	const string HEAD      = 'HEAD';
	const string OPTIONS   = 'OPTIONS';
	const string TRACE     = 'TRACE';
	const string CONNECT   = 'CONNECT';

	/**
	 * Gets or sets the HTTP method. Usually GET, POST, PUT or DELETE in
	 * traditional CRUD applications.
	 *
	 * @param   string   $method  Method to use for this request
	 * @return  ($method is null ? string : static)
	 */
	public function method(?string $method = null): string|static;

	/**
	 * Gets the URI of this request, optionally allows setting
	 * of [Route] specific parameters during the URI generation.
	 * If no parameters are passed, the request will use the
	 * default values defined in the Route.
	 *
	 * @return  string|static
	 */
	public function uri(): string|static;

	/**
	 * Gets or sets HTTP query string.
	 *
	 * @param   array<string, mixed>|string|null   $key    Key or key value pairs to set
	 * @param   string  $value  Value to set to a key
	 * @return  ($key is null ? array<int|string, mixed> : ($key is array ? static : ($value is null ? mixed : static)))
	 */
	public function query(array|string|null $key = null, mixed $value = null): mixed;

	/**
	 * Gets or sets HTTP POST parameters to the request.
	 *
	 * @param   array<string, mixed>|string|null   $key   Key or key value pairs to set
	 * @param   string  $value Value to set to a key
	 * @return  ($key is null ? array<int|string, mixed> : ($key is array ? static : ($value is null ? mixed : static)))
	 */
	public function post(array|string|null $key = null, mixed $value = null): mixed;

}
