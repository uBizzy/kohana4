# Hello World

A first page, in three steps: a controller, a view, and a value passed from one to the other.

## 1. A controller

A controller is a class named `Controller_<Name>` in `application/classes/Controller/`. Each public method named `action_<name>` is a page.

```php
// application/classes/Controller/Hello.php
class Controller_Hello extends Controller {

	public function action_index(): void
	{
		$this->response->body('Hello, world!');
	}
}
```

Open `/hello` (or `/index.php/hello` without [clean URLs](clean-urls)):

![Hello, world!](hello_world_1.png)

The default route reads the address as `controller/action/id`. `/hello` is the controller `Hello` and, because no action is named, the action `index`.

## 2. A view

HTML belongs in a view: a PHP file in `application/views/`.

```php
<!-- application/views/hello.php -->
<h1>Hello, <?php echo HTML::chars($name) ?>!</h1>
<p>It is <?php echo date('H:i') ?>.</p>
```

```php
// application/classes/Controller/Hello.php
class Controller_Hello extends Controller {

	public function action_index(): void
	{
		$view = View::factory('hello');
		$view->name = 'world';

		$this->response->body($view);
	}
}
```

![The view](hello_world_2.png)

[!!] Always print values that come from outside with `HTML::chars()`. See [XSS](../security/xss).

## 3. A value from the address

Add a second action. The default route gives the third part of the address as the parameter `id`.

```php
public function action_to(): void
{
	// /hello/to/Ana
	$name = $this->request->param('id', 'stranger');

	$this->response->body(View::factory('hello', ['name' => $name]));
}
```

`/hello/to/Ana` shows "Hello, Ana!".

## When something is wrong

Forget to give the view its `$name` and the error page shows the exact line of the view, the steps that led to it and the values involved:

![The error page](hello_world_2_error.png)

## Next

- [Routing](../routing): your own address shapes
- [Controllers](../mvc/controllers) and [Views](../mvc/views) in detail
- [Template Controller](controller-template): one layout for every page
