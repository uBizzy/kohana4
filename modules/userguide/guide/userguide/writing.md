# Writing a Guide

A guide is a directory of Markdown files. Every module can have one, and so can your application.

## 1. Register it

```php
// modules/shop/config/userguide.php (or application/config/userguide.php)
return [
	'modules' => [
		// The name of the directory in guide/
		'shop' => [
			'enabled'     => true,
			'name'        => 'Shop',
			'description' => 'Products, carts and orders.',
			'copyright'   => '&copy; 2026 My Company',
		],
	],
];
```

## 2. The files

```text
modules/shop/
	guide/shop/
		index.md        the first page: /guide/shop
		menu.md         the menu at the left
		orders.md       /guide/shop/orders
		orders/refunds.md   /guide/shop/orders/refunds
	media/guide/shop/
		checkout.png    images of the guide
```

## 3. The menu

`menu.md` is a list. An entry without a link is the title of a section.

```text
- Shop
   - [Overview](index)
   - [Products](products)
- Orders
   - [Placing Orders](orders)
   - [Refunds](orders/refunds)
```

Every page must be in the menu; the title in the menu is the title in the browser tab.

## 4. The pages

Each page starts with its title as a first-level heading.

````text
# Refunds

A refund gives the money of an order back, in full or in part.

```php
$order->refund(1500);   // in cents
```

## Partial refunds

...
````

### Links

Links are relative to the file they are in, and may end in `.md`: the same pages then also work when read on GitHub.

| From `orders/refunds.md` to | Write |
|---|---|
| `orders/returns.md` | `[Returns](returns)` |
| `orders.md` | `[Orders](../orders)` |
| The guide of another module | `[ORM](../../orm/relationships)` |
| A heading of the same page | `[Partial refunds](#partial-refunds)` |
| A class in the API browser | `[Arr::get()]`, `[Cookie::$salt]`, `[Model_Order]` |

### Images

Images are in `media/guide/<module>/` and are named from there, wherever the page is.

```text
![The checkout page](checkout.png)
```

## How to write a good page

- **Start with one sentence** that says what the thing is for, then a small example that works.
- **One short example per task**, under a heading that names the task ("Sending a file"), not the method.
- **Tables** for lists of options; do not explain each in a paragraph.
- **Notes** (`[!!]`) only for what causes real damage when missed.
- Leave the full list of methods to the [API browser](api) and link to it.
- Give every code block its language: `php`, `sql`, `bash`, `text`.

## The checks

The tests of this module check every guide:

```bash
vendor/bin/phpunit --group kohana.userguide.guides
```

- Every enabled module has a guide, with an `index.md` and a `menu.md`.
- Every page is in the menu, starts with a title, and every link and image leads somewhere.
- Every `php` example is valid PHP.
- Every static call, constant and property of a framework class used in an example exists, and so does every API link.

So an example cannot silently go stale when the code changes.
