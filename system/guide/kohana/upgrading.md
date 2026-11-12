# Upgrading to 4.0

Version 4.0.0 ("renatus") runs on PHP 8.4 and later. This page lists what
an application written for Kohana 3.3 or Koseven has to change, in the
order to do it. Most applications only need the first three sections.

Renatus is a first name of Latin origin which means "born again"
("natus" = "born").

## 1. Requirements

- PHP 8.4 or later.
- The `mbstring` and `intl` extensions are required.
- Removed, because PHP no longer has them: the Mcrypt encryption engine
  (use OpenSSL or Sodium) and the Wincache cache driver.

## 2. Class names

The framework's classes are named `Kohana_*` again, and the core class is
`Kohana`.

- **Coming from Kohana 3.3**: nothing to rename.
- **Coming from Koseven** (`KO7`, `KO7_Arr`, ...): either rename, or enable
  the compatibility module, which makes the old names aliases of the new
  ones:

        Kohana::modules([
            'ko7' => MODPATH.'ko7',   // or 'ko9' => MODPATH.'ko9'
            // ...
        ]);

  The aliases exist once `Kohana::modules()` has run. `index.php` and the
  top of `bootstrap.php` run before that and must use the new names:
  `Kohana`, `KOHANA_START_TIME`, `KOHANA_START_MEMORY`. See the README of
  the module.

## 3. Types: update the classes you extend

Every method, property and constant of the framework now declares its
types. PHP refuses a child class whose declaration does not fit its
parent, so classes of yours that extend or override the framework need the
same types. The error message names the exact line to change.

The cases almost every application has:

    // Controllers
    public function before(): void
    public function after(): void
    public string|View $template = 'template';   // Controller_Template

    // Models (ORM)
    protected ?string $_table_name = 'users';
    protected array $_has_many = [...];
    protected array $_belongs_to = [...];
    public function rules(): array
    public function filters(): array
    public function labels(): array

To find all of them at once, load every class of the application:

    php docker/load-all-classes.php

Other things that follow from the types:

- Nothing is typed more loosely than before on purpose, but **values that
  come from a visitor are typed `mixed`** and checked inside. Validation
  rules (`Valid::*`) fail anything that is not text or a number; an array
  can never pass a text rule.
- Text helpers (`Text::*`, `UTF8::*`, `HTML::chars()`) accept `null` as an
  empty string, so nullable database columns can be passed directly.
- `Config::load()` takes a string. `Log::add()` takes the level as an
  integer (`Log::ERROR`).
- `true`, `false` and `null` are written in lowercase everywhere; your code
  may keep uppercase, PHP accepts both.

## 4. Data stored by an old version

These are thrown away and created again on first use. Nothing to do,
but expect visitors to be logged out once.

- **Cookies** signed by an old version (SHA-1) are rejected and deleted.
  Signatures are now HMAC-SHA-256.
- **Sessions** that cannot be read are destroyed and a new one starts.
- **Cache entries**: keys and file names are derived with SHA-256, so old
  entries are not found any more. Empty the cache directory.
- **Auth "remember me" tokens** get a new value each time they are used.

Two things need a change in the database:

- **Auth tables**: the `user_agent` column of `user_tokens` must hold 64
  characters (it holds a SHA-256 value now). The migration that
  `./minion auth:migration` writes shows the tables as they should be.
- **Passwords** need no migration. `Auth::hash()` uses PHP's
  `password_hash()`. An old HMAC hash still logs in, and is replaced by a
  new hash at that login. Widen the `password` column of `users` to 255
  characters, so that it also holds the hashes of future algorithms.
  If your code compares hashes itself, use `Auth::verify()`.

## 5. Behaviour that changed

### SQLite follows foreign keys

Every SQLite connection now runs `PRAGMA foreign_keys = ON`, so
`REFERENCES ... ON DELETE CASCADE` and the like do what the table says,
as on MySQL and PostgreSQL. Before, SQLite ignored them. If your tables
declare foreign keys, a delete or insert that passed in silence can now
cascade or fail. To get the old behaviour back for a connection, run
`$db->query(null, 'PRAGMA foreign_keys = OFF')` after connecting.

### Query builder

