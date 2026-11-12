# The API Browser

The API browser shows every class of the system, the modules and the application: what it extends, its constants, properties and methods, with their source code.

## Where it gets things from

| Shown | Comes from |
|---|---|
| Types of parameters, return values and properties | The code. They are always right |
| Default values | The code |
| Descriptions | The comment above the class, method or property |
| Menu at the left | `@package` and `@category` of the class comment |

Values of static properties with a secret name (`$salt`, a key) are shown as `***`.

## Writing comments

```php
/**
 * Prices and taxes of the shop.
 *
 * @package    Shop
 * @category   Helpers
 */
class Kohana_Price {

	/**
	 * @var  float  tax rate used when none is given
	 */
	public static float $rate = 0.23;

	/**
	 * Returns an amount with tax added.
	 *
	 *     echo Price::with_tax(100);        // 123.0
	 *     echo Price::with_tax(100, 0.06);  // 106.0
	 *
	 * [!!] Amounts are in whole cents.
	 *
	 * @param   float   $amount  amount without tax
	 * @param   ?float  $rate    tax rate; [Price::$rate] when null
	 * @return  float
	 * @throws  Kohana_Exception  when the amount is negative
	 * @uses    Num::round
	 */
	public static function with_tax(float $amount, ?float $rate = null): float
	{
		return Num::round($amount * (1 + ($rate ?? static::$rate)), 2);
	}
}
```

- The first lines are the description, in [Markdown](markdown). Lines indented by four spaces after ` * ` are an example.
- `@param type $name description`: the type of the code wins over the one written here; the description is what matters.
- `@return type description`, `@throws Class when`, `@uses Class::method`, `@see Class::method`, `@link https://...`.

## Classes shown as one

The framework's classes come in pairs: `Kohana_Arr` holds the code and `Arr` extends it empty (see [Extending Classes](../kohana/extension)). The browser shows `Arr` with the methods of `Kohana_Arr`, and leaves `Kohana_Arr` out of the menu.

A module with its own prefix adds it:

```php
// modules/shop/config/userguide.php
return [
	'transparent_prefixes' => [
		'Shop' => true,   // Shop_Cart is the base of Cart
	],
];
```

## Linking to it

From a guide page or a comment: `[Arr::get()]`, `[Cookie::$salt]`, `[Kohana::PRODUCTION]`. From a view:

```php
echo HTML::anchor(Route::get('docs/api')->uri(['class' => 'Arr']).'#get', 'Arr::get()');
```

## Limiting what is shown

```php
// application/config/userguide.php
return [
	// No API browser at all
	'api_browser' => false,

	// Or only some packages ('None' is classes without @package)
	'api_packages' => 'Shop,Kohana/ORM',
];
```
