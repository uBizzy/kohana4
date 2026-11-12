# Users in the Database

The ORM driver keeps users, roles and "remember me" tokens in database tables. It is part of the [ORM module](../orm).

## Setting up

```php
// application/bootstrap.php
Kohana::modules([
	'auth'     => MODPATH.'auth',
	'database' => MODPATH.'database',
	'orm'      => MODPATH.'orm',
]);
```

```php
// application/config/auth.php
return [
	'driver' => 'ORM',
];
```

Create the tables with a migration. Enable the [migration module](../migration), then:

```bash
./minion auth:migration    # writes application/migrations/<date>_create_auth_tables.php
./minion migration:run     # creates the tables
```

The first command copies the migration into your application, on MySQL/MariaDB, PostgreSQL and SQLite alike. The file is yours from then on: when your users need more columns, add them before you run it. It makes four tables and two roles:

| Table | Holds |
|---|---|
| `users` | `id`, `email`, `username`, `password`, `logins`, `last_login` |
| `roles` | `login` and `admin` to start with |
| `roles_users` | Which user has which role |
| `user_tokens` | "Remember me" tokens |

Deleting a user deletes the roles and tokens of that user (`ON DELETE CASCADE`).

A site that already has these tables, made with the `.sql` files of an older version, needs nothing: do not run `auth:migration` there.

## Creating a user

```php
try
{
	$user = ORM::factory('User')->create_user(
		$this->request->post(),
		['username', 'email', 'password']   // the fields the form may set
	);

	// Without the "login" role nobody can log in
	$user->add('roles', ORM::factory('Role', ['name' => 'login']));
}
catch (ORM_Validation_Exception $e)
{
	$errors = $e->errors('models');
}
```

`create_user()` checks the rules of the model, that `password` has at least 8 characters, and that `password_confirm` is the same. The password is hashed when it is set.

[!!] A user needs the role `login` to log in. Give it after the email address is confirmed, and take it away to block an account.

## Logging in

```php
$auth = Auth::instance();

// By user name or email address
$auth->login('ana', $password);
$auth->login('ana@example.org', $password);

// Keep the visitor logged in for two weeks ("remember me")
$auth->login('ana', $password, true);
```

```php
$user = $auth->get_user();   // a Model_User, or null

echo $user->username;
echo $user->logins;          // how often they logged in
echo date('Y-m-d', $user->last_login);
```

## Roles

```php
// Logged in and has the role
if ( ! Auth::instance()->logged_in('admin'))
	throw HTTP_Exception::factory(403, 'Only for administrators');

// All of several roles
Auth::instance()->logged_in(['login', 'editor']);

// Give and take a role
$user->add('roles', ORM::factory('Role', ['name' => 'admin']));
$user->remove('roles', ORM::factory('Role', ['name' => 'admin']));

// A new role
ORM::factory('Role')->values(['name' => 'editor', 'description' => 'May write articles'])->save();
```

A controller for administrators:

```php
abstract class Controller_Admin extends Controller {

	public function before(): void
	{
		parent::before();

		if ( ! Auth::instance()->logged_in('admin'))
			throw HTTP_Exception::factory(403);
	}
}
```

## Changing a user

```php
// Name and email
$user->update_user($this->request->post(), ['username', 'email']);

// A new password: checked like at creation, hashed when set
$user->update_user($this->request->post(), ['password']);
```

## Logging out

```php
$auth->logout();             // this browser
$auth->logout(true);         // and throw the whole session away
$auth->logout(false, true);  // and every other device ("remember me" tokens of the user)
```

## Remember me

With the third argument of `login()`, a cookie holds a random token; the next visit logs the visitor in from it.

- The token changes every time it is used, so a copied cookie stops working after the real visitor returns.
- Tokens are tied to the browser they were made for.
- `logout(false, true)` deletes all tokens of the user: use it after a password change.

## Your own user model

`Model_User` is empty and extends `Model_Auth_User`. Add what your users need:

```php
// application/classes/Model/User.php
class Model_User extends Model_Auth_User {

	protected array $_has_many = [
		'user_tokens' => ['model' => 'User_Token'],
		'roles'       => ['model' => 'Role', 'through' => 'roles_users'],
		'articles'    => ['model' => 'Article', 'foreign_key' => 'author_id'],
	];

	public function is_admin(): bool
	{
		return $this->has('roles', ORM::factory('Role', ['name' => 'admin']));
	}
}
```

Keep the two relationships of the parent (`user_tokens`, `roles`) when you declare `$_has_many`.

## Logging in without a password

After confirming an email address, or to let an administrator see the site as a user:

```php
Auth::instance()->force_login($user);
```

## Bcrypt at a fixed cost

The `Bcrypt` driver is the ORM driver with one difference: passwords are always hashed with bcrypt, at the cost you set. A hash made with another cost is replaced at the next login. Old HMAC hashes are not accepted.

```php
// application/config/auth.php
return [
	'driver' => 'Bcrypt',
	'cost'   => 12,   // 10 or more
];
```
