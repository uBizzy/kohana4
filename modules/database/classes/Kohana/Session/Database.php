<?php
/**
 * Database-based session class.
 *
 * Sample schema:
 *
 *     CREATE TABLE  `sessions` (
 *         `session_id` VARCHAR( 24 ) NOT NULL,
 *         `last_active` INT UNSIGNED NOT NULL,
 *         `contents` TEXT NOT NULL,
 *         PRIMARY KEY ( `session_id` ),
 *         INDEX ( `last_active` )
 *     ) ENGINE = MYISAM ;
 *
 * @package    Kohana/Database
 * @category   Session
 * 
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Session_Database extends Session {

	// Database instance
	protected Database $_db;

	// Database table name
	protected string $_table = 'sessions';

	/**
	 * @var  array<string, string>  Database column names
	 */
	protected array $_columns = [
		'session_id'  => 'session_id',
		'last_active' => 'last_active',
		'contents'    => 'contents'
	];

	// Garbage collection requests
	protected int $_gc = 500;

	// The current session id
	protected ?string $_session_id = null;

	// The old session id
	protected ?string $_update_id = null;

	/**
	 * @param   array{group?: string, table?: string, gc?: int, columns?: array<string, string>}|null  $config  besides these, the settings of every session
	 * @param   string|null  $id  session id
	 */
	public function __construct(?array $config = null, ?string $id = null)
	{
		if ( ! isset($config['group']))
		{
			// Use the default group
			$config['group'] = Database::$default;
		}

		// Load the database
		$this->_db = Database::instance($config['group']);

		if (isset($config['table']))
		{
			// Set the table name
			$this->_table = (string) $config['table'];
		}

		if (isset($config['gc']))
		{
			// Set the gc chance
			$this->_gc = (int) $config['gc'];
		}

		if (isset($config['columns']))
		{
			// Overload column names
			$this->_columns = $config['columns'];
		}

		parent::__construct($config, $id);

		if (random_int(0, $this->_gc) === $this->_gc)
		{
			// Run garbage collection
			// This will average out to run once every X requests
			$this->_gc();
		}
	}

	public function id(): ?string
	{
		return $this->_session_id;
	}

	protected function _read(?string $id = null): ?string
	{
		$id = $id ?: Cookie::get($this->_name);

		if (is_string($id) AND $id !== '')
		{
			$query = DB::select([$this->_columns['contents'], 'contents'])
				->from($this->_table)
				->where($this->_columns['session_id'], '=', ':id')
				->limit(1)
				->param(':id', $id);

			if ($this->_lifetime)
			{
				// An expired session is never loaded, even if garbage
				// collection has not removed its row yet
				$query->where($this->_columns['last_active'], '>=', time() - $this->_lifetime);
			}

			$result = $query->execute($this->_db);

			if ($result->count())
			{
				// Set the current session id
				$this->_session_id = $this->_update_id = $id;

				// Return the contents
				$contents = $result->get('contents');

				return is_string($contents) ? $contents : null;
			}
		}

		// Create a new session id
		$this->_regenerate();

		return null;
	}

	protected function _regenerate(): ?string
	{
		// Create the query to find an ID
		$query = DB::select($this->_columns['session_id'])
			->from($this->_table)
			->where($this->_columns['session_id'], '=', ':id')
			->limit(1)
			->bind(':id', $id);

		do
		{
			// Create a new session id
			// 18 random bytes = 144 bits, encoded as 24 URL-safe characters
			$id = strtr(base64_encode(random_bytes(18)), '+/', '-_');

			// Get the the id from the database
			$result = $query->execute($this->_db);
		}
		while ($result->count());

		return $this->_session_id = $id;
	}

	protected function _write(): bool
	{
		if ($this->_update_id === null)
		{
			// Insert a new row
			$query = DB::insert($this->_table, $this->_columns)
				->values([':new_id', ':active', ':contents']);
		}
		else
		{
			// Update the row
			$query = DB::update($this->_table)
				->value($this->_columns['last_active'], ':active')
				->value($this->_columns['contents'], ':contents')
				->where($this->_columns['session_id'], '=', ':old_id');

			if ($this->_update_id !== $this->_session_id)
			{
				// Also update the session id
				$query->value($this->_columns['session_id'], ':new_id');
			}
		}

		$query
			->param(':new_id',   $this->_session_id)
			->param(':old_id',   $this->_update_id)
			->param(':active',   $this->_data['last_active'])
			->param(':contents', $this->__toString());

		// Execute the query
		$query->execute($this->_db);

		// The update and the session id are now the same
		$this->_update_id = $this->_session_id;

		// Update the cookie with the new session id
		Cookie::set($this->_name, (string) $this->_session_id, $this->_lifetime);

		return true;
	}

	/**
	 * @return  bool
	 */
	protected function _restart(): bool
	{
		$this->_regenerate();

		return true;
	}

	protected function _destroy(): bool
	{
		if ($this->_update_id === null)
		{
			// Session has not been created yet
			return true;
		}

		// Delete the current session
		$query = DB::delete($this->_table)
			->where($this->_columns['session_id'], '=', ':id')
			->param(':id', $this->_update_id);

		try
		{
			// Execute the query
			$query->execute($this->_db);

			// Delete the old session id
			$this->_update_id = null;

			// Delete the cookie
			Cookie::delete($this->_name);
		}
		catch (Exception $e)
		{
			// An error occurred, the session has not been deleted
			return false;
		}

		return true;
	}

	protected function _gc(): void
	{
		if ($this->_lifetime)
		{
			// Expire sessions when their lifetime is up
			$expires = $this->_lifetime;
		}
		else
		{
			// Expire sessions after one month
			$expires = Date::MONTH;
		}

		// Delete all sessions that have expired
		DB::delete($this->_table)
			->where($this->_columns['last_active'], '<', ':time')
			->param(':time', time() - $expires)
			->execute($this->_db);
	}

} // End Session_Database
