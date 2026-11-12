# Arrays

`Arr` reads values that may be missing and reshapes arrays.

```php
$data = [
	'user'  => ['name' => 'Ana', 'roles' => ['admin', 'editor']],
	'theme' => 'dark',
];
```

## Reading

```php
// A key, with a default when it is missing
Arr::get($data, 'theme');          // 'dark'
Arr::get($data, 'lang', 'en');     // 'en'

// A value inside arrays: keys joined by "."
Arr::path($data, 'user.name');                  // 'Ana'
Arr::path($data, 'user.roles.0');               // 'admin'
Arr::path($data, 'user.email', 'none');         // 'none'

// Only the keys you name; missing ones get the default
Arr::extract(['title' => 'Hi', 'admin' => 1], ['title', 'body'], '');
// ['title' => 'Hi', 'body' => '']
```

[!!] `Arr::extract()` is the safe way to take form fields: a visitor cannot add a field (`admin`) that you did not name.

When missing keys should stay missing, use `only()` and `except()`:

```php
$post = ['title' => 'Hi', 'admin' => 1];

Arr::only($post, ['title', 'body']);            // ['title' => 'Hi']
Arr::except($post, ['admin']);                  // ['title' => 'Hi']
```

Testing and removing by path:

```php
$config = ['db' => ['host' => null, 'port' => 3306]];

Arr::has($config, 'db.host');                   // true: the key exists, its value is null
Arr::has($config, 'db.user');                   // false

Arr::delete_path($config, 'db.port');           // true; $config is now ['db' => ['host' => null]]
```

## Lists of rows

```php
$users = [
	['id' => 1, 'name' => 'Ana'],
	['id' => 2, 'name' => 'Rui'],
];

// One value of each row
Arr::pluck($users, 'name');        // ['Ana', 'Rui']

// The same with a path: "*" stands for every row
Arr::path($users, '*.name');       // ['Ana', 'Rui']
```

## Writing

```php
// Set a value deep inside, making the arrays on the way
$settings = [];
Arr::set_path($settings, 'mail.smtp.port', 587);
// ['mail' => ['smtp' => ['port' => 587]]]

// Put a pair at the start
$options = Arr::unshift($options, '', 'Choose one');
```

## Combining

```php
// Merge: keys are replaced, lists are joined, arrays inside are merged too
Arr::merge(
	['a' => 1, 'list' => [1, 2], 'opt' => ['x' => 1]],
	['a' => 2, 'list' => [3],    'opt' => ['y' => 2]]
);
// ['a' => 2, 'list' => [1, 2, 3], 'opt' => ['x' => 1, 'y' => 2]]

// Overwrite: only keys that the first array already has
Arr::overwrite(['a' => 1, 'b' => 2], ['a' => 9, 'c' => 3]);
// ['a' => 9, 'b' => 2]
```

## Reshaping

```php
// All values of nested arrays in one flat array
Arr::flatten(['a' => 1, 'b' => ['c' => 2, 'd' => ['e' => 3]]]);
// ['a' => 1, 'c' => 2, 'e' => 3]

// A function on every value, at every depth
Arr::map('trim', [' a ', ['b ']]);
// ['a', ['b']]

// Only on some keys
$clean = Arr::map('strip_tags', $post, ['title', 'summary']);

// Numbers for a select box
Arr::range(5, 20);
// [5 => 5, 10 => 10, 15 => 15, 20 => 20]
```

## Asking

```php
Arr::is_assoc(['a' => 1]);   // true: keys are names
Arr::is_assoc([1, 2]);       // false: a list

// An array, or an object that can be looped like one
Arr::is_array($value);
```
