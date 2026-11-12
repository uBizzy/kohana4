<?php
/**
 * RSS and Atom feed helper.
 *
 * @package    Kohana
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Feed {

	/**
	 * Parses a remote feed into an array.
	 *
	 * @param   string  $feed   remote feed URL
	 * @param   int $limit  item limit to fetch
	 * @return  array<int|string, mixed>
	 */
	public static function parse(string $feed, int $limit = 0): array
	{
		// Check if SimpleXML is installed
		if ( ! function_exists('simplexml_load_file'))
			throw new Kohana_Exception('SimpleXML must be installed!');

		// Allow loading by filename or raw XML string
		if (Valid::url($feed))
		{
			// Use native Request client to get remote contents
			$response = Request::factory($feed)->execute();
			$feed     = $response->body();
		}
		elseif (is_file($feed))
		{
			// Get file contents
			$feed = file_get_contents($feed);
		}

		// Collect XML errors quietly instead of raising PHP warnings
		$previous = libxml_use_internal_errors(true);

		// Load the feed
		$feed = simplexml_load_string(is_string($feed) ? $feed : '', 'SimpleXMLElement', LIBXML_NOCDATA);

		// Restore the previous error handling
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		// Feed could not be loaded
		if ($feed === false)
			return [];

		$namespaces = $feed->getNamespaces(true);

		// Detect the feed type. RSS 1.0/2.0 and Atom 1.0 are supported.
		$feed = isset($feed->channel) ? $feed->xpath('//item') : $feed->entry;

		$i = 0;
		$items = [];

		foreach ($feed ?: [] as $item)
		{
			if ($limit > 0 AND $i++ === $limit)
				break;
			$item_fields = (array) $item;

			// get namespaced tags
			foreach ($namespaces as $ns)
			{
				$item_fields += (array) $item->children($ns);
			}
			$items[] = $item_fields;
		}

		return $items;
	}

	/**
	 * Creates a feed from the given parameters.
	 *
	 * @param   array<string, mixed>   $info       feed information
	 * @param   iterable<int|string, array<string, mixed>>   $items      items to add to the feed
	 * @param   string  $encoding   define which encoding to use
	 * @return  string
	 */
	public static function create(array $info, iterable $items, string $encoding = 'UTF-8'): string
	{
		$info += ['title' => 'Generated Feed', 'link' => '', 'generator' => 'KohanaPHP'];

		$feed = '<?xml version="1.0" encoding="'.$encoding.'"?><rss version="2.0"><channel></channel></rss>';
		$feed = simplexml_load_string($feed);

		if ($feed === false)
			throw new Kohana_Exception('The feed could not be made, is the encoding :encoding valid?', [':encoding' => $encoding]);

		foreach ($info as $name => $value)
		{
			if ($name === 'image')
			{
				// Create an image element
				$image = $feed->channel->addChild('image');

				if ( ! is_array($value) OR ! isset($value['link'], $value['url'], $value['title']))
				{
					throw new Kohana_Exception('Feed images require a link, url, and title');
				}

				Feed::_add_child($image, 'link', Feed::_value('link', $value['link'], [], ['link']));
				Feed::_add_child($image, 'url', Feed::_value('url', $value['url'], [], ['url']));
				Feed::_add_child($image, 'title', $value['title']);
			}
			else
			{
				// Add the info to the channel
				Feed::_add_child($feed->channel, $name, Feed::_value($name, $value, ['pubDate', 'lastBuildDate'], ['link', 'docs']));
			}
		}

		foreach ($items as $item)
		{
			// Add an item to the channel
			$row = $feed->channel->addChild('item');

			foreach ($item as $name => $value)
			{
				Feed::_add_child($row, $name, Feed::_value($name, $value, ['pubDate'], ['link', 'guid']));
			}
		}

		if (function_exists('dom_import_simplexml'))
		{
			// Convert the feed object to a DOM object, which can indent the output
			$dom = dom_import_simplexml($feed)->ownerDocument;

			if ($dom !== null)
			{
				// DOM generates more readable XML
				$dom->formatOutput = true;

				return (string) $dom->saveXML();
			}
		}

		// Export the document as XML
		return (string) $feed->asXML();
	}

	/**
	 * Prepares the value of a feed element: UNIX timestamps in date fields
	 * become RFC 2822 dates, and relative links become complete URLs.
	 *
	 * @param   string  $name    element name
	 * @param   mixed   $value   element value
	 * @param   array<int, string>   $dates   names of the elements that hold a date
	 * @param   array<int, string>   $links   names of the elements that hold a link
	 * @return  string
	 * @uses    URL::site
	 */
	protected static function _value(string $name, mixed $value, array $dates, array $links): string
	{
		if (in_array($name, $dates, true) AND (is_int($value) OR (is_string($value) AND ctype_digit($value))))
		{
			// Convert timestamps to RFC 2822 formatted dates
			return date('r', (int) $value);
		}

		$value = is_scalar($value) ? (string) $value : '';

		if (in_array($name, $links, true) AND ! str_contains($value, '://'))
		{
			// Convert URIs to URLs
			return URL::site($value, 'http');
		}

		return $value;
	}

	/**
	 * Adds a child element with a text value. SimpleXML does not escape "&"
	 * in values by itself, so the value is escaped here.
	 *
	 * @param   SimpleXMLElement  $parent  element to add to
	 * @param   string            $name    name of the new element
	 * @param   mixed             $value   text of the new element
	 * @return  void
	 */
	protected static function _add_child(SimpleXMLElement $parent, string $name, mixed $value): void
	{
		$parent->addChild($name, htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_XML1 | ENT_NOQUOTES, 'UTF-8'));
	}

}
