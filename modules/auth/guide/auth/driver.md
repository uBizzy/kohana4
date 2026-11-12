# Writing a Driver

A driver tells Auth where users are and how to check a password. Write one when your users are somewhere else: another service, LDAP, a table of your own shape.

A driver is a class `Auth_<Name>` that extends `Auth` and has three methods.

```php
// application/classes/Auth/Api.php
class Auth_Api extends Auth {

	/**
	 * Checks the name and password, and logs the user in.
	 */
	protected function _login(mixed $username, string $password, bool $remember): bool
	{
		if ( ! is_string($username))
			return false;

		$response = Request::factory('https://accounts.example.org/check')
			->method(HTTP_Request::POST)
			->post(['username' => $username, 'password' => $password])
			->execute();

		if ($response->status() !== 200)
			return false;

		// Stores the user in the session, with a new session id
		return $this->complete_login($username);
	}

	/**
	 * Returns the stored password hash of a user.
	 */
	public function password(mixed $username): mixed
	{
		// This service does not give hashes out
		return null;
	}

	/**
	 * Is this the password of the logged-in user?
	 */
	public function check_password(string $password): bool
	{
		$username = $this->get_user();

		return $username !== null AND $this->_login($username, $password, false);
	}
}
```

```php
// application/config/auth.php
return [
	'driver' => 'Api',
];
```

## What the base class gives you

| Method | Does |
|---|---|
| `login($username, $password, $remember)` | Refuses an empty password and anything that is not text, then calls your `_login()` |
| `complete_login($user)` | Gives the session a new id and stores the user in it |
| `get_user($default)` | Returns what `complete_login()` stored |
| `logged_in($role)` | Is someone stored? Override it if your users have roles |
| `logout($destroy, $logout_all)` | Removes the user from the session |
| `hash()`, `verify()`, `needs_rehash()` | Password hashing: see [Passwords](passwords) |
| `$this->_config` | The `auth` config group, with settings of your own |

## Users in a table of your own

```php
class Auth_Members extends Auth {

	protected function _login(mixed $username, string $password, bool $remember): bool
	{
		if ( ! is_string($username))
			return false;

		$member = DB::select('id', 'email', 'password_hash')
			->from('members')
			->where('email', '=', $username)
			->where('blocked', '=', 0)
			->execute()
			->current();

		// verify() takes the same time whether the hash is right or wrong
		if ($member === null OR ! $this->verify($password, (string) $member['password_hash']))
			return false;

		if ($this->needs_rehash($member['password_hash']))
		{
			// Made with older settings: store a new hash now that we know the password
			DB::update('members')
				->set(['password_hash' => $this->hash($password)])
				->where('id', '=', $member['id'])
				->execute();
		}

		// Keep only what later pages need; never the hash
		return $this->complete_login(['id' => (int) $member['id'], 'email' => $member['email']]);
	}

	public function password(mixed $username): mixed
	{
		return DB::select('password_hash')->from('members')->where('email', '=', $username)->execute()->get('password_hash');
	}

	public function check_password(string $password): bool
	{
		$user = $this->get_user();

		return $user !== null AND $this->verify($password, (string) $this->password($user['email']));
	}
}
```

## Rules for a driver

- Return `false` for every failure; do not tell a wrong name from a wrong password.
- Call `complete_login()` only after the password was checked.
- Do not put the password hash, or anything secret, in the session.
- `_login()` receives `mixed` as the user name: check that it is what you expect.
