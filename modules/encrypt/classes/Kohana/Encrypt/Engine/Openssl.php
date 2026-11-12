<?php
/**
 * The Encrypt Openssl engine provides two-way encryption of text and binary strings
 * using the [OpenSSL](http://php.net/openssl) extension, which consists of two
 * parts: the key and the cipher.
 *
 * The Key
 * :  A secret passphrase that is used for encoding and decoding
 * The Cipher
 * :  A [cipher](http://php.net/manual/en/openssl.ciphers.php) determines how the encryption
 *    is mathematically calculated. By default, the "AES-256-CBC" cipher is used.
 *
 * @package    Kohana/Encrypt
 * @category   Security
 *
 * @copyright  (c) 2007-2012 Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Encrypt_Engine_Openssl extends Kohana_Encrypt_Engine {

	/**
	 * AES_128_CBC
	 * @var    string
	 */
	const string AES_128_CBC = 'AES-128-CBC';

	/**
	 * AES_256_CBC
	 * @var    string
	 */
	const string AES_256_CBC = 'AES-256-CBC';

	/**
	 * Creates a new openssl wrapper.
	 *
	 * @param  array<string, mixed> $config Configuration
	 *
	 * @throws Kohana_Exception
	 */
	public function __construct(array $config)
	{
		if (!extension_loaded('openssl')) {
			// @codeCoverageIgnoreStart
			throw new Kohana_Exception('OpenSSL extension is not installed.');
			// @codeCoverageIgnoreEnd
		}

		parent::__construct($config);

		$this->_cipher = is_string($config['cipher'] ?? null) ? $config['cipher'] : self::AES_256_CBC;

		$iv_size = @openssl_cipher_iv_length($this->_cipher);

		if ($iv_size === false)
			throw new Kohana_Exception('OpenSSL does not know the cipher ":cipher" of the encryption configuration', [':cipher' => $this->_cipher]);

		$this->_iv_size = $iv_size;

		$required_length = $this->_cipher === self::AES_128_CBC ? 16 : 32;

		$this->valid_key_length($required_length);
	}

	/**
	 * Encrypts a string and returns an encrypted string that can be decoded.
	 *
	 * @param string $data
	 * @param string $iv
	 *
	 * @return null|string
	 */
	public function encrypt(string $data, string $iv): ?string
	{
		// First we will encrypt the value using OpenSSL. After this is encrypted we
		// will proceed to calculating a MAC for the encrypted value so that this
		// value can be verified later as not having been changed by the users.
		$value = openssl_encrypt($data, $this->_cipher, $this->_key, 0, $iv);

		// Nothing could be encrypted (a wrong size of the key or of $iv)
		if ($value === false)
			return null;

		// Once we have the encrypted value we will go ahead base64_encode the input
		// vector and create the MAC for the encrypted value so we can verify its
		// authenticity. Then, we'll JSON encode the data in a "payload" array.
		$mac = $this->hash($iv = base64_encode($iv), $value);

		$json = json_encode(compact('iv', 'value', 'mac'));

		return !is_string($json) ? null : base64_encode($json);
	}

	/**
	 * Create a MAC for the given value.
	 *
	 * @param  string $iv
	 * @param  string  $value
	 *
	 * @return string
	 */
	protected function hash(string $iv, string $value): string
	{
		return hash_hmac('sha256', $iv.$value, $this->_key);
	}

	/**
	 * Decrypts an encoded string back to its original value.
	 *
	 * @param   string $data encoded string to be decrypted
	 *
	 * @return null|string if decryption fails
	 * @throws Exception
	 */
	public function decrypt(string $data): ?string
	{
		// Convert the data back to an array; strict base64 rejects anything malformed
		$json = base64_decode($data, true);
		$data = is_string($json) ? json_decode($json, true) : null;

		// If the payload is not valid JSON or does not have the proper keys set we will
		// assume it is invalid and bail out of the routine since we will not be able
		// to decrypt the given value. We'll also check the MAC for this encryption.
		if ( ! $this->valid_payload($data) OR ! $this->valid_mac($data))
		{
			// Decryption failed
			return null;
		}

		// The MAC is valid, so the payload was produced with our key: decrypt it
		$decrypted = openssl_decrypt($data['value'], $this->_cipher, $this->_key, 0, (string) base64_decode($data['iv'], true));

		return $decrypted === false ? null : $decrypted;
	}

	/**
	 * Verify that the encryption payload is valid.
	 *
	 * @param  mixed $payload
	 *
	 * @return bool
	 * @phpstan-assert-if-true array{iv: string, value: string, mac: string} $payload
	 */
	protected function valid_payload(mixed $payload): bool
	{
		// All three parts must be present and must be strings
		if ( ! is_array($payload)
			OR ! is_string($payload['iv'] ?? null)
			OR ! is_string($payload['value'] ?? null)
			OR ! is_string($payload['mac'] ?? null))
			return false;

		// The IV must decode to exactly the size the cipher needs
		$iv = base64_decode($payload['iv'], true);

		return is_string($iv) AND strlen($iv) === $this->_iv_size;
	}

	/**
	 * Determine if the MAC for the given payload is valid.
	 *
	 * @param  array{iv: string, value: string, mac: string} $payload
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected function valid_mac(array $payload): bool
	{
		$bytes = $this->create_iv();
		$calculated = hash_hmac('sha256', $this->hash($payload['iv'], $payload['value']), $bytes, true);
		return hash_equals(hash_hmac('sha256', $payload['mac'], $bytes, true), $calculated);
	}
}
