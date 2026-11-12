# Minion

Minion runs code of your application from the command line: scheduled jobs, imports, maintenance. Tasks have the whole framework at hand: models, config, the database.

```php
// application/bootstrap.php
Kohana::modules([
	'minion' => MODPATH.'minion',
]);
```

## Running a task

```bash
# From the project directory
./minion cache:clean --older=7

# Which tasks are there? Each with a line that says what it does
./minion

# What does a task do, and which options does it take?
./minion cache:clean --help
```

The first word is the task; `--name=value` are its options. `php public/index.php cache:clean` does the same where the `minion` file cannot be run directly, as on Windows.

Every task also takes these options:

| Option | Does |
|---|---|
| `--help` | Shows the description and the options of the task |
| `--no-interaction` | Asks no questions: for cron jobs and deploys. See [Input and Output](cli#asking) |
| `--no-color` | Writes without colours |

A task that fails, is unknown or gets a wrong option ends with an error status and writes the reason to the error output, so a script that calls it can stop.

## A task

```php
// application/classes/Task/Cache/Clean.php: the task "cache:clean"

/**
 * Removes expired values from the cache.
 *
 * Options:
 *  --group  the cache group (default: the default group)
 */
class Task_Cache_Clean extends Minion_Task {

	protected array $_options = [
		'group' => null,
	];

	protected function _execute(array $params): mixed
	{
		$cache = Cache::instance($params['group']);

		if ($cache instanceof Cache_GarbageCollect)
		{
			$cache->garbage_collect();
		}

		return 'Done.'.PHP_EOL;
	}
}
```

See [Writing Tasks](tasks).

## Scheduled tasks

Let cron (or a systemd timer) run them:

```text
# Every night at 03:10
10 3 * * *  cd /var/www/shop && ./minion cache:clean --no-interaction >> application/logs/cron.log 2>&1
```

Set the environment for the command line too, or tasks run as `development`:

```text
10 3 * * *  cd /var/www/shop && KOHANA_ENV=production ./minion cache:clean --no-interaction
```

## Tasks that never end

For workers that run all the time (a queue), `modules/minion/miniond` starts a task in the background and starts it again when it stops.

```bash
modules/minion/miniond queue:work --queue=mail
```

A process manager such as systemd or supervisor does the same and is usually the better choice.

## Good to know

- An error in a task is printed as plain text and written to the log, as on the web. In production only a reference code is printed.
- Tasks have no request: `Request::current()` is `null`, and there is no session.
- Addresses built with `URL::site()` need to know the domain: see [Writing Tasks](tasks).
