# Logging

```php
Kohana::$log->add(Log::INFO, 'User :name logged in', [':name' => $user->username]);
```

Messages go to the **writers** you attach in the bootstrap. By default: one file per day under `application/logs/`.

```text
application/logs/2026/11/11.php
2026-11-11 10:44:12 --- INFO: User ana logged in
```

## Levels

From most to least serious.

| Level | Use it for |
|---|---|
| `Log::EMERGENCY` | The site cannot work. Uncaught exceptions are logged at this level |
| `Log::ALERT` | Act now |
| `Log::CRITICAL` | A part of the site is failing |
| `Log::ERROR` | Something went wrong for one request |
| `Log::WARNING` | Odd, but handled |
| `Log::NOTICE` | Worth knowing. Deprecations are logged at this level |
| `Log::INFO` | Normal events: a log in, an order |
| `Log::DEBUG` | Details while developing |

```php
Kohana::$log->add(Log::ERROR, 'Payment :id refused: :reason', [':id' => $id, ':reason' => $reason]);
Kohana::$log->add(Log::WARNING, 'Slow answer from the price service: :ms ms', [':ms' => $ms]);
```

Write the values as placeholders, as above, not glued into the text.

[!!] Never log passwords, tokens or card numbers. Log files are read by more people than the database.

## Exceptions

```php
try
{
	$mailer->send($message);
}
catch (Throwable $e)
{
	// With the reference code, the causes and the place; level EMERGENCY
	Kohana_Exception::log($e);
}

// At another level
Kohana_Exception::log($e, Log::WARNING);
```

## Writers

```php
// application/bootstrap.php

// Everything, in files
Kohana::$log->attach(new Log_File(APPPATH.'logs'));

// Only ERROR and worse, to the system log
Kohana::$log->attach(new Log_Syslog('myshop'), Log::ERROR);

// Only some levels
Kohana::$log->attach(new Log_File(APPPATH.'logs/audit'), [Log::INFO, Log::NOTICE]);

// To the terminal: for command line tasks and containers
Kohana::$log->attach(new Log_StdErr);
```

`attach($writer, $levels, $min_level)`: `$levels` is a list of levels, or one level meaning "this and worse".

### One JSON object on each line

```php
// Beside the text log, or in its place
Kohana::$log->attach(new Log_JSON(APPPATH.'logs'));
```

`Log_JSON` writes the same messages in a form that programs read without guessing: one JSON object on each line, in `logs/2026/11/12.json.php`.

```json
{"time":"2026-11-12T10:15:02+00:00","level":"ERROR","message":"[A1B2C3D4] ...","file":"APPPATH/classes/Payment.php","line":88,"request":"3f9a1c0e","method":"POST","uri":"orders/save","reference":"A1B2C3D4","exception":"Kohana_Exception","trace":"#0 ..."}
```

- Every line of one request carries the same `request` code, so all that happened in a request can be put together again.
- An error has its reference code, its class and its trace in the same line.
- The address of the visitor is not written.

The `ub_log_reader` module of `extra/` searches both kinds of log.

### A writer of your own

```php
// application/classes/Log/Slack.php
class Log_Slack extends Log_Writer {

	public function __construct(protected string $webhook)
	{
	}

	public function write(array $messages): void
	{
		foreach ($messages as $message)
		{
			Request::factory($this->webhook)
				->method(HTTP_Request::POST)
				->headers('content-type', 'application/json')
				->body(json_encode(['text' => $this->format_message($message)]))
				->execute();
		}
	}
}
```

```php
Kohana::$log->attach(new Log_Slack('https://hooks.example.com/...'), Log::CRITICAL);
```

## When messages are written

Messages are kept in memory and written when the request ends. To write each one at once (long tasks, or to see messages before a crash):

```php
Log::$write_on_add = true;
```

## Format and files

```php
// Time zone and format of the time at the start of each line
Log_Writer::$timezone = 'Europe/Lisbon';
Log_Writer::$timestamp = 'Y-m-d H:i:s';

// Permissions of new log directories and files
Log_File::$directory_mode = 02775;
Log_File::$file_mode = 0664;
```

Log files end in `.php` and start with a line that stops PHP, so a log file can never be shown by the web server, even if the directory were reachable.
