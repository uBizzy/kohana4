<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Kohana i18n class
 *
 * @package    Kohana
 * @category   Tests
 *
 * , Jeremy Bush <contractfrombelow@gmail.com>
 * @copyright  (c) 2008´- 2016 Kohana Team
 * @copyright  (c) 2018-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.i18n')]
class Kohana_I18nTest extends Unittest_TestCase {

	/**
	 * Default values for the environment, see setEnvironment
	 * @var array
	 */
	protected array $environmentDefault =	[
		'I18n::$lang' => 'en-us'
	];

	/**
	 * Provides test data for test_lang()
	 *
	 * @return array
	 */
	public static function provider_lang() : array
	{
		return [
			// $input, $expected_result
			[
				null, 'en-us'
			],
			[
				'es-es', 'es-es'
			],
		];
	}

	/**
	 * Tests I18n::lang()
	 *
	 * @param  boolean  $input     Input for I18n::lang
	 * @param  boolean  $expected  Output for I18n::lang
	 */
	#[DataProvider('provider_lang')]
	public function test_lang($input, $expected)
	{
		self::assertSame($expected, I18n::lang($input));
		self::assertSame($expected, I18n::lang());
	}

	/**
	 * Provides test data for test_get()
	 *
	 * @return array
	 */
	public static function provider_get() : array
	{
		return [
			// $lang, $input, $expected
			['en-us', 'Hello, world!', 'Hello, world!'],
			['es-es', 'Hello, world!', '¡Hola, mundo!'],
			['fr-fr', 'Hello, world!', 'Bonjour, monde!'],
		];
	}

	/**
	 * Tests i18n::get()
	 *
	 * @param string $lang      Target Language to translate to
	 * @param string $input     Input Translation String
	 * @param string $expected  Expected Result
	 */
	#[DataProvider('provider_get')]
	public function test_get(string $lang, string $input, string $expected)
	{
		// Set Language
		I18n::lang($lang);

		// Test I18n::get function
		self::assertSame($expected, I18n::get($input));

		// Test I18n::get function with source language same as target language (we don't expect to be translated)
		self::assertSame($input, I18n::get($input, $lang, $lang));

		// Test __() function
		self::assertSame($expected, __($input));

		// Test __() function with source language (Note: we don't expect the string to be translated)
		self::assertSame($input, __($input, null, $lang));

		// Test I18n::get function with target language passed as variable
		I18n::lang('en-us');
		self::assertSame($expected, I18n::get($input, $lang));
	}

	/**
	 * @return array
	 */
	public static function provider_get_values() :array
	{
		return [
			// $lang, $input, $values, $expected
			['en-us', 'Good Morning :name!', [':name' => 'Koseven'], 'Good Morning Koseven!'],
			['es-es', 'Good Morning :name!', [':name' => 'Koseven'], 'Buenos dias Koseven!'],
			['fr-fr', 'Good Morning :name!', [':name' => 'Koseven'], 'Bonjour Koseven!'],
		];
	}

	/**
	 * Tests i18n::get with values passed to replace
	 *
	 * @param string $lang      Target Language to translate to
	 * @param string $input     Input Translation String
	 * @param array  $values    Values to replace in translated string
	 * @param string $expected  Expected Result
	 */
	#[DataProvider('provider_get_values')]
	public function test_get_values(string $lang, string $input, array $values, string $expected)
	{
		// Set Language
		I18n::lang($lang);

		// Test I18n::get function with values to replace
		self::assertSame($expected, I18n::get([$input, $values]));

		// Test __() function with values to replace
		self::assertSame($expected, __($input, $values));

		// Don't expect string to be translated but expect values to be re-placed
		self::assertSame('Good Morning Koseven!', I18n::get([$input, $values], $lang, $lang));
	}

}
