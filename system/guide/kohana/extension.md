# Extending Classes

Every class of the framework comes as a pair: the real code in `Kohana_<Name>`, and an empty class `<Name>` that extends it.

```php
// system/classes/Kohana/Cookie.php: the code
class Kohana_Cookie { /* ... */ }

// system/classes/Cookie.php: empty; this is the class everyone uses
class Cookie extends Kohana_Cookie {}
```

Because of the [cascading filesystem](files), your own `application/classes/Cookie.php` is found before the empty one. So you can change any class, and all code that uses `Cookie` gets your version.

## Change a setting

```php
// application/classes/Cookie.php
class Cookie extends Kohana_Cookie {

	public static ?string $salt = 'a-long-random-text';

	public static int $expiration = Date::WEEK;
}
```

## Change a method

```php
// application/classes/HTML.php
class HTML extends Kohana_HTML {

	/**
	 * Links to other sites open in a new tab.
	 */
	public static function anchor(string $uri, ?string $title = null, ?array $attributes = null, mixed $protocol = null, bool $index = true): string
	{
		if (str_contains($uri, '://'))
		{
			$attributes = ['target' => '_blank', 'rel' => 'noopener'] + (array) $attributes;
		}

		return parent::anchor($uri, $title, $attributes, $protocol, $index);
	}
}
```

[!!] The method must declare the same parameter and return types as the one it replaces, or PHP refuses to load the class. Copy the declaration from the API browser.

## Add a method

```php
// application/classes/Text.php
class Text extends Kohana_Text {

	/**
	 * "ana maria" becomes "AM".
	 */
	public static function initials(string $name): string
	{
		preg_match_all('/\b\p{L}/u', $name, $letters);

		return mb_strtoupper(implode('', $letters[0]));
	}
}
```

```php
echo Text::initials('ana maria');  // AM
```

## Rules

- Extend `Kohana_<Name>`, never edit files in `system/` or `modules/`: your changes would be lost at the next update.
- Name the file after the short class: `application/classes/Text.php`.
- A module can do the same. If two modules extend the same class, the one listed first in `Kohana::modules()` wins.
- Call `parent::method()` when you only add to what the method does.
