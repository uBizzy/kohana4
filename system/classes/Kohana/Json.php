<?php
/**
 * Json helper.
 *
 * @package    Kohana
 * @category   Helpers
 *
 * @copyright  (c) 2024-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Json {

	/**
	 * Tests if variable is a valid Json string or not.
	 *
	 * @param   mixed   $value  array to check
	 * @return  bool
	 */
	public static function valid(mixed $value): bool
	{
		// Only a string can be JSON; json_validate() checks it without decoding
		return is_string($value) AND json_validate($value);
	}

	/**
	 * Turns a value into a JSON string. Unlike json_encode(), a value that
	 * cannot be encoded throws an exception instead of returning false.
	 *
	 *     $json = Json::encode(['name' => 'Ana', 'age' => 30]);
	 *
	 *     // Easy to read, for files and debugging
	 *     $json = Json::encode($data, JSON_PRETTY_PRINT);
	 *
	 * @param   mixed  $value  value to encode
	 * @param   int    $flags  json_encode() flags to add
	 * @return  string
	 * @throws  JsonException
	 */
	public static function encode(mixed $value, int $flags = 0): string
	{
		// Slashes and non-ASCII characters are kept readable
		return json_encode($value, $flags | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Turns a JSON string into a value; objects become arrays. Unlike
	 * json_decode(), invalid JSON throws an exception instead of returning
	 * null, so it cannot be mistaken for a real "null".
	 *
	 *     $data = Json::decode('{"name":"Ana","age":30}');
	 *
	 *     // Objects as stdClass
	 *     $data = Json::decode($json, false);
	 *
	 * @param   string  $json   JSON string
	 * @param   bool    $array  return objects as arrays?
	 * @return  mixed
	 * @throws  JsonException
	 */
	public static function decode(string $json, bool $array = true): mixed
	{
		return json_decode($json, $array, 512, JSON_THROW_ON_ERROR);
	}

}
