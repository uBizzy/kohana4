<?php
/**
 * Abstract controller class. Controllers should only be created using a [Request].
 *
 * Controllers methods will be automatically called in the following order by
 * the request:
 *
 *     $controller = new Controller_Foo($request);
 *     $controller->before();
 *     $controller->action_bar();
 *     $controller->after();
 *
 * The controller action should add the output it creates to
 * `$this->response->body($output)`, typically in the form of a [View], during the
 * "action" part of execution.
 *
 * @package    Kohana
 * @category   Controller
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Controller {

	/**
	 * @var  Request  Request that created the controller
	 */
	public Request $request;

	/**
	 * @var  Response The response that will be returned from controller
	 */
	public Response $response;

	/**
	 * Creates a new controller instance. Each controller must be constructed
	 * with the request object that created it.
	 *
	 * @param   Request   $request  Request that created the controller
	 * @param   Response  $response The request's response
	 *
	 * @return  void
	 */
	public function __construct(Request $request, Response $response)
	{
		// Assign the request to the controller
		$this->request = $request;

		// Assign a response to the controller
		$this->response = $response;
	}

	/**
	 * Executes the given action and calls the [Controller::before] and [Controller::after] methods.
	 *
	 * Can also be used to catch exceptions from actions in a single place.
	 *
	 * 1. Before the controller action is called, the [Controller::before] method
	 * will be called.
	 * 2. Next the controller action will be called.
	 * 3. After the controller action is called, the [Controller::after] method
	 * will be called.
	 *
	 * @throws  HTTP_Exception_404
	 * @return  Response
	 */
	public function execute(): Response
	{
		if (Kohana::$profiling)
		{
			// The three steps are measured one by one
			return $this->_execute_measured();
		}

		// Execute the "before action" method
		$this->before();

		// Only now: before() may have chosen another action
		$action = $this->_action();

		// Execute the action itself
		$this->{$action}();

		// Execute the "after action" method
		$this->after();

		// Return the response
		return $this->response;
	}

	/**
	 * Returns the name of the method of the action that the request asks
	 * for. Called after [Controller::before], which may change the action.
	 *
	 * @return  string
	 * @throws  HTTP_Exception_404  when the action does not exist or is not public
	 */
	protected function _action(): string
	{
		$action = 'action_'.$this->request->action();

		if ( ! method_exists($this, $action) OR ! (new ReflectionMethod($this, $action))->isPublic())
		{
			throw HTTP_Exception::factory(404,
				'The requested URL :uri was not found on this server.',
				[':uri' => $this->request->uri()]
			)->request($this->request);
		}

		return $action;
	}

	/**
	 * Does what [Controller::execute] does, with a benchmark for each of
	 * the three steps: before(), the action and after(). Used while
	 * [Kohana::$profiling] is on.
	 *
	 * @return  Response
	 * @throws  HTTP_Exception_404
	 */
	protected function _execute_measured(): Response
	{
		$class = get_class($this);

		Profiler::measure('Controller', $class.'::before', fn () => $this->before());

		// Only now: before() may have chosen another action
		$action = $this->_action();

		Profiler::measure('Controller', $class.'::'.$action, fn () => $this->{$action}());
		Profiler::measure('Controller', $class.'::after', fn () => $this->after());

		return $this->response;
	}

	/**
	 * Automatically executed before the controller action. Can be used to set
	 * class properties, do authorization checks, and execute other custom code.
	 *
	 * @return  void
	 */
	public function before(): void
	{
		// Nothing by default
	}

	/**
	 * Automatically executed after the controller action. Can be used to apply
	 * transformation to the response, add extra output, and execute
	 * other custom code.
	 *
	 * @return  void
	 */
	public function after(): void
	{
		// Nothing by default
	}

	/**
	 * Issues a HTTP redirect.
	 *
	 * Proxies to the [HTTP::redirect] method.
	 *
	 * @param  string  $uri   URI to redirect to
	 * @param  int     $code  HTTP Status code to use for the redirect
	 * @throws HTTP_Exception
	 */
	public static function redirect(?string $uri = '', int $code = 302): never
	{
		HTTP::redirect($uri, $code);
	}

	/**
	 * Checks the browser cache to see the response needs to be returned,
	 * execution will halt and a 304 Not Modified will be sent if the
	 * browser cache is up to date.
	 *
	 *     $this->check_cache(hash('sha256', $content));
	 *
	 * @param  string  $etag  Resource Etag
	 * @return Response
	 */
	protected function check_cache(?string $etag = null): Response
	{
		return HTTP::check_cache($this->request, $this->response, $etag);
	}

}
