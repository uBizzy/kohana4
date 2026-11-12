<?php
/**
 * Native PHP session class.
 *
 * @package    Kohana
 * @category   Session
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Session_Native extends Session {

	/**
	 * @return  string
	 */
	public function id(): ?string
	{
		// session_id() gives '' or false when there is no session
		return session_id() ?: null;
	}

	/**
	 * @param   string  $id  session id
	 * @return  null
	 */
	protected function _read(?string $id = null): ?string
	{
		/**
		 * session_set_cookie_params will override php ini settings
		 * If Cookie::$domain is null or empty and is passed, PHP
		 * will override ini and sent cookies with the host name
		 * of the server which generated the cookie
		 *
		 * see issue #3604
		 *
		 * see http://www.php.net/manual/en/function.session-set-cookie-params.php
		 * see http://www.php.net/manual/en/session.configuration.php#ini.session.cookie-domain
		 *
		 * set to Cookie::$domain if available, otherwise default to ini setting
		 */
		$session_cookie_domain = empty(Cookie::$domain)
			? ini_get('session.cookie_domain'): Cookie::$domain;

		// Sync up the session cookie with the Cookie settings (path, secure,
		// httponly, samesite); sessions take a lifetime instead of an expiry time
		$params = Cookie::options();
		unset($params['expires']);
		$params['lifetime'] = $this->_lifetime;
		$params['domain']   = $session_cookie_domain;

		// @phpstan-ignore argument.type (the values are those of Cookie::options())
		session_set_cookie_params($params);

		// Only accept session ids that PHP itself created, and only from the
		// cookie: an attacker cannot plant a session id of their choice
		ini_set('session.use_strict_mode', '1');
		ini_set('session.use_only_cookies', '1');

		// Do not allow PHP to send Cache-Control headers
		session_cache_limiter('');

		// Set the session cookie name
		session_name($this->_name);

		if ($id)
		{
			// Set the session id
			session_id($id);
		}

		// Start the session
		try
		{
			session_start();
		}
		catch (Exception $e)
		{
			// The stored session is unusable: remove it and start a new one
			$this->_destroy();
			session_start();
		}

		// If the session could not start (e.g. output already sent), $_SESSION does
		// not exist: fall back to an empty array so the data lives for this request only
		$_SESSION ??= [];

		// Use the $_SESSION global for storing data
		$this->_data =& $_SESSION;

		return null;
	}

	/**
	 * @return  string
	 */
	protected function _regenerate(): ?string
	{
		// Regenerate the session id; true also deletes the old session, so
		// the old id cannot be used any more
		session_regenerate_id(true);

		return session_id() ?: null;
	}

	/**
	 * @return  bool
	 */
	protected function _write(): bool
	{
		// Write and close the session
		session_write_close();

		return true;
	}

	/**
	 * @return  bool
	 */
	protected function _restart(): bool
	{
		// Fire up a new session
		$status = session_start();

		// If the session could not start (e.g. output already sent), $_SESSION does
		// not exist: fall back to an empty array so the data lives for this request only
		$_SESSION ??= [];

		// Use the $_SESSION global for storing data
		$this->_data =& $_SESSION;

		return $status;
	}

	/**
	 * @return  bool
	 */
	protected function _destroy(): bool
	{
		// Destroy the current session
		session_destroy();

		// Did destruction work?
		$status = ! session_id();

		if ($status)
		{
			// Make sure the session cannot be restarted
			Cookie::delete($this->_name);
		}

		return $status;
	}

}
