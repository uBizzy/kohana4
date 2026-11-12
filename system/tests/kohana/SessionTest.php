<?php

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Ticket;

/**
 * Tests the session class
 *
 * @package    Kohana
 * @category   Tests
 *
 * @author     Jeremy Bush <contractfrombelow@gmail.com>
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.session')]
// Partial mocks of an abstract class are used here as plain objects
#[AllowMockObjectsWithoutExpectations]
class Kohana_SessionTest extends Unittest_TestCase
{

	/**
	 * Gets a mock of the session class
	 *
	 * @return Session
	 */
	// @codingStandardsIgnoreStart
	public function getMockSession(array $config = [])
	// @codingStandardsIgnoreEnd
	{
		return $this->getMockForAbstractClass('Session', [$config]);
	}

	/**
	 * Check that the constructor will load a session if it's provided
	 * witha session id
	 *
	 * @covers Session::__construct
	 * @covers Session::read
	 */
	public function test_constructor_loads_session_with_session_id()
	{
		$config = [];
		$session_id = 'lolums';

		// Don't auto-call constructor, we need to setup the mock first
		$session = $this->getMockForAbstractClass('Session', [], false, ['read']);

		$session
			->expects($this->once())
			->method('read')
			->with($session_id);

		$session->__construct($config, $session_id);
	}

	/**
	 * Builds a Session mock whose driver returns the given raw stored string.
	 *
	 * @param   string  $raw  what the storage driver "read"
	 * @return  Session
	 */
	protected function session_with_stored_data(string $raw)
	{
		$session = $this->getMockForAbstractClass('Session', [], false, ['restart']);

		$session->method('_read')->willReturn($raw);

		return $session;
	}

	/**
	 * Valid stored data is loaded and the session is not restarted.
	 *
	 * @covers Session::read
	 */
	public function test_read_loads_valid_data()
	{
		$session = $this->session_with_stored_data(base64_encode(serialize(['foo' => 'bar'])));
		$session->expects($this->never())->method('restart');

		$session->read();

		$this->assertSame('bar', $session->get('foo'));
	}

	/**
	 * Provides stored strings that are not a valid session.
	 *
	 * @return array
	 */
	public static function provider_read_restarts_on_invalid_data()
	{
		return [
			'not base64'           => ['%%% not base64 %%%'],
			'not serialized'       => [base64_encode('garbage')],
			'serialized non-array' => [base64_encode(serialize('a string'))],
		];
	}

	/**
	 * Corrupt or tampered data is discarded and a new session is started.
	 *
	 * @covers Session::read
	 */
	#[DataProvider('provider_read_restarts_on_invalid_data')]
	public function test_read_restarts_on_invalid_data($raw)
	{
		$session = $this->session_with_stored_data($raw);
		$session->expects($this->once())->method('restart');

		$session->read();

		$this->assertSame([], $session->as_array());
	}

	/**
	 * Calling $session->bind() should allow you to bind a variable
	 * to a session variable
	 *
	 * @covers Session::bind
	 */
	#[Ticket('3164')]
	public function test_bind_actually_binds_variable()
	{
		$session = $this->getMockForAbstractClass('Session');

		$var = 'asd';

		$session->bind('our_var', $var);

		$var = 'foobar';

		$this->assertSame('foobar', $session->get('our_var'));
	}

	/**
	 * Provides test data for test_get_returns_default_if_var_dnx()
	 *
	 * @return array
	 */
	public static function provider_get_returns_default_if_var_dnx()
	{
		return [
			['something_crazy', false],
			['a_true', true],
			['an_int', 158163158],
		];
	}

	/**
	 * Make sure that get() is using the default value we provide and
	 * isn't tampering with it
	 *
	 * @covers Session::get
	 */
	#[DataProvider('provider_get_returns_default_if_var_dnx')]
	public function test_get_returns_default_if_var_dnx($var, $default)
	{
		$session = $this->getMockSession();

		$this->assertSame($default, $session->get($var, $default));
	}

	/**
	 * By default get() should be using null as the var DNX return value
	 *
	 * @covers Session::get
	 */
	public function test_get_uses_null_as_default_return_value()
	{
		$session = $this->getMockSession();

		$this->assertSame(null, $session->get('level_of_cool'));
	}

	/**
	 * This test makes sure that session is using array_key_exists
	 * as isset will return false if the value is null
	 *
	 * @covers Session::get
	 */
	public function test_get_returns_value_if_it_equals_null()
	{
		$session = $this->getMockSession();

		$session->set('arkward', null);

		$this->assertSame(null, $session->get('arkward', 'uh oh'));
	}

	/**
	 * regenerate() should tell the driver to regenerate its id
	 *
	 * @covers Session::regenerate
	 */
	public function test_regenerate_tells_driver_to_regenerate()
	{
		$session = $this->getMockSession();

		$new_session_id = 'asdnoawdnoainf';

		$session->expects($this->once())
				->method('_regenerate')
				->with()
				->willReturn($new_session_id);

		$this->assertSame($new_session_id, $session->regenerate());
	}

	/**
	 * Returns a session that keeps its stored text in memory, so that
	 * a later "request" can read it again.
	 */
	protected function stored(array $config = [], ?string $id = 'one'): Session_Kohanatestmemory
	{
		return new Session_Kohanatestmemory($config, $id);
	}

	/**
	 * What one request writes, the next one reads: the whole way through
	 * serializing and encoding.
	 */
	public function test_data_written_is_read_by_the_next_request(): void
	{
		Session_Kohanatestmemory::$store = [];

		$session = $this->stored();
		$session->set('user', 'ana')->set('cart', ['a' => 1]);

		$this->assertSame('one', $session->id());
		$this->assertSame('session', $session->name());
		$this->assertTrue($session->write());

		$next = $this->stored();

		$this->assertSame('ana', $next->get('user'));
		$this->assertSame(['a' => 1], $next->get('cart'));
		$this->assertEqualsWithDelta(time(), $next->get('last_active'), 5);

		// Another id is another, empty session
		$this->assertSame([], $this->stored([], 'two')->as_array());
	}

	/**
	 * An encrypted session cannot be read where it is stored, and stored
	 * text that was changed gives an empty session, not an error.
	 */
	public function test_encrypted_session(): void
	{
		if ( ! class_exists('Encrypt') OR ! extension_loaded('openssl'))
		{
			$this->markTestSkipped('The encrypt module with OpenSSL is not available.');
		}

		$this->setEnvironment(['encrypt.default' => [
			'type'   => 'openssl',
			'key'    => 'a-32-characters-long-secret-key!',
			'cipher' => Encrypt_Engine_Openssl::AES_256_CBC,
		]]);

		$instances = new ReflectionProperty('Encrypt', 'instances');
		$before    = $instances->getValue();
		$instances->setValue(null, []);

		try
		{
			Session_Kohanatestmemory::$store = [];

			$session = $this->stored(['encrypted' => true]);
			$session->set('secret', 'the-secret-value');
			$session->write();

			$raw = Session_Kohanatestmemory::$store['one'];

			$this->assertStringNotContainsString('the-secret-value', $raw);
			$this->assertStringNotContainsString('the-secret-value', (string) base64_decode($raw));
			$this->assertSame('the-secret-value', $this->stored(['encrypted' => 'default'])->get('secret'));

			// One character changed: the session is thrown away
			Session_Kohanatestmemory::$store['one'] = substr($raw, 0, -4).'AAAA';

			$this->assertSame([], $this->stored(['encrypted' => true])->as_array());

			// And text stored without encryption is not accepted either
			Session_Kohanatestmemory::$store['one'] = base64_encode(serialize(['admin' => true]));

			$this->assertNull($this->stored(['encrypted' => true])->get('admin'));
		}
		finally
		{
			$instances->setValue(null, $before);
		}
	}

	public function test_get_once_delete_and_as_array(): void
	{
		Session_Kohanatestmemory::$store = [];

		$session = $this->stored();
		$session->set('message', 'saved')->set('a', 1)->set('b', 2)->set('c', 3);

		$this->assertSame('saved', $session->get_once('message'));
		$this->assertNull($session->get('message'));
		$this->assertSame('none', $session->get_once('message', 'none'));

		$session->delete('a', 'b', 'not there');

		$this->assertSame(['c' => 3], $session->as_array());

		// as_array() gives the data itself, not a copy
		$data = & $session->as_array();
		$data['d'] = 4;

		$this->assertSame(4, $session->get('d'));
	}

	/**
	 * A destroyed session is empty and is not written again.
	 */
	public function test_destroyed_session_is_not_written(): void
	{
		Session_Kohanatestmemory::$store = [];

		$session = $this->stored();
		$session->set('user', 'ana');
		$session->write();

		$this->assertTrue($session->destroy());
		$this->assertSame([], $session->as_array());
		$this->assertArrayNotHasKey('one', Session_Kohanatestmemory::$store);

		$session->set('user', 'bea');

		$this->assertFalse($session->write());
		$this->assertArrayNotHasKey('one', Session_Kohanatestmemory::$store);

		// After a restart it can be saved again
		$this->assertTrue($session->restart());
		$session->set('user', 'cleo');

		$this->assertTrue($session->write());
		$this->assertSame('cleo', $this->stored()->get('user'));
	}

	/**
	 * A session that cannot be saved is shown while developing, and
	 * written to the log elsewhere.
	 */
	public function test_failed_write_is_thrown_in_development_only(): void
	{
		Session_Kohanatestmemory::$store = [];

		$session = $this->stored();
		$session->fail = true;

		$this->setEnvironment(['Kohana::$environment' => Kohana::PRODUCTION, 'Kohana::$log' => new Log]);

		$this->assertFalse($session->write());

		$this->setEnvironment(['Kohana::$environment' => Kohana::DEVELOPMENT]);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('the storage is full');

		$session->write();
	}

	/**
	 * Session::instance() makes one object per type, from config/session.php.
	 */
	public function test_instance_makes_one_session_per_type(): void
	{
		Session_Kohanatestmemory::$store = [];

		$this->setEnvironment(['session.kohanatestmemory' => ['name' => 'my_session', 'lifetime' => 30]]);

		$instances = Session::$instances;

		try
		{
			$session = Session::instance('kohanatestmemory', 'one');

			$this->assertInstanceOf('Session_Kohanatestmemory', $session);
			$this->assertSame('my_session', $session->name());
			$this->assertSame($session, Session::instance('kohanatestmemory'));
		}
		finally
		{
			Session::$instances = $instances;
		}
	}
}

/**
 * A session driver that keeps the stored text in memory, by session id.
 */
class Session_Kohanatestmemory extends Session
{
	/**
	 * @var array<string, string>  The stored sessions
	 */
	public static array $store = [];

	/**
	 * @var bool  Makes writing fail
	 */
	public bool $fail = false;

	/**
	 * @var string|null  Id of this session
	 */
	protected ?string $_id = null;

	public function id(): ?string
	{
		return $this->_id;
	}

	protected function _read(?string $id = null): ?string
	{
		$this->_id = $id;

		return static::$store[(string) $id] ?? null;
	}

	protected function _regenerate(): ?string
	{
		return $this->_id = bin2hex(random_bytes(8));
	}

	protected function _write(): bool
	{
		if ($this->fail)
			throw new RuntimeException('the storage is full');

		static::$store[(string) $this->_id] = $this->__toString();

		return true;
	}

	protected function _destroy(): bool
	{
		unset(static::$store[(string) $this->_id]);

		return true;
	}

	protected function _restart(): bool
	{
		return true;
	}
}
