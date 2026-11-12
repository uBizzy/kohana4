<?php
/**
 * Applies and undoes the migrations of one database.
 *
 *     $runner = new Migration_Runner;                      // the default database
 *     $runner = new Migration_Runner(Database::instance('reports'));
 *
 *     $runner->pending();    // names of the migrations not applied yet
 *     $runner->run();        // applies them, oldest first
 *     $runner->rollback();   // undoes the newest applied one
 *     $runner->fresh();      // drops all tables and applies everything again
 *     $runner->seed();       // fills the tables with the data of the seed files
 *
 * Migrations are found in the "migrations" directory of the application
 * and of every module, seed files in the "seeds" directories. The names of
 * the applied migrations are kept in a table of the database itself
 * ("migrations" by default, see config/migration.php).
 *
 * @package    Kohana/Migration
 * @category   Base
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Migration_Runner {

	/**
	 * @var  string  What a migration name looks like: date, time, description
	 */
	public const string NAME = '/^\d{8}_\d{6}_[a-z0-9_]+$/';

	/**
	 * @var  Database  The database the migrations are for
	 */
	protected Database $_db;

	/**
	 * @var  string  Name of the table that remembers the applied migrations
	 */
	protected string $_table;

	/**
	 * @var  bool  Was the table checked (and created) in this request?
	 */
	protected bool $_table_ready = false;

	/**
	 * Returns a runner for a database connection, by its name.
	 *
	 *     $runner = Migration_Runner::factory('reports');
	 *
	 * @param   string|null  $db  name of the connection in config/database.php, the default one when null
	 * @return  Migration_Runner
	 */
	public static function factory(?string $db = null): Migration_Runner
	{
		return new Migration_Runner(Database::instance($db));
	}

	/**
	 * @param   Database|null  $db  database to work on, the default one when null
	 */
	public function __construct(?Database $db = null)
	{
		$this->_db    = $db ?? Database::instance();
		$table = Kohana::$config->load('migration.table');

		$this->_table = (is_string($table) AND $table !== '') ? $table : 'migrations';
	}

	/**
	 * Returns every migration there is, oldest first.
	 *
	 *     // ['20261111_143000_create_articles' => '/path/to/the/file.php', ...]
	 *     $migrations = $runner->all();
	 *
	 * @return  array<string, string>  file paths by migration name
	 * @throws  Kohana_Exception  when a file has a name that is not a migration name
	 */
	public function all(): array
	{
		$migrations = [];

		foreach ($this->_files() as $file)
		{
			$name = basename($file, EXT);

			if ( ! preg_match(static::NAME, $name))
				throw new Kohana_Exception('The migration :file must be named like "20261231_235959_what_it_does.php"',
					[':file' => Debug::path($file)]);

			$migrations[$name] = $file;
		}

		// The names start with date and time, so this is the order they were made in
		ksort($migrations);

		return $migrations;
	}

	/**
	 * Returns the names of the migrations that were applied to the
	 * database, oldest first.
	 *
	 * @return  array<int, string>  list of migration names
	 */
	public function applied(): array
	{
		$this->_prepare_table();

		/** @var array<int, string> $names */
		$names = DB::select('name')
			->from($this->_table)
			->order_by('name')
			->execute($this->_db)
			->as_array(null, 'name');

		return $names;
	}

	/**
	 * Returns the migrations that were not applied to the database yet,
	 * oldest first.
	 *
	 * @return  array<string, string>  file paths by migration name
	 */
	public function pending(): array
	{
		return array_diff_key($this->all(), array_flip($this->applied()));
	}

	/**
	 * Applies every pending migration, oldest first. Stops at the first
	 * one that fails; the ones before it stay applied.
	 *
	 *     foreach ($runner->run() as $name) echo 'Applied '.$name.PHP_EOL;
	 *
	 * @param   callable|null  $each  called with the name after each migration, to report progress
	 * @return  array<int, string>  names of the migrations that were applied
	 * @throws  Kohana_Exception  when a migration fails
	 */
	public function run(?callable $each = null): array
	{
		$done = [];

		foreach ($this->pending() as $name => $file)
		{
			$this->_change($name, $file, true);

			$done[] = $name;

			$each and $each($name);
		}

		return $done;
	}

	/**
	 * Undoes applied migrations, newest first. Stops at the first one that
	 * fails or that cannot be undone; the ones before it stay undone.
	 *
	 *     $runner->rollback();     // the newest one
	 *     $runner->rollback(3);    // the newest three
	 *
	 * @param   int            $steps  how many migrations to undo
	 * @param   callable|null  $each   called with the name after each migration, to report progress
	 * @return  array<int, string>  names of the migrations that were undone
	 * @throws  Kohana_Exception  when a migration fails, cannot be undone or its file is gone
	 */
	public function rollback(int $steps = 1, ?callable $each = null): array
	{
		$all  = $this->all();
		$done = [];

		// The newest applied ones, newest first
		foreach (array_slice(array_reverse($this->applied()), 0, max(0, $steps)) as $name)
		{
			if ( ! isset($all[$name]))
				throw new Kohana_Exception('The migration :name is applied, but its file was not found', [':name' => $name]);

			$this->_change($name, $all[$name], false);

			$done[] = $name;

			$each and $each($name);
		}

		return $done;
	}

	/**
	 * Drops every table of the database and applies all migrations again:
	 * an empty, up-to-date database for development and tests.
	 *
	 * [!!] All data is lost. This never runs in production.
	 *
	 * Only tables whose names start with the table prefix of the connection
	 * are dropped, so applications that share a database by prefix do not
	 * lose each other's tables. Without a prefix, all tables go.
	 *
	 * @param   callable|null  $each  called with the name after each migration, to report progress
	 * @return  array<int, string>  names of the migrations that were applied
	 * @throws  Kohana_Exception  in production, on a database that is not MySQL, PostgreSQL or SQLite, or when a migration fails
	 */
	public function fresh(?callable $each = null): array
	{
		if (Kohana::$environment === Kohana::PRODUCTION)
			throw new Kohana_Exception('The tables of a production database are never dropped');

		$this->_drop_tables();

		// The table of applied migrations is gone too
		$this->_table_ready = false;

		return $this->run($each);
	}

	/**
	 * Returns every seed file there is, in the order of their names.
	 *
	 *     // ['users' => '/path/to/seeds/users.php', ...]
	 *     $seeds = $runner->seeds();
	 *
	 * @return  array<string, string>  file paths by seed name
	 */
	public function seeds(): array
	{
		$seeds = [];

		// Files in sub directories are not seeds: only the top level counts
		foreach (Kohana::list_files('seeds') as $file)
		{
			if (is_string($file) AND str_ends_with($file, EXT))
			{
				$seeds[basename($file, EXT)] = $file;
			}
		}

		ksort($seeds);

		return $seeds;
	}

	/**
	 * Fills the database with the data of the seed files: all of them, in
	 * the order of their names, or only the one that is named.
	 *
	 * A seed is a file in a "seeds" directory that returns a function:
	 *
	 *     // application/seeds/users.php
	 *     return function (Database $db): void
	 *     {
	 *         DB::insert('users', ['username', 'email'])
	 *             ->values(['ana', 'ana@example.com'])
	 *             ->execute($db);
	 *     };
	 *
	 * Each seed runs in a transaction: when it fails, nothing of it stays.
	 *
	 * @param   string|null    $name  name of the one seed to run, all of them when null
	 * @param   callable|null  $each  called with the name after each seed, to report progress
	 * @return  array<int, string>  names of the seeds that ran
	 * @throws  Kohana_Exception  when the named seed does not exist, a file returns no function, or a seed fails
	 */
	public function seed(?string $name = null, ?callable $each = null): array
	{
		$seeds = $this->seeds();

		if ($name !== null)
		{
			if ( ! isset($seeds[$name]))
				throw new Kohana_Exception('The seed :name was not found', [':name' => $name]);

			$seeds = [$name => $seeds[$name]];
		}

		foreach ($seeds as $seed => $file)
		{
			$this->_seed($seed, $file);

			$each and $each($seed);
		}

		return array_keys($seeds);
	}

	/**
	 * Writes the file of a new migration and returns its path. The
	 * migration is empty, unless its content is given: that is how a module
	 * hands a ready-made migration to the application.
	 *
	 *     // application/migrations/20261111_143000_create_articles.php
	 *     $file = $runner->create('create_articles');
	 *
	 * @param   string       $description  what the migration does: lower case letters, digits and "_"
	 * @param   string|null  $directory    where to write it, "migrations" of the application by default
	 * @param   string|null  $content      PHP code of the file, an empty migration by default
	 * @return  string  path of the new file
	 * @throws  Kohana_Exception  when the description is not valid or the file cannot be written
	 */
	public function create(string $description, ?string $directory = null, ?string $content = null): string
	{
		$name = date('Ymd_His').'_'.$description;

		if ( ! preg_match(static::NAME, $name))
			throw new Kohana_Exception('A migration is described with lower case letters, digits and "_" only, ":name" is not valid',
				[':name' => $description]);

		$directory = rtrim($directory ?? APPPATH.'migrations', '/\\');

		if ( ! is_dir($directory) AND ! @mkdir($directory, 0755, true) AND ! is_dir($directory))
			throw new Kohana_Exception('Unable to create the directory :dir', [':dir' => Debug::path($directory)]);

		$file = $directory.DIRECTORY_SEPARATOR.$name.EXT;

		// The "x" mode fails when the file is already there: nothing is overwritten
		$handle = @fopen($file, 'x');

		if ($handle === false OR fwrite($handle, $content ?? $this->_template()) === false)
			throw new Kohana_Exception('Unable to write the migration :file', [':file' => Debug::path($file)]);

		fclose($handle);

		return $file;
	}

	/**
	 * Applies or undoes one migration and updates the list of applied
	 * migrations, all in one transaction.
	 *
	 * [!!] MySQL ends a transaction by itself when a table is created,
	 * altered or dropped. A migration that fails after such a statement
	 * leaves it done.
	 *
	 * @param   string  $name  migration name
	 * @param   string  $file  path of its file
	 * @param   bool    $up    apply (true) or undo (false)
	 * @return  void
	 * @throws  Kohana_Exception  when the migration fails
	 */
	protected function _change(string $name, string $file, bool $up): void
	{
		$migration = Kohana::load($file);

		if ( ! $migration instanceof Migration)
			throw new Kohana_Exception('The migration :file must return an object that extends Migration',
				[':file' => Debug::path($file)]);

		$this->_prepare_table();

		try
		{
			$this->_db->transaction(function (Database $db) use ($migration, $name, $up)
			{
				if ($up)
				{
					$migration->up($db);

					DB::insert($this->_table, ['name', 'applied_at'])
						->values([$name, gmdate('Y-m-d H:i:s')])
						->execute($db);
				}
				else
				{
					$migration->down($db);

					DB::delete($this->_table)->where('name', '=', $name)->execute($db);
				}
			});
		}
		catch (Throwable $e)
		{
			// Say which migration it was; the original error is kept as the cause
			throw new Kohana_Exception('The migration :name could not be :action: :error', [
				':name'   => $name,
				':action' => $up ? 'applied' : 'undone',
				':error'  => $e->getMessage(),
			], 0, $e);
		}
	}

	/**
	 * Runs one seed file, in a transaction.
	 *
	 * @param   string  $name  seed name
	 * @param   string  $file  path of its file
	 * @return  void
	 * @throws  Kohana_Exception  when the file returns no function, or the seed fails
	 */
	protected function _seed(string $name, string $file): void
	{
		$seed = Kohana::load($file);

		if ( ! $seed instanceof Closure)
			throw new Kohana_Exception('The seed :file must return a function', [':file' => Debug::path($file)]);

		try
		{
			$this->_db->transaction(fn (Database $db) => $seed($db));
		}
		catch (Throwable $e)
		{
			// Say which seed it was; the original error is kept as the cause
			throw new Kohana_Exception('The seed :name failed: :error', [':name' => $name, ':error' => $e->getMessage()], 0, $e);
		}
	}

	/**
	 * Drops every table that carries the table prefix of the connection,
	 * also when tables point to each other.
	 *
	 * @return  void
	 * @throws  Kohana_Exception  on a database that is not MySQL, PostgreSQL or SQLite
	 */
	protected function _drop_tables(): void
	{
		// How this database is told not to mind the order of the drops:
		// a statement before and after, or a word after each DROP
		list($before, $after, $cascade) = match ($this->_db->driver()) {
			'mysql'  => ['SET FOREIGN_KEY_CHECKS = 0', 'SET FOREIGN_KEY_CHECKS = 1', ''],
			'sqlite' => ['PRAGMA foreign_keys = OFF', 'PRAGMA foreign_keys = ON', ''],
			'pgsql'  => [null, null, ' CASCADE'],
			default  => throw new Kohana_Exception('Tables can be dropped on MySQL, PostgreSQL and SQLite only'),
		};

		$prefix = $this->_db->table_prefix();

		$before and $this->_db->query(null, $before);

		try
		{
			foreach ($this->_db->list_tables() as $table)
			{
				// The names are complete ones: no prefix must be added again
				if (str_starts_with($table, $prefix))
				{
					$this->_db->query(null, 'DROP TABLE '.$this->_db->quote_identifier($table).$cascade);
				}
			}
		}
		finally
		{
			$after and $this->_db->query(null, $after);
		}
	}

	/**
	 * Returns the path of every migration file of the application and
	 * the modules.
	 *
	 * @return  array<int|string, string>  list of file paths
	 */
	protected function _files(): array
	{
		// Files in sub directories are not migrations: only the top level counts
		return array_filter(Kohana::list_files('migrations'), fn ($file) => is_string($file) AND str_ends_with($file, EXT));
	}

	/**
	 * Creates the table that remembers the applied migrations, when it is
	 * not there yet. The SQL is plain enough for every database.
	 *
	 * @return  void
	 */
	protected function _prepare_table(): void
	{
		if ($this->_table_ready)
			return;

		$this->_db->query(null, 'CREATE TABLE IF NOT EXISTS '.$this->_db->quote_table($this->_table)
			.' (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at VARCHAR(19) NOT NULL)');

		$this->_table_ready = true;
	}

	/**
	 * Returns the content of a new migration file.
	 *
	 * @return  string
	 */
	protected function _template(): string
	{
		return <<<'PHP'
			<?php

			return new class extends Migration {

				/**
				 * Makes the change.
				 */
				public function up(Database $db): void
				{
					// $db->query(null, 'CREATE TABLE ...');
				}

				/**
				 * Undoes the change. Remove this method when there is no way back.
				 */
				public function down(Database $db): void
				{
					// $db->query(null, 'DROP TABLE ...');
				}

			};

			PHP;
	}

}
