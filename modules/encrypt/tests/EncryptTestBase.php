<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test Base for Encryption Tests. Encryption tests need to extend this Class.
 * Checks simple encode and decode function and Provides Keys
 *
 * @package    Kohana/Encrypt
 * @category   Test
 *
 * @copyright  (c) 2007-2012 Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.encrypt')]
abstract class EncryptTestBase extends Unittest_TestCase {

	/**
	 * 128 bit encryption key
	 * @var string
	 */
	const KEY16 = '0123456789012345';

	/**
	 * 256 bit encryption key
	 * @var string
	 */
	const KEY32 = '01234567890123456789012345678901';

	/**
	 * Should be called from every test
	 *
	 * @return void
	 */
	public function setUp(): void
	{
		parent::setUp();

		// clear instances
		Encrypt::$instances = [];
	}

	/**
	 * Checks if encoded string is same after decoding
	 * WARNING: This could also pass if encryption is not
	 * implemented correctly! Please ensure you do KAT tests!
	 *
	 * @param string $plaintext
	 *
	 * @return void
	 * @throws Kohana_Exception
	 */
	#[DataProvider('provider_encode_and_decode')]
	public function test_encode_and_decode(string $plaintext)
	{
		$instance = Encrypt::instance();
		$this->assertEquals($plaintext, $instance->decode($instance->encode($plaintext)));
	}

	/**
	 * Test Encrypt class initialization
	 *
	 * @return void
	 * @throws Kohana_Exception
	 */
	public function test_initialization()
	{
		$this->assertCount(0, Encrypt::$instances);
		Encrypt::instance();
		Encrypt::instance(Encrypt::$default);
		$this->assertCount(1, Encrypt::$instances);
		$this->assertArrayHasKey('default', Encrypt::$instances);

		$this->set_config(Kohana::$config->load('encrypt')->default, 'secondary');
		Encrypt::instance('secondary');
		$this->assertCount(2, Encrypt::$instances);
		$this->assertArrayHasKey('secondary', Encrypt::$instances);
	}

	/**
	 * Overwrites Configuration Values
	 *
	 * @param array       $config
	 * @param string|null $name
	 *
	 * @return void
	 * @throws Kohana_Exception
	 */
	public function set_config(array $config, ?string $name = null)
	{
		if ($name === null) {
			$name = Encrypt::$default;
		}
		Kohana::$config->load('encrypt')->set($name, $config);
	}

	/**
	 * Data source for encode_and_decode test
	 *
	 * @return array
	 */
	public static function provider_encode_and_decode(): array
	{
		return [
			[
				'abcdefghijklm',
			],
			[
				'777888000',
			],
			[
				'verylongTEXTwithSTUFFandnumbers0123456789',
			],
		];
	}

	/**
	 * Provides ciphertexts that are malformed in different ways.
	 *
	 * @return array
	 */
	public static function provider_decode_malformed(): array
	{
		return [
			'empty string'      => [''],
			'not base64'        => ['%%% not base64 %%%'],
			'not json'          => [base64_encode('not json')],
			'json but no array' => [base64_encode(json_encode('text'))],
			'missing parts'     => [base64_encode(json_encode(['iv' => 'x']))],
			'parts not strings' => [base64_encode(json_encode(['iv' => ['a'], 'value' => ['b'], 'mac' => ['c']]))],
		];
	}

	/**
	 * Anything that is not a ciphertext we produced must decode to null,
	 * without raising an error.
	 */
	#[DataProvider('provider_decode_malformed')]
	public function test_decode_malformed_returns_null(string $ciphertext): void
	{
		$this->assertNull(Encrypt::instance()->decode($ciphertext));
	}

	/**
	 * A ciphertext changed after encryption must be rejected.
	 */
	public function test_decode_tampered_returns_null(): void
	{
		$encrypt = Encrypt::instance();
		$payload = json_decode(base64_decode($encrypt->encode('secret message')), true);

		// Replace the encrypted value by another valid base64 string
		$payload['value'] = base64_encode('tampered with');

		$this->assertNull($encrypt->decode(base64_encode(json_encode($payload))));
	}

	/**
	 * Serializing an engine would write the key into the session or cache.
	 */
	public function test_engine_cannot_be_serialized(): void
	{
		$this->expectException(Kohana_Exception::class);

		serialize(Encrypt::instance());
	}

	/**
	 * A class that is not an encryption engine is refused with a clear
	 * message, not with a type error.
	 */
	public function test_class_that_is_no_engine_is_refused(): void
	{
		// Encrypt builds the class name as Encrypt_Engine_<type>: the class
		// at the end of this file
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('is not a valid type/driver class');

		new Encrypt('test_not_an_engine', ['type' => 'notanengine', 'key' => EncryptTestBase::KEY32]);
	}
}

/**
 * A class with the name of an engine that is not one.
 */
class Encrypt_Engine_Notanengine
{
	public function __construct(array $config)
	{
	}
}
