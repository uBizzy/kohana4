<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ORM auth driver against a real database, using the tables
 * of the migration that ships with the module: login, forced login, roles,
 * the upgrade of old password hashes, "remember me" tokens and the user
 * model.
 *
 * Runs on SQLite in memory, so it needs no server. AuthORMMysqlTest and
 * AuthORMPgsqlTest run the same tests on those servers, AuthBcryptTest
 * on the Bcrypt driver.
 *
 * @package    Kohana/ORM
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.orm')]
#[Group('kohana.auth')]
class Kohana_AuthORMTest extends Unittest_TestCase
{
	/**
	 * @var string  Site key that old installations used to hash passwords
	 */
	const OLD_KEY = 'old-site-key';

	/**
	 * @var string  Browser name of the visitor in these tests
	 */
	const BROWSER = 'Test Browser 1.0';

	/**
	 * @var Auth_ORM|Auth_Bcrypt  The driver being tested
	 */
	protected $auth;

	/**
	 * @var Kohana_AuthORMTest_Session  The session of the driver
	 */
	protected $session;

	/**
	 * @var Database  The database of the auth tables
	 */
	protected $db;

	/**
	 * Returns the settings of the test database, or null when it is not
	 * available. Other test classes extend this one for another database.
	 */
	protected function config(): ?array
	{
		if ( ! extension_loaded('pdo_sqlite'))
			return null;

		return [
			'type'         => 'PDO',
			'connection'   => ['dsn' => 'sqlite::memory:'],
			'identifier'   => '"',
			'table_prefix' => '',
		];
	}

	/**
	 * The migration of the auth tables, as shipped.
	 */
	protected function migration(): Migration
	{
		return Kohana::load(MODPATH.'orm/auth/create_auth_tables.php');
	}

	/**
	 * Closes the default database and makes ORM forget its tables.
	 */
	protected function forget_database(): void
	{
		if (isset(Database::$instances['default']))
		{
			Database::$instances['default']->disconnect();
			unset(Database::$instances['default']);
		}

		(new ReflectionProperty('ORM', '_column_cache'))->setValue(null, []);
		(new ReflectionProperty('ORM', '_init_cache'))->setValue(null, []);
	}

	/**
	 * The driver class to test: one that counts the password checks made
	 * for unknown users.
	 */
	protected function driver(): string
	{
		return 'Kohana_AuthORMTest_Auth';
	}

	/**
	 * Creates the auth tables in the default database and an Auth_ORM
	 * object that keeps its session in memory.
	 */
	public function setUp(): void
	{
		parent::setUp();

		$config = $this->config();

		if ($config === null)
		{
			$this->markTestSkipped('The database for '.static::class.' is not configured or its extension is missing.');
		}

		// The auth models use the default database: a fresh one for every
		// test, and ORM forgets the tables of another database
		$this->forget_database();

		$this->db = Database::instance('default', $config);

		foreach (['user_tokens', 'roles_users', 'roles', 'users'] as $table)
		{
			$this->db->query(null, 'DROP TABLE IF EXISTS '.$table);
		}

		// Create the tables with the migration that ships with the module
		$this->migration()->up($this->db);

		// An Auth_ORM object whose session lives in memory
		$class         = new ReflectionClass($this->driver());
		$this->auth    = $class->newInstanceWithoutConstructor();
		$this->session = new Kohana_AuthORMTest_Session;

		$class->getProperty('_config')->setValue($this->auth, [
			'hash_method' => 'sha256',
			'hash_key'    => self::OLD_KEY,
			'lifetime'    => 1209600,
			'session_key' => 'auth_user',
			'cost'        => 10,
		]);
		$class->getProperty('_session')->setValue($this->auth, $this->session);

		// The user model hashes passwords through Auth::instance()
		(new ReflectionClass('Auth'))->getProperty('_instance')->setValue(null, $this->auth);

		// A visitor with a browser name and no cookies
		$this->setEnvironment([
			'Cookie::$salt'        => 'salt-of-the-auth-tests',
			'Request::$user_agent' => self::BROWSER,
		]);
		$_COOKIE = [];
	}