- `reset()` of the select, insert, update and delete builders, and of a
  join, works; each stopped with a type error.

### Encryption

- A configured engine class that is not an encryption engine is refused
  with a `Kohana_Exception` that names it; it was a type error.

### Requests

- The curl client checks the certificate of every `https` server, and
  limits a request and its redirects to the allowed schemes inside curl
  as well. If you passed `CURLOPT_SSL_VERIFYPEER => false` in the
  options, call `$request->client()->verify(false)` instead; better,
  give it the authority of that server: `verify($file)`.
- A `HEAD` request with the curl client returns as soon as the headers
  are there. It waited for a body that never comes, until the time limit.
- `Request_Client_HTTP`, the client for the PECL `http` extension, is
  gone. Requests to other servers use curl, or PHP's streams when curl
  is not loaded.
- The protected `Request::_parse_accept()` is gone. Nothing called it;
  accept headers are read by `HTTP_Header`.
- `HTTP_Header::parse_cache_control()` never returns `false`.
- A REST request for a format that does not exist throws the
  `REST_Exception` that names it; it was a type error.
- `HTTP_Header::parse_accept_header()` without an Accept header returns
  "accept everything" (`*/*`), as documented; it returned an empty list.
- A REST request that announces JSON and has an empty body has no
  values; it stopped on an undefined variable.

### Database

- A connection that was closed with `disconnect()` opens again on the
  next query; it used to fail. `Database::reconnect()` is new.
- The settings of a connection stay in the object (they were removed
  after connecting). Dumps of the object do not show them.
- `Database_PDO::list_columns()` throws for a table that does not exist.

### Debugging

- `Debug::dump()` shows of an object what its `__debugInfo()` returns,
  when the class has that method, as `var_dump()` does.

### Profiler

- Benchmarks of the framework have new names. File lookups are in the
  group `find file`, named after the file (they were `kohana` /
  `find_file`); config loading is named after the group and validation
  after its fields. Code that reads these by name needs the new names.

### Minion

- A task started with a wrong option, and an unknown task, end with an
  error status; they used to end as a success.
- Errors are written to the error output (STDERR), in red.
- Colours are left out when the output is not a terminal. `color()` now
  also works on Windows 10 and later.
- The views `minion/help/list`, `minion/help/error` and
  `minion/error/validation` are gone; the texts are written by the tasks.

### Images

- `save()` and `render()` use quality 85 when none is given; it was 100.
  Pass 100 to get the old, much larger files.
- Images of more than 50 million pixels are refused. Raise
  `Image::$max_pixels`, or set it to 0, when you need larger ones.
- `Image::factory()` without a driver now uses `default_driver` of
  `config/image.php`; it ignored that setting before.
- The Imagick driver throws an `Image_Exception` when ImageMagick cannot
  write the format asked for. It used to write the original format under
  the new name. An unknown file extension is refused by both drivers.

### Cache

- `Kohana::cache()` uses the Cache module when its default group is in
  the config. It only did when that group had been used before in the
  same request, and wrote plain cache files otherwise.
- The file driver makes its cache directory when it is not there. It
  was meant to, but failed with "Unable to read from the cache
  directory".
- In the SQLite driver, `_` and `%` in the name of a tag are part of the
  name. `delete_tag('user_1')` also deleted entries tagged `userA1`.
- `Cache_Redis::find()` leaves out entries that have expired; it returned
  them with the value `false`.
- The GD driver applied each step to the original file again, so only the
  last step of a chain (and nothing at all when saving) had an effect.
  Chains now work; check code that relied on the old result.

### Controllers

- An `action_...` method that is not `public` gives a 404. It could be
  reached from the address before.

### REST controller

- A request with a method the controller does not map (`PATCH`, for
  example) is answered with 405 and runs nothing. It used to get the
  status 405 and still run the action named in the address.
- The default format works on servers whose file names tell capitals
  apart (Linux): `json` now finds `REST_Format_JSON`. A format may only
  hold letters and digits.
- The answer has a `Content-Type` also when the address names no format.
- A request body that is not valid JSON is answered with 400, not 500.
- Without a format in the address, a client that asks for XML or CSV in
  its `Accept` header gets it; before, only the address chose. The
  response then carries `Vary: Accept`.
