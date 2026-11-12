<?php
/**
 * Formats the body of a REST response as XML.
 *
 *     // <root><name>Ana</name><tags><item>a</item><item>b</item></tags></root>
 *     $xml = REST_Format_XML::encode(['name' => 'Ana', 'tags' => ['a', 'b']]);
 *
 * @package        Kohana\REST
 *
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license        https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_REST_Format_XML extends REST_Format {

	/**
	 * Format function
	 *
	 * @throws REST_Exception
	 *
	 * @return string
	 */
	public function format(): string
	{
		return static::encode(is_iterable($this->_body) ? $this->_body : []);
	}

	/**
	 * Writes data as an XML document. Keys become the names of elements,
	 * the items of a list are named "item", and values are written as
	 * text: what a visitor typed can never become markup.
	 *
	 * @param   iterable<mixed>  $data  the data, lists and values inside each other
	 * @return  string
	 * @throws  REST_Exception  without the xml extension, or when the data cannot be written
	 */
	public static function encode(iterable $data): string
	{
		if ( ! extension_loaded('xml'))
			throw new REST_Exception('PHP XML Module not loaded.');

		// Collect the errors of the XML library instead of warnings, and
		// start with none (somebody may have left some behind)
		$before = libxml_use_internal_errors(true);

		libxml_clear_errors();

		$root = new SimpleXMLElement('<?xml version="1.0" encoding="'.Kohana::$charset.'"?><root/>', LIBXML_COMPACT | LIBXML_BIGLINES);

		static::_add($root, $data);

		$xml = $root->asXML();

		libxml_use_internal_errors($before);

		if ( ! is_string($xml) OR $xml === '')
			throw new REST_Exception(static::_errors());

		return $xml;
	}

	/**
	 * Adds data to an element, level by level.
	 *
	 * @param   SimpleXMLElement  $element  element that gets the data
	 * @param   iterable<mixed>   $data     the data
	 * @return  void
	 */
	protected static function _add(SimpleXMLElement $element, iterable $data): void
	{
		foreach ($data as $key => $value)
		{
			$name = static::_name($key);

			if (is_iterable($value))
			{
				static::_add($element->addChild($name), $value);
			}
			else
			{
				// SimpleXML does not escape "&" itself
				$element->addChild($name, htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_XML1 | ENT_NOQUOTES, 'UTF-8'));
			}
		}
	}

	/**
	 * Returns the name of the element for a key. A number cannot be a
	 * name: the items of a list are named "item". Characters that cannot
	 * be part of a name become "_", so a key is never read as markup.
	 *
	 * @param   mixed  $key  key of the data
	 * @return  string
	 */
	protected static function _name(mixed $key): string
	{
		if ( ! is_string($key) OR $key === '')
			return 'item';

		$name = (string) preg_replace('/[^A-Za-z0-9_.\-]/', '_', $key);

		// A name starts with a letter or "_"
		return preg_match('/^[A-Za-z_]/', $name) ? $name : '_'.$name;
	}

	/**
	 * Returns what the XML library reported, as one text, and forgets it.
	 *
	 * @return  string
	 */
	protected static function _errors(): string
	{
		$levels = [LIBXML_ERR_WARNING => 'Warning', LIBXML_ERR_ERROR => 'Error', LIBXML_ERR_FATAL => 'Fatal'];
		$text   = 'Unknown XML Error';

		foreach (libxml_get_errors() as $error)
		{
			$text .= ($levels[$error->level] ?? 'Error').' '.$error->code.': '.trim($error->message)
				."\n  Line: ".$error->line."\n  Column: ".$error->column;
		}

		libxml_clear_errors();

		return $text;
	}

}
