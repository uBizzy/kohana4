<?php
/**
 * Abstract Controller class for REST controller mapping.
 * Supports GET, PUT, POST, and DELETE.
 *
 * @package        Kohana\REST
 *
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license        https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Controller_REST extends Controller {

	/**
	 * REST types
	 *
	 * @var array<string, string>
	 */
	protected array $_action_map = [
		HTTP_Request::GET => 'index',
		HTTP_Request::PUT => 'update',
		HTTP_Request::POST => 'create',
		HTTP_Request::DELETE => 'delete'
	];

	/**
	 * @var bool  Is the current request a CORS preflight that has been answered?
	 */
	protected bool $_preflight = false;

	/**
	 * Action of a CORS preflight request. The response was already
	 * prepared in before(), so there is nothing left to do.
	 *
	 * @return void
	 */
	public function action_preflight(): void
	{
	}

	/**
	 * Automatically executed before the controller action.
	 * Evaluate Request (method, action, parameter, format)
	 */
	public function before(): void
	{
		// Parent call
		parent::before();

		// Add the CORS headers when the cors config allows the calling site.
		// A preflight request is fully answered by that: no action is run.
		if (CORS::apply($this->request, $this->response))
		{
			$this->_preflight = true;
			$this->request->action('preflight');

			return;
		}

		// Determine the request action from the request method, if the action/method is not allowed throw error
		// We need to do this because this module does not support all HTTP_Requests
		if ( ! isset($this->_action_map[$this->request->method()]))
		{
			// Stop here: without this the action named in the address would
			// run, for a method the controller never agreed to
			throw (new HTTP_Exception_405('The method :method is not supported', [':method' => $this->request->method()]))
				->allowed(implode(', ', array_keys($this->_action_map)));
		}

		$this->request->action($this->_action_map[$this->request->method()]);
	}

	/**
	 * Automatically executed after the controller action.
	 *
	 * - Adds cache and content header(s).
	 * - Formats body with given formatting method
	 * - Adds attachment header if necessary
	 *
	 * @throws REST_Exception
	 * @throws HTTP_Exception
	 */
	public function after(): void
	{
		// Parent call
		parent::after();

		// A preflight response has headers only: there is nothing to format
		if ($this->_preflight)
			return;

		// Parse Parameter
		$params = $this->_parse_params();

		// Some clients cannot handle HTTP responses different than 200
		if (isset($params['suppressResponseCodes']) && (bool)$params['suppressResponseCodes'] === true)
		{
			$body = $this->response->body();

			// The body is still the data here: it is formatted later
			if (is_array($body))
			{
				$body['responseCode'] = $this->response->status();
				$this->response->body($body);
			}
			$this->response->status(200);
		}

		// Try initializing the formatter
		try
		{
			$formatter = REST_Format::factory($this->request, $this->response);
		}
		catch (REST_Exception $e)
		{
			throw HTTP_Exception::factory(500, $e->getMessage(), null, $e);
		}

		// No cache / must-revalidate cache if method is not GET
		if ($this->request->method() !== HTTP_Request::GET)
		{
			$this->response->headers('cache-control', 'no-cache, no-store, max-age=0, must-revalidate');
		}

		// Format body
		$this->response->body($formatter->format());

		// Support attachment header
		if (is_string($params['attachment'] ?? null))
		{
			$format = $this->request->param('format');

			try
			{
				$this->response->send_file(true, $params['attachment'].'.'.((is_string($format) AND $format !== '') ? $format : REST_Format::$default_format));
			}
			catch (Kohana_Exception $e)
			{
				throw new REST_Exception($e->getMessage(), null, $e->getCode(), $e);
			}
		}
	}

	/**
	 * Initializes the request params array based on the current request.
	 *
	 * @throws REST_Exception
	 *
	 * @return array<int|string, mixed>
	 */
	protected function _parse_params(): array
	{
		// If method is GET, fetch params from query
		if ($this->request->method() === HTTP_Request::GET)
		{
			return $this->request->query();
		}

		// Otherwise we have a PUT, POST, DELETE Method
		// An empty body gives no values
		$parsed_body = [];

		// If content_type is JSON we need to decode the body first
		// Both come from the client: only text is read
		$content_type = is_string($_SERVER['CONTENT_TYPE'] ?? null) ? $_SERVER['CONTENT_TYPE'] : '';
		$raw_body     = is_string($this->request->body()) ? $this->request->body() : '';

		if (str_contains($content_type, 'application/json'))
		{
			try
			{
				// json_decode() throws an error when decoding an empty string
				if ($raw_body !== '' AND $raw_body !== '0')
				{
					$parsed_body = json_decode($raw_body, true, 512, JSON_THROW_ON_ERROR);
				}
			}
			catch (JsonException $e)
			{
				// The mistake is in what the client sent, not on the server
				throw HTTP_Exception::factory(400, 'The body of the request is not valid JSON: :error', [':error' => $e->getMessage()], $e);
			}
		}
		else
		{
			parse_str($raw_body, $parsed_body);
		}

		return array_merge((array)$parsed_body, (array)$this->request->post());
	}

}