- In XML, a key that cannot be the name of an element has the wrong
  characters replaced by `_`; it used to stop with an error.
- The protected `iterable_to_xml()` and `evaluate_error()` of
  `REST_Format_XML` are gone: the work is done by the static `encode()`.

### Logging

- A log message says the file and line of the code that logged it, and
  an error those of the place it was thrown. Both used to name the code
  one step further up the call stack.

### Sessions

- A `cookie` session over 4 KB is not sent. In development it throws a
  `Session_Exception` (as does any other failed session write); on other
  environments it is logged. Before, the browser dropped it in silence.

### Auth

- `force_login()` with a user that does not exist throws a
  `Kohana_Exception`. It used to start a session without a user. This
  holds for the file driver too: the name must be in the `users` list.
- `Auth_File::check_password()` accepts the password only. It also
  accepted the stored hash, so reading the config file was enough to
  pass it.
- The Bcrypt driver (`'driver' => 'Bcrypt'`) now extends `Auth_ORM` and
  only keeps its own hashing. This fixes `logged_in('admin')`, which
  ignored the role and answered `true` for every logged-in user, and
  `force_login()`, which did not count the login. If you extended
  `Auth_Bcrypt`, check the methods you override against `Auth_ORM`.
- The files `auth-schema-mysql.sql` and `auth-schema-postgresql.sql` of
  the ORM module are gone. A new site creates the auth tables with
  `./minion auth:migration` and `./minion migration:run`. A site that has
  the tables already needs nothing.

### Cookies

- `Cookie::$httponly` is `true` by default.
- `Cookie::$secure` is `null` by default: the cookie is secure whenever
  the request is HTTPS.
- New `Cookie::$samesite`, `'Lax'` by default. `'None'` forces secure.

### Base URL

`base_url` is `'/'` by default in `bootstrap.php`. Do not set it to `null`
or an empty string.

The `trusted_hosts` of `config/url.php` are compared with the host name
without its port: `'localhost'` also trusts `localhost:8000`. An entry
that names a port still trusts that port only.

### Client IP address

`Request::$client_ip` only trusts forwarding headers (`X-Forwarded-For`,
...) when the request comes from a proxy listed in
`Request::$trusted_proxies` (addresses or CIDR networks). Behind a load
balancer or CDN, list its addresses there; otherwise you get the proxy's
address.

### Uploads

- `Upload::save()` returns `false` for a file name you pass that leads
  out of the directory: one with `..`, one that starts at the root of
  the disk, or one with a control character. A sub directory in the name
  is still fine. This matters when the name is the one a visitor sent.

`Upload::save()` refuses files a web server could run (`.php`, `.phtml`,
`.phar`, `.htaccess`, `.cgi`, ...). Every extension of the name is
checked. To allow one, remove it from `Upload::$forbidden_extensions`.

### Database

- **Prepared statements are on.** Values are sent to the database apart
  from the SQL. The query builder API is the same. If a query of yours
  cannot be prepared, set `'prepared' => false` for that connection in
  `config/database.php`.
- **Operators are checked.** `where()`, `having()` and `on()` only accept
  the operators in `Database_Query_Builder::$operators`. Add yours to that
  list if you use an unusual one.
- A failed MySQLi query always throws `Database_Exception`.
- The `as_object` argument of `Database::query()` accepts a class name
  again.

### External requests

- Only `http` and `https` addresses are requested. Allow others for the
  whole application with `Request_Client_External::$allowed_schemes`, or
  for one request with the `allowed_schemes` client parameter.
- Requests time out after 10 seconds to connect and 30 seconds in total
  (`Request_Client_External::$connect_timeout`, `::$timeout`).
- On a redirect to another host, headers such as `Authorization` are not
  sent along.
- The browser name that requests carry is `Mozilla/5.0 (compatible;
  Kohana v4.0.0)`; it named Koseven and its site before. Set your own in
  `config/curl.php`.

### Files

- `Kohana::find_file()` refuses names with `..` or NUL bytes.
- Log files start with a line that stops PHP when the file is requested
  directly. Log directories are created with mode 2775, files with 0664
  (`Log_File::$directory_mode`, `Log_File::$file_mode`).

