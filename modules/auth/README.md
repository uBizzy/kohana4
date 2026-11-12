# Auth module

Logins: users in a file or, with the `orm` module, in the database. Passwords are stored with `password_hash()`.

Part of [Kohana 4](https://github.com/uBizzy/kohana4).

```php
// application/bootstrap.php
Kohana::modules([
	'auth' => MODPATH.'auth',
]);
```

The full documentation is in `guide/auth/` and in the user guide of your application.

Licensed as the framework: [BSD-3-Clause](../../LICENSE.md).
