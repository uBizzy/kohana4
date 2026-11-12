<?php
/**
 * Security helper class.
 *
 * @package    Kohana
 * @category   Security
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Security {

	/**
	 * @var  string  key name used for token storage
	 */
	public static string $token_name = 'security_token';

	/**
	 * Generate and store a unique token which can be used to help prevent
	 * [CSRF](http://wikipedia.org/wiki/Cross_Site_Request_Forgery) attacks.
	 *
	 *     $token = Security::token();
	 *
	 * You can insert this token into your forms as a hidden field:
	 *
	 *     echo Form::hidden('csrf', Security::token());
	 *
	 * And then check it when using [Validation]:
	 *
	 *     $array->rules('csrf', array(
	 *         array('not_empty'),
	 *         array('Security::check'),
	 *     ));
	 *
	 * This provides a basic, but effective, method of preventing CSRF attacks.
	 *
	 * @param   bool    $new    force a new token to be generated?
	 * @return  string
	 * @uses    Session::instance
	 */
	public static function token(bool $new = false): string
	{
		$session = Session::instance();

		// Get the current token
		$token = $session->get(Security::$token_name);

		if ($new || ! is_string($token) || $token === '')
		{
			$token = Security::_generate_token();

			// Store the new token
			$session->set(Security::$token_name, $token);
		}

		return $token;
	}

	/**
	 * @var  string|null  The nonce of the current request, created on first use
	 */
	protected static ?string $_nonce = null;

	/**
	 * Returns the nonce of the current request: a random value that is the
	 * same for the whole request and different for every request.
	 *
	 * It is used with a Content-Security-Policy (see [HTTP::$security_headers]):
	 * the policy names the nonce, and only inline scripts that carry it run.
	 *
	 *     <script nonce="<?php echo Security::nonce() ?>"> ... </script>
	 *
	 * @return  string  24 characters, safe to use in HTML attributes and headers
	 */
	public static function nonce(): string
	{
		// 18 random bytes are 24 base64 characters, without padding
		return Security::$_nonce ??= base64_encode(random_bytes(18));
	}

	/**
	 * Generate a unique, unpredictable token (48 hexadecimal characters).
	 *
	 * Uses the operating system's cryptographically secure random source.
	 *
	 * @return  string
	 */
	protected static function _generate_token(): string
	{
		return bin2hex(random_bytes(24));
	}

	/**
	 * Check that the given token matches the currently stored security token.
	 *
	 *     if (Security::check($token))
	 *     {
	 *         // Pass
	 *     }
	 *
	 * The token usually comes straight from user input, so any value is
	 * accepted: anything that is not a string (null, an array, ...) simply fails.
	 *
	 * @param   mixed   $token  token to check
	 * @return  bool
	 * @uses    Security::token
	 */
	public static function check(mixed $token): bool
	{
		return is_string($token) AND hash_equals(Security::token(), $token);
	}

	/**
	 * Compare two hashes in a time-invariant manner.
	 * Prevents cryptographic side-channel attacks (timing attacks, specifically)
	 *
	 * Kept for compatibility: this is an alias of PHP's hash_equals().
	 *
	 * @param   string  $a  cryptographic hash
	 * @param   string  $b  cryptographic hash
	 * @return  bool
	 */
	public static function slow_equals(string $a, string $b): bool
	{
		return hash_equals($a, $b);
	}

	/**
	 * Encodes PHP tags in a string.
	 *
	 *     $str = Security::encode_php_tags($str);
	 *
	 * @param   string  $str    string to sanitize
	 * @return  string
	 */
	public static function encode_php_tags(string $str): string
	{
		return str_replace(['<?', '?>'], ['&lt;?', '?&gt;'], $str);
	}

	/**
	 * Checks a CSRF token like [Security::check] and, when it is valid,
	 * replaces it with a new one, so a token can be used only once.
	 *
	 *     if (Security::check_and_rotate($this->request->post('csrf'))) ...
	 *
	 * [!!] Other forms the visitor has open (a second browser tab) still
	 * hold the old token and will be refused. Use this for sensitive
	 * actions only, such as a change of password or a payment.
	 *
	 * @param   mixed  $token  token to check (visitor input)
	 * @return  bool
	 * @uses    Security::check
	 * @uses    Security::token
	 */
	public static function check_and_rotate(mixed $token): bool
	{
		if ( ! Security::check($token))
			return false;

		// Make a new token for the next form
		Security::token(true);

		return true;
	}

}
