# Passwords

Passwords are never stored. What is stored is a **hash** made by PHP's `password_hash()`: it cannot be turned back into the password, it is different for every user even with the same password, and it is slow on purpose, so that guessing is expensive.

## The methods

```php
$auth = Auth::instance();

// Make a hash to store
$hash = $auth->hash('correct horse battery staple');
// $2y$12$N9qo8uLOickgx2ZMRZoMye...

// Check a password against a stored hash
if ($auth->verify($password, $hash))
{
	// Right
}

// Was the hash made with older settings?
if ($auth->needs_rehash($hash))
{
	$hash = $auth->hash($password);
	// ... store the new hash
}
```

`login()` does all of this. You only need these methods for code of your own: a password change form, an import.

## Storing

The column must hold at least 255 characters, so that hashes of future algorithms fit.

```sql
ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL;
```

With the ORM driver, the model hashes the password when it is set:

```php
$user->password = 'the new password';   // stored as a hash
$user->save();
```

## Hashes from older versions

Before version 4.0, passwords were stored as an HMAC made with the `hash_key` of the config. Such users can still log in:

1. Keep your old `hash_method` and `hash_key` in `config/auth.php`.
2. At log in, an old hash is checked the old way. When the password is right, it is stored again with `password_hash()`.
3. When every user has logged in once (or after a date you set, by asking the rest to reset their password), remove the two settings.

```php
// application/config/auth.php
return [
	'driver'      => 'ORM',
	'hash_method' => 'sha256',
	'hash_key'    => 'the key of your old installation',
];
```

## Rules for a password form

```php
$validation = Validation::factory($this->request->post())
	->rule('password', 'not_empty')
	->rule('password', 'min_length', [':value', 10])
	->rule('password_confirm', 'matches', [':validation', 'password_confirm', 'password']);
```

- Ask for length, not for symbols: a long sentence is stronger than `P@ssw0rd!`.
- Do not limit the length to something short, and do not forbid characters. Pasting from a password manager must work.
- Ask for the current password before changing it, and log other devices out afterwards: `Auth::instance()->logout(false, true)`.
- Limit attempts: see [Rate Limiting](../kohana/security/throttle).

## Password reset

The module has no password reset. A sound one works like this:

```php
// 1. The visitor asks for a reset: store the hash of a random token, send the token by email
$token = Text::random('hexdec', 64);

DB::insert('password_resets', ['user_id', 'token_hash', 'expires'])
	->values([$user->id, hash('sha256', $token), time() + Date::HOUR])
	->execute();

// 2. The link in the email comes back with the token: look for its hash
$reset = DB::select()->from('password_resets')
	->where('token_hash', '=', hash('sha256', (string) $this->request->query('token')))
	->where('expires', '>', time())
	->execute()
	->current();
```

- Answer the same ("If the address is known, we sent a link") whether the address exists or not.
- A token works once and for a short time.
- Store only the hash of the token, as with passwords.
