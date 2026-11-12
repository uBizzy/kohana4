<?php
/**
 * HTML helper class. Provides generic methods for generating various HTML
 * tags and making output HTML safe.
 *
 * @package    Kohana
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_HTML {

	/**
	 * @var  array<int, string>  preferred order of attributes
	 */
	public static array $attribute_order = [
		'action',
		'method',
		'type',
		'id',
		'name',
		'value',
		'href',
		'src',
		'width',
		'height',
		'cols',
		'rows',
		'size',
		'maxlength',
		'rel',
		'media',
		'accept-charset',
		'accept',
		'tabindex',
		'accesskey',
		'alt',
		'title',
		'class',
		'style',
		'selected',
		'checked',
		'readonly',
		'disabled',
	];

	/**
	 * @var  bool  use strict XHTML mode?
	 */
	public static bool $strict = true;

	/**
	 * @var  bool  automatically target external URLs to a new window?
	 */
	public static bool $windowed_urls = false;

	/**
	 * Convert special characters to HTML entities. All untrusted content
	 * should be passed through this method to prevent XSS injections.
	 *
	 *     echo HTML::chars($username);
	 *
	 * @param   string  $value          string to convert
	 * @param   bool $double_encode  encode existing entities
	 * @return  string
	 */
	public static function chars(string|int|float|bool|Stringable|null $value, bool $double_encode = true): string
	{
		return htmlspecialchars( (string) $value, ENT_QUOTES, Kohana::$charset, $double_encode);
	}

	/**
	 * Convert all applicable characters to HTML entities. All characters
	 * that cannot be represented in HTML with the current character set
	 * will be converted to entities.
	 *
	 *     echo HTML::entities($username);
	 *
	 * @param   string  $value          string to convert
	 * @param   bool $double_encode  encode existing entities
	 * @return  string
	 */
	public static function entities(string|int|float|bool|Stringable|null $value, bool $double_encode = true): string
	{
		return htmlentities( (string) $value, ENT_QUOTES, Kohana::$charset, $double_encode);
	}

	/**
	 * Create HTML link anchors. Note that the title is not escaped, to allow
	 * HTML elements within links (images, etc).
	 *
	 *     echo HTML::anchor('/user/profile', 'My Profile');
	 *
	 * @param   string  $uri        URL or URI string
	 * @param   string  $title      link text
	 * @param   array<int|string, mixed>   $attributes HTML anchor attributes
	 * @param   string|bool|Request|null   $protocol   protocol to pass to URL::base()
	 * @param   bool $index      include the index page
	 * @return  string
	 * @uses    URL::base
	 * @uses    URL::site
	 * @uses    HTML::attributes
	 */
	public static function anchor(string $uri, ?string $title = null, ?array $attributes = null, mixed $protocol = null, bool $index = true): string
	{
		if ($title === null)
		{
			// Use the URI as the title
			$title = $uri;
		}

		if ($uri === '')
		{
			// Only use the base URL
			$uri = URL::base($protocol, $index);
		}
		else
		{
			if (strpos($uri, '://') !== false OR strncmp($uri, '//', 2) == 0)
			{
				if (HTML::$windowed_urls === true AND empty($attributes['target']))
				{
					// Make the link open in a new window
					$attributes['target'] = '_blank';
				}
			}
			elseif ($uri[0] !== '#' AND $uri[0] !== '?')
			{
				// Make the URI absolute for non-fragment and non-query anchors
				$uri = URL::site($uri, $protocol, $index);
			}
		}

		// Add the sanitized link to the attributes
		$attributes['href'] = $uri;

		return '<a'.HTML::attributes($attributes).'>'.$title.'</a>';
	}

	/**
	 * Creates an HTML anchor to a file. Note that the title is not escaped,
	 * to allow HTML elements within links (images, etc).
	 *
	 *     echo HTML::file_anchor('media/doc/user_guide.pdf', 'User Guide');
	 *
	 * @param   string  $file       name of file to link to
	 * @param   string  $title      link text
	 * @param   array<int|string, mixed>   $attributes HTML anchor attributes
	 * @param   string|bool|Request|null   $protocol   protocol to pass to URL::base()
	 * @param   bool $index      include the index page
	 * @return  string
	 * @uses    URL::base
	 * @uses    HTML::attributes
	 */
	public static function file_anchor(string $file, ?string $title = null, ?array $attributes = null, mixed $protocol = null, bool $index = false): string
	{
		if ($title === null)
		{
			// Use the file name as the title
			$title = basename($file);
		}

		// Add the file link to the attributes
		$attributes['href'] = URL::site($file, $protocol, $index);

		return '<a'.HTML::attributes($attributes).'>'.$title.'</a>';
	}

	/**
	 * Creates an email (mailto:) anchor. Note that the title is not escaped,
	 * to allow HTML elements within links (images, etc).
	 *
	 *     echo HTML::mailto($address);
	 *
	 * @param   string  $email      email address to send to
	 * @param   string  $title      link text
	 * @param   array<int|string, mixed>   $attributes HTML anchor attributes
	 * @return  string
	 * @uses    HTML::attributes
	 */
	public static function mailto(string $email, ?string $title = null, ?array $attributes = null): string
	{
		// The address goes into an attribute, so it must be escaped
		$email = HTML::chars($email);

		// Use the (escaped) email address as the title
		$title ??= $email;

		return '<a href="&#109;&#097;&#105;&#108;&#116;&#111;&#058;'.$email.'"'.HTML::attributes($attributes).'>'.$title.'</a>';
	}

	/**
	 * Creates a style sheet link element.
	 *
	 *     echo HTML::style('media/css/screen.css');
	 *
	 * @param   string  $file       file name
	 * @param   array<int|string, mixed>   $attributes default attributes
	 * @param   string|bool|Request|null   $protocol   protocol to pass to URL::base()
	 * @param   bool $index      include the index page
	 * @return  string
	 * @uses    URL::base
	 * @uses    HTML::attributes
	 */
	public static function style(string $file, ?array $attributes = null, mixed $protocol = null, bool $index = false): string
	{
		if (strpos($file, '://') === false AND strncmp($file, '//', 2))
		{
			// Add the base URL
			$file = URL::site($file, $protocol, $index);
		}

		// Set the stylesheet link
		$attributes['href'] = $file;

		// Set the stylesheet rel
		$attributes['rel'] = empty($attributes['rel']) ? 'stylesheet' : $attributes['rel'];

		// Set the stylesheet type
		// A type given by the caller is kept
		$attributes['type'] ??= 'text/css';

		return '<link'.HTML::attributes($attributes).' />';
	}

	/**
	 * Creates a script link.
	 *
	 *     echo HTML::script('media/js/jquery.min.js');
	 *
	 * @param   string  $file       file name
	 * @param   array<int|string, mixed>   $attributes default attributes
	 * @param   string|bool|Request|null   $protocol   protocol to pass to URL::base()
	 * @param   bool $index      include the index page
	 * @return  string
	 * @uses    URL::base
	 * @uses    HTML::attributes
	 */
	public static function script(string $file, ?array $attributes = null, mixed $protocol = null, bool $index = false): string
	{
		if (strpos($file, '://') === false AND strncmp($file, '//', 2))
		{
			// Add the base URL
			$file = URL::site($file, $protocol, $index);
		}

		// Set the script link
		$attributes['src'] = $file;

		// Set the script type
		// A type given by the caller is kept (e.g. "module")
		$attributes['type'] ??= 'text/javascript';

		return '<script'.HTML::attributes($attributes).'></script>';
	}

	/**
	 * Creates a image link.
	 *
	 *     echo HTML::image('media/img/logo.png', array('alt' => 'My Company'));
	 *
	 * @param   string  $file       file name
	 * @param   array<int|string, mixed>   $attributes default attributes
	 * @param   string|bool|Request|null   $protocol   protocol to pass to URL::base()
	 * @param   bool $index      include the index page
	 * @return  string
	 * @uses    URL::base
	 * @uses    HTML::attributes
	 */
	public static function image(string $file, ?array $attributes = null, mixed $protocol = null, bool $index = false): string
	{
		if (strpos($file, '://') === false AND strncmp($file, '//', 2) AND strncmp($file, 'data:', 5))
		{
			// Add the base URL
			$file = URL::site($file, $protocol, $index);
		}

		// Add the image link
		$attributes['src'] = $file;

		return '<img'.HTML::attributes($attributes).' />';
	}

	/**
	 * Compiles an array of HTML attributes into an attribute string.
	 * Attributes will be sorted using HTML::$attribute_order for consistency.
	 *
	 *     echo '<div'.HTML::attributes($attrs).'>'.$content.'</div>';
	 *
	 * @param   array<int|string, mixed>   $attributes attribute list
	 * @return  string
	 */
	public static function attributes(?array $attributes = null): string
	{
		if (empty($attributes))
			return '';

		$sorted = [];
		foreach (HTML::$attribute_order as $key)
		{
			if (isset($attributes[$key]))
			{
				// Add the attribute to the sorted list
				$sorted[$key] = $attributes[$key];
			}
		}

		// Combine the sorted attributes
		$attributes = $sorted + $attributes;

		$compiled = '';
		foreach ($attributes as $key => $value)
		{
			if ($value === null)
			{
				// Skip attributes that have null values
				continue;
			}

			if (is_int($key))
			{
				// Assume non-associative keys are mirrored attributes
				$key = $value;

				if ( ! HTML::$strict)
				{
					// Just use a key
					$value = false;
				}
			}

			// Add the attribute key
			$compiled .= ' '.(is_scalar($key) ? $key : '');

			if ($value OR HTML::$strict)
			{
				// Add the attribute value
				$compiled .= '="'.HTML::chars((is_scalar($value) OR $value instanceof Stringable) ? $value : '').'"';
			}
		}

		return $compiled;
	}

	/**
	 * Creates an HTML tag element.
	 *
	 *     echo HTML::tag('div', 'Hello world');
	 *
	 * @param   string  $type       HTML element
	 * @param   string  $content    content of the HTML element
	 * @param   array<int|string, mixed>   $attributes default attributes
	 * @return  string
	 * @uses    HTML::attributes
	 */
	public static function tag(string $type, ?string $content = null, ?array $attributes = null): string
	{
		// HTML::attributes() already starts with a space when there is anything to add
		return '<'.$type.HTML::attributes($attributes).'>'.$content.'</'.$type.'>';
	}
}
