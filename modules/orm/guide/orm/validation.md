# Validation

A model knows the rules of its data. They run at every `save()`, so no controller can store something invalid.

## Rules

`rules()` returns the rules for each column, in the form the [Validation](../kohana/security/validation) class uses.

```php
class Model_User extends ORM {

	public function rules(): array
	{
		return [
			'username' => [
				['not_empty'],
				['min_length', [':value', 4]],
				['max_length', [':value', 32]],
				['alpha_dash'],
				[[$this, 'unique'], ['username', ':value']],
			],
			'email' => [
				['not_empty'],
				['email'],
				[[$this, 'unique'], ['email', ':value']],
			],
			'age' => [
				['range', [':value', 18, 120]],
			],
		];
	}
}
```

- A rule is `[name, parameters]`. Without parameters it gets the value.
- `[$this, 'unique']` checks that no other row has the value. It is a method of every model.
- A method of the model can be a rule: `[[$this, 'method_name'], [':value']]`.

[!!] `rules()`, `filters()` and `labels()` must be declared `public` and `: array`.

## Saving

```php
try
{
	$user = ORM::factory('User')
		->values($this->request->post(), ['username', 'email', 'age'])
		->save();
}
catch (ORM_Validation_Exception $e)
{
	$errors = $e->errors('models');
}
```

To check without saving:

```php
$user->check();   // throws ORM_Validation_Exception when a rule fails
```

## Error texts

`$e->errors('models')` reads the texts from `messages/models/<model>.php`, then from the general `messages/validation.php`.

```php
// application/messages/models/user.php
return [
	'username' => [
		'not_empty' => 'Choose a user name.',
		'unique'    => 'That user name is taken.',
	],
	'email' => [
		'unique' => 'That address already has an account.',
	],
];
```

Without a text of your own you get the general one, with the label of the column:

```php
public function labels(): array
{
	return [
		'username' => 'User name',
		'email'    => 'Email address',
	];
}
// "Email address must be an email address"
```

## Filters

Filters change a value when it is set, before the rules see it.

```php
public function filters(): array
{
	return [
		// For every column
		true => [
			['trim'],
		],
		'email' => [
			['strtolower'],
		],
		'username' => [
			[[$this, 'clean_name'], [':value']],
		],
	];
}
```

```php
$user->email = '  ANA@Example.org ';

echo $user->email;   // ana@example.org
```

A filter is `[function, parameters]`; without parameters it gets the value. In the parameters, `:value` is the value, `:field` the column and `:model` the model.

## Fields that are not columns

A password typed twice, a checkbox to accept the terms: rules for these go in a `Validation` object that is checked together with the model.

```php
$extra = Validation::factory($this->request->post())
	->rule('password', 'min_length', [':value', 10])
	->rule('confirm', 'matches', [':validation', 'confirm', 'password'])
	->rule('terms', 'not_empty');

try
{
	$user->values($this->request->post(), ['username', 'email'])->save($extra);
}
catch (ORM_Validation_Exception $e)
{
	$errors = $e->errors('models');

	// Errors of the extra fields are under "_external"
	$errors = Arr::merge($errors, Arr::get($errors, '_external', []));
	unset($errors['_external']);
}
```

Texts for these are in `messages/models/<model>/_external.php`.

## A complete form

```php
public function action_register(): void
{
	$user = ORM::factory('User');
	$errors = [];

	if ($this->request->method() === HTTP_Request::POST)
	{
		try
		{
			$user->values($this->request->post(), ['username', 'email'])->save();

			$this->redirect('welcome');
		}
		catch (ORM_Validation_Exception $e)
		{
			$errors = $e->errors('models');
		}
	}

	$this->response->body(View::factory('user/register', ['user' => $user, 'errors' => $errors]));
}
```

```php
<!-- views/user/register.php -->
<?php echo Form::open() ?>
	<?php echo Form::csrf() ?>

	<?php echo Form::label('username', 'User name') ?>
	<?php echo Form::input('username', $user->username) ?>
	<?php if (isset($errors['username'])): ?>
		<p class="error"><?php echo HTML::chars($errors['username']) ?></p>
	<?php endif ?>

	<?php echo Form::label('email', 'Email address') ?>
	<?php echo Form::input('email', $user->email, ['type' => 'email']) ?>
	<?php if (isset($errors['email'])): ?>
		<p class="error"><?php echo HTML::chars($errors['email']) ?></p>
	<?php endif ?>

	<?php echo Form::submit('save', 'Register') ?>
<?php echo Form::close() ?>
```