	public function tearDown(): void
	{
		(new ReflectionClass('Auth'))->getProperty('_instance')->setValue(null, null);
		$_COOKIE = [];
		$this->forget_database();

		parent::tearDown();
	}

	/**
	 * Creates a user who is allowed to log in.
	 */
	protected function user(string $password = 'correct horse', string $name = 'ana'): Model_User
	{
		$user = ORM::factory('User')
			->values(['username' => $name, 'email' => $name.'@example.com', 'password' => $password])
			->create();

		$user->add('roles', ORM::factory('Role', ['name' => 'login']));

		return $user;
	}

	/**
	 * Gives the visitor the "remember me" cookie of a token, signed as
	 * the browser would send it back.
	 */
	protected function cookie(string $token): void
	{
		$_COOKIE['authautologin'] = Cookie::salt('authautologin', $token).'~'.$token;
	}

	/**
	 * The tokens stored for a user, oldest first.
	 *
	 * @return array<int, string>
	 */
	protected function tokens(Model_User $user): array
	{
		return DB::select('token')
			->from('user_tokens')
			->where('user_id', '=', $user->pk())
			->order_by('id')
			->execute()
			->as_array(null, 'token');
	}

	/**
	 * The migration makes the four tables and the two roles, and takes
	 * them away again.
	 */
	public function test_migration_can_be_applied_and_undone(): void
	{
		$tables = ['roles', 'roles_users', 'user_tokens', 'users'];

		$this->assertSame($tables, array_values(array_intersect($this->db->list_tables(), $tables)));
		$this->assertSame(['admin', 'login'], DB::select('name')->from('roles')->order_by('name')->execute()->as_array(null, 'name'));

		$this->migration()->down($this->db);
		$this->assertSame([], array_intersect($this->db->list_tables(), $tables));

		// Nothing is left behind that stops a second run
		$this->migration()->up($this->db);
		$this->assertSame(2, ORM::factory('Role')->count_all());
	}

	/**
	 * Tables and indexes get the table prefix of the connection.
	 */
	public function test_migration_uses_the_table_prefix(): void
	{
		$db = Database::instance('auth_prefix', ['table_prefix' => 'kohana_auth_'] + $this->config());

		try
		{
			foreach (['user_tokens', 'roles_users', 'roles', 'users'] as $table)
			{
				$db->query(null, 'DROP TABLE IF EXISTS kohana_auth_'.$table);
			}

			// The tables without prefix are there already: no name may clash
			$this->migration()->up($db);

			$this->assertCount(4, $db->list_tables('kohana_auth_%'));
			$this->assertSame(2, (int) DB::select([DB::expr('COUNT(*)'), 'n'])->from('roles')->execute($db)->get('n'));

			$this->migration()->down($db);
			$this->assertCount(0, $db->list_tables('kohana_auth_%'));
		}
		finally
		{
			$db->disconnect();
			unset(Database::$instances['auth_prefix']);
		}
	}

	public function test_login_with_username_or_email(): void
	{
		$this->user();

		$this->assertFalse($this->auth->login('ana', 'wrong'));
		$this->assertFalse($this->auth->logged_in());

		$this->assertTrue($this->auth->login('ana', 'correct horse'));
		$this->assertTrue($this->auth->logged_in());
		$this->assertTrue($this->auth->logged_in('login'));
		$this->assertFalse($this->auth->logged_in('admin'));
		$this->assertSame('ana', $this->auth->get_user()->username);
		$this->assertTrue($this->auth->check_password('correct horse'));
		$this->assertFalse($this->auth->check_password('wrong'));

		$this->assertTrue($this->auth->login('ana@example.com', 'correct horse'));

		// The login was counted
		$this->assertEquals(2, ORM::factory('User', ['username' => 'ana'])->logins);
	}

