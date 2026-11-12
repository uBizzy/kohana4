<?php
/**
 * Base session class.
 *
 * @package    Kohana
 * @category   Session
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Session {

	/**
	 * @var  string  default session adapter
	 */
	public static string $default = 'native';

	/**
	 * @var  array<string, Session>  session instances
	 */
	public static array $instances = [];

	/**
	 * Creates a singleton session of the given type. Some session types
	 * (native, database) also support restarting a session by passing a
	 * session id as the second parameter.
	 *
	 *     $session = Session::instance();
	 *
	 * [!!] [Session::write] will automatically be called when the request ends.
	 *
	 * @param   string  $type   type of session (native, cookie, etc)
	 * @param   string  $id     session identifier
	 * @return  Session
	 * @uses    Kohana::$config
	 */
	public static function instance(?string $type = null, ?string $id = null): Session
	{
		// Use the default type
		$type ??= Session::$default;

		if ( ! isset(Session::$instances[$type]))
		{
			// Load the configuration for this type
			/** @var Config_Group $group */
			$group = Kohana::$config->load('session');

			$config = $group->get($type);

			/** @var class-string<Session> $class  the driver class, e.g. Session_Native */
			$class = 'Session_'.ucfirst($type);

			// Create a new session instance
			Session::$instances[$type] = $session = new $class($config, $id);

			// Write the session at shutdown
			register_shutdown_function([$session, 'write']);
		}

		return Session::$instances[$type];
	}

	/**
	 * @var  string  cookie name
	 */
	protected string $_name = 'session';

	/**
	 * @var  int  cookie lifetime
	 */
	protected int $_lifetime = 0;

	/**
	 * @var  string|false  name of the Encrypt instance that encrypts the data, false for none
	 */
	protected string|false $_encrypted = false;

	/**
	 * @var  array<int|string, mixed>  session data
	 */
	protected array $_data = [];

	/**
	 * @var  bool  session destroyed?
	 */
	protected bool $_destroyed = false;

	/**
	 * Overloads the name, lifetime, and encrypted session settings.
	 *
	 * [!!] Sessions can only be created using the [Session::instance] method.
	 *
	 * @param   array{name?: string, lifetime?: int, encrypted?: string|bool}|null   $config configuration; a driver can have more settings
	 * @param   string  $id     session id
	 * @return  void
	 * @uses    Session::read
	 */
	public function __construct(?array $config = null, ?string $id = null)
	{
		if (isset($config['name']))
		{
			// Cookie name to store the session id in
			$this->_name = (string) $config['name'];
		}

		if (isset($config['lifetime']))
		{
			// Cookie lifetime
			$this->_lifetime = (int) $config['lifetime'];
		}

		if (isset($config['encrypted']))
		{
			if ($config['encrypted'] === true)
			{
				// Use the default Encrypt instance
				$config['encrypted'] = 'default';
			}

			// Enable or disable encryption of data
			$this->_encrypted = $config['encrypted'] ?: false;
		}

		if ( ! Kohana::$profiling)
		{
			// Load the session
			$this->read($id);

			return;
		}

		// The same, measured
		Profiler::measure('Session', 'read ('.get_class($this).')', fn () => $this->read($id));
	}

	/**
	 * Session object is rendered to a serialized string. If encryption is
	 * enabled, the session will be encrypted. If not, the output string will
	 * be encoded.
	 *
	 *     echo $session;
	 *
	 * @return  string
	 * @uses    Encrypt::encode
	 */
	public function __toString(): string
	{
		// Serialize the data array
		$data = $this->_serialize($this->_data);

		if ($this->_encrypted)
		{
			// Encrypt the data using the default key
			$data = Encrypt::instance($this->_encrypted)->encode($data);

			if ($data === null)
				throw new Session_Exception('The session data could not be encrypted');
		}
		else
		{
			// Encode the data
			$data = $this->_encode($data);
		}

		return $data;
	}

	/**
	 * Returns the current session array. The returned array can also be
	 * assigned by reference.
	 *
	 *     // Get a copy of the current session data
	 *     $data = $session->as_array();
	 *
	 *     // Assign by reference for modification
	 *     $data =& $session->as_array();
	 *
	 * @return  array<int|string, mixed>
	 */
	public function & as_array(): array
	{
		return $this->_data;
	}

	/**
	 * Get the current session id, if the session supports it.
	 *
	 *     $id = $session->id();
	 *
	 * [!!] Not all session types have ids.
	 *
	 * @return  string
	 * @since   3.0.8
	 */
	public function id(): ?string
	{
		return null;
	}

	/**
	 * Get the current session cookie name.
	 *
	 *     $name = $session->name();
	 *
	 * @return  string
	 * @since   3.0.8
	 */
	public function name(): string
	{
		return $this->_name;
	}

	/**
	 * Get a variable from the session array.
	 *
	 *     $foo = $session->get('foo');
	 *
	 * @param   string  $key        variable name
	 * @param   mixed   $default    default value to return
	 * @return  mixed
	 */
	public function get(string|int $key, mixed $default = null): mixed
	{
		return array_key_exists($key, $this->_data) ? $this->_data[$key] : $default;
	}

	/**
	 * Get and delete a variable from the session array.
	 *
	 *     $bar = $session->get_once('bar');
	 *
	 * @param   string  $key        variable name
	 * @param   mixed   $default    default value to return
	 * @return  mixed
	 */
	public function get_once(string|int $key, mixed $default = null): mixed
	{
		$value = $this->get($key, $default);

		unset($this->_data[$key]);

		return $value;
	}

	/**
	 * Set a variable in the session array.
	 *
	 *     $session->set('foo', 'bar');
	 *
	 * @param   string  $key    variable name
	 * @param   mixed   $value  value
	 * @return  $this
	 */
	public function set(string|int $key, mixed $value): static
	{
		$this->_data[$key] = $value;

		return $this;
	}

	/**
	 * Set a variable by reference.
	 *
	 *     $session->bind('foo', $foo);
	 *
	 * @param   string  $key    variable name
	 * @param   mixed   $value  referenced value
	 * @return  $this
	 */
	public function bind(string|int $key, mixed & $value): static
	{
		$this->_data[$key] =& $value;

		return $this;
	}

	/**
	 * Removes a variable in the session array.
	 *
	 *     $session->delete('foo');
	 *
	 * Several names can be given at once:
	 *
	 *     $session->delete('foo', 'bar');
	 *
	 * @param   string|int  ...$keys  variable names
	 * @return  $this
	 */
	public function delete(string|int ...$keys): static
	{
		foreach ($keys as $key)
		{
			unset($this->_data[$key]);
		}

		return $this;
	}

	/**
	 * Loads existing session data.
	 *
	 *     $session->read();
	 *
	 * @param   string  $id session id
	 * @return  void
	 */
	public function read(?string $id = null): void
	{
		// Raw stored string, or null when there is no session yet
		$raw = $this->_read($id);

		if ( ! is_string($raw) OR $raw === '')
		{
			// Nothing stored: this is a new, empty session
			return;
		}

		try
		{
			// Decrypt (when encryption is enabled) or decode the stored string
			$data = $this->_encrypted
				? Encrypt::instance($this->_encrypted)->decode($raw)
				: $this->_decode($raw);

			// Unserialize the data
			$data = is_string($data) ? $this->_unserialize($data) : null;
		}
		catch (Throwable $e)
		{
			// Corrupt, tampered or old-format data
			$data = null;
		}

		if (is_array($data))
		{
			// Load the data locally
			$this->_data = $data;
		}
		else
		{
			// The stored session is invalid: destroy it and start a new one
			$this->restart();
		}
	}

	/**
	 * Generates a new session id and returns it.
	 *
	 *     $id = $session->regenerate();
	 *
	 * @return  string
	 */
	public function regenerate(): ?string
	{
		return $this->_regenerate();
	}

	/**
	 * Sets the last_active timestamp and saves the session.
	 *
	 *     $session->write();
	 *
	 * [!!] Any errors that occur during session writing will be logged,
	 * but not displayed, because sessions are written after output has
	 * been sent.
	 *
	 * @return  bool
	 * @uses    Kohana::$log
	 */
	public function write(): bool
	{
		if (headers_sent() OR $this->_destroyed)
		{
			// Session cannot be written when the headers are sent or when
			// the session has been destroyed
			return false;
		}

		// Set the last active timestamp
		$this->_data['last_active'] = time();

		try
		{
			if (Kohana::$profiling)
				return (bool) Profiler::measure('Session', 'write ('.get_class($this).')', fn () => $this->_write());

			return $this->_write();
		}
		catch (Throwable $e)
		{
			// Log all errors when a write fails
			Kohana::$log?->add(Log::ERROR, Kohana_Exception::text($e))->write();

			// A lost session is hard to track down: show the cause while developing
			if (Kohana::$environment === Kohana::DEVELOPMENT)
				throw $e;

			return false;
		}
	}

	/**
	 * Completely destroy the current session.
	 *
	 *     $success = $session->destroy();
	 *
	 * @return  bool
	 */
	public function destroy(): bool
	{
		if ($this->_destroyed === false)
		{
			if ($this->_destroyed = $this->_destroy())
			{
				// The session has been destroyed, clear all data
				$this->_data = [];
			}
		}

		return $this->_destroyed;
	}

	/**
	 * Restart the session.
	 *
	 *     $success = $session->restart();
	 *
	 * @return  bool
	 */
	public function restart(): bool
	{
		if ($this->_destroyed === false)
		{
			// Wipe out the current session.
			$this->destroy();
		}

		// Allow the new session to be saved
		$this->_destroyed = false;

		return $this->_restart();
	}

	/**
	 * Serializes the session data.
	 *
	 * @param   array<int|string, mixed>  $data  data
	 * @return  string
	 */
	protected function _serialize(array $data): string
	{
		return serialize($data);
	}

	/**
	 * Unserializes the session data.
	 *
	 * @param   string  $data  data
	 * @return  mixed  the data; false when it cannot be read
	 */
	protected function _unserialize(string $data): mixed
	{
		// Data that cannot be read gives false, which read() treats as
		// "no session": PHP's warning about it is not needed
		return @unserialize($data);
	}

	/**
	 * Encodes the session data using [base64_encode].
	 *
	 * @param   string  $data  data
	 * @return  string
	 */
	protected function _encode(string $data): string
	{
		return base64_encode($data);
	}

	/**
	 * Decodes the session data using [base64_decode].
	 *
	 * @param   string  $data  data
	 * @return  string
	 */
	protected function _decode(string $data): string|false
	{
		// Strict mode: returns false when the data is not valid base64
		return base64_decode($data, true);
	}

	/**
	 * Loads the raw session data string and returns it.
	 *
	 * @param   string  $id session id
	 * @return  string
	 */
	abstract protected function _read(?string $id = null): ?string;

	/**
	 * Generate a new session id and return it.
	 *
	 * @return  string
	 */
	abstract protected function _regenerate(): ?string;

	/**
	 * Writes the current session.
	 *
	 * @return  bool
	 */
	abstract protected function _write(): bool;

	/**
	 * Destroys the current session.
	 *
	 * @return  bool
	 */
	abstract protected function _destroy(): bool;

	/**
	 * Restarts the current session.
	 *
	 * @return  bool
	 */
	abstract protected function _restart(): bool;

}
