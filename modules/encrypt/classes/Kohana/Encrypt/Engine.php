<?php
/**
 * Abstract Encryption Engine Base Class.
 * Holds Encryption Key, Mode, Cipher and IV Size.
 *
 * Integrated Key Length Validation. Removes Key from var_dump
 * to avoid dumping encryption key.
 *
 * @category   Security
 * @package    Kohana/Encrypt
 *
 * @copyright  (c) 2007-2012 Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Encrypt_Engine {

	/**
	 * Encryption key
	 * @var string
	 */
	protected string $_key;

	/**
	 * Encryption Mode
	 * @var string
	 */
	protected ?string $_mode = null;

	/**
	 * Cipher
	 * @var string
	 */
	protected string $_cipher;

	/**
	 * The size of the Initialization Vector (IV) in bytes
	 * @var int
	 */
	protected int $_iv_size;

	/**
	 * Creates a new Encrypt object.
	 *
	 * @param  array<string, mixed> $config Configuration
	 *
	 * @throws Kohana_Exception
	 */
	public function __construct(array $config)
	{
		if ( ! is_string($config['key'] ?? null))
			throw new Kohana_Exception('No encryption key is defined in the encryption configuration');

		$this->_key = $config['key'];
	}

	/**
	 * Encrypts the message
	 *
	 * @param string $message Your message to be encrypted.
	 * @param string $iv
	 *
	 * @return null|string
	 */
	abstract public function encrypt(string $message, string $iv): ?string;

	/**
	 * Decrypts the ciphertext
	 *
	 * @param string $ciphertext Your ciphertext to be decrypted.
	 *
	 * @return null|string
	 */
	abstract public function decrypt(string $ciphertext): ?string;

	/**
	 * Creates random IV (Initialization vector) for each encryption action.
	 *
	 * @throws Exception     Not possible to gather sufficient entropy.
	 * @return string         Initialization Vector
	 */
	public function create_iv(): string
	{
		// Cryptographically secure random bytes
		return random_bytes(max(1, $this->_iv_size));
	}

	/**
	 * Override __debugInfo function to not display key in var_dump
	 *
	 * @codeCoverageIgnore
	 * @return array<string, mixed>
	 */
	public function __debugInfo(): array
	{
		/** @var array<string, mixed> $result */
		$result = get_object_vars($this);
		unset($result['_key']);
		return $result;
	}

	/**
	 * An engine must never be serialized: the key would be written into
	 * the session, cache or log that stores it. Build a new engine from
	 * the configuration instead.
	 *
	 * @return  array<string, mixed>
	 * @throws  Kohana_Exception  always
	 */
	public function __serialize(): array
	{
		throw new Kohana_Exception('An encryption engine cannot be serialized, it holds the key');
	}

	/**
	 * Check if key has valid length
	 *
	 * @param  int $expected Expected Key Length
	 *
	 * @throws Kohana_Exception
	 */
	protected function valid_key_length(int $expected): void
	{
		$length = mb_strlen($this->_key, '8bit');
		if ($length !== $expected) {
			throw new Kohana_Exception('No valid encryption key is defined in the encryption configuration: length should be :required_length for :cipher, is: :current_length', [
				':cipher' => $this->_cipher,
				':required_length' => $expected,
				':current_length' => $length
			]);
		}
	}
}
