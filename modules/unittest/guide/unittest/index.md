# Unittest

The Unittest module prepares the framework for [PHPUnit](https://phpunit.de) and holds the helpers the framework's own tests use. Tests run on PHPUnit 13.

## Running tests

```bash
composer install

vendor/bin/phpunit                               # everything
vendor/bin/phpunit --group kohana.core.arr       # one group
vendor/bin/phpunit --filter ArrTest              # one class
vendor/bin/phpunit --filter test_merge           # tests whose name matches
vendor/bin/phpunit system/tests/kohana/ArrTest.php
```

```text
OK, but some tests were skipped!
Tests: 1914, Assertions: 4637, Skipped: 102.
```

Tests that need something this machine does not have (a Redis server, the `imagick` extension) are skipped, not failed.

## How it is set up

| File | Does |
|---|---|
| `phpunit.xml` | Says where tests are (`system/tests`, `modules/*/tests`; files named `*Test.php`) and what counts as a failure |
| `modules/unittest/bootstrap.php` | Starts the application, with **every module enabled** |
| `Unittest_TestCase` | The base class of tests, with a few helpers |

[!!] Warnings, notices and deprecations make a run fail. The code must stay clean on current and coming PHP versions.

## Tests of your application

Put them in `application/tests/` and add the directory to `phpunit.xml`:

```xml
<testsuite name="application">
	<directory>application/tests</directory>
</testsuite>
```

```bash
vendor/bin/phpunit --testsuite application
```

## Tests that need a database

The database tests of the framework use SQLite in memory, and MySQL or MariaDB when these variables are set:

```bash
KOHANA_TEST_MYSQL_HOST=127.0.0.1 KOHANA_TEST_MYSQL_PORT=3306 \
KOHANA_TEST_MYSQL_USER=root KOHANA_TEST_MYSQL_PASSWORD=secret \
KOHANA_TEST_MYSQL_DATABASE=kohana_test vendor/bin/phpunit
```

PostgreSQL is tested the same way, with `KOHANA_TEST_PGSQL_HOST`, `_PORT`, `_USER` (`postgres` when left out), `_PASSWORD` and `_DATABASE`:

```bash
KOHANA_TEST_PGSQL_HOST=127.0.0.1 KOHANA_TEST_PGSQL_PASSWORD=secret vendor/bin/phpunit
```

## Tests that need a cache server

The Redis and Memcached drivers of the Cache module are tested when a server is named (the ports are optional):

```bash
KOHANA_TEST_REDIS_HOST=127.0.0.1 KOHANA_TEST_REDIS_PORT=6379 \
KOHANA_TEST_MEMCACHED_HOST=127.0.0.1 KOHANA_TEST_MEMCACHED_PORT=11211 \
vendor/bin/phpunit
```

A test that misses its server or its PHP extension is skipped, and says why: run `vendor/bin/phpunit --display-skipped` to see them. `docker/Dockerfile` builds an image that has every extension.

## Coverage

```bash
vendor/bin/phpunit --coverage-html build/coverage
```

```bash
# A summary in the terminal, class by class
vendor/bin/phpunit --coverage-text
```

Needs the Xdebug or PCOV extension; the image of `docker/Dockerfile` has PCOV. The `<source>` part of `phpunit.xml` lists the code that is measured.

Run it with every test database and cache server switched on (see above): a test that is skipped covers nothing, and the numbers come out lower than they are.

## All checks at once

Tests are one of several checks. One script runs them all, the fastest first, and stops at the first that fails:

```bash
docker/check.sh
```

| Check | Finds |
|---|---|
| Syntax (`php -l`) | Files PHP cannot read |
| Code style (`vendor/bin/phpcs`) | Code that does not follow `phpcs.xml` |
| Types (`php docker/check-types.php`) | A function, parameter, property or constant without a type |
| Assignments (`php docker/check-logic.php`) | `$a = $b OR $c`, which assigns `$b` only: write `$a = ($b OR $c)` |
| Class loading (`php docker/load-all-classes.php`) | A class that no longer fits its parent |
| Static analysis (`vendor/bin/phpstan analyse`) | Values used as something they may not be |
| Tests (`vendor/bin/phpunit`) | Code that does not do what it should |

Options are passed on to PHPUnit: `docker/check.sh --filter Auth`.

### With Docker

Nothing but Docker is needed. This builds an image with PHP 8.4, every extension and Composer, starts MariaDB, PostgreSQL, Redis, Memcached, an FTP server and an S3 server, and runs the script with all of them, so no test is skipped for a missing server:

```bash
docker compose -f docker/compose.yml run --rm php docker/check.sh
```

```bash
# One command in the same surroundings
docker compose -f docker/compose.yml run --rm php vendor/bin/phpunit --filter Auth
```

```bash
# Stop the servers
docker compose -f docker/compose.yml down
```

The first run builds the image, which takes a few minutes. The servers keep their data in memory: every start is a clean one.

The GitHub workflow in `.github/workflows/ci.yml` runs the same script on every push, on each supported PHP version.

PHPStan runs at level 10, the strictest one, (`phpstan.neon`) and the code is clean at that level, so anything it reports is new and must be fixed: there is no list of accepted findings. The few places where the analysis cannot follow the framework are listed in that file, each with its reason.
