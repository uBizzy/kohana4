<?php

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * A version of the stock PHPUnit testcase that includes some extra helpers
 * and default settings
 *
 * @package    Kohana/UnitTest
 *
 *
 * @copyright  (c) 2007-2012 Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Unittest_TestCase extends TestCase {

	/**
	 * A set of Unittest helpers that are shared between normal / database testcases
	 * @var Kohana_Unittest_Helpers
	 */
	protected ?Kohana_Unittest_Helpers $_helpers = null;

	//Workaround for test
	private ?float $requestTimeFloat = null;
	private ?int $requestTime = null;
	/**
	 * A default set of environment to be applied before each test
	 * @var array<string, mixed>
	 */
	protected array $environmentDefault = [];

	/**
	 * Creates a predefined environment using the default environment
	 *
	 * Extending classes that have their own setUp() should call
	 * parent::setUp()
	 *
	 * @throws Kohana_Exception
	 * @throws ReflectionException
	 */
	public function setUp(): void
	{
		$this->requestTimeFloat = is_float($_SERVER['REQUEST_TIME_FLOAT'] ?? null) ? $_SERVER['REQUEST_TIME_FLOAT'] : null;
		$this->requestTime = is_int($_SERVER['REQUEST_TIME'] ?? null) ? $_SERVER['REQUEST_TIME'] : null;

		$this->_helpers = new Unittest_Helpers;

		// Make sure PHPUnit does not backup globals
		$this->setBackupGlobals(false);

		$this->setEnvironment($this->environmentDefault);

		parent::setUp();
	}

	/**
	 * Restores the original environment overridden with setEnvironment()
	 *
	 * Extending classes that have their own tearDown()
	 * should call parent::tearDown()
	 *
	 * @throws Kohana_Exception
	 * @throws ReflectionException
	 */
	public function tearDown(): void
	{
		// setUp() may have been left early (a skipped test), before the helpers were made
		$this->_helpers?->restore_environment();
		$_SERVER['REQUEST_TIME_FLOAT'] = $this->requestTimeFloat;
		$_SERVER['REQUEST_TIME'] = $this->requestTime;
		parent::tearDown();
	}

	/**
	 * Removes all koseven related cache files in the cache directory
	 */
	public function cleanCacheDir(): void
	{
		Unittest_Helpers::clean_cache_dir();
	}

	/**
	 * Helper function that replaces all occurrences of '/' with
	 * the OS-specific directory separator
	 *
	 * @param  string $path The path to act on
	 * @return string
	 *
	 * @codeCoverageIgnore Gets Tested with Helpers test
	 */
	public function dirSeparator(string $path): string
	{
		return Unittest_Helpers::dir_separator($path);
	}

	/**
	 * Allows easy setting & backing up of Environment config
	 *
	 * Option types are checked in the following order:
	 *
	 * - Server Var
	 * - Static Variable
	 * - Config option
	 *
	 * @param  array<string, mixed> $environment List of environment to set
	 *
	 * @return bool
	 * @throws Kohana_Exception
	 * @throws ReflectionException
	 */
	public function setEnvironment(array $environment): bool
	{
		$this->_helpers ??= new Unittest_Helpers;

		return $this->_helpers->set_environment($environment);
	}

	/**
	 * Check for internet connectivity
	 *
	 * @return bool Whether an internet connection is available
	 */
	public function hasInternet(): bool
	{
		return Unittest_Helpers::has_internet();
	}

	/**
	 * Returns a mock of an abstract class: its abstract methods (and the
	 * methods named in $methods) are replaced, everything else is the
	 * real code of the class.
	 *
	 * PHPUnit removed its method of this name in version 12; this one
	 * keeps existing tests working.
	 *
	 *     $session = $this->getMockForAbstractClass('Session', [$config]);
	 *
	 * @param   class-string  $class  name of the abstract class
	 * @param   array<int, mixed>   $arguments    arguments for the constructor
	 * @param   bool    $constructor  call the constructor of the class?
	 * @param   array<int, string>   $methods      other methods to replace
	 * @return  MockObject
	 */
	protected function getMockForAbstractClass(string $class, array $arguments = [], bool $constructor = true, array $methods = []): MockObject
	{
		foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_ABSTRACT) as $method)
		{
			$methods[] = $method->getName();
		}

		$builder = $this->getMockBuilder($class)->onlyMethods(array_values(array_unique(array_filter($methods))));

		if ($constructor)
		{
			$builder->setConstructorArgs($arguments);
		}
		else
		{
			$builder->disableOriginalConstructor();
		}

		return $builder->getMock();
	}

	/**
	 * Evaluate an HTML or XML string and assert its structure and/or contents.
	 *
	 * @param array<string, mixed>  $matcher
	 * @param string $actual
	 * @param string $message
	 * @param bool   $isHtml
	 *
	 * @deprecated since 4.0
	 */
	public static function assertTag(array $matcher, string $actual, ?string $message = null, ?bool $isHtml = null): void
	{
		Kohana::deprecated('4.0');
		$matched = static::tag_match($matcher, $actual, $isHtml ?? true);
		static::assertTrue($matched, $message ?? '');
	}

	/**
	 * Helper function to match HTML string tags against certain criteria
	 *
	 * @param array<string, mixed>  $matcher
	 * @param string $actual
	 * @param bool 	 $isHtml
	 *
	 * @return bool true if there is a match false otherwise
	 *
	 * @deprecated since 4.0
	 */
	protected static function tag_match(array $matcher, string $actual, ?bool $isHtml = null): bool
	{
		Kohana::deprecated('4.0');
		$document = new DOMDocument;

		// The HTML of a test is often a fragment: parser warnings are of no interest here
		$loaded = ($isHtml ?? true)
			? @$document->loadHTML($actual, LIBXML_NOERROR | LIBXML_NOWARNING)
			: @$document->loadXML($actual, LIBXML_NOERROR | LIBXML_NOWARNING);

		return $loaded AND $document->getElementsByTagName(is_string($matcher['tag'] ?? null) ? $matcher['tag'] : '')->length > 0;
	}
}
