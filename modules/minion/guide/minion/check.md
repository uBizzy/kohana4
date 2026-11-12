# Checking the Installation

`./minion check` tells whether the application can run on this machine. Run it after installing, and as a step of every deploy.

```text
./minion check
      Check                Detail
----  -------------------  -----------------------------------------
ok    PHP version          8.4.27 (8.4 or newer is needed)
ok    Extension intl       for the framework
ok    Extension mbstring   for the framework
ok    Cache directory      APPPATH/cache
ok    Logs directory       APPPATH/logs
fail  Cookie salt          Cookie::$salt is not set in the bootstrap
ok    Extension pdo        for the database
ok    Database connection  "default" sqlite
ok    Migrations           all applied

✖ 1 of 9 checks failed.
```

- **ok**: fine.
- **warn**: works, but look at it. Warnings do not stop anything.
- **fail**: the application cannot work like this. The command ends with an error status, so a deploy script stops:

```bash
git pull
./minion migration:run --no-interaction
./minion check
```

## What is checked

| Check | Fails or warns when |
|---|---|
| PHP version | **fail**: older than 8.4 |
| Extensions `intl`, `mbstring` | **fail**: not loaded |
| Cache and logs directories | **fail**: missing or not writable |
| Cookie salt | **fail**: not set · **warn**: shorter than 32 characters |
| Theme | **warn**: a value of `config/theme.php` is not valid (only listed when there is one) |

The rest depends on the modules that are enabled in the bootstrap:

| Module | Check | Fails or warns when |
|---|---|---|
| database | Extension of the default connection | **fail**: not loaded |
| database | Database connection | **fail**: the default connection cannot connect or answer `SELECT 1` |
| migration | Migrations | **warn**: some are not applied yet |
| cache | Cache | **fail**: an entry cannot be written, read and deleted in the default group |
| encrypt | Extension and key of the default group | **fail**: extension not loaded · **warn**: no key |
| image | Driver and its extension | **warn**: no driver configured · **fail**: extension not loaded |

In **production** (`KOHANA_ENV=production`) it also warns about what belongs to development:

- `public/install.php` is still there;
- a module for development is enabled: userguide, unittest, codebench, and any module that names itself in a `config/check.php` (the debug bar of `extra/` do);
- profiling is on, or internal caching is off.

## Good to know

- **No secret is written.** Of the cookie salt only the length is shown, of the encryption key only whether it is set. A database that cannot be reached shows the message of the database, which names the user and host but never the password.
- Only the default database connection and the default cache group are tried.
- The command line can use another `php.ini` than the web server: an extension found here may be missing there. `public/install.php` checks the web server's side, before you delete it.
- With the migration module, the check creates the table that remembers the applied migrations when it is not there yet, as `migration:status` does.

## Your own checks

Extend the task in your application. Each check adds one line with `ok`, `warn` or `fail`.

```php
// application/classes/Task/Check.php
class Task_Check extends Kohana_Task_Check {

	protected function _checks(): void
	{
		parent::_checks();

		$this->_add(is_writable(DOCROOT.'uploads') ? 'ok' : 'fail', 'Uploads directory');

		$this->_check_extension('curl', 'the payment service');
	}

}
```
