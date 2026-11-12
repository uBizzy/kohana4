# To do

What is planned for Kohana itself: the core and the modules that come
with it. The extra modules, and ideas for new modules, have a list of
their own in their repository. Each new feature is discussed before it
is written.

## 1. To fix

- **Rolling development**: write down what a user can count on: when a
  breaking change may come, how it is announced, how long a PHP version
  stays supported. To be defined.
- **Extra modules**: add the link to their repository in `README.md`
  when it exists.

## 2. For the future

- **YAML output**, and reading YAML with the `yaml` extension.
- **More databases**: MS SQL first, then Oracle, then Firebird and DB2
  when somebody needs them. One small "dialect" for each inside the PDO
  driver (how it writes LIMIT, quotes names, lists tables and columns,
  numbers rows), not a driver each. MariaDB, MySQL, PostgreSQL and
  SQLite are supported and tested today.
- **Scheduled tasks.** One cron line runs `./minion schedule:run` every
  minute; the schedule is a file of the application, in version control.
  Needs: a way to write the times, a lock so a long task is not started
  twice, a log of what ran. Small.

## Smaller things, when there is time

- Break up the longest methods: 18 are over 80 lines (`Debug::_dump`,
  `Kodoc_Markdown::_blocks`, `Cache_File::_delete_file`, ...).
