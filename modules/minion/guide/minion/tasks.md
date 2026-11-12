# Writing Tasks

A task is a class `Task_<Name>` in `classes/Task/` that extends `Minion_Task`.

| Class | File | Called as |
|---|---|---|
| `Task_Import` | `classes/Task/Import.php` | `import` |
| `Task_Db_Backup` | `classes/Task/Db/Backup.php` | `db:backup` |

```php
/**
 * Imports products from a CSV file.
 *
 * Options:
 *  --file     path of the CSV file (needed)
 *  --dry-run  only say what would be done
 */
class Task_Import extends Minion_Task {

	// The options the task accepts, with their defaults
	protected array $_options = [
		'file'    => null,
		'dry-run' => false,
	];

	protected function _execute(array $params): mixed
	{
		$count = 0;

		foreach ($this->rows($params['file']) as $row)
		{
			if ($params['dry-run'] === false)
			{
				ORM::factory('Product')->values($row, ['sku', 'name', 'price'])->save();
			}

			$count++;
		}

		Minion_CLI::write($count.' products imported.');

		return null;
	}

	protected function rows(string $file): Generator
	{
		$handle = fopen($file, 'r');

		while (($row = fgetcsv($handle, null, ',', '"', '')) !== false)
		{
			yield ['sku' => $row[0], 'name' => $row[1], 'price' => $row[2]];
		}

		fclose($handle);
	}
}
```

```bash
./minion import --file=products.csv --dry-run
```

## The rules

- `_execute()` is declared `protected function _execute(array $params): mixed`. What it returns is printed; return `null` to print nothing.
- `$_options` is declared `protected array`. An option that is not listed there is refused.
- An option given without a value (`--dry-run`) arrives as `null`. Give such flags a default of `false` and test `=== false`, as above.
- The comment above the class is what `--help` shows. Write how to use the task there.

## Checking the options

```php
public function build_validation(Validation $validation): Validation
{
	return parent::build_validation($validation)
		->rule('file', 'not_empty')
		->rule('file', 'is_readable')
		->rule('limit', 'digit');
}
```

When a rule fails, the task does not run and the errors are printed. The texts come from `messages/validation.php`; name another file with:

```php
protected string $_errors_file = 'tasks/import';
```

## Links in tasks

A task has no request, so the framework does not know the domain of the site. Tell it before building addresses (for links in emails):

```php
protected function _execute(array $params): mixed
{
	Minion_Task::set_domain_name('https://www.example.org');

	$link = URL::site('order/view/42', true);   // https://www.example.org/order/view/42

	return null;
}
```

## Long tasks

```php
protected function _execute(array $params): mixed
{
	// Write log messages at once, not at the end
	Log::$write_on_add = true;

	foreach ($this->batches() as $n => $batch)
	{
		$this->process($batch);

		Minion_CLI::write_replace('Batch '.$n.' done');

		// Models keep data: free it between batches
		gc_collect_cycles();
	}

	Minion_CLI::write('');

	return null;
}
```

- Work in batches (`limit()` / `offset()`, or "where id > last id") and do not load a whole table.
- Make tasks safe to run twice: a job that stopped halfway will be started again.
- Stop two copies from running together with a lock:

```php
$lock = fopen(APPPATH.'cache/import.lock', 'c');

if ( ! flock($lock, LOCK_EX | LOCK_NB))
	return 'Already running.'.PHP_EOL;
```

## Telling the shell that it failed

Throw an exception: it is printed and logged, and the command ends with a status other than zero, so cron and scripts notice.

```php
if ( ! is_readable($params['file']))
	throw new Minion_Exception('Cannot read :file', [':file' => $params['file']]);
```

When the task ran well but its result is bad (a check that did not pass, an import with refused rows), throw `Minion_Exception_Failed`. Only your message is printed, without file and line, also in production:

```php
if ($refused > 0)
	throw new Minion_Exception_Failed($refused.' rows were refused.');
```

## Testing a task

A task is a class; run it from a test.

```php
public function test_import_counts_rows(): void
{
	$task = Minion_Task::factory(['task' => 'import', 'file' => $this->fixture, 'dry-run' => true]);

	ob_start();
	$task->execute();
	$output = ob_get_clean();

	$this->assertStringContainsString('3 products imported', $output);
}
```