### Views

An error inside a view is no longer wrapped in a `View_Exception`: the
original error is thrown, with the file and line of the template. Code
that catches `View_Exception` around `render()` to detect any failure
should catch `Throwable`. A missing view file still throws
`View_Exception`.

### ORM

- `count_all()` leaves `order_by()` and the columns of joined relations
  out of its query. On PostgreSQL it failed with either of them: a sorted
  query could not be counted, nor a model with `$_load_with`. Both are
  put back for a `find_all()` that follows after `reset(false)`.
- The Guid behavior gives a record its id once. It used to write a new
  one at every save, unless `verify` was on.
- The Slug and Guid behaviors look for used values in the database of
  the model. They asked the default connection, which is the wrong one
  for a model with its own `$_db_group`. The Slug behavior now also sees
  that a slug is taken when the record is new.
- `ORM::db()` returns the database of a model.

### Config kept in the database

`Config_Database_Reader` reads values and lists only: an object stored
in the table is not created. If you kept objects there, store their
data.

### Downloads

- `Response::send_file()` only follows a `Range` header of the client
  when the download is `resumable`. Without that option the whole file
  is sent; before, a part was sent as if it were the whole file.
- A range for "the last N bytes" with N larger than the file sends the
  whole file.

### Response headers

Every response gets `X-Content-Type-Options: nosniff`, a `Referrer-Policy`
and `X-Frame-Options`. Change or remove them in `HTTP::$security_headers`.
If your pages are shown inside frames on other sites, remove
`x-frame-options`.

### Stricter types and clearer failures

Found by static analysis (PHPStan levels 7 to 10). Each of these used to end in
a PHP error; a class of yours is only affected when it redeclares one of
the properties or methods named here.

- `Debug::trace()` declares `array` as its return type (was
  `string|array`; it never returned a string).
- `ORM::$_db` is declared `?Database` and `Session::$_encrypted`
  `string|false`. A class that redeclares one of them must use the same
  type. The database group of a model is set in `$_db_group`, as before.
- `Kohana::sanitize()` cleans the public properties of an object. It used
  to stop with an error for any object that is not used as an array.
- A validation rule that cannot be called (a misspelled function or
  method) throws a `Kohana_Exception` that names the rule.
- `Feed::create()` throws a `Kohana_Exception` for an encoding that does
  not exist.
- `File::split()` and `File::join()` throw a `Kohana_Exception` when a
  piece cannot be opened.
- The GD image driver throws an `Image_Exception` when GD cannot read the
  file or cannot make the new image.
- A model column that is stored as JSON throws a `JsonException` for a
  value that cannot be written as JSON.
- `Profiler::stats()` of an empty list gives zeros (it divided by zero).
- These properties are no longer nullable, they have no value until
  they are set: `Image_Imagick::$im`, `Cache_Redis::$_redis`,
  `Cache_Memcached::$memcached_instance`, `Cache_File::$_cache_dir`,
  `Pagination::$_request`, and `$media` and `$guide` of
  `Controller_Userguide`.
- `on()` and `using()` of a select query throw a `Kohana_Exception` when
  no `join()` came first.
- `new Pagination` without a request (in a task, for example) throws a
  `Kohana_Exception`, as does making a link without a route.
- `HTTP_Header::send_headers()` without a response throws a
  `Kohana_Exception`.
- `Request::post_max_size_exceeded()` is false when there is no request.
- `Image::resize()` with `Image::NONE` and no sizes keeps the size of the
  image (it made an image of 1 pixel).
- A session whose data cannot be encrypted throws a `Session_Exception`.
- `HTTP_Header::accepts_language_at_quality()` works for a browser that
  sends `Accept-Language: *` (it ended in a type error).
- The database drivers have `_pdo()` and `_mysqli()`, which connect when
  needed and return the connection; a driver of your own that extends
  them can use these in place of `$this->_connection`.
- `Auth` reads its settings through `_setting()`, the cache drivers their
  default lifetime through `_default_expire()`.
- The behaviours `ORM_Behavior_Guid` and `ORM_Behavior_Slug` only look a
  record up by GUID or slug when the id is a text.
