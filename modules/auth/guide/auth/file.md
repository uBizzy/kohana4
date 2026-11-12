# Users in a File

The File driver keeps user names and password hashes in the config file. It needs no database and suits a site with one or two fixed log ins: an admin area, a staging site.

## Setting up

```php
// application/config/auth.php
return [
	'driver' => 'File',
	'users'  => [
		'admin' => '$2y$12$...the hash...',
	],
];
```

Make the hash of a password on the command line and paste it in:

```bash
php -r 'echo password_hash("the password", PASSWORD_DEFAULT), "\n";'
```

[!!] Never write the password itself in the file, only its hash. And keep `config/auth.php` out of version control.

## Using it

```php
$auth = Auth::instance();

if ($auth->login('admin', $password))
{
	// ...
}

echo $auth->get_user();   // "admin": the user is its name
$auth->logged_in();
$auth->logout();
```

## What it does not have

- **No roles**: `logged_in('admin')` is the same as `logged_in()`.
- **No "remember me"**: `login($name, $password, true)` throws an exception.
- **No registration**: users are the ones in the file.

When you need any of these, use the [ORM driver](orm).