	public function test_user_without_login_role_cannot_log_in(): void
	{
		ORM::factory('User')
			->values(['username' => 'ana', 'email' => 'ana@example.com', 'password' => 'correct horse'])
			->create();

		$this->assertFalse($this->auth->login('ana', 'correct horse'));
	}

	public function test_new_passwords_are_stored_with_password_hash(): void
	{
		$stored = $this->user()->password;

		$this->assertNotSame('correct horse', $stored);
		$this->assertTrue(password_verify('correct horse', $stored));
	}

	/**
	 * A password stored by an older version (an HMAC hash) still logs in,
	 * and is replaced by a password_hash() hash at that moment.
	 */
	public function test_old_hash_is_accepted_once_and_upgraded(): void
	{
		$user   = $this->user();
		$legacy = hash_hmac('sha256', 'correct horse', self::OLD_KEY);

		// Put the old hash straight into the table, as an old installation has it
		DB::update('users')->set(['password' => $legacy])->where('id', '=', $user->pk())->execute();

		$this->assertFalse($this->auth->login('ana', 'wrong'));
		$this->assertSame($legacy, ORM::factory('User', $user->pk())->password, 'A failed login must not change the hash');

		$this->assertTrue($this->auth->login('ana', 'correct horse'));

		$upgraded = ORM::factory('User', $user->pk())->password;

		$this->assertNotSame($legacy, $upgraded);
		$this->assertTrue(password_verify('correct horse', $upgraded));

		// And it keeps working afterwards
		$this->assertTrue($this->auth->login('ana', 'correct horse'));
	}

	/**
	 * A "remember me" token gets a new value each time it is saved, so
	 * each token can only be used once.
	 */
	public function test_tokens_are_random_and_rotate(): void
	{
		$user = $this->user();

		$token = ORM::factory('User_Token')
			->values(['user_id' => $user->pk(), 'expires' => time() + 1000, 'user_agent' => hash('sha256', 'test')])
			->create();

		$first = $token->token;

		$this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $first);
		$this->assertSame('ana', $token->user->username);

		$token->save();

