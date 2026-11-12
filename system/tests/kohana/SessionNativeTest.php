<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the session driver that uses the sessions of PHP itself, with
 * real session files in a temporary directory.
 *
 * @package    Kohana
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.session')]
class Kohana_SessionNativeTest extends Unittest_TestCase
{
	/**
	 * @var string  Directory of the session files of this test
	 */
	protected $directory;

	/**
	 * @var string  The save path of PHP before the test
	 */
	protected $save_path;

	public function setUp(): void
	{
		parent::setUp();

		// A session left open by another test
		$this->close();

		$this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kohana_sessions_'.bin2hex(random_bytes(6));
		mkdir($this->directory);

		$this->save_path = (string) ini_get('session.save_path');
		ini_set('session.save_path', $this->directory);

		$this->setEnvironment([
			'Cookie::$salt'     => 'salt-of-the-session-tests',
			'Cookie::$path'     => '/shop/',
			'Cookie::$domain'   => 'www.example.com',
			'Cookie::$secure'   => true,
			'Cookie::$httponly' => true,
			'Cookie::$samesite' => 'Strict',
		]);

		$_COOKIE = [];
	}

	public function tearDown(): void
	{
		$this->close();

		ini_set('session.save_path', $this->save_path);

		array_map('unlink', glob($this->directory.DIRECTORY_SEPARATOR.'*'));
		rmdir($this->directory);

		$_COOKIE = [];

		parent::tearDown();
	}

	/**
	 * Closes the session of PHP, when one is open, and forgets its id.
	 */
	protected function close(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE)
		{
			session_write_close();
		}

		session_id('');
		$_SESSION = [];
	}

	/**
	 * Starts a session: a new one, or the one with this id.
	 */
	protected function session(?string $id = null): Session_Native
	{
		return new Session_Native(['name' => 'kohana_test_session', 'lifetime' => 600], $id);
	}

	/**
	 * The session cookie gets the settings of Cookie, and PHP is told to
	 * take session ids from cookies only and to accept only its own.
	 */
	public function test_session_starts_with_safe_settings(): void
	{
		$session = $this->session();

		$this->assertSame(PHP_SESSION_ACTIVE, session_status());
		$this->assertSame('kohana_test_session', session_name());
		$this->assertSame('kohana_test_session', $session->name());
		$this->assertSame(session_id(), $session->id());
		$this->assertNotSame('', (string) $session->id());

		$this->assertSame('1', ini_get('session.use_strict_mode'));
		$this->assertSame('1', ini_get('session.use_only_cookies'));
		$this->assertSame('', session_cache_limiter());

		$this->assertEquals([
			'lifetime' => 600,
			'path'     => '/shop/',
			'domain'   => 'www.example.com',
			'secure'   => true,
			'httponly' => true,
			'samesite' => 'Strict',
		], session_get_cookie_params());
	}

	public function test_data_is_kept_between_requests(): void
	{
		$session = $this->session();
		$session->set('user', 'ana')->set('cart', [1, 2]);

		$id = $session->id();

		$this->assertTrue($session->write());
		$this->assertSame(PHP_SESSION_NONE, session_status());

		// The next request comes with the id of the session
		$this->close();
		$next = $this->session($id);

		$this->assertSame($id, $next->id());
		$this->assertSame('ana', $next->get('user'));
		$this->assertSame([1, 2], $next->get('cart'));
		$this->assertSame('ana', $_SESSION['user']);
	}

	/**
	 * An id that PHP did not hand out is not accepted: an attacker cannot
	 * make a visitor use a session of their choice ("session fixation").
	 */
	public function test_made_up_session_id_is_replaced(): void
	{
		$planted = 'attackerchosensessionid0000000000';

		$session = $this->session($planted);

		$this->assertNotSame($planted, $session->id());
		$this->assertNotSame('', (string) $session->id());
		$this->assertSame([], $session->as_array());
		$this->assertFileDoesNotExist($this->directory.DIRECTORY_SEPARATOR.'sess_'.$planted);
	}

	/**
	 * After a login the session gets a new id, and the old one is worth
	 * nothing.
	 */
	public function test_regenerate_gives_a_new_id_and_ends_the_old_one(): void
	{
		$session = $this->session();
		$session->set('user', 'ana');

		$old = $session->id();
		$new = $session->regenerate();

		$this->assertNotSame($old, $new);
		$this->assertSame($new, $session->id());
		$this->assertSame('ana', $session->get('user'), 'The data moves to the new id');
		$this->assertFileDoesNotExist($this->directory.DIRECTORY_SEPARATOR.'sess_'.$old);

		$session->write();
		$this->close();

		// The old id starts an empty session under another id
		$stolen = $this->session($old);

		$this->assertNotSame($old, $stolen->id());
		$this->assertNull($stolen->get('user'));
	}

	public function test_destroy_removes_the_data_and_the_session(): void
	{
		$session = $this->session();
		$session->set('user', 'ana');
		$session->write();

		$id = $session->id();

		$this->close();
		$session = $this->session($id);

		$this->assertTrue($session->destroy());
		$this->assertSame([], $session->as_array());
		$this->assertNull($session->id());
		$this->assertFileDoesNotExist($this->directory.DIRECTORY_SEPARATOR.'sess_'.$id);
	}

	public function test_restart_begins_an_empty_session(): void
	{
		$session = $this->session();
		$session->set('user', 'ana');

		$old = $session->id();

		$this->assertTrue($session->restart());
		$this->assertSame(PHP_SESSION_ACTIVE, session_status());
		$this->assertNull($session->get('user'));
		$this->assertNotSame($old, $session->id());

		// The new session works like any other
		$session->set('user', 'bea');
		$this->assertSame('bea', $_SESSION['user']);
	}
}
