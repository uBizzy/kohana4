# Minion module

Tasks for the command line: `./minion <task> [--option=value]`. A task is a class in `classes/Task/`.

Part of [Kohana 4](https://github.com/uBizzy/kohana4).

```php
// application/bootstrap.php
Kohana::modules([
	'minion' => MODPATH.'minion',
]);
```

The full documentation is in `guide/minion/` and in the user guide of your application.

Licensed as the framework: [BSD-3-Clause](../../LICENSE.md).
