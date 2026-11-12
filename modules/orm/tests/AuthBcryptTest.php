<?php

use PHPUnit\Framework\Attributes\Group;

require_once __DIR__.'/AuthORMTest.php';

/**
 * Runs the tests of the ORM auth driver on the Bcrypt driver, which uses
 * the same tables. Only the handling of old password hashes differs.
 *
 * @package    Kohana/ORM
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.orm')]
#[Group('kohana.auth')]
class Kohana_AuthBcryptTest extends Kohana_AuthORMTest
{
	protected function driver(): string
	{
		return 'Kohana_AuthBcryptTest_Auth';
	}

	public function test_new_passwords_are_stored_with_password_hash(): void
	{
		$stored = $this->user()->password;

		$this->assertTrue(password_verify('correct horse', $stored));
		$this->assertSame('bcrypt', password_get_info($stored)['algoName']);
		$this->assertSame(10, password_get_info($stored)['options']['cost']);
	}

	/**
	 * This driver never knew the old HMAC hashes: such a hash logs nobody in.
	 */
	public function test_old_hash_is_accepted_once_and_upgraded(): void
	{
		$user   = $this->user();
		$legacy = hash_hmac('sha256', 'correct horse', self::OLD_KEY);

		DB::update('users')->set(['password' => $legacy])->where('id', '=', $user->pk())->execute();

		$this->assertFalse($this->auth->login('ana', 'correct horse'));
		$this->assertSame($legacy, ORM::factory('User', $user->pk())->password);
	}

	/**
	 * A hash made with a lower cost is replaced at the next login.
	 */
	public function test_hash_with_a_lower_cost_is_upgraded(): void
	{
		$user = $this->user();
		$weak = password_hash('correct horse', PASSWORD_BCRYPT, ['cost' => 4]);

		DB::update('users')->set(['password' => $weak])->where('id', '=', $user->pk())->execute();

		$this->assertTrue($this->auth->login('ana', 'correct horse'));

		$stored = ORM::factory('User', $user->pk())->password;

		$this->assertSame(10, password_get_info($stored)['options']['cost']);
		$this->assertTrue(password_verify('correct horse', $stored));
	}

	public function test_cost_must_be_configured(): void
	{
		$this->expectException(Kohana_Exception::class);

		new Auth_Bcrypt(['cost' => 4]);
	}
}

/**
 * The Bcrypt driver, with the counter.
 */
class Kohana_AuthBcryptTest_Auth extends Auth_Bcrypt
{
	use Kohana_AuthORMTest_Counter;
}
