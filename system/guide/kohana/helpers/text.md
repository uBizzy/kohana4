# Text

Three helpers: `Text` for text shown to people, `UTF8` for text functions that work in every language, `Inflector` for words.

[!!] These methods return text, not safe HTML. Still print the result with `HTML::chars()`, except for the methods that say they return HTML.

## Shortening

```php
Text::limit_words('The quick brown fox jumps over the lazy dog', 4);
// 'The quick brown fox…'

Text::limit_chars('The quick brown fox', 9);
// 'The quick…'

// Do not cut inside a word
Text::limit_chars('The quick brown fox', 12, '…', true);
// 'The quick…'
```

## Formatting

```php
Text::bytes(1536000);             // '1.54 MB'
Text::bytes(1536000, 'MiB');      // '1.46 MiB'

Text::number(1024);               // 'one thousand and twenty-four'
Text::ucfirst('jean-luc picard'); // 'Jean-Luc picard'
Text::reduce_slashes('foo//bar/baz');   // 'foo/bar/baz'

// No last word alone on a line (returns HTML)
Text::widont('A title that should not end with one word');
// 'A title that should not end with one&nbsp;word'
```

## Text typed by visitors (these return HTML)

```php
// Addresses and emails become links
echo Text::auto_link(HTML::chars($comment));
// See <a href="https://example.org">https://example.org</a> ...

// Line breaks become paragraphs
echo Text::auto_p(HTML::chars($comment));
// <p>One</p> <p>Two<br />lines</p>

// Hide words
Text::censor('That was a damn good show', ['damn']);
// 'That was a #### good show'
```

Escape first, then link: `Text::auto_link(HTML::chars($text))`.

## Random text

```php
Text::random('alnum', 12);     // 12 letters and digits
Text::random('hexdec', 32);    // 32 hexadecimal characters
Text::random('distinct', 8);   // no characters that look alike (0/O, 1/l): for codes people type
Text::random('ABC123', 6);     // only from these characters
```

It uses the secure random source of the system, so the result can be used for codes and tokens.

## In loops

```php
<?php foreach ($rows as $row): ?>
	<tr class="<?php echo Text::alternate('odd', 'even') ?>">...</tr>
<?php endforeach ?>
```

## UTF8: text in every language

PHP's plain text functions count bytes, so they break letters such as `á` or `ß`. `UTF8` has the same functions counting characters.

```php
strlen('Olá');             // 4  (bytes)
UTF8::strlen('Olá');       // 3  (characters)

UTF8::ucfirst('élan');     // 'Élan'
UTF8::strtoupper('straße');
UTF8::substr('Olá mundo', 0, 3);   // 'Olá'

// Plain letters, for addresses and file names
UTF8::transliterate_to_ascii('Olá Ærø Straße');   // 'Ola AEro Strasse'

// Remove bytes that are not valid UTF-8 (the framework does this to all input)
UTF8::clean($text);
```

They also accept `null` as an empty text, which suits values from nullable database columns.

## Inflector: words

```php
Inflector::plural('category');      // 'categories'
Inflector::singular('people');      // 'person'

// With a count: plural unless it is 1
echo $count.' '.Inflector::plural('article', $count);   // '1 article', '5 articles'

Inflector::camelize('user profile');     // 'userProfile'
Inflector::decamelize('userProfile');    // 'user profile'
Inflector::underscore('user profile');   // 'user_profile'
Inflector::humanize('first_name');       // 'first name'
```

English only. Words with their own plural are in `config/inflector.php`; add yours in `application/config/inflector.php`.
