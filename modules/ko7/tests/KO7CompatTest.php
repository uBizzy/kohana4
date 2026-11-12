<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the KO7 compatibility module.
 *
 * @package    Kohana/KO7
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.ko7')]
class Kohana_KO7CompatTest extends Unittest_TestCase
{
	public function test_core_class_is_kohana(): void
	{
		$this->assertSame(Kohana::VERSION, KO7::VERSION);
		$this->assertSame(Kohana::$environment, KO7::$environment);
		$this->assertSame(Kohana::find_file('classes', 'Kohana/Arr'), KO7::find_file('classes', 'Kohana/Arr'));

		// One class under two names: a change through one is seen through the other
		$this->setEnvironment(['Kohana::$charset' => 'iso-8859-1']);
		$this->assertSame('iso-8859-1', KO7::$charset);
	}

	/**
	 * Provides old names and the classes or interfaces they stand for.
	 *
	 * @return array
	 */
	public static function provider_aliases(): array
	{
		return [
			['KO7', 'Kohana'],
			['KO7_Core', 'Kohana_Core'],
			['KO7_Arr', 'Kohana_Arr'],
			['KO7_Request', 'Kohana_Request'],
			['KO7_Controller', 'Kohana_Controller'],
			['KO7_HTTP_Exception_404', 'Kohana_HTTP_Exception_404'],
			['KO7_Exception', 'Kohana_Exception'],
			['KO7_KO7_Exception', 'Kohana_Kohana_Exception'],
			['KO7_Config_Reader', 'Kohana_Config_Reader'],
			['KO7_Database_Query_Builder_Select', 'Kohana_Database_Query_Builder_Select'],
			['KO7_ORM', 'Kohana_ORM'],
		];
	}

	#[DataProvider('provider_aliases')]
	public function test_old_names_are_aliases(string $old, string $new): void
	{
		$this->assertTrue(class_exists($old) OR interface_exists($old), $old.' does not exist');
		$this->assertSame($new, (new ReflectionClass($old))->getName());
	}

	public function test_static_calls_work(): void
	{
		$this->assertSame('bar', KO7_Arr::get(['foo' => 'bar'], 'foo'));
		$this->assertSame('1st', '1'.KO7_Num::ordinal(1));
	}

	/**
	 * Old code extends the old names, and type-hints with them.
	 */
	public function test_classes_can_extend_and_type_hint_old_names(): void
	{
		$child = new Kohana_KO7CompatTest_Validation(['a' => 1]);

		$this->assertInstanceOf('Kohana_Validation', $child);
		$this->assertInstanceOf('KO7_Validation', $child);
		$this->assertSame(['a' => 1], $child->data());

		// An object created by the framework passes a check against the old name
		$request = new Request('welcome/index');

		$this->assertInstanceOf('KO7_Request', $request);
		$this->assertSame('welcome/index', Kohana_KO7CompatTest_Validation::uri_of($request));
	}

	public function test_exceptions_can_be_caught_by_old_name(): void
	{
		try
		{
			throw new Kohana_Exception('test');
		}
		catch (KO7_Exception $e)
		{
			$this->assertSame('test', $e->getMessage());
			return;
		}

		$this->fail('The exception was not caught under its old name');
	}

	public function test_unknown_names_are_not_invented(): void
	{
		$this->assertFalse(class_exists('KO7_No_Such_Class'));
		$this->assertFalse(class_exists('KO7SomethingElse'));
	}

	public function test_constants(): void
	{
		$this->assertSame(KOHANA_START_TIME, KO7_START_TIME);
		$this->assertSame(KOHANA_START_MEMORY, KO7_START_MEMORY);
	}
}

/**
 * A class written the old way: it extends an old name and type-hints with one.
 */
class Kohana_KO7CompatTest_Validation extends KO7_Validation
{
	public static function uri_of(KO7_Request $request): string
	{
		return $request->uri();
	}
}
