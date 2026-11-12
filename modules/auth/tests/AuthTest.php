<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests password hashing and checking in Auth.
 *
 * @package    Kohana/Auth
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.auth')]
class Kohana_AuthTest extends Unittest_TestCase
{
	/**
	 * Creates a file-based Auth object without starting a session.
	 *
	 * @param   array  $config  auth configuration
	 * @return  Auth
	 */
	protected function auth(array $config = []): Auth
	{
		$class = new ReflectionClass('Kohana_AuthTest_Auth');
		$auth  = $class->newInstanceWithoutConstructor();

		$config += ['hash_method' => 'sha256', 'hash_key' => null, 'users' => []];

		$class->getProperty('_config')->setValue($auth, $config + ['session_key' => 'auth_user']);
		$class->getProperty('_users')->setValue($auth, $config['users']);
		$class->getProperty('_session')->setValue($auth, new Kohana_AuthTest_Session);

		return $auth;
	}

	public function test_hash_is_salted_and_verifiable(): void
	{
		$auth = $this->auth();

		$first  = $auth->hash('correct horse');
		$second = $auth->hash('correct horse');

		$this->assertNotSame($first, $second, 'Two hashes of one password must differ (random salt)');
		$this->assertLessThanOrEqual(64, strlen($first), 'The hash must fit the 64-character password column');

		$this->assertTrue($auth->verify('correct horse', $first));
		$this->assertTrue($auth->verify('correct horse', $second));
		$this->assertFalse($auth->verify('wrong', $first));
		$this->assertFalse($auth->needs_rehash($first));
	}

	/**
	 * Hashes stored before version 4.0 keep working and are marked for replacement.
	 */
	public function test_legacy_hmac_hashes_are_accepted_and_flagged(): void
	{
		$auth   = $this->auth(['hash_key' => 'old-site-key']);
		$legacy = hash_hmac('sha256', 'correct horse', 'old-site-key');

		$this->assertSame($legacy, $auth->legacy_hash('correct horse'));
		$this->assertTrue($auth->verify('correct horse', $legacy));
		$this->assertFalse($auth->verify('wrong', $legacy));
		$this->assertTrue($auth->needs_rehash($legacy));

		// Without the old key, an old hash can never match
		$this->assertFalse($this->auth()->verify('correct horse', $legacy));
	}

	public function test_empty_hash_never_matches(): void
	{
		$this->assertFalse($this->auth(['hash_key' => 'k'])->verify('', ''));
		$this->assertFalse($this->auth()->verify('anything', ''));
	}

	/**
	 * Input from a login form can be anything: only strings can log in.
	 */
	public function test_login_refuses_input_that_is_not_text(): void
	{
		$auth = $this->auth();

		foreach ([null, '', [], ['a'], false, 123] as $password)
		{
			$this->assertFalse($auth->login('admin', $password));
		}

		$this->assertFalse($auth->login(['admin'], 'password'));
		$this->assertFalse($auth->login(null, 'password'));
	}

	/**
	 * An Auth_File object with one user, "admin".
	 */
	protected function with_admin(): Auth
	{
		return $this->auth(['users' => ['admin' => password_hash('correct horse', PASSWORD_DEFAULT)]]);
	}

	public function test_file_login_and_logout(): void
	{
		$auth = $this->with_admin();

		$this->assertFalse($auth->logged_in());
		$this->assertFalse($auth->login('admin', 'wrong'));
		$this->assertFalse($auth->logged_in());

		$this->assertTrue($auth->login('admin', 'correct horse'));
		$this->assertTrue($auth->logged_in());
		$this->assertSame('admin', $auth->get_user());

		$this->assertTrue($auth->logout());
		$this->assertFalse($auth->logged_in());
		$this->assertNull($auth->get_user());
		$this->assertSame('nobody', $auth->get_user('nobody'));

		// Logging out can also end the whole session
		$auth->login('admin', 'correct horse');
		$this->assertTrue($auth->logout(true));
		$this->assertFalse($auth->logged_in());
	}

	/**
	 * A login with a name that is not in the list fails after a password
	 * check, so it takes as long as a wrong password.
	 */
	public function test_file_login_of_unknown_user_checks_a_password(): void
	{
		$auth = $this->with_admin();

		$this->assertFalse($auth->login('nobody', 'correct horse'));
		$this->assertSame(1, $auth->fake_checks);
		$this->assertFalse($auth->logged_in());

		$this->assertFalse($auth->login('admin', 'wrong'));
		$this->assertSame(1, $auth->fake_checks);
	}

	public function test_file_login_with_an_old_hash(): void
	{
		$auth = $this->auth([
			'hash_key' => 'old-site-key',
			'users'    => ['admin' => hash_hmac('sha256', 'correct horse', 'old-site-key')],
		]);

		$this->assertFalse($auth->login('admin', 'wrong'));
		$this->assertTrue($auth->login('admin', 'correct horse'));
	}

	public function test_file_driver_has_no_remember_me(): void
	{
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('does not support remember');

		$this->with_admin()->login('admin', 'correct horse', true);
	}

	public function test_file_force_login_needs_a_known_user(): void
	{
		$auth = $this->with_admin();

		$this->assertTrue($auth->force_login('admin'));
		$this->assertSame('admin', $auth->get_user());

		$auth->logout();

		try
		{
			$auth->force_login('nobody');
			$this->fail('A forced login of an unknown user must fail');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('user was not found', $e->getMessage());
		}

		$this->assertFalse($auth->logged_in());
	}

	public function test_file_password_and_check_password(): void
	{
		$auth   = $this->with_admin();
		$stored = $auth->password('admin');

		$this->assertTrue(password_verify('correct horse', $stored));
		$this->assertFalse($auth->password('nobody'));
		$this->assertFalse($auth->password(['admin']));

		// Nobody is logged in: there is no password to compare with
		$this->assertFalse($auth->check_password('correct horse'));

		$auth->login('admin', 'correct horse');

		$this->assertTrue($auth->check_password('correct horse'));
		$this->assertFalse($auth->check_password('wrong'));
		$this->assertFalse($auth->check_password(''));

		// The stored hash is not the password: reading the config file is not enough
		$this->assertFalse($auth->check_password($stored));
	}

	public function test_legacy_hash_needs_a_key(): void
	{
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('A valid hash key must be set');

		$this->auth()->legacy_hash('correct horse');
	}
}

/**
 * The file driver, counting the password checks made for unknown users.
 */
class Kohana_AuthTest_Auth extends Auth_File
{
	/**
	 * @var int  Number of checks against a made-up hash
	 */
	public int $fake_checks = 0;

	protected function _fake_verify(string $password): void
	{
		$this->fake_checks++;

		parent::_fake_verify($password);
	}
}

/**
 * A session kept in memory, so that logging in can be tested from the
 * command line without cookies.
 */
class Kohana_AuthTest_Session extends Session
{
	public function __construct()
	{
	}

	protected function _read(?string $id = null): ?string
	{
		return null;
	}

	protected function _regenerate(): ?string
	{
		return null;
	}

	protected function _write(): bool
	{
		return true;
	}

	protected function _destroy(): bool
	{
		return true;
	}

	protected function _restart(): bool
	{
		return true;
	}
}
