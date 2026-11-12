# Input and Output

`Minion_CLI` talks to the person at the terminal.

## Writing

```php
Minion_CLI::write('Importing...');

// Several lines
Minion_CLI::write(['First line', 'Second line']);

// On the same line, replacing what was there: for progress
foreach ($files as $n => $file)
{
	Minion_CLI::write_replace('File '.($n + 1).' of '.count($files));
}

// End the line when done
Minion_CLI::write_replace('All files done.', true);
```

## Messages

Four kinds of message, each with its own mark and colour, so the result of a task shows at a glance.

```php
Minion_CLI::success('120 products imported.');     // ✔ green
Minion_CLI::info('Reading products.csv');          // › cyan
Minion_CLI::warning('3 rows had no price.');       // ! yellow
Minion_CLI::error('The file was not found.');      // ✖ red

Minion_CLI::title('Importing products');           // bold, with a line under it
```

`warning()` and `error()` write to the error output, so `./minion import > import.log` keeps them on the screen and a calling script can tell them apart.

[!!] `error()` only writes. To end a task with an error status, throw an exception: Minion writes its message the same way.

## Tables

```php
Minion_CLI::table([
	['name' => 'Ana',   'role' => 'admin'],
	['name' => 'Bruno', 'role' => 'editor'],
]);
```

```text
name   role
-----  ------
Ana    admin
Bruno  editor
```

```php
// Titles of your own, and coloured values
Minion_CLI::table($rows, ['Name', 'Role']);

Minion_CLI::table([
	[Minion_CLI::color('applied', 'green'), '20261111_143000_create_articles'],
	[Minion_CLI::color('pending', 'yellow'), '20261112_101500_add_published'],
], ['Status', 'Migration']);
```

## Progress

Call `progress()` after every step of a long job.

```php
foreach ($rows as $i => $row)
{
	$this->import($row);

	Minion_CLI::progress($i + 1, count($rows));
}
```

```text
[===============               ]  50% (60/120)
```

On a terminal the bar is drawn again on the same line. When the output goes to a file, one line is written at every tenth of the way, so a log stays short.

## Colours and styles

```php
Minion_CLI::write(Minion_CLI::color('Done', 'green'));
Minion_CLI::write(Minion_CLI::color('Something went wrong', 'white', 'red'));

Minion_CLI::write(Minion_CLI::style('Important', 'bold'));
Minion_CLI::write(Minion_CLI::style('Title', 'bold', 'underline'));
```

| | Names |
|---|---|
| Text | `black`, `gray`, `dark_gray`, `blue`, `light_blue`, `green`, `light_green`, `cyan`, `light_cyan`, `red`, `light_red`, `magenta`, `light_magenta`, `purple`, `light_purple`, `brown`, `yellow`, `light_gray`, `white` |
| Background | `black`, `red`, `green`, `yellow`, `blue`, `magenta`, `cyan`, `light_gray` |
| Style | `bold`, `dim`, `italic`, `underline` |

Colours and styles are used when the output is a terminal. They are left out by themselves when it goes to a file or another program, with the option `--no-color`, and when the environment variable `NO_COLOR` is set.

```php
// Decide yourself
Minion_CLI::$colors = false;
```

## Asking

```php
// A free answer
$name = Minion_CLI::read('Name of the new admin');

// One of a list: asked again until the answer is one of them
$format = Minion_CLI::read('Format', ['csv', 'json']);

// Yes or no; Enter gives the default, here "no"
if ( ! Minion_CLI::confirm('Delete 120 rows?'))
	return 'Nothing deleted.'.PHP_EOL;

// Enter gives "yes"
$again = Minion_CLI::confirm('Try again?', true);

// A password: what is typed is not shown
$password = Minion_CLI::password('Password');
```

[!!] On Windows the password is visible while it is typed, and the user is told so.

### Tasks that run unattended

A cron job or a deploy has nobody to answer. Started with `--no-interaction`:

- `confirm()` asks nothing and returns its default, so make the default the safe answer;
- `read()` and `password()` throw an exception that names the question, in place of waiting for ever.

```bash
./minion cache:clean --no-interaction
```

## Waiting

```php
// Until a key is pressed
Minion_CLI::wait();

// 10 seconds, counting down
Minion_CLI::wait(10, true);
```

## Options of the command line

A task gets its options as the parameter of `_execute()`. Outside a task:

```php
// ./minion import --file=a.csv --limit=10
$all = Minion_CLI::options();              // ['file' => 'a.csv', 'limit' => '10', ...]
$file = Minion_CLI::options('file');       // 'a.csv': one name gives its value
$two = Minion_CLI::options('file', 'limit');   // ['file' => 'a.csv', 'limit' => '10']
```

## An example: creating a user

```php
/**
 * Creates an administrator. Asks for what it needs.
 */
class Task_User_Create extends Minion_Task {

	protected function _execute(array $params): mixed
	{
		$username = Minion_CLI::read('User name');
		$email = Minion_CLI::read('Email address');
		$password = Minion_CLI::password('Password');

		try
		{
			$user = ORM::factory('User')->create_user(
				['username' => $username, 'email' => $email, 'password' => $password, 'password_confirm' => $password],
				['username', 'email', 'password']
			);
		}
		catch (ORM_Validation_Exception $e)
		{
			foreach ($e->errors('models') as $error)
			{
				Minion_CLI::error(is_array($error) ? implode(', ', $error) : $error);
			}

			return null;
		}

		$user->add('roles', ORM::factory('Role', ['name' => 'login']));
		$user->add('roles', ORM::factory('Role', ['name' => 'admin']));

		Minion_CLI::success('User '.$username.' created.');

		return null;
	}
}
```

[!!] A task that asks questions cannot run from cron. Give such tasks options as well, and only ask for what was not given.
