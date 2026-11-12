<?php
/**
 * Creates the tables of the ORM auth driver: users, roles, which user has
 * which role, and the "remember me" tokens. Adds the roles "login" and
 * "admin".
 *
 * Works on MySQL/MariaDB, PostgreSQL and SQLite. This file is yours:
 * add the columns your users need before you run it.
 */
return new class extends Migration {

	/**
	 * Makes the tables and the two roles.
	 */
	public function up(Database $db): void
	{
		// The column that numbers the rows, and a column that points to
		// one, are spelled differently by each database
		list($id, $ref) = match ($db->driver()) {
			'mysql'  => ['id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', 'INT UNSIGNED NOT NULL'],
			'pgsql'  => ['id SERIAL PRIMARY KEY', 'INTEGER NOT NULL'],
			'sqlite' => ['id INTEGER PRIMARY KEY AUTOINCREMENT', 'INTEGER NOT NULL'],
			default  => throw new Kohana_Exception('The auth tables can be created on MySQL, PostgreSQL and SQLite only'),
		};

		$users  = $db->quote_table('users');
		$roles  = $db->quote_table('roles');
		$tokens = $db->quote_table('user_tokens');
		$pivot  = $db->quote_table('roles_users');

		// Deleting a user or a role deletes what points to it
		$to_user = 'FOREIGN KEY (user_id) REFERENCES '.$users.' (id) ON DELETE CASCADE';
		$to_role = 'FOREIGN KEY (role_id) REFERENCES '.$roles.' (id) ON DELETE CASCADE';

		$db->query(null, 'CREATE TABLE '.$users.' ('
			.$id.', '
			.'email VARCHAR(254) NOT NULL UNIQUE, '
			.'username VARCHAR(32) NOT NULL UNIQUE, '
			.'password VARCHAR(255) NOT NULL, '
			.'logins INTEGER NOT NULL DEFAULT 0, '
			.'last_login INTEGER NULL)');

		$db->query(null, 'CREATE TABLE '.$roles.' ('
			.$id.', '
			.'name VARCHAR(32) NOT NULL UNIQUE, '
			.'description VARCHAR(255) NOT NULL)');

		$db->query(null, 'CREATE TABLE '.$pivot.' ('
			.'user_id '.$ref.', '
			.'role_id '.$ref.', '
			.'PRIMARY KEY (user_id, role_id), '
			.$to_user.', '
			.$to_role.')');

		$db->query(null, 'CREATE TABLE '.$tokens.' ('
			.$id.', '
			.'user_id '.$ref.', '
			.'user_agent VARCHAR(64) NOT NULL, '
			.'token VARCHAR(40) NOT NULL UNIQUE, '
			.'created INTEGER NOT NULL, '
			.'expires INTEGER NOT NULL, '
			.$to_user.')');

		// Index names are shared by all tables on PostgreSQL and SQLite, so
		// they carry the table prefix too
		$prefix = $db->table_prefix();

		$db->query(null, 'CREATE INDEX '.$prefix.'roles_users_role_id ON '.$pivot.' (role_id)');
		$db->query(null, 'CREATE INDEX '.$prefix.'user_tokens_user_id ON '.$tokens.' (user_id)');
		$db->query(null, 'CREATE INDEX '.$prefix.'user_tokens_expires ON '.$tokens.' (expires)');

		DB::insert('roles', ['name', 'description'])
			->values(['login', 'Login privileges, granted after account confirmation'])
			->values(['admin', 'Administrative user, has access to everything.'])
			->execute($db);
	}

	/**
	 * Removes the tables, with all users in them.
	 */
	public function down(Database $db): void
	{
		foreach (['user_tokens', 'roles_users', 'roles', 'users'] as $table)
		{
			$db->query(null, 'DROP TABLE '.$db->quote_table($table));
		}
	}

};
