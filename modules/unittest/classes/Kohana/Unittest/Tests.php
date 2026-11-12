<?php
/**
 * Prepares the environment in which the tests run.
 *
 * The tests themselves are found by PHPUnit, from the directories listed
 * in phpunit.xml.
 *
 * @package    Kohana/UnitTest
 * @author     Koseven Team, Paul Banks, BRMatt <matthew@sigswitch.com>
 * @copyright  (c) 2007-2012 Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Unittest_Tests {

	/**
	 * Loads the classes that tests share and that are not tests themselves
	 * (base classes, helpers), from the "tests" directories:
	 * `Cache_BasicTestCase` is `tests/Cache/BasicTestCase.php`.
	 *
	 * @param   string  $class  name of the class
	 * @return  void
	 */
	public static function autoload(string $class): void
	{
		$file = Kohana::find_file('tests', str_replace('_', '/', $class));

		if ($file)
		{
			require_once $file;
		}
	}

	/**
	 * Configures the environment for testing:
	 *
	 * - removes the framework's error and exception handlers, so that
	 *   PHPUnit reports errors itself
	 * - registers the autoloader of the shared test classes
	 *
	 * @return  void
	 */
	public static function configure_environment(): void
	{
		restore_exception_handler();
		restore_error_handler();

		spl_autoload_register([Unittest_Tests::class, 'autoload']);
	}

}
