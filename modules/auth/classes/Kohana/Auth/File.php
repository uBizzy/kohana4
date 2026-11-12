<?php
/**
 * File Auth driver.
 * [!!] this Auth driver does not support roles nor autologin.
 *
 * @package    Kohana/Auth
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Auth_File extends Auth {

	/**
	 * @var  array<string, string>  User list
	 */
	protected array $_users = [];

	/**
	 * Constructor loads the user list into the class.
	 * @param   array<string, mixed>|Config_Group  $config
	 * @param   array<string, mixed>  $config
	 */
	public function __construct(Config_Group|array $config = [])
	{
		parent::__construct($config);

		// Load user list
		/** @var array<string, string> $users  user name => hash of the password */
		$users = (array) ($config['users'] ?? []);

		$this->_users = $users;
	}

	/**
	 * Logs a user in.
	 *
	 * @param   mixed   $username  user name (visitor input: anything but a text fails)
	 * @param   string  $password  password
	 * @param   bool    $remember  enable autologin (not supported)
	 * @return  bool
	 */
	protected function _login(mixed $username, string $password, bool $remember): bool
	{
		if ($remember)
		{
			throw new Kohana_Exception('File based auth does not support remember');
		}

		if ( ! is_string($username) OR ! isset($this->_users[$username]))
		{
			// Unknown user: still check a password, so the answer takes as
			// long as for a wrong password and user names cannot be guessed
			$this->_fake_verify($password);

			return false;
		}

		// Check the password against the hash stored in the config
		if ($this->verify($password, (string) $this->_users[$username]))
		{
			// Complete the login
			return $this->complete_login($username);
		}

		// Login failed
		return false;
	}

	/**
	 * Forces a user to be logged in, without specifying a password.
	 *
	 * @param   string    $username  Username
	 * @return  bool
	 * @throws  Kohana_Exception  when the user is not in the list of the config
	 */
	public function force_login(string $username): bool
	{
		// Never start a session for a user that does not exist
		if ( ! isset($this->_users[$username]))
			throw new Kohana_Exception('Cannot force the login, the user was not found');


		// Complete the login
		return $this->complete_login($username);
	}

	/**
	 * Get the stored password for a username.
	 *
	 * @param   mixed   $username  Username
	 * @return  mixed   the stored hash, or false when there is no such user
	 */
	public function password(mixed $username): mixed
	{
		return is_string($username) ? ($this->_users[$username] ?? false) : false;
	}

	/**
	 * Compare password with original (plain text). Works for current (logged in) user
	 *
	 * @param   string   $password  Password
	 * @return  bool
	 */
	public function check_password(string $password): bool
	{
		$username = $this->get_user();

		if ($username === false)
			return false;

		$stored = $this->password($username);

		if ( ! is_string($stored))
			return false;

		// Only the password itself: the stored hash is no proof of knowing it
		return $this->verify($password, $stored);
	}

}
