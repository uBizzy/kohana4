# CSRF

Cross-site request forgery (CSRF) is when another site makes a visitor's browser send a request to yours: a hidden form that posts to `/account/delete` while the visitor is logged in.

Two things stop it.

## 1. SameSite cookies (already on)

Cookies are sent with `SameSite=Lax` by default, so the browser does not send the session cookie with a form posted from another site. Keep it that way: see [Cookies](../cookies).

This only protects requests that change something if they are **not** `GET` requests. So:

[!!] Never change data in a `GET` request. Deleting, saving and logging out are `POST`.

## 2. A token in every form

A random token is kept in the visitor's session and put in each form. A forged form cannot know it.

```php
<!-- The view -->
<?php echo Form::open('account/email') ?>
	<?php echo Form::csrf() ?>
	<?php echo Form::input('email', $email, ['type' => 'email']) ?>
	<?php echo Form::submit('save', 'Save') ?>
<?php echo Form::close() ?>
```

```php
// The controller
public function action_email(): void
{
	if ($this->request->method() === HTTP_Request::POST)
	{
		if ( ! Security::check($this->request->post('csrf')))
			throw HTTP_Exception::factory(403, 'The form has expired. Go back and try again.');

		// ... save
	}
}
```

Or as a validation rule, with the other checks:

```php
$validation = Validation::factory($this->request->post())
	->rule('csrf', 'not_empty')
	->rule('csrf', 'Security::check')
	->rule('email', 'email');
```

## For every form at once

Check in a base controller, and no action can forget.

```php
// application/classes/Controller/Base.php
abstract class Controller_Base extends Controller {

	public function before(): void
	{
		parent::before();

		$safe = in_array($this->request->method(), ['GET', 'HEAD', 'OPTIONS'], true);

		if ( ! $safe AND ! Security::check($this->request->post('csrf') ?? $this->request->headers('x-csrf-token')))
			throw HTTP_Exception::factory(403, 'The form has expired. Go back and try again.');
	}
}
```

## Requests from JavaScript

Put the token in the page and send it in a header.

```php
<meta name="csrf-token" content="<?php echo HTML::chars(Security::token()) ?>">
```

```js
fetch('/account/email', {
	method: 'POST',
	headers: {
		'Content-Type': 'application/json',
		'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
	},
	body: JSON.stringify({ email: 'ana@example.org' })
});
```

## The methods

| Method | Does |
|---|---|
| `Security::token()` | Returns the token of the visitor's session, making one if needed |
| `Security::token(true)` | Makes a new token (after log in) |
| `Security::check($token)` | `true` when the token is the right one. Accepts anything; fails what is not text |
| `Security::check_and_rotate($token)` | The same, and makes a new token when the check passes, so each token works once |
| `Form::csrf()` | The hidden field `csrf` with the token |

Use `check_and_rotate()` for sensitive actions such as a change of password or a payment:

```php
if ( ! Security::check_and_rotate($this->request->post('csrf')))
	throw HTTP_Exception::factory(403, 'This form has expired, please send it again.');
```

[!!] Another form the visitor has open, in a second tab, still holds the old token and is refused. That is why `check()` does not do this.

An API that is called with a key or a bearer token, not with the session cookie, does not need CSRF tokens.
