<?php
/**
 * User authorization library. Handles user login and logout, as well as secure
 * password hashing.
 *
 * @package    Kohana/Auth
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Auth {

	// Auth instances
	protected static ?Auth $_instance = null;

	/**
	 * Singleton pattern
	 *
	 * @return Auth
	 */
	public static function instance(): Auth
	{
		if ( ! isset(Auth::$_instance))
		{
			// Load the configuration for this type
			/** @var Config_Group $config */
			$config = Kohana::$config->load('auth');

			$type = $config->get('driver');

			if ( ! is_string($type) OR $type === '')
			{
				$type = 'file';
			}

			/** @var class-string<Auth> $class  the driver class, e.g. Auth_File */
			$class = 'Auth_'.ucfirst($type);

			// Create a new session instance
			Auth::$_instance = new $class($config);
		}

		return Auth::$_instance;
	}

	protected Session $_session;

	/**
	 * @var  Config_Group|array<string, mixed>  settings of the auth config file
	 */
	protected Config_Group|array $_config;

	/**
	 * Loads Session and configuration options.
	 *
	 * @param   array<string, mixed>  $config  Config Options
	 * @param   array<string, mixed>|Config_Group  $config
	 * @return  void
	 */
	public function __construct(Config_Group|array $config = [])
	{
		// Save the config in the object
		$this->_config = $config;

		$this->_session = Session::instance($this->_setting('session_type') ?: null);
	}

	/**
	 * A setting of the auth config file, as text.
	 *
	 * @param   string  $key      name of the setting
	 * @param   string  $default  value when it is not set
	 * @return  string
	 */
	protected function _setting(string $key, string $default = ''): string
	{
		$value = $this->_config[$key] ?? $default;

		return is_scalar($value) ? (string) $value : $default;
	}

	abstract protected function _login(mixed $username, string $password, bool $remember): bool;

	abstract public function password(mixed $username): mixed;

	abstract public function check_password(string $password): bool;

	/**
	 * Gets the currently logged in user from the session.
	 * Returns null if no user is currently logged in.
	 *
	 * @param   mixed  $default  Default value to return if the user is currently not logged in.
	 * @return  mixed
	 */
	public function get_user(mixed $default = null): mixed
	{
		return $this->_session->get($this->_setting('session_key'), $default);
	}

	/**
	 * Attempt to log in a user by using an ORM object and plain-text password.
	 *
	 * @param   mixed  $username  user name, or a user object (visitor input: anything else fails)
	 * @param   mixed  $password  password to check (visitor input: anything but a text fails)
	 * @param   bool   $remember  enable autologin
	 * @return  bool
	 */
	public function login(mixed $username, mixed $password, bool $remember = false): bool
	{
		// Both come straight from user input: a password that is empty or not
		// a string, or a user that is neither a name nor a user object, can
		// never log in
		if ( ! is_string($password) OR $password === '')
			return false;

		if ( ! is_string($username) AND ! is_object($username))
			return false;

		return $this->_login($username, $password, $remember);
	}

	/**
	 * Log out a user by removing the related session variables.
	 *
	 * @param   bool  $destroy     Completely destroy the session
	 * @param   bool  $logout_all  Remove all tokens for user
	 * @return  bool
	 */
	public function logout(bool $destroy = false, bool $logout_all = false): bool
	{
		if ($destroy === true)
		{
			// Destroy the session completely
			$this->_session->destroy();
		}
		else
		{
			// Remove the user from the session
			$this->_session->delete($this->_setting('session_key'));

			// Regenerate session_id
			$this->_session->regenerate();
		}

		// Double check
		return ! $this->logged_in();
	}

	/**
	 * Check if there is an active session. Optionally allows checking for a
	 * specific role.
	 *
	 * @param   string  $role  role name
	 * @return  bool
	 */
	public function logged_in(mixed $role = null): bool
	{
		return ($this->get_user() !== null);
	}

	/**
	 * Hashes a password for storage, with PHP's password_hash().
	 *
	 * The result contains a random salt, so hashing the same password twice
	 * gives two different strings: compare a password with a stored hash
	 * using [Auth::verify], never by hashing it again.
	 *
	 * @param   string  $str  password to hash
	 * @return  string
	 */
	public function hash(string $str): string
	{
		return password_hash($str, PASSWORD_DEFAULT);
	}

	/**
	 * Checks a password against a made-up hash and throws the result away.
	 * Drivers call this when the user does not exist, so that a login with
	 * an unknown name takes as long as one with a wrong password.
	 *
	 * @param   string  $password  password as typed
	 * @return  void
	 */
	protected function _fake_verify(string $password): void
	{
		// Made once per request, with the same settings as real hashes
		/** @var string|null $hash */
		static $hash = null;

		$hash ??= $this->hash(bin2hex(random_bytes(16)));

		password_verify($password, $hash);
	}

	/**
	 * Checks a password against a stored hash.
	 *
	 * Hashes made by [Auth::hash] are checked with password_verify().
	 * Hashes stored by older versions (an HMAC of the password with the
	 * "hash_key" of the auth config) are still accepted, so existing users
	 * can log in; [Auth::needs_rehash] then tells that such a hash should
	 * be replaced.
	 *
	 * @param   string  $password  password as typed
	 * @param   string  $hash      stored hash
	 * @return  bool
	 */
	public function verify(string $password, string $hash): bool
	{
		if ($hash === '')
			return false;

		if (password_get_info($hash)['algo'] !== null)
		{
			// A password_hash() hash
			return password_verify($password, $hash);
		}

		// An old HMAC hash can only be checked with the key it was made with
		if (empty($this->_config['hash_key']))
			return false;

		// hash_equals() compares in constant time
		return hash_equals($hash, $this->legacy_hash($password));
	}

	/**
	 * Tells whether a stored hash should be replaced by a new one: because
	 * it is an old HMAC hash, or because PHP's default algorithm or cost
	 * has become stronger since it was made.
	 *
	 * @param   string  $hash  stored hash
	 * @return  bool
	 */
	public function needs_rehash(string $hash): bool
	{
		return password_needs_rehash($hash, PASSWORD_DEFAULT);
	}

	/**
	 * Hashes a password the way older versions did: an HMAC with the
	 * "hash_method" and "hash_key" of the auth config. Only used to check
	 * hashes that were stored before the move to password_hash().
	 *
	 * @param   string  $str  password to hash
	 * @return  string
	 * @throws  Kohana_Exception  if no hash key is configured
	 */
	public function legacy_hash(string $str): string
	{
		if (empty($this->_config['hash_key']))
			throw new Kohana_Exception('A valid hash key must be set in your auth config.');

		return hash_hmac($this->_setting('hash_method', 'sha256'), $str, $this->_setting('hash_key'));
	}

	protected function complete_login(mixed $user): bool
	{
		// Regenerate session_id
		$this->_session->regenerate();

		// Store username in session
		$this->_session->set($this->_setting('session_key'), $user);

		return true;
	}

}
