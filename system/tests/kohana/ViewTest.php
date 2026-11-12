<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the View class
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
#[Group('kohana.core.view')]
class Kohana_ViewTest extends Unittest_TestCase
{
	protected static $old_modules = [];

	/**
	 * Setups the filesystem for test view files
	 *
	 * @return null
	 */
	// @codingStandardsIgnoreStart
	public static function setupBeforeClass() : void
	// @codingStandardsIgnoreEnd
	{
		self::$old_modules = Kohana::modules();

		$new_modules = self::$old_modules+[
			'test_views' => realpath(dirname(__FILE__).'/../test_data/')
		];
		Kohana::modules($new_modules);
	}

	/**
	 * Restores the module list
	 *
	 * @return null
	 */
	// @codingStandardsIgnoreStart
	public static function teardownAfterClass() : void
	// @codingStandardsIgnoreEnd
	{
		Kohana::modules(self::$old_modules);
	}

	/**
	 * Provider for test_instaniate
	 *
	 * @return array
	 */
	public static function provider_instantiate()
	{
		return [
			['kohana/error', false],
			['test.css', false],
			['doesnt_exist', true],
		];
	}

	/**
	 * Provider to test_set
	 *
	 * @return array
	 */
	public static function provider_set()
	{
		return [
			['foo', 'bar', 'foo', 'bar'],
			[['foo' => 'bar'], null, 'foo', 'bar'],
			[new ArrayIterator(['foo' => 'bar']), null, 'foo', 'bar'],
		];
	}

	/**
	 * Tests that we can instantiate a view file
	 *
	 * @return null
	 */
	#[DataProvider('provider_instantiate')]
	public function test_instantiate($path, $expects_exception)
	{
		try
		{
			$view = new View($path);
			$this->assertSame(false, $expects_exception);
		}
		catch(View_Exception $e)
		{
			$this->assertSame(true, $expects_exception);
		}
	}

	/**
	 * Tests that we can set using string, array or Traversable object
	 *
	 * @return null
	 */
	#[DataProvider('provider_set')]
	public function test_set($data_key, $value, $test_key, $expected)
	{
		$view = View::factory()->set($data_key, $value);
		$this->assertSame($expected, $view->$test_key);
	}

	/**
	 * Tests that we can set global using string, array or Traversable object
	 *
	 * @return null
	 */
	#[DataProvider('provider_set')]
	public function test_set_global($data_key, $value, $test_key, $expected)
	{
		$view = View::factory();
		$view::set_global($data_key, $value);
		$this->assertSame($expected, $view->$test_key);
	}
}
