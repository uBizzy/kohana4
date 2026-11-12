# Conventions

Names decide where the framework finds things. Follow them and no configuration is needed.

## Class names and files

A class name is its path under a `classes/` directory, with `_` for each `/`.

| Class | File |
|---|---|
| `Controller_Article` | `classes/Controller/Article.php` |
| `Model_User_Token` | `classes/Model/User/Token.php` |
| `Kohana_Session_Native` | `classes/Kohana/Session/Native.php` |
| `Arr` | `classes/Arr.php` |

- Words start with a capital: `Controller_Shop_Cart`, not `controller_shop_cart`.
- An abbreviation keeps its capitals: `HTML`, `URL`, `ORM`.
- One class per file.

## Other names

| Thing | Name | Example |
|---|---|---|
| Action of a controller | `action_` + lowercase | `action_index()`, `action_sign_up()` |
| Method, function | lowercase with `_` | `find_file()`, `as_array()` |
| Protected or private member | starts with `_` | `$_table_name`, `_compile()` |
| Constant | capitals with `_` | `Kohana::PRODUCTION` |
| View, config, message file | lowercase path | `views/article/view.php` |

## Code style

The framework's own code follows these rules; `vendor/bin/phpcs` checks them.

```php
class Model_Article extends Model {

	/**
	 * @var  int  articles shown on a page
	 */
	public const int PER_PAGE = 20;

	/**
	 * Returns the newest articles.
	 *
	 * @param   int  $page  page number, from 1
	 * @return  array
	 */
	public function newest(int $page = 1): array
	{
		if ($page < 1)
		{
			$page = 1;
		}

		return DB::select()
			->from('articles')
			->order_by('created', 'DESC')
			->limit(static::PER_PAGE)
			->offset(($page - 1) * static::PER_PAGE)
			->execute()
			->as_array();
	}
}
```

- **Tabs** for indentation.
- **Braces on their own line** for classes, methods and control structures.
- `true`, `false` and `null` in **lowercase**.
- `AND` and `OR` in conditions; `!` with a space on both sides: `if ( ! $found AND $required)`.
- **Types everywhere**: parameters, return values, properties and constants. A nullable type is written `?string`.
- A comment above every class, method and property: what it is for, then `@param`, `@return`, `@throws`.
- Short array syntax: `['a' => 1]`.

## Values from visitors

A value that comes from a request can be a string, an array or missing. Type such parameters `mixed` and check inside; never trust the shape.

```php
// $_POST['email'] may be an array: Valid::email() takes anything and fails what is not text
if ( ! Valid::email($this->request->post('email')))
{
	throw HTTP_Exception::factory(400, 'A valid email address is needed');
}
```
