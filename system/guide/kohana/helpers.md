# Helpers

Helpers are classes of static methods for everyday jobs. They need no setup: call them anywhere.

| Helper | For | Page |
|---|---|---|
| `Arr` | Reading and reshaping arrays | [Arrays](helpers/arrays) |
| `Text` | Shortening, linking and formatting text | [Text](helpers/text) |
| `UTF8` | Text functions that are safe for every language | [Text](helpers/text) |
| `Inflector` | Plural and singular, `camelCase` and `under_score` | [Text](helpers/text) |
| `HTML` | Escaping, links, images, tags | [HTML and Forms](helpers/html) |
| `Form` | Form fields | [HTML and Forms](helpers/html) |
| `URL` | Addresses of your site | [HTML and Forms](helpers/html) |
| `Date` | Time spans, time zones, lists for date fields | [Dates and Numbers](helpers/dates) |
| `Num` | Formatting and rounding numbers | [Dates and Numbers](helpers/dates) |
| `File` | Types of files | [Files and Uploads](helpers/files) |
| `Upload` | Receiving uploaded files | [Files and Uploads](helpers/files) |
| `Feed` | Reading and writing RSS feeds | [Files and Uploads](helpers/files) |
| `Archive` | Zip, tar and tar.gz archives, gzip | [Archives](helpers/archives) |
| `Valid` | Checking values | [Validation](security/validation) |
| `Security` | Tokens against forged forms | [CSRF](security/csrf) |
| `Cookie` | Signed cookies | [Cookies](cookies) |
| `Debug` | Showing values while developing | [Debugging](debugging) |

## The ones you will use every day

```php
// A value of an array that may not be there
$page = Arr::get($_GET, 'page', 1);

// A value deep inside an array
$city = Arr::path($order, 'customer.address.city', 'unknown');

// Text that is safe to print
echo HTML::chars($comment);

// A link to a page of your site
echo HTML::anchor('article/view/42', 'Read more');

// "3 minutes ago"
echo Date::fuzzy_span($article->created);

// A title for an address: "ola-mundo"
$slug = URL::title('Olá, Mundo!', '-', true);
```

## Adding your own

A helper of yours is a class in `application/classes/`. To add a method to a helper of the framework, [extend it](extension).
