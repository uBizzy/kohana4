<?php
/**
 * Cookie-based session class.
 *
 * @package    Kohana
 * @category   Session
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Session_Cookie extends Session {

	/**
	 * @var  int  biggest cookie, in bytes, that every browser accepts
	 */
	public const int MAX_SIZE = 4096;

	/**
	 * @param   string  $id  session id
	 * @return  string
	 */
	protected function _read(?string $id = null): ?string
	{
		$value = Cookie::get($this->_name, null);

		return is_string($value) ? $value : null;
	}

	/**
	 * @return  null
	 */
	protected function _regenerate(): ?string
	{
		// Cookie sessions have no id
		return null;
	}

	/**
	 * @return  bool
	 */
	protected function _write(): bool
	{
		$data = $this->__toString();

		// What the browser receives: the name, the signature and the encoded data
		$size = strlen($this->_name) + strlen(rawurlencode(Cookie::salt($this->_name, $data).'~'.$data));

		if ($size > Session_Cookie::MAX_SIZE)
		{
			// Browsers drop a cookie this big without any error, so the
			// session would be lost anyway. Session::write() logs this
			// exception and, in development, shows it.
			throw new Session_Exception('The session data is too big for a cookie (:size of :max bytes), use another session driver', [
				':size' => $size,
				':max'  => Session_Cookie::MAX_SIZE,
			]);
		}

		return Cookie::set($this->_name, $data, $this->_lifetime);
	}

	/**
	 * @return  bool
	 */
	protected function _restart(): bool
	{
		return true;
	}

	/**
	 * @return  bool
	 */
	protected function _destroy(): bool
	{
		return Cookie::delete($this->_name);
	}

}
