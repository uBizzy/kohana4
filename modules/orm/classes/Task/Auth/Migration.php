<?php
/**
 * Writes the migration that creates the tables of the ORM auth driver
 * (users, roles, roles_users, user_tokens) in application/migrations.
 *
 *     ./minion auth:migration
 *     ./minion migration:run
 *
 * The file is a copy of modules/orm/auth/create_auth_tables.php with the
 * date of today in its name. It belongs to your application from then on:
 * change it before you run it, when your users need more columns.
 *
 * Needs the migration module.
 *
 * @package    Kohana/ORM
 * @category   Tasks
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Task_Auth_Migration extends Minion_Task {

	/**
	 * @var  string  What the migration is called, after the date and time
	 */
	public const string NAME = 'create_auth_tables';

	/**
	 * @param   array<int|string, mixed>  $params  options of the command line
	 * @return  mixed
	 * @throws  Kohana_Exception  when the migration module is off, or the migration is already there
	 */
	protected function _execute(array $params): mixed
	{
		if ( ! class_exists('Migration_Runner'))
			throw new Kohana_Exception('Enable the migration module first: the auth tables are created by a migration');

		$runner = new Migration_Runner;

		foreach (array_keys($runner->all()) as $name)
		{
			// Written twice, the second one would fail on tables that exist
			if (str_ends_with($name, '_'.static::NAME))
				throw new Kohana_Exception('The migration :name is already there', [':name' => $name]);
		}

		$file = $runner->create(static::NAME, null, $this->_content());

		Minion_CLI::success('Created '.Debug::path($file));
		Minion_CLI::write('Apply it with: ./minion migration:run');

		return null;
	}

	/**
	 * Returns the PHP code of the migration, as shipped with the module.
	 *
	 * @return  string
	 * @throws  Kohana_Exception  when the file cannot be read
	 */
	protected function _content(): string
	{
		$file    = Kohana::find_file('auth', static::NAME);
		$content = is_string($file) ? file_get_contents($file) : false;

		if ($content === false)
			throw new Kohana_Exception('The file auth/:name.php of the ORM module was not found', [':name' => static::NAME]);

		return $content;
	}

}
