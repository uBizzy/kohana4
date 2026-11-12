# Changes

## 4.0.0 "renatus" (2026-11-15)

What changed since Koseven 3.3.x, by kind. For what an application has to do about each breaking change, see the guide: [Upgrading to 4.0](system/guide/kohana/upgrading.md).

Renatus is a first name of Latin origin which means "born again" ("natus" = "born").

- [Breaking changes](#breaking-changes)
- [Security](#security)
- [New in the core](#new-in-the-core)
- [New in the modules](#new-in-the-modules)
- [Extra modules](#extra-modules)
- [Fixes](#fixes)
- [Removed](#removed)
- [Documentation](#documentation)
- [Tests and tools](#tests-and-tools)

## Breaking changes

### Requirements and names

- PHP 8.4 or later; the `mbstring` and `intl` extensions are required.
- The classes of the framework are `Kohana_*` and the core class is `Kohana`. The names of Koseven (`KO7`, `KO7_*`) are aliases when the `ko7` or `ko9` module is enabled.
- `KOHANA_START_TIME` and `KOHANA_START_MEMORY` in `index.php`.

### Types

- Every method, property and constant declares its types. A class that extends the framework must declare the same: controllers (`before(): void`, `after(): void`), models (`protected array $_has_many`, `rules(): array`), Minion tasks (`_execute(array $params): mixed`), Codebench benchmarks.
- Values that come from a visitor are typed `mixed` and checked inside. Validation rules fail anything that is not text or a number.
- `Config::load()` takes a string; `Log::add()` takes the level as an integer.
- `Debug::trace()` returns an array. `ORM::$_db` is `?Database`. Several properties are no longer nullable.

### Stored data

- Cookies signed by an older version are rejected: signatures are HMAC-SHA-256.
- Sessions that cannot be read are destroyed and started again.
- Cache keys and file names are derived with SHA-256: old entries are not found.
- The `user_agent` column of `user_tokens` holds 64 characters; `password` of `users` should hold 255.
- Passwords are stored with `password_hash()`. An old hash still logs in and is replaced at that login.

### Defaults that changed

- **Database**: prepared statements are on; operators of `where()`, `having()` and `on()` are checked against a list; a failed MySQLi query always throws; SQLite follows foreign keys.
- **Cookies**: `HttpOnly` by default; secure whenever the request is HTTPS; `SameSite=Lax`.
- **Client address**: forwarding headers are trusted only from the proxies in `Request::$trusted_proxies`.
- **Base URL**: `'/'` by default. Trusted hosts are compared without the port.
- **Responses**: `X-Content-Type-Options`, `Referrer-Policy` and `X-Frame-Options` on every response.
- **Uploads**: files a web server could run are refused; a file name that leads out of the directory is refused.
- **Requests to other servers**: only `http` and `https`; time limits of 10 and 30 seconds; certificates are checked; credentials are not sent along on a redirect to another host.
- **Images**: quality 85 when none is given (was 100); images over 50 million pixels are refused.
- **Files**: `Kohana::find_file()` refuses names with `..` or NUL bytes.

### Errors

- In production a visitor sees a general message and a reference code only, in HTML, JSON and plain text. The full error is in the log under that code.
- Deprecations do not stop a request: they are logged, and listed in a panel in development.
- An error inside a view is thrown as it is, with the file and line of the template, not wrapped in a `View_Exception`.
- Many failures that ended in a PHP error now throw a `Kohana_Exception` that says what is wrong: a validation rule that cannot be called, `on()` without `join()`, a pagination without a request, an encryption engine that is none.

### Behaviour of single parts

- **Controllers**: an `action_` method that is not `public` gives a 404.
- **REST**: a method the controller does not map is answered with 405 and runs nothing; a body that is not valid JSON with 400; the format can be chosen by the `Accept` header.
- **Auth**: `force_login()` for a user that does not exist throws; `Auth_File::check_password()` accepts the password only; `Auth_Bcrypt` extends `Auth_ORM`; the `.sql` schema files are replaced by a migration.
- **ORM**: `count_all()` leaves sorting and joined columns out; the Guid behavior writes its id once; behaviors use the database of the model.
- **Cache**: `Kohana::cache()` uses the Cache module whenever it is configured.
- **Minion**: a wrong option and an unknown task end with an error status; errors go to STDERR; three views are gone.
- **Profiler**: the benchmarks of the framework have new names.
- **Sessions**: a cookie session over 4 KB is not sent, and is reported.
- **Downloads**: a `Range` header is followed only for a resumable download.
- **Config in the database**: objects stored in the table are not created.
- **User guide**: off in production; a new Markdown parser that does not pass HTML through.
- **Tests**: PHPUnit 13; attributes in place of annotations; static data providers; `Unittest_TestSuite` and `config/unittest.php` are gone.

## Security

- Prepared statements for every query of the query builder and of ORM.
- HMAC-SHA-256 for cookie signatures; SHA-256 wherever a key or a name is derived.
- `password_hash()` and `password_verify()` in every auth driver; hashes are renewed when the algorithm changes.
- A login attempt for a user that does not exist takes as long as one for a user that does.
- "Remember me" tokens get a new value at every use.
- CSRF: `Form::csrf()`, `Security::check()`, and `Security::check_and_rotate()` for tokens that work once.
- A nonce for a Content-Security-Policy: `Security::nonce()`. Everything the framework prints works under a strict policy.
- Security headers on every response: `HTTP::$security_headers`.
- Rate limiting: `Throttle`. Cross-origin requests: `CORS`.
- Uploads: dangerous extensions refused, every extension of a name checked, no path out of the directory.
- Archives are unpacked safely: no path out of the directory, no links, limits on size and number of files.
- Requests to other servers can be limited to public addresses (`only_public_addresses()`), against requests into the own network.
- `Config_Database_Reader` never creates objects from stored data.
- Log files cannot be read through the web server; their permissions are set.
- Dumps, the error page, the debug bar and the log reader show `***` for values named like a secret.
- Development tools (guide, Codebench, debug bar) are off in production; `./minion check` warns about development settings there.

## New in the core

- **Requests and responses**: `Request::json()`, `Response::json()`, `Response::csv()`, `Response::xml()`.
- **Requests to other servers**: one client for curl with logins (`auth()`, `bearer()`), proxies, certificates, uploads, downloads to a file, retries, and several requests at once (`Request_Client_Curl::all()`).
- **REST**: CSV as a format; the format chosen by the `Accept` header.
- **Archives**: `Archive` creates, lists and unpacks zip, tar and tar.gz, and gzips single files.
- **Logging**: `Log_JSON` writes one JSON object for each line, with a code for each request. A log line names the right file and line.
- **Errors**: a new error page for development, with the causes of an error, the steps in your own code marked, a reference code, a copy button and optional links into your editor (`Debug::$editor`).
- **Profiler**: classes loaded, routes tried, controller steps, session time, rows of each query, slow requests in the log; `Profiler::measure()`, `note()`, `add()`, `summary()`.
- **Themes**: `Theme` and `config/theme.php`. Eight themes (default, Catppuccin, Dracula, Nord, Solarized, Gruvbox, Tokyo Night, One), each light and dark, for everything the framework prints; a button to switch; your own colours, themes and fonts.
- **Validation**: the rules `uuid`, `json`, `boolean`, `in`, `date_format`.
- **Helpers**: `Arr::only()`, `except()`, `has()`, `delete_path()`; `Form::email()`, `number()`, `date()`, `time()`, `tel()`, `search()`, `color()`, `range()`, `url_field()`; `Json::encode()` and `decode()` that throw; `Cookie::has()` and `options()`; `Route::has()`, `I18n::has()`, `Fragment::has()` and `discard()`.
- **Addresses**: `Request::ip_in_list()` checks an address against addresses and networks, IPv4 and IPv6.
- **Data brought up to date**: mime types (webp, avif, heic, woff2), card number ranges, browsers, plural words.

## New in the modules

- **migration** (new): changes to the database as files, applied in order and undone in reverse; `migration:create`, `run`, `rollback`, `status`, `fresh`, `seed`; seed data.
- **minion**: `./minion` in the project root; `./minion check` tests the installation (PHP, extensions, directories, keys, database, migrations, cache, theme); messages, tables, a progress bar, `confirm()`, `--no-interaction`, colours only on a terminal.
- **database**: `Database::transaction()`, `reconnect()`, `driver()`; ORM on SQLite and PostgreSQL through PDO (`list_tables()`, `list_columns()`).
- **orm**: `ORM::db()`; the auth tables as a migration for MySQL, PostgreSQL and SQLite (`./minion auth:migration`).
- **auth**: `Auth::verify()`, `Auth::needs_rehash()`.
- **cache**: hits and misses of the request (`Cache::$stats`, `Cache::$reads`).
- **image**: `auto_orient()` by the EXIF note, `thumbnail()`, `from_string()`, AVIF, HEIC with Imagick.
- **userguide**: a new design, search, an API browser with types, a Markdown parser of its own.
- **ko7**, **ko9** (new): the class names of Koseven as aliases.

## Extra modules

The modules that are not part of the core have a repository and a list of changes of their own: https://github.com/uBizzy/kohana4-modules

## Fixes

Bugs found while the code was typed, analysed and tested. Each has a test.

- **Query builder**: `reset()` of every builder and of a join stopped with a type error.
- **Database**: a connection closed with `disconnect()` could not be used again; `as_object` did not accept a class name.
- **ORM**: `count_all()` failed on PostgreSQL for sorted queries and models with `$_load_with`; the Slug behavior did not see a taken slug of a new record; behaviors asked the wrong database.
- **Auth**: with the Bcrypt driver, `logged_in('admin')` answered `true` for every logged-in user, and `force_login()` did not count the login; `force_login()` started a session without a user; the file driver accepted the stored hash as a password.
- **Controllers**: a method that was not public could be reached from the address.
- **REST**: an unmapped method still ran an action; the default format was not found on Linux; an empty JSON body stopped on an undefined variable; a key that is no element name stopped the XML output.
- **Requests**: a `HEAD` request with curl waited for a body until the time limit; `parse_accept_header()` without a header returned nothing; `Accept-Language: *` ended in a type error.
- **Downloads**: a `Range` header on a download that was not resumable sent a part as if it were the whole file.
- **Cache**: `Kohana::cache()` ignored the Cache module unless it had been used before in the request; the file driver failed when its directory was missing; `delete_tag('user_1')` of the SQLite driver also deleted `userA1`; the Redis driver returned expired entries.
- **Images**: the GD driver applied each step to the original file, so only the last step of a chain had an effect; `Image::factory()` ignored `default_driver`; Imagick wrote the original format under a new name; `resize()` with `Image::NONE` made an image of one pixel.
- **Sessions**: a cookie session that was too large was dropped by the browser in silence.
- **Logging**: a log line named the code one step further up than the code that logged.
- **Debug**: `Debug::dump()` failed on objects with numbered properties, which could break the error page while it showed an error.
- **Profiler**: `stats()` of an empty list divided by zero.
- **Helpers**: a regular expression that failed (text that is not valid UTF-8) passed `null` on and ended in a PHP error; `Kohana::sanitize()` stopped on objects.
- **Minion**: a wrong option and an unknown task ended as a success; a task whose class only extends another had no description.
- **Uploads**: a file name passed to `save()` could lead out of the directory.
- **URL**: the list of trusted hosts did not fit a host with a port.
- The API browser cut a type with a space inside its brackets (`array<string, mixed>`) in two and showed the rest as the description.

## Removed

- The Mcrypt encryption engine and the Wincache cache driver: PHP no longer has them.
- `Request_Client_HTTP`, the client for the PECL `http` extension.
- `Request::_parse_accept()`; `iterable_to_xml()` and `evaluate_error()` of `REST_Format_XML`.
- The auth schema files `auth-schema-mysql.sql` and `auth-schema-postgresql.sql`: a migration takes their place.
- `modules/unittest/tests.php`, `Unittest_TestSuite`, `config/unittest.php`.
- The Minion views `minion/help/list`, `minion/help/error`, `minion/error/validation`.
- Disqus comments, HTML and `{{view}}` includes in guide pages.

Removed by Koseven before this version, for those who come from Kohana 3.3: the MySQL driver (use MySQLi), the APC and Memcache cache drivers (use APCu and Memcached). Encryption and pagination are modules.

## Documentation

- The guide was rewritten: installation, the request flow, every helper, security, and each module.
- Tutorials: Hello world, a template controller, error pages, clean URLs, and a new one that builds a complete model from the tables to a test.
- An upgrade guide that lists every change in the order to make it.
- The examples of every guide page are checked to be valid PHP, and its links to lead somewhere.
- A logo for version 4 as a vector image (`Theme::logo()`) and a small icon (`Theme::favicon()`), shown by the guide, Codebench and the install page; new pictures in the guide, and the diagram of the cascading filesystem redrawn.

## Tests and tools

- About 3,400 tests on PHPUnit 13; warnings, notices and deprecations fail a run.
- Tests run against real servers: MariaDB, PostgreSQL, Redis and Memcached, set up by `docker/compose.yml`.
- About 90% of the lines are covered.
- Static analysis with PHPStan at level 10; code style with PHP_CodeSniffer.
- Checks of its own: every declaration has a type; no assignment ends before `and` or `or`; every class loads.
- One command runs all of them, and the CI runs the same one on PHP 8.4 and 8.5: `docker/check.sh`.
