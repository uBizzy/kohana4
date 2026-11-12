<?php

/**
 * Bcrypt Auth driver: the ORM driver, with passwords always hashed by
 * bcrypt at the cost set in the auth config.
 *
 * Everything else (login, roles, "remember me" tokens, forced login) is
 * that of [Auth_ORM]. Old HMAC hashes are not accepted by this driver.
 *
 * @package    Kohana/Auth
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Auth_Bcrypt extends Auth_ORM {

	/**
	 * @param   array<string, mixed>|Config_Group  $config
	 * @throws  Kohana_Exception  if the cost is not set or lower than 10
	 */
	public function __construct(Config_Group|array $config = [])
	{
		if ( ! isset($config['cost']) OR ! is_numeric($config['cost']) OR $config['cost'] < 10)
		{
			throw new Kohana_Exception(__CLASS__ . ' cost parameter must be set and must be integer >= 10');
		}
		parent::__construct($config);
	}

	/**
	 * The options given to PHP's password functions.
	 *
	 * @return  array{cost: int}
	 */
	protected function _options(): array
	{
		return ['cost' => (int) $this->_setting('cost', (string) PASSWORD_BCRYPT_DEFAULT_COST)];
	}

	/**
	 * Hashes a password for storage, with bcrypt.
	 *
	 * @param   string  $str  password to hash
	 * @return  string
	 */
	public function hash(string $str): string
	{
		return password_hash($str, PASSWORD_BCRYPT, $this->_options());
	}

	/**
	 * Checks a password against a stored hash. Only password_hash()
	 * hashes are accepted.
	 *
	 * @param   string  $password  password as typed
	 * @param   string  $hash      stored hash
	 * @return  bool
	 */
	public function verify(string $password, string $hash): bool
	{
		return password_verify($password, $hash);
	}

	/**
	 * Tells whether a stored hash should be replaced: it is not bcrypt, or
	 * was made with another cost than the configured one.
	 *
	 * @param   string  $hash  stored hash
	 * @return  bool
	 */
	public function needs_rehash(string $hash): bool
	{
		return password_needs_rehash($hash, PASSWORD_BCRYPT, $this->_options());
	}

} // End Auth Bcrypt
