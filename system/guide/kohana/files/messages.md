# Messages

A message file holds texts found by a key: error texts of a form, labels, anything you do not want inside the code. Files are in the `messages/` directories and return an array.

```php
// application/messages/account.php
return [
	'welcome' => 'Your account is ready.',
	'email' => [
		'taken' => 'That address already has an account.',
	],
];
```

```php
echo Kohana::message('account', 'welcome');

// A key inside a key
echo Kohana::message('account', 'email.taken');

// A default when the key is missing
echo Kohana::message('account', 'goodbye', 'See you soon.');

// The whole file
$all = Kohana::message('account');

// A file in a sub-directory: messages/forms/contact.php
echo Kohana::message('forms/contact', 'sent');
```

## Validation errors

The main use: the texts of validation errors. `$validation->errors('contact')` looks in `messages/contact.php` for `field.rule`, then `field.default`, then in `messages/validation.php` for the rule.

```php
// application/messages/contact.php
return [
	'email' => [
		'not_empty' => 'Please give your email address.',
		'email'     => 'That does not look like an email address.',
	],
	'message' => [
		'default' => 'Please write a message of at least 10 characters.',
	],
];
```

```php
$validation = Validation::factory($this->request->post())
	->rule('email', 'not_empty')
	->rule('email', 'email')
	->rule('message', 'min_length', [':value', 10]);

if ( ! $validation->check())
{
	// ['email' => 'Please give your email address.', ...]
	$errors = $validation->errors('contact');
}
```

The general texts are in `system/messages/validation.php`. Copy only the ones you want to change:

```php
// application/messages/validation.php
return [
	'not_empty' => ':field is needed',
	'email'     => ':field is not an email address',
];
```

`:field` is the label of the field, `:value` its value, `:param2`, `:param3` the parameters of the rule.

## Messages and translation

Message texts are passed through [translation](i18n) when they are shown as validation errors. Write them in your main language and translate them in the `i18n/` files, like any other text.

[!!] Do not call `__()` inside a message file: the file only returns texts.

## Files are merged

Like config files, message files of the same name from the system, the modules and the application are merged; the application wins key by key.