		$this->assertNotSame($first, $token->token);
		$this->assertFalse(ORM::factory('User_Token', ['token' => $first])->loaded(), 'The old token must no longer exist');
		$this->assertTrue(ORM::factory('User_Token', ['token' => $token->token])->loaded());
	}

	public function test_expired_tokens_are_deleted_when_loaded(): void
	{
		$user = $this->user();

		$token = ORM::factory('User_Token')
			->values(['user_id' => $user->pk(), 'expires' => time() - 10, 'user_agent' => hash('sha256', 'test')])
			->create();

		$this->assertFalse(ORM::factory('User_Token', ['token' => $token->token])->loaded());
		$this->assertSame(0, ORM::factory('User_Token')->count_all());
	}

	/**
	 * A login with a name that does not exist fails, and still checks a
	 * password: the answer must take as long as for a wrong password, or
	 * the response time tells which user names exist.
	 */
	public function test_unknown_user_fails_after_a_password_check(): void
	{
		$this->user();

		$this->assertFalse($this->auth->login('nobody', 'correct horse'));
		$this->assertFalse($this->auth->login('nobody@example.com', 'correct horse'));
		$this->assertSame(2, $this->auth->fake_checks);
		$this->assertFalse($this->auth->logged_in());

		// A known user is checked against the stored hash, not a made-up one
		$this->assertFalse($this->auth->login('ana', 'wrong'));
		$this->assertSame(2, $this->auth->fake_checks);
	}

	/**
	 * What a visitor sends can be anything: only a text can be a name or
	 * a password.
	 */
	public function test_login_refuses_input_that_is_not_text(): void
	{
		$this->user();

		$this->assertFalse($this->auth->login('ana', ''));
		$this->assertFalse($this->auth->login('ana', ['correct horse']));
		$this->assertFalse($this->auth->login(['ana'], 'correct horse'));
		$this->assertFalse($this->auth->login(null, 'correct horse'));
		$this->assertFalse($this->auth->logged_in());
	}

	public function test_force_login_with_unknown_user_starts_no_session(): void
	{
		try
		{
			$this->auth->force_login('nobody', true);
			$this->fail('A forced login of an unknown user must fail');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('user was not found', $e->getMessage());
		}

		$this->assertFalse($this->auth->logged_in());
		$this->assertNull($this->session->get('auth_forced'));

		// The same for a user object that was never saved
		$this->expectException(Kohana_Exception::class);
		$this->auth->force_login(ORM::factory('User'));
	}

	public function test_force_login_by_name_email_or_object(): void
	{
		$user = $this->user();

		$this->auth->force_login('ana');
		$this->assertTrue($this->auth->logged_in('login'));
		$this->assertNull($this->session->get('auth_forced'));

		$this->auth->logout();
		$this->auth->force_login('ana@example.com');
		$this->assertSame('ana', $this->auth->get_user()->username);

		$this->auth->logout();
		$this->auth->force_login($user, true);
		$this->assertTrue($this->auth->logged_in());
		$this->assertTrue($this->session->get('auth_forced'));

		// Logging out removes the mark of the forced session
		$this->assertTrue($this->auth->logout());
		$this->assertNull($this->session->get('auth_forced'));
		$this->assertFalse($this->auth->logged_in());

		// Forced logins are counted like any other
		$this->assertEquals(3, ORM::factory('User', $user->pk())->logins);
	}

	public function test_logged_in_checks_roles(): void
	{
		$user = $this->user();
		$this->auth->force_login($user);

		$this->assertTrue($this->auth->logged_in(['login']));
		$this->assertFalse($this->auth->logged_in(['login', 'admin']));
		$this->assertFalse($this->auth->logged_in('no such role'));
		$this->assertFalse($this->auth->logged_in(['login', 'no such role']));
		$this->assertTrue($this->auth->logged_in(ORM::factory('Role', ['name' => 'login'])));
		$this->assertFalse($this->auth->logged_in(ORM::factory('Role', ['name' => 'admin'])));

		$user->add('roles', ORM::factory('Role', ['name' => 'admin']));

		$this->assertTrue($this->auth->logged_in('admin'));
		$this->assertTrue($this->auth->logged_in(['login', 'admin']));
	}

	/**
	 * Only a saved user in the session counts as logged in.
	 */
	public function test_logged_in_needs_a_loaded_user(): void
	{
		$this->session->set('auth_user', ORM::factory('User'));
		$this->assertFalse($this->auth->logged_in());

		$this->session->set('auth_user', 'ana');
		$this->assertFalse($this->auth->logged_in());
		$this->assertFalse($this->auth->check_password('correct horse'));
	}

	public function test_password_returns_the_stored_hash(): void
	{
		$user = $this->user();

		$this->assertSame($user->password, $this->auth->password('ana'));
		$this->assertSame($user->password, $this->auth->password('ana@example.com'));
		$this->assertSame($user->password, $this->auth->password($user));
		$this->assertSame('', $this->auth->password('nobody'));
	}

	/**
	 * A login with "remember me" stores a token for this browser, and
	 * a login without it stores nothing.
	 */
	public function test_remember_stores_a_token_for_the_browser(): void
	{
		$user = $this->user();

		$this->assertTrue($this->auth->login('ana', 'correct horse'));
		$this->assertSame([], $this->tokens($user));

		$this->assertFalse($this->auth->login('ana', 'wrong', true));
		$this->assertSame([], $this->tokens($user));

		$this->assertTrue($this->auth->login('ana', 'correct horse', true));
		$this->assertCount(1, $this->tokens($user));

		$token = ORM::factory('User_Token', ['token' => $this->tokens($user)[0]]);

		$this->assertSame(hash('sha256', self::BROWSER), $token->user_agent);
		$this->assertEqualsWithDelta(time() + 1209600, (int) $token->expires, 5);
	}

	/**
	 * With the cookie of a token, a visitor without a session is logged
	 * in again, and the token is replaced: the old cookie works only once.
	 */
	public function test_auto_login_with_the_cookie_of_a_token(): void
	{
		$user = $this->user();
		$this->auth->login('ana', 'correct horse', true);
		$first = $this->tokens($user)[0];

		// A new visit: no session, only the cookie
		$this->session->delete('auth_user');
		$this->cookie($first);

		$this->assertSame('ana', $this->auth->get_user()->username);
		$this->assertTrue($this->auth->logged_in('login'));
		$this->assertCount(1, $this->tokens($user));
		$this->assertNotSame($first, $this->tokens($user)[0]);

		// A copy of the old cookie is worthless
		$this->session->delete('auth_user');
		$this->cookie($first);

		$this->assertFalse($this->auth->auto_login());
		$this->assertNull($this->auth->get_user());
		$this->assertFalse($this->auth->logged_in());
	}

	/**
	 * A token only works in the browser it was made for; used elsewhere,
	 * it is deleted.
	 */
	public function test_auto_login_refuses_another_browser(): void
	{
		$user = $this->user();
		$this->auth->login('ana', 'correct horse', true);
		$this->session->delete('auth_user');
		$this->cookie($this->tokens($user)[0]);

		Request::$user_agent = 'Another Browser';

		$this->assertFalse($this->auth->auto_login());
		$this->assertFalse($this->auth->logged_in());
		$this->assertSame([], $this->tokens($user));
	}

	public function test_auto_login_refuses_bad_cookies(): void
	{
		$user = $this->user();
		$this->auth->login('ana', 'correct horse', true);
		$this->session->delete('auth_user');
		$token = $this->tokens($user)[0];

		// No cookie
		$this->assertFalse($this->auth->auto_login());

		// A token that does not exist
		$this->cookie(str_repeat('a', 40));
		$this->assertFalse($this->auth->auto_login());

		// A real token without a valid signature
		$_COOKIE['authautologin'] = $token;
		$this->assertFalse($this->auth->auto_login());

		$_COOKIE['authautologin'] = str_repeat('0', 64).'~'.$token;
		$this->assertFalse($this->auth->auto_login());

		$this->assertFalse($this->auth->logged_in());
		$this->assertSame([$token], $this->tokens($user), 'A bad cookie must not touch the stored token');
	}

	/**
	 * Logging out removes the token of this browser, so the cookie cannot
	 * log in again; the tokens of other browsers stay.
	 */
	public function test_logout_deletes_the_token_of_this_browser(): void
	{
		$user = $this->user();
		$this->auth->login('ana', 'correct horse', true);
		$this->auth->login('ana', 'correct horse', true);
		list($here, $other) = $this->tokens($user);

		$this->cookie($here);

		$this->assertTrue($this->auth->logout());
		$this->assertArrayNotHasKey('authautologin', $_COOKIE);
		$this->assertSame([$other], $this->tokens($user));

		$this->cookie($here);
		$this->assertFalse($this->auth->logged_in());
	}

	/**
	 * "Log out everywhere" removes all tokens of the user, and only of
	 * that user.
	 */
	public function test_logout_all_deletes_every_token_of_the_user(): void
	{
		$user  = $this->user();
		$other = $this->user('battery staple', 'bea');

		$this->auth->login('ana', 'correct horse', true);
		$this->auth->login('ana', 'correct horse', true);
		$this->auth->login('bea', 'battery staple', true);
		$this->auth->login('ana', 'correct horse');

		$this->cookie($this->tokens($user)[0]);

		$this->assertTrue($this->auth->logout(true, true));
		$this->assertSame([], $this->tokens($user));
		$this->assertCount(1, $this->tokens($other));
	}

	/**
	 * Deleting a user deletes the tokens too (the tables say so), and the
	 * cookie of such a token logs nobody in.
	 */
	public function test_token_of_a_deleted_user_is_worthless(): void
	{
		$user = $this->user();
		$this->auth->login('ana', 'correct horse', true);
		$token = $this->tokens($user)[0];

		$this->session->delete('auth_user');
		$user->delete();
		$this->cookie($token);

		$this->assertFalse($this->auth->auto_login());
		$this->assertSame(0, ORM::factory('User_Token')->count_all());
	}

	public function test_create_user_checks_password_and_unique_fields(): void
	{
		$fields = ['username', 'password', 'email'];
		$values = ['username' => 'ana', 'email' => 'ana@example.com', 'password' => 'correct horse', 'password_confirm' => 'correct horse'];

		// The list of expected fields keeps other values out
		$user = ORM::factory('User')->create_user($values + ['logins' => 99], $fields);

		$this->assertTrue($user->loaded());
		$this->assertEquals(0, ORM::factory('User', $user->pk())->logins);
		$this->assertTrue($this->auth->verify('correct horse', $user->password));

		$bad = [
			'a short password'    => ['username' => 'bea', 'email' => 'bea@example.com', 'password' => 'short', 'password_confirm' => 'short'],
			'an empty password'   => ['username' => 'bea', 'email' => 'bea@example.com', 'password' => '', 'password_confirm' => ''],
			'a wrong repeat'      => ['username' => 'bea', 'email' => 'bea@example.com', 'password' => 'correct horse', 'password_confirm' => 'other horse'],
			'a name that exists'  => ['username' => 'ana', 'email' => 'bea@example.com'] + $values,
			'an email that exists' => ['username' => 'bea'] + $values,
			'a bad email'         => ['username' => 'bea', 'email' => 'not an email'] + $values,
		];

		foreach ($bad as $what => $input)
		{
			try
			{
				ORM::factory('User')->create_user($input, $fields);
				$this->fail('A user with '.$what.' must not be created');
			}
			catch (ORM_Validation_Exception $e)
			{
				$this->assertSame(1, ORM::factory('User')->count_all(), $what);
			}
		}
	}

	public function test_update_user_keeps_the_password_when_none_is_given(): void
	{
		$user = $this->user();
		$hash = $user->password;

		$user->update_user(['username' => 'anna', 'password' => '', 'password_confirm' => ''], ['username', 'password']);

		$stored = ORM::factory('User', $user->pk());
		$this->assertSame('anna', $stored->username);
		$this->assertSame($hash, $stored->password);

		// A new password is checked and stored as a hash
		$user->update_user(['password' => 'battery staple', 'password_confirm' => 'battery staple'], ['password']);
		$this->assertTrue($this->auth->login('anna', 'battery staple'));
		$this->assertFalse($this->auth->login('anna', 'correct horse'));

		$this->expectException(ORM_Validation_Exception::class);
		$user->update_user(['password' => 'short', 'password_confirm' => 'short'], ['password']);
	}

	public function test_unique_key_looks_at_the_value(): void
	{
		$user  = $this->user();
		$other = ORM::factory('User');

		$this->assertSame('email', $other->unique_key('ana@example.com'));
		$this->assertSame('username', $other->unique_key('ana'));

		// Taken for somebody else, free for the user who has it
		$this->assertTrue($other->unique_key_exists('ana'));
		$this->assertTrue($other->unique_key_exists('ana@example.com'));
		$this->assertFalse($other->unique_key_exists('bea'));
		$this->assertFalse($user->unique_key_exists('ana'));
		$this->assertTrue($other->unique_key_exists('ana', 'username'));
		$this->assertFalse($other->unique_key_exists('ana', 'email'));
	}
}

/**
 * Counts the password checks that are made for users that do not exist.
 */
trait Kohana_AuthORMTest_Counter
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
 * The ORM driver, with the counter.
 */
class Kohana_AuthORMTest_Auth extends Auth_ORM
{
	use Kohana_AuthORMTest_Counter;
}

/**
 * A session kept in memory, so that logging in can be tested from the
 * command line without cookies.
 */
class Kohana_AuthORMTest_Session extends Session
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