- The key of the HTTP cache (`HTTP_Cache::basic_cache_key_generator()`)
  is made differently, so that a header with several values has a place
  in it: responses cached by an older version are not found once.
- `Request::body()`, `query()`, `post()`, `cookie()` and `headers()` (and
  the same of `Response`) document what they return for each way of
  calling them; code analysed with PHPStan sees these types now.
- A helper whose regular expression fails (text that is not valid UTF-8,
  for example) returns an empty text. `Text`, `Inflector`, `URL::title()`
  and the others used to pass `null` on, which ended in a PHP error.

## 6. Errors

- **Production** (`Kohana::$environment = Kohana::PRODUCTION`): a visitor
  only sees a general message and a reference code, in HTML, JSON and
  plain text alike. The full error is in the log, on a line that starts
  with `[REFERENCE]`. Change the page in `views/kohana/error_production.php`.
  "Please try again later" is only said for errors of the server (500
  and above), not for a page that was not found.
- **Development**: a new error page (`views/kohana/error.php`). A custom
  error view of yours receives `$error` (see `Kohana_Exception::describe()`),
  `$reference` and `$status`; the old variables `$class`, `$code`,
  `$message`, `$file`, `$line` and `$trace` are still passed.
- **Deprecations do not stop the request.** They are logged, and while
  developing they are listed in a panel at the bottom of the page.
  `Kohana::$soft_errors` holds the error levels treated this way.
- Values named like a secret (`password`, `token`, ...) are shown as
  `***` in dumps. The list is `Debug::$sensitive`.
- Optional links that open a file in your editor: `Debug::$editor`.
- The error pages, the profiler and the guide take their colours from
  `config/theme.php`, light or dark. A copy of `views/kohana/debug.css`
  or `views/kohana/error_production.php` in your application keeps
  working with the colours it has, but does not follow the theme.

## 7. Tests

The tests run on PHPUnit 13. If your application has tests built on the
unittest module:

- Docblock annotations (`@dataProvider`, `@group`, `@depends`) are ignored
  by PHPUnit; use attributes (`#[DataProvider('name')]`, `#[Group('x')]`).
- Data providers must be `public static`.
- `modules/unittest/tests.php`, `Unittest_TestSuite` and
  `config/unittest.php` are gone. PHPUnit finds the tests from the
  directories in `phpunit.xml`.
- Removed by PHPUnit: `$this->at()`, `->will($this->returnValue())` (use
  `->willReturn()`), `setMethods()` (use `onlyMethods()`).
  `getMockForAbstractClass()` is kept by `Unittest_TestCase`.

See the README of the unittest module.

## 8. The guide and Codebench

- **The user guide** is off in production (`userguide.enabled`). Its Markdown parser is new and does not pass HTML through; pages of your own guide that used HTML, definition lists or `{{view}}` includes need rewriting. Links are now relative to the file of the page. Disqus comments are gone. See the [Userguide](../userguide/writing) guide.
- **Minion tasks** declare `protected array $_options` and `protected function _execute(array $params): mixed`.
- **Codebench** benchmarks declare `public string $description`, `public int $loops`, `public array $subjects`, and methods named `bench_...(mixed $subject): mixed`. The module is off in production, and only runs classes from `classes/Bench/`.

## 9. New in 4.0

Nothing here needs a change; these are available to use.

