# Validation

`Validation` checks an array of values against rules, and gives you an error text for each field that fails.

```php
$validation = Validation::factory($this->request->post())
	->rule('username', 'not_empty')
	->rule('username', 'min_length', [':value', 4])
	->rule('email', 'not_empty')
	->rule('email', 'email')
	->rule('password', 'min_length', [':value', 10])
	->rule('confirm', 'matches', [':validation', 'confirm', 'password']);

if ($validation->check())
{
	// All good: use the checked values
	$data = $validation->data();
}
else
{
	// ['email' => 'email must be an email address', ...]
	$errors = $validation->errors('user');
}
```

## Writing rules

```php
$validation->rule($field, $rule, $parameters);
```

- The rule is the name of a `Valid` method, any PHP function, a `'Class::method'`, or a closure.
- Without parameters, the rule gets the value. With parameters, `:value` stands for the value, `:field` for the field name, and `:validation` for the validation object.

```php
// A Valid method
$validation->rule('age', 'digit');
$validation->rule('age', 'range', [':value', 18, 120]);

// A PHP function
$validation->rule('color', 'in_array', [':value', ['red', 'green', 'blue'], true]);

// A method of yours
$validation->rule('username', 'Model_User::available');

// A closure
$validation->rule('start', fn ($value) => strtotime((string) $value) > time());

// Several rules at once
$validation->rules('title', [
	['not_empty'],
	['max_length', [':value', 200]],
]);

// A rule for every field
$validation->rule(true, 'max_length', [':value', 5000]);
```

[!!] An empty field only fails `not_empty` and `matches`. Other rules are skipped for it, so an optional field needs no special care: `->rule('website', 'url')` accepts an empty website.

## The rules of Valid

| Rule | Passes when the value |
|---|---|
| `not_empty` | is not empty |
| `min_length`, `max_length`, `exact_length` | has that many characters |
| `email` | is an email address |
| `email_domain` | is an email address whose domain can receive mail |
| `url` | is an address with a scheme |
| `ip` | is an IP address |
| `phone` | has the number of digits of a phone number |
| `date` | can be read as a date |
| `date_format` | is a date in exactly the given format: `['date_format', [':value', 'Y-m-d']]` |
| `alpha`, `alpha_numeric`, `alpha_dash` | has only letters / letters and digits / those and `-` `_` |
| `digit` | has only digits |
| `numeric` | is a number, also with decimals or a sign |
| `decimal` | is a number with that many decimals |
| `range` | is between two numbers |
| `color` | is a hexadecimal colour (`#fc0`, `#ffcc00`) |
| `uuid` | is a UUID |
| `json` | is valid JSON |
| `boolean` | is `1`, `0`, `true` or `false` (as a value or as text) |
| `in` | is one of a list: `['in', [':value', ['s', 'm', 'l']]]` |
| `equals` | equals a given value |
| `matches` | is the same as another field |
| `regex` | matches a pattern |
| `credit_card` | is a valid card number |

Every rule accepts any kind of value and fails what is not text or a number. A field sent as an array (`email[]=x`) can never pass a text rule.

## Error texts

`errors('user')` reads the texts from `messages/user.php`. See [Messages](../files/messages) for the lookup order.

```php
// application/messages/user.php
return [
	'username' => [
		'not_empty'  => 'Choose a user name.',
		'min_length' => 'The user name needs at least :param2 characters.',
	],
	'confirm' => [
		'matches' => 'The two passwords are not the same.',
	],
];
```

Labels replace the field names in the general texts:

```php
$validation->labels([
	'username' => 'User name',
	'email'    => 'Email address',
]);
// "Email address must be an email address"
```

## Rules of your own

A rule is a function that returns `true` or `false`.

```php
class Model_User extends ORM {

	/**
	 * Is the user name still free?
	 */
	public static function available(mixed $username): bool
	{
		return is_string($username)
			AND ! ORM::factory('User', ['username' => $username])->loaded();
	}
}
```

```php
$validation->rule('username', 'Model_User::available');
```

```php
// application/messages/user.php
return [
	'username' => ['Model_User::available' => 'That user name is taken.'],
];
```

### A rule over several fields

Give the rule the validation object and add the error yourself.

```php
$validation->rule('phone', function (Validation $validation, string $field) {
	// A phone number or an email address is needed
	if (empty($validation['phone']) AND empty($validation['email']))
	{
		$validation->error($field, 'phone_or_email');
	}
}, [':validation', ':field']);
```

### Values a rule needs

```php
$validation->bind(':user', $current_user);

$validation->rule('password', 'Model_User::check_old_password', [':value', ':user']);
```

## In a controller

```php
public function action_register(): void
{
	$view = View::factory('user/register')
		->set('values', [])
		->set('errors', []);

	if ($this->request->method() === HTTP_Request::POST)
	{
		$validation = Validation::factory($this->request->post())
			->rule('csrf', 'Security::check')
			->rule('email', 'not_empty')
			->rule('email', 'email');

		if ($validation->check())
		{
			// ... create the account
			$this->redirect('welcome');
		}

		$view->values = Arr::extract($this->request->post(), ['email']);
		$view->errors = $validation->errors('user');
	}

	$this->response->body($view);
}
```

## With ORM

An ORM model carries its rules and checks itself when it is saved: see [ORM validation](../../orm/validation).
