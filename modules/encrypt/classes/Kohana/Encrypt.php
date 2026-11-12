<?php
/**
 * Encryption Class for Kohana.
 * - Handles Encryption instances and proxies encrypt/decrypt functions
 *
 * Example Usage:
 *        $engine = Encrypt::instance();
 *        $msg    = $engine->encode('Crypt this!');    -> will return an encrypted string
 *        $text    = $engine->decode($msg);            -> will return "Crypt this!"
 *
 * @package    Kohana/Encrypt
 * @category   Security
 *
 * @copyright  (c) 2007-2012 Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Encrypt {

	/**
	 * Name of default config instance
	 * @var string
	 */
	public static string $default = 'default';

	/**
	 * Name of default engine to use
	 * @var string
	 */
	public static string $default_engine = 'openssl';

	/**
	 * Encrypt class instances
	 * @var array<string, Encrypt>
	 */
	public static array $instances = [];

	/**
	 * Engine Name
	 * @var string
	 */
	protected string $_name;

	/**
	 * Encryption engine
	 * @var Kohana_Encrypt_Engine
	 */
	protected Kohana_Encrypt_Engine $_engine;

	/**
	 * Creates a new Encrypt Engine instance.
	 *
	 * @param string $name   Engine Name
	 * @param array<string, mixed>  $config Configuration
	 *
	 * @throws Kohana_Exception
	 */
	public function __construct(string $name, array $config)
	{
		// Get Driver Type
		$config['type'] = is_string($config['type'] ?? null) ? $config['type'] : self::$default_engine;

		// Set the engine class name
		$engine_name = 'Encrypt_Engine_'.ucfirst($config['type']);

		// Create the engine class
		$this->_name = $name;

		// Since user can define class via string we need to check if it exists
		if (!class_exists($engine_name)) {
			throw new Kohana_Exception('Encryption type: :name defined in the encryption configuratin does not exist.', [
				':name' => $engine_name,
			]);
		}

		$engine = new $engine_name($config);

		// Make sure the class is an engine, before it is kept: the typed
		// property would refuse anything else with a far less clear error
		if ( ! $engine instanceof Kohana_Encrypt_Engine)
		{
			throw new Kohana_Exception('Encryption type: :name defined in the encryption configuration is not a valid type/driver class.', [
				':name' => $engine_name,
			]);
		}

		$this->_engine = $engine;
	}

	/**
	 * Returns a singleton instance of Encrypt. An encryption key must be
	 * provided in your "encrypt" configuration file.
	 *     $encrypt = Encrypt::instance();
	 *
	 * @param  string     $name   Configuration group name
	 * @param  array<string, mixed>|null $config Configuration
	 *
	 * @return Encrypt
	 * @throws Kohana_Exception
	 */
	public static function instance(?string $name = null, ?array $config = null): Encrypt
	{
		if ($name === null) {
			// Use the default instance name
			$name = self::$default;
		}

		if (!isset(self::$instances[$name])) {
			if ($config === null) {
				// Load the configuration data
				$config = Kohana::$config->load('encrypt')->$name;
			}

			if ( ! is_array($config) || empty($config['key'])) {
				// No default encryption key is provided!
				throw new Kohana_Exception('No encryption key is defined in the encryption configuration group: :group',
					[':group' => $name]);
			}

			// Create a new instance
			// "Encrypt" (not self) so that an application's own Encrypt class is used
			/** @var array<string, mixed> $config */
			self::$instances[$name] = new Encrypt($name, $config);
		}

		return self::$instances[$name];
	}

	/**
	 * Encrypts a string and returns an encrypted string that can be decoded.
	 *     $data = $encrypt->encode($message);
	 * The encrypted binary data is encoded using [base64](http://php.net/base64_encode)
	 * to convert it to a string. This string can be stored in a database,
	 * displayed, and passed using most other means without corruption.
	 * WARNING! The $iv variable is for testing purposes only, do not use this variable
	 * unless you REALLY know what you are doing.
	 *
	 * @param  string      $message Message to be encrypted
	 * @param  ?string $iv      Initialization Vector
	 *
	 * @return null|string
	 * @throws Exception
	 */
	public function encode(string $message, ?string $iv = null): ?string
	{
		if ($iv === null) {
			$iv = $this->_engine->create_iv();
		}
		return $this->_engine->encrypt($message, $iv);
	}

	/**
	 * Decrypts an encoded string back to its original value.
	 *     $data = $encrypt->decode($ciphertext);
	 *
	 * @param  string $ciphertext Text to decrypt
	 *
	 * @return null|string
	 */
	public function decode(string $ciphertext): ?string
	{
		return $this->_engine->decrypt($ciphertext);
	}

	/**
	 * Returns text representation of Encrypt class
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return get_class($this->_engine).' ('.$this->_name.')';
	}
}
