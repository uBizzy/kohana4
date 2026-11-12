<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for internal request client
 *
 * @package    Kohana
 * @category   Tests
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.request')]
#[Group('kohana.core.request.client')]
#[Group('kohana.core.request.client.internal')]
class Kohana_Request_Client_InternalTest extends Unittest_TestCase
{

	protected $_log_object;

	// @codingStandardsIgnoreStart
	public function setUp(): void
	// @codingStandardsIgnoreEnd
	{
		parent::setUp();

		// temporarily save $log object
		$this->_log_object = Kohana::$log;
		Kohana::$log = null;
	}

	// @codingStandardsIgnoreStart
	public function tearDown(): void
	// @codingStandardsIgnoreEnd
	{
		// re-assign log object
		Kohana::$log = $this->_log_object;

		parent::tearDown();
	}

	public static function provider_response_failure_status()
	{
		return [
			['', 'Welcome', 'missing_action', 'Welcome/missing_action', 404],
			['Kohana', 'missing_controller', 'index', 'Kohana/missing_controller/index', 404],
			['', 'Template', 'missing_action', 'Kohana/Template/missing_action', 500],
		];
	}

	/**
	 * Tests for correct exception messages
	 *
	 * @return null
	 */
	#[DataProvider('provider_response_failure_status')]
	public function test_response_failure_status($directory, $controller, $action, $uri, $expected)
	{
		// PHPUnit cannot double a Request (it has a method named "method"):
		// a real one is made without running its constructor, which would
		// look for a matching route
		$request = (new ReflectionClass('Request'))->newInstanceWithoutConstructor();

		$request->directory($directory);
		$request->controller($controller);
		$request->action($action);
		$request->uri($uri);

		$internal_client = new Request_Client_Internal;

		$response = $internal_client->execute($request);

		$this->assertSame($expected, $response->status());
	}
}
