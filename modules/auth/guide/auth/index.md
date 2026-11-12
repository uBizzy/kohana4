# Auth

The Auth module logs visitors in and out and tells you who they are. It is a small base to build on: where the users are kept is the job of a **driver**.

```php
// application/bootstrap.php
Kohana::modules([
	'auth' => MODPATH.'auth',
]);
```

## Using it

```php
$auth = Auth::instance();

// Log in: true when the name and password are right
if ($auth->login($username, $password))
{
	$this->redirect('account');
}

// Who is it?
$user = $auth->get_user();        // the user, or null
$auth->logged_in();               // true or false
$auth->logged_in('admin');        // logged in and has this role (ORM driver)

// Log out
$auth->logout();
```

`login()` accepts whatever the form sent: anything that is not a text is simply a failed log in.

## Protecting pages

```php
abstract class Controller_Account extends Controller {

	public function before(): void
	{
		parent::before();

		if ( ! Auth::instance()->logged_in())
		{
			$this->redirect('login');
		}
	}
}
```

## A log in form

```php
public function action_login(): void
{
	$error = null;

	if ($this->request->method() === HTTP_Request::POST)
	{
		$username = (string) $this->request->post('username');

		// At most 5 attempts in 15 minutes for one account
		if ( ! Security::check($this->request->post('csrf')))
		{
			$error = 'The form has expired. Try again.';
		}
		elseif (Throttle::hits('login:'.strtolower($username), 900) >= 5)
		{
			$error = 'Too many attempts. Try again in a few minutes.';
		}
		elseif (Auth::instance()->login($username, $this->request->post('password')))
		{
			$this->redirect('account');
		}
		else
		{
			Throttle::hit('login:'.strtolower($username), 900);

			// The same text for a wrong name and a wrong password
			$error = 'The name or the password is wrong.';
		}
	}

	$this->response->body(View::factory('login', ['error' => $error]));
}
```

```php
<!-- views/login.php -->
<?php echo Form::open() ?>
	<?php echo Form::csrf() ?>
	<?php if ($error): ?><p class="error"><?php echo HTML::chars($error) ?></p><?php endif ?>

	<?php echo Form::label('username', 'User name') ?>
	<?php echo Form::input('username', null, ['autocomplete' => 'username']) ?>

	<?php echo Form::label('password', 'Password') ?>
	<?php echo Form::password('password', null, ['autocomplete' => 'current-password']) ?>

	<?php echo Form::submit('login', 'Log in') ?>
<?php echo Form::close() ?>
```

## Drivers

| Driver | Users are kept | Use it for |
|---|---|---|
| `ORM` | In database tables, with roles and "remember me" | Sites with accounts: see [Users in the Database](orm) |
| `File` | In the config file | One or two fixed log ins: see [Users in a File](file) |
| your own | Anywhere: another service, LDAP | See [Writing a Driver](driver) |

## Settings

```php
// application/config/auth.php
return [
	'driver'       => 'ORM',
	'lifetime'     => 1209600,     // seconds a "remember me" lasts: two weeks
	'session_type' => 'native',    // which session holds the logged-in user
	'session_key'  => 'auth_user',
];
```

## What the module does for you

- Passwords are stored with PHP's `password_hash()`: see [Passwords](passwords).
- The session gets a new id at log in, so a planted session id is useless.
- Passwords are compared in constant time.

[!!] This module is a starting point. Most sites add their own registration, email confirmation and password reset on top of it.
