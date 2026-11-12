<?php
/**
 * Cookie helper.
 *
 * @package    Kohana
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Cookie {

	/**
	 * @var  string|null  Secret key used to sign cookies; must be set in bootstrap.php
	 */
	public static ?string $salt = null;

	/**
	 * @var  int  Length of the signature prepended to every cookie value (SHA-256 in hexadecimal)
	 */
	const int SIGNATURE_LENGTH = 64;

	/**
	 * @var  int  Signature length used before the move to SHA-256 (SHA-1 in hexadecimal)
	 */
	const int LEGACY_SIGNATURE_LENGTH = 40;

	/**
	 * @var  int  Number of seconds before the cookie expires
	 */
	public static int $expiration = 0;

	/**
	 * @var  string  Restrict the path that the cookie is available to
	 */
	public static string $path = '/';

	/**
	 * @var  string  Restrict the domain that the cookie is available to
	 */
	public static string $domain = '';

	/**
	 * @var  bool|null  Only transmit cookies over secure (HTTPS) connections.
	 *                  null (the default) means automatic: secure whenever the
	 *                  current request itself is HTTPS.
	 */
	public static ?bool $secure = null;

	/**
	 * @var  bool  Only transmit cookies over HTTP, disabling Javascript access
	 */
	public static bool $httponly = true;

	/**
	 * @var  string  SameSite policy: 'Lax', 'Strict' or 'None'. It controls whether
	 *               the browser sends the cookie on requests coming from other
	 *               sites, which is a defence against CSRF. 'None' is only
	 *               accepted by browsers together with the secure flag, so it
	 *               forces that flag on.
	 */
	public static string $samesite = 'Lax';

	/**
	 * Gets the value of a signed cookie. Cookies without signatures will not
	 * be returned. If the cookie signature is present, but invalid, the cookie
	 * will be deleted.
	 *
	 *     // Get the "theme" cookie, or use "blue" if the cookie does not exist
	 *     $theme = Cookie::get('theme', 'blue');
	 *
	 * @param   string  $key        cookie name
	 * @param   mixed   $default    default value to return
	 * @return  mixed   the cookie value (a string), or $default
	 */
	public static function get(string $key, mixed $default = null): mixed
	{
		if ( ! isset($_COOKIE[$key]) OR ! is_string($_COOKIE[$key]))
		{
			// The cookie does not exist (or is an array, which is never a signed cookie)
			return $default;
		}

		// Get the cookie value
		$cookie = $_COOKIE[$key];

		// A signed cookie is "<signature>~<value>"; the signature has a fixed length
		if (isset($cookie[Cookie::SIGNATURE_LENGTH]) AND $cookie[Cookie::SIGNATURE_LENGTH] === '~')
		{
			// Separate the signature and the value
			list ($hash, $value) = explode('~', $cookie, 2);

			if (hash_equals(Cookie::salt($key, $value), $hash))
			{
				// Cookie signature is valid
				return $value;
			}

			// The cookie signature is invalid, delete it
			static::delete($key);
		}
		elseif (isset($cookie[Cookie::LEGACY_SIGNATURE_LENGTH]) AND $cookie[Cookie::LEGACY_SIGNATURE_LENGTH] === '~')
		{
			// Signed with the old SHA-1 algorithm: no longer trusted, delete it
			// so a new cookie can be issued
			static::delete($key);
		}

		return $default;
	}

	/**
	 * Checks whether a cookie exists and carries a valid signature.
	 *
	 *     if (Cookie::has('theme'))
	 *     {
	 *         // The "theme" cookie is set and was not tampered with
	 *     }
	 *
	 * @param   string  $key  cookie name
	 * @return  bool
	 * @uses    Cookie::get
	 */
	public static function has(string $key): bool
	{
		return static::get($key) !== null;
	}

	/**
	 * Sets a signed cookie. Note that all cookie values must be strings and no
	 * automatic serialization will be performed!
	 *
	 * [!!] By default, Cookie::$expiration is 0 - if you skip/pass null for the optional
	 *      lifetime argument the cookie lasts until the browser is closed, unless you
	 *      have separately configured Cookie::$expiration.
	 *
	 *
	 *     // Set the "theme" cookie
	 *     Cookie::set('theme', 'red');
	 *
	 * @param   string  $name       name of cookie
	 * @param   string  $value      value of cookie
	 * @param   ?int $lifetime  lifetime in seconds, null to use Cookie::$expiration
	 * @return  bool
	 * @uses    Cookie::salt
	 */
	public static function set(string $name, string $value, ?int $lifetime = null): bool
	{
		if ($lifetime === null)
		{
			// Use the default expiration
			$lifetime = Cookie::$expiration;
		}

		if ($lifetime !== 0)
		{
			// The expiration is expected to be a UNIX timestamp
			$lifetime += static::_time();
		}

		// Add the salt to the cookie value
		$value = Cookie::salt($name, $value).'~'.$value;

		return static::_setcookie($name, $value, static::options($lifetime));
	}

	/**
	 * Deletes a cookie by making the value null and expiring it.
	 *
	 *     Cookie::delete('theme');
	 *
	 * @param   string  $name   cookie name
	 * @return  bool
	 */
	public static function delete(string $name): bool
	{
		// Remove the cookie
		unset($_COOKIE[$name]);

		// Nullify the cookie and make it expire
		return static::_setcookie($name, '', static::options(-86400));
	}

	/**
	 * Generates the signature (HMAC-SHA-256, 64 hexadecimal characters) for a
	 * cookie, based on its name and value and keyed with Cookie::$salt.
	 *
	 *     $salt = Cookie::salt('theme', 'red');
	 *
	 * @param   string $name name of cookie
	 * @param   string $value value of cookie
	 *
	 * @throws Kohana_Exception if Cookie::$salt is not configured
	 * @return  string
	 */
	public static function salt(string $name, string $value): string
	{
		// Require a valid salt
		if ( ! Cookie::$salt)
		{
			throw new Kohana_Exception('A valid cookie salt is required. Please set Cookie::$salt in your bootstrap.php. For more information check the documentation');
		}

		// Sign name=value together so a signature cannot be reused for another cookie
		return hash_hmac('sha256', $name.'='.$value, Cookie::$salt);
	}

	/**
	 * Builds the options array for PHP's setcookie(), from the configured
	 * path, domain, secure, httponly and samesite settings.
	 *
	 *     setcookie('name', 'value', Cookie::options(time() + 3600));
	 *
	 * @param   int  $expires  UNIX timestamp when the cookie expires; 0 for a session cookie
	 * @return  array<string, mixed>  keys: expires, path, domain, secure, httponly, samesite
	 * @uses    Cookie::is_secure
	 */
	public static function options(int $expires = 0): array
	{
		// Normalise the policy name: "lax" -> "Lax"
		$samesite = ucfirst(strtolower(Cookie::$samesite));

		return [
			'expires'  => $expires,
			'path'     => Cookie::$path,
			'domain'   => Cookie::$domain,
			// Browsers reject SameSite=None cookies that are not secure
			'secure'   => ($samesite === 'None') ? true : static::is_secure(),
			'httponly' => Cookie::$httponly,
			'samesite' => $samesite,
		];
	}

	/**
	 * Tells whether cookies are sent with the secure flag: the value of
	 * Cookie::$secure or, when that is null, whether the current request is HTTPS.
	 *
	 * @return  bool
	 */
	public static function is_secure(): bool
	{
		if (Cookie::$secure !== null)
			return Cookie::$secure;

		if (Request::$initial !== null)
		{
			// The request knows about trusted proxies and forwarded HTTPS
			return (bool) Request::$initial->secure();
		}

		// No request yet (e.g. a session started in bootstrap.php): ask the server
		$https = $_SERVER['HTTPS'] ?? null;

		return ! empty($https) AND is_string($https) AND strtolower($https) !== 'off';
	}

	/**
	 * Proxy for the native setcookie function - to allow mocking in unit tests so that they do not fail when headers
	 * have been sent.
	 *
	 * @param   string  $name     cookie name
	 * @param   string  $value    cookie value, already signed
	 * @param   array<string, mixed>   $options  options array as built by Cookie::options()
	 * @return  bool
	 * @see     setcookie
	 */
	protected static function _setcookie(string $name, string $value, array $options): bool
	{
		// @phpstan-ignore argument.type (the options are those of Cookie::options())
		return setcookie($name, $value, $options);
	}

	/**
	 * Proxy for the native time function - to allow mocking of time-related logic in unit tests
	 *
	 * @return int
	 * @see    time
	 */
	protected static function _time(): int
	{
		return time();
	}

}
