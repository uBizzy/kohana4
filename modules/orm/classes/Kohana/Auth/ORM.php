<?php
/**
 * ORM Auth driver.
 *
 * @package    Kohana/Auth
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Auth_ORM extends Auth {

	/**
	 * Checks if a session is active.
	 *
	 * @param   mixed    $role Role name string, role ORM object, or array with role names
	 * @return  bool
	 */
	public function logged_in(mixed $role = null): bool
	{
		// Get the user from the session
		$user = $this->get_user();

		if ( ! $user instanceof Model_Auth_User)
			return false;

		if ($user instanceof Model_User AND $user->loaded())
		{
			// If we don't have a roll no further checking is needed
			if ( ! $role)
				return true;

			if (is_array($role))
			{
				// Get all the roles
				$roles = ORM::factory('Role')
							->where('name', 'IN', $role)
							->find_all()
							->as_array(null, 'id');

				// Make sure all the roles are valid ones
				if (count($roles) !== count($role))
					return false;
			}
			else
			{
				if ( ! is_object($role))
				{
					// Load the role
					$roles = ORM::factory('Role', ['name' => $role]);

					if ( ! $roles->loaded())
						return false;
				}
				else
				{
					$roles = $role;
				}
			}

			return $user->has('roles', $roles);
		}

		// Whatever is in the session is not a loaded user
		return false;
	}

	/**
	 * Logs a user in.
	 *
	 * @param   Model_Auth_User|string   $user      user name, or user model
	 * @param   string  $password
	 * @param   bool    $remember  enable autologin
	 * @return  bool
	 */
	protected function _login(mixed $user, string $password, bool $remember): bool
	{
		if ( ! is_object($user))
		{
			$username = $user;

			// Load the user
			/** @var Model_User $user */
			$user = ORM::factory('User');
			$user->where($user->unique_key($username), '=', $username)->find();
		}

		if ( ! $user->loaded())
		{
			// Unknown user: still check a password, so the answer takes as
			// long as for a wrong password and user names cannot be guessed
			$this->_fake_verify($password);

			return false;
		}

		// If the passwords match, perform a login
		if ($user->has('roles', ORM::factory('Role', ['name' => 'login'])) AND $this->verify($password, (string) $user->password))
		{
			if ($this->needs_rehash( (string) $user->password))
			{
				// The stored hash is of an older kind: replace it now that we
				// know the password. The model hashes the value when it is set.
				$user->password = $password;
				$user->update();
			}

			if ($remember === true)
			{
				// Token data
				$data = [
					'user_id'    => $user->pk(),
					'expires'    => time() + (int) $this->_setting('lifetime'),
					'user_agent' => hash('sha256', (string) Request::$user_agent),
				];

				// Create a new autologin token
				/** @var Model_User_Token $token */
				$token = ORM::factory('User_Token')
							->values($data)
							->create();

				// Set the autologin cookie
				Cookie::set('authautologin', $token->token, (int) $this->_setting('lifetime'));
			}

			// Finish the login
			$this->complete_login($user);

			return true;
		}

		// Login failed
		return false;
	}

	/**
	 * Forces a user to be logged in, without specifying a password.
	 *
	 * @param   Model_Auth_User|string    $user                    username string, or user ORM object
	 * @param   bool  $mark_session_as_forced  mark the session as forced
	 * @return  void
	 */
	public function force_login(mixed $user, bool $mark_session_as_forced = false): void
	{
		if ( ! is_object($user))
		{
			$username = $user;

			// Load the user
			/** @var Model_User $user */
			$user = ORM::factory('User');
			$user->where($user->unique_key($username), '=', $username)->find();
		}

		// Never start a session for a user that does not exist
		if ( ! $user->loaded())
			throw new Kohana_Exception('Cannot force the login, the user was not found');

		if ($mark_session_as_forced === true)
		{
			// Mark the session as forced, to prevent users from changing account information
			$this->_session->set('auth_forced', true);
		}

		// Run the standard completion
		$this->complete_login($user);
	}

	/**
	 * Logs a user in, based on the authautologin cookie.
	 *
	 * @return  mixed
	 */
	public function auto_login(): mixed
	{
		if ($token = Cookie::get('authautologin'))
		{
			// Load the token and user
			/** @var Model_User_Token $token */
			$token = ORM::factory('User_Token', ['token' => $token]);

			if ($token->loaded() AND $token->user->loaded())
			{
				if (hash_equals(hash('sha256', (string) Request::$user_agent), (string) $token->user_agent))
				{
					// Save the token to create a new unique token
					$token->save();

					// Set the new token
					Cookie::set('authautologin', $token->token, (int) $token->expires - time());

					// Complete the login with the found data
					$this->complete_login($token->user);

					// Automatic login was successful
					return $token->user;
				}

				// Token is invalid
				$token->delete();
			}
		}

		return false;
	}

	/**
	 * Gets the currently logged in user from the session (with auto_login check).
	 * Returns $default if no user is currently logged in.
	 *
	 * @param   mixed    $default to return in case user isn't logged in
	 * @return  mixed
	 */
	public function get_user(mixed $default = null): mixed
	{
		$user = parent::get_user($default);

		if ($user === $default)
		{
			// check for "remembered" login
			if (($user = $this->auto_login()) === false)
				return $default;
		}

		return $user;
	}

	/**
	 * Log a user out and remove any autologin cookies.
	 *
	 * @param   bool  $destroy     completely destroy the session
	 * @param	bool  $logout_all  remove all tokens for user
	 * @return  bool
	 */
	public function logout(bool $destroy = false, bool $logout_all = false): bool
	{
		// Set by force_login()
		$this->_session->delete('auth_forced');

		if ($token = Cookie::get('authautologin'))
		{
			// Delete the autologin cookie to prevent re-login
			Cookie::delete('authautologin');

			// Clear the autologin token from the database
			/** @var Model_User_Token $token */
			$token = ORM::factory('User_Token', ['token' => $token]);

			if ($token->loaded() AND $logout_all)
			{
				// Delete all user tokens. This isn't the most elegant solution but does the job
				$tokens = ORM::factory('User_Token')->where('user_id','=',$token->user_id)->find_all();

				/** @var Model_User_Token $_token */
				foreach ($tokens as $_token)
				{
					$_token->delete();
				}
			}
			elseif ($token->loaded())
			{
				$token->delete();
			}
		}

		return parent::logout($destroy);
	}

	/**
	 * Get the stored password for a username.
	 *
	 * @param   Model_Auth_User|string   $user  username string, or user ORM object
	 * @return  string
	 */
	public function password(mixed $user): mixed
	{
		if ( ! is_object($user))
		{
			$username = $user;

			// Load the user
			/** @var Model_User $user */
			$user = ORM::factory('User');
			$user->where($user->unique_key($username), '=', $username)->find();
		}

		return (string) $user->password;
	}

	/**
	 * Complete the login for a user by incrementing the logins and setting
	 * session data: user_id, username, roles.
	 *
	 * @param   Model_Auth_User  $user  user ORM object
	 * @return  bool
	 */
	protected function complete_login(mixed $user): bool
	{
		$user->complete_login();

		return parent::complete_login($user);
	}

	/**
	 * Compare password with original (hashed). Works for current (logged in) user
	 *
	 * @param   string  $password
	 * @return  bool
	 */
	public function check_password(string $password): bool
	{
		$user = $this->get_user();

		if ( ! $user instanceof Model_Auth_User)
			return false;

		return $this->verify($password, (string) $user->password);
	}

} // End Auth ORM
