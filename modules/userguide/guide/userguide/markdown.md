# Markdown

Guide pages and the comments in the code are written in Markdown, in the form GitHub also shows. This page lists what the guide understands.

## Text

```text
A paragraph is one or more lines of text.
A blank line starts a new one.

**bold**, *emphasis*, ~~struck~~ and `code`.

A line that ends with two spaces  
breaks here.
```

A paragraph is one or more lines of text.
A blank line starts a new one.

**bold**, *emphasis*, ~~struck~~ and `code`.

An underscore inside a word (`some_name`) is left alone. To print a character that Markdown would read, put a backslash before it: `\*`.

## Headings

```text
# Title of the page
## A section
### A part of a section
```

Each heading gets an id from its text (`## Partial refunds` is `#partial-refunds`), listed under "On this page" at the right. To choose the id: `## Partial refunds {#partial}`.

## Lists

```text
- An item
- Another
   - Inside the second (indent by two or more spaces)

1. First
2. Second
```

- An item
- Another
   - Inside the second

## Links and images

```text
[Text](page)                    a page of this guide
[Text](page.md#heading)         also fine; works on GitHub too
[Text](../database/config)      a page of another guide
[Text](https://www.php.net)     another site
<https://www.php.net>           an address as its own text
![Description](image.png)       an image from media/guide/<module>/
```

## Links to the API browser

A class name in square brackets becomes a link, when the class exists.

```text
[Arr]             the class
[Arr::get()]      a method
[Cookie::$salt]   a property
[Kohana::PRODUCTION]   a constant
```

[Arr], [Arr::get()], [Cookie::$salt], [Kohana::PRODUCTION]

## Code

Three backticks, with the language. PHP is coloured.

````text
```php
$name = Arr::get($_GET, 'name', 'world');
```
````

```php
$name = Arr::get($_GET, 'name', 'world');
```

Lines indented by a tab or four spaces are code too. That is how examples are written in the comments of the code:

```php
/**
 * Returns a value of an array.
 *
 *     $name = Arr::get($_GET, 'name', 'world');
 */
```

## Notes

```text
[!!] Something the reader must not miss.
```

[!!] Something the reader must not miss.

## Tables

```text
| Setting | Default | Meaning |
|---|--:|---|
| `lifetime` | 3600 | Seconds a value is kept |
```

| Setting | Default | Meaning |
|---|--:|---|
| `lifetime` | 3600 | Seconds a value is kept |

`:--` aligns a column left, `--:` right, `:-:` in the middle. A `|` inside `code` is fine.

## Quotes and lines

```text
> A quote.

---
```

> A quote.

## Not supported

- **HTML**: it is shown as text, not used. A guide cannot inject markup or scripts.
- Footnotes, definition lists and links by reference (`[text][ref]`).