| Feature | Where |
|---|---|
| JSON request body and response | `Request::json()`, `Response::json()` |
| Transactions with automatic commit and rollback | `Database::transaction()` |
| Validation rules `uuid`, `json`, `boolean`, `in`, `date_format` | `Valid` |
| Rate limiting | `Throttle` |
| Cross-origin requests | `CORS`, `config/cors.php` |
| Content-Security-Policy nonce | `Security::nonce()` |
| CSRF hidden field | `Form::csrf()` |
| Password hashing helpers | `Auth::verify()`, `Auth::needs_rehash()` |
| Cookie helpers | `Cookie::has()`, `Cookie::options()` |
| ORM on SQLite, PostgreSQL and MySQL through PDO | `Database_PDO::list_tables()`, `list_columns()` |
| Photos turned upright by their EXIF note | `Image::auto_orient()` |
| AVIF images, HEIC photos (Imagick), thumbnails, images from data | `Image::thumbnail()`, `Image::from_string()`, `Image::is_heif()` |
| A bar that shows what the request did, in development | the `ub_debug` module, one of the extra modules (not part of the core) |
| Files on the disk, on an FTP server or in S3, behind the same methods | the `ub_storage` module, one of the extra modules (not part of the core) |
| Logs as one JSON object on each line, with a code for each request | `Log_JSON` |
| A search through the logs and the most frequent errors, from the command line and on a page (in production for listed addresses only), with an optional index | the `ub_log_reader` module, one of the extra modules (not part of the core) |
| An address checked against a list of addresses and networks | `Request::ip_in_list()` |
| Settings saved in the database, changed while the application runs | the `ub_settings` module, one of the extra modules (not part of the core) |
| Libraries of others loaded without Composer: PHPMailer, HTML Purifier, TCPDF, ... | the `ub_autoload` module, one of the extra modules (not part of the core) |
| A complete model example: tables, models, controller, form and test | the tutorial "A Model from Start to End" |
| Themes for the framework's own pages (default, Catppuccin, Dracula, Nord, Solarized, Gruvbox, Tokyo Night, One), each light and dark, with a button, your own colours, themes and fonts | `Theme`, `config/theme.php` |
| Modules for development that `./minion check` warns about | `config/check.php` |
| Cache hits and misses of the request | `Cache::$stats`, `Cache::$reads` |
| More in the profile: classes loaded, routes tried, controller steps, session time, rows per query, a note on each benchmark | `Profiler::note()` |
| Slow requests in the log; timing of views; data of your own | `Profiler::$slow_request`, `Profiler::add()`, `Profiler::summary()`, `Profiler::marks()` |
| Command line: messages, tables, progress bar, `confirm()`, `--no-interaction`, `./minion` | `Minion_CLI` |
| A check of the installation: PHP, extensions, directories, database, cache, keys | `./minion check` |
| Database migrations | the new `migration` module |
| An empty database again, and data to develop with | `./minion migration:fresh`, `./minion migration:seed` |
| The auth tables as a migration, on MySQL, PostgreSQL and SQLite | `./minion auth:migration` |
| Requests to other servers: logins, retries, uploads, downloads to a file, several at once, a check against internal addresses | `$request->client()->bearer()`, `retry()`, `upload()`, `body_from()`, `save_to()`, `only_public_addresses()`, `Request_Client_Curl::all()` |
| CSV as a format of REST answers, and the format chosen by the `Accept` header of the request | `REST_Format_CSV`, `REST_Format::$accept` |
| CSV and XML responses in one line | `Response::csv()`, `Response::xml()` |
| Zip, tar and tar.gz archives, unpacked safely; gzip for single files | `Archive::create()`, `contents()`, `extract()`, `gzip()`, `gunzip()` |
| The kind of database behind a connection | `Database::driver()` |
| The database of a model | `ORM::db()` |
| Array helpers | `Arr::only()`, `Arr::except()`, `Arr::has()`, `Arr::delete_path()` |
| HTML5 form fields | `Form::email()`, `number()`, `date()`, `time()`, `tel()`, `search()`, `color()`, `range()`, `url_field()` |
| JSON that throws on bad data | `Json::encode()`, `Json::decode()` |
| Measure a function in one line | `Profiler::measure()` |
| Single-use CSRF token | `Security::check_and_rotate()` |
| Small tests | `Route::has()`, `I18n::has()`, `Fragment::has()`, `Fragment::discard()` |
| Current data | mime types (webp, avif, heic, woff2), card number ranges, browsers, plural words |
| Code style, static analysis and CI configuration | `phpcs.xml`, `phpstan.neon`, `.github/workflows/ci.yml` |

## Coming from Kohana 3.3

Besides everything above, these changes were made by Koseven before 4.0:

- **Encryption is a module.** Enable it in the bootstrap:
  `'encrypt' => MODPATH.'encrypt'`.
- **The MySQL driver is removed.** Set `'type' => 'MySQLi'` in
  `config/database.php`.
- **The APC and Memcache cache drivers are removed.** Use APCu and
  Memcached.
- **Pagination** is included as a module.
