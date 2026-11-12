<?php
/**
 * The Encrypt Sodium engine provides two-way encryption of text and binary strings
 * using the [Sodium](http://php.net/sodium) extension, which consists of two
 * parts: the key and the cipher.
 *
 * The Key
 * :  A secret passphrase that is used for encoding and decoding
 * The Cipher
 * :  A [cipher](https://paragonie.com/book/pecl-libsodium/read/08-advanced.md) determines how the encryption
 *    is mathematically calculated. By default, the "AES-256-GCM" cipher is used.
 *
 * @package    Kohana/Encrypt
 * @category   Security
 *
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Encrypt_Engine_Sodium extends Kohana_Encrypt_Engine {

	/**
	 * AES 256 + GCM
	 * NOTE only available on specific hardware
	 */
	const string AES_256_GCM = 'aes256gcm';

	/**
	 * ChaCha20 + Poly1305
	 */
	const string CHACHA_POLY = 'chacha20poly1305';

	/**
	 * ChaCha20 + Poly1305 [IETF]
	 */
	const string CHACHA_POLY_IETF = 'chacha20poly1305_ietf';

	/**
	 * XChaCha20 + Poly1305 [IETF]
	 */
	const string XCHACHA_POLY_IETF = 'xchacha20poly1305_ietf';

	/**
	 * Sodium constructor.
	 *
	 * @param array<string, mixed> $config Array with configuration
	 *
	 * @throws Kohana_Exception
	 */
	public function __construct(array $config)
	{
		if (!extension_loaded('sodium')) {
			// @codeCoverageIgnoreStart
			throw new Kohana_Exception('Sodium extension is not available');
			// @codeCoverageIgnoreEnd
		}

		// Check if cipher is set, otherwise fallback to AES 256 + GCM
		$this->_cipher = is_string($config['cipher'] ?? null) ? $config['cipher'] : self::AES_256_GCM;

		// Can you access AES-256-GCM? This is only available if you have supported hardware.
		if ($this->_cipher === Encrypt_Engine_Sodium::AES_256_GCM && !sodium_crypto_aead_aes256gcm_is_available()) {
			// @codeCoverageIgnoreStart
			throw new Kohana_Exception('AES-256-GCM is not available on your hardware.');
			// @codeCoverageIgnoreEnd
		}

		parent::__construct($config);

		/** @var int $required_length */
		$required_length = constant('SODIUM_CRYPTO_AEAD_'.strtoupper($this->_cipher).'_KEYBYTES');

		$this->valid_key_length($required_length);

		/** @var int $iv_size */
		$iv_size = constant('SODIUM_CRYPTO_AEAD_'.strtoupper($this->_cipher).'_NPUBBYTES');

		$this->_iv_size = $iv_size;
	}

	/**
	 * The sodium function of the chosen cipher for an action.
	 *
	 * @param   string  $action  "encrypt" or "decrypt"
	 * @return  callable
	 * @throws  Kohana_Exception  if sodium does not have the cipher
	 */
	protected function _function(string $action): callable
	{
		$function = 'sodium_crypto_aead_'.$this->_cipher.'_'.$action;

		if ( ! is_callable($function))
			throw new Kohana_Exception('The sodium cipher :cipher is not available', [':cipher' => $this->_cipher]);

		return $function;
	}

	/**
	 * Encrypts a message. See [Encrypt::encode].
	 *
	 * @param   string  $message  the text to encrypt
	 * @param   string  $iv       the nonce
	 * @return  string|null
	 */
	public function encrypt(string $message, string $iv): ?string
	{
		$value = call_user_func($this->_function('encrypt'), $message, '', $iv, $this->_key);

		//Base64 encode binary data, otherwise they cannot be transformed into JSON.
		$value = base64_encode(is_string($value) ? $value : '');
		$iv = base64_encode($iv);

		$json = json_encode(compact('iv', 'value'));

		return !is_string($json) ? null : base64_encode($json);
	}

	/**
	 * Decrypts the ciphertext
	 *
	 * @param  string $ciphertext Ciphertext to be decrypted
	 *
	 * @return null|string
	 */
	public function decrypt(string $ciphertext): ?string
	{
		// Convert the data back to binary
		$decode = base64_decode($ciphertext, true);

		// If the payload is not valid JSON or does not have the proper keys set we will
		// assume it is invalid and bail out of the routine since we will not be able
		// to decrypt the given value. We'll also check the MAC for this encryption.
		if ($decode === false) {
			return null;
		}

		$data = json_decode($decode, true);

		if ($data === null || !$this->valid_payload($data)) {
			return null;
		}

		$iv = base64_decode($data['iv'], true);
		$value = base64_decode($data['value'], true);

		// Here we will decrypt the value.
		// If we are unable to decrypt this value we will return null.
		$decrypted = call_user_func($this->_function('decrypt'), $value, '', $iv, $this->_key);

		return is_string($decrypted) ? $decrypted : null;
	}

	/**
	 * Verify that the encryption payload is valid.
	 *
	 * @param  mixed $payload
	 *
	 * @return bool
	 * @phpstan-assert-if-true array{iv: string, value: string} $payload
	 */
	protected function valid_payload(mixed $payload): bool
	{
		// Both parts must be present and must be strings
		if ( ! is_array($payload)
			OR ! is_string($payload['iv'] ?? null)
			OR ! is_string($payload['value'] ?? null))
			return false;

		// Both must be valid base64, and the IV exactly the size the cipher needs
		$iv = base64_decode($payload['iv'], true);

		return is_string($iv)
			AND strlen($iv) === $this->_iv_size
			AND base64_decode($payload['value'], true) !== false;
	}
}
