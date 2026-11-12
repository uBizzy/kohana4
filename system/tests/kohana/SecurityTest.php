<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Kohana_Security
 *
 * @package    Kohana
 * @category   Tests
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.security')]
class Kohana_SecurityTest extends Unittest_TestCase
{
	/**
	 * Provides test data for test_envode_php_tags()
	 *
	 * @return array Test data sets
	 */
	public static function provider_encode_php_tags()
	{
		return [
			["&lt;?php echo 'helloo'; ?&gt;", "<?php echo 'helloo'; ?>"],
		];
	}

	/**
	 * Tests Security::encode_php_tags()
	 *
	 * @covers Security::encode_php_tags
	 */
	#[DataProvider('provider_encode_php_tags')]
	public function test_encode_php_tags($expected, $input)
	{
		$this->assertSame($expected, Security::encode_php_tags($input));
	}

	/**
	 * Provides test data for Security::token()
	 *
	 * @return array Test data sets
	 */
	public static function provider_csrf_token()
	{
		$array = [];
		for ($i = 0; $i <= 4; $i++)
		{
			$id = uniqid('', false);
			Security::$token_name = 'token_'.$id;
			$array[] = [Security::token(true), Security::check(Security::token()), $id];
		}
		return $array;
	}

	/**
	 * Tests Security::token()
	 *
	 * @covers Security::token
	 */
	#[DataProvider('provider_csrf_token')]
	public function test_csrf_token($expected, $input, $iteration)
	{
		//@todo: the Security::token tests need to be reviewed to check how much of the logic they're actually covering
		Security::$token_name = 'token_'.$iteration;
		self::assertTrue($input);
		self::assertSame($expected, Security::token());
		Session::instance()->delete(Security::$token_name);
	}
}
