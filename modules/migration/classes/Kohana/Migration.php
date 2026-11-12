<?php
/**
 * One change to the database, and the way to undo it.
 *
 * A migration is a file in a "migrations" directory that returns an object
 * of this class. The file name starts with the date and time it was made,
 * so migrations are applied in that order:
 *
 *     // application/migrations/20261111_143000_create_articles.php
 *     return new class extends Migration {
 *
 *         public function up(Database $db): void
 *         {
 *             $db->query(null, 'CREATE TABLE articles (id INT PRIMARY KEY, title VARCHAR(200))');
 *         }
 *
 *         public function down(Database $db): void
 *         {
 *             $db->query(null, 'DROP TABLE articles');
 *         }
 *     };
 *
 * Migrations are applied and undone by [Migration_Runner].
 *
 * @package    Kohana/Migration
 * @category   Base
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Migration {

	/**
	 * Makes the change.
	 *
	 * @param   Database  $db  connection to change
	 * @return  void
	 */
	abstract public function up(Database $db): void;

	/**
	 * Undoes the change made by [Migration::up]. A migration that does not
	 * define this method cannot be undone: a rollback stops there.
	 *
	 * @param   Database  $db  connection to change
	 * @return  void
	 * @throws  Kohana_Exception  when the migration has no way back
	 */
	public function down(Database $db): void
	{
		throw new Kohana_Exception('it has no down() method, so it cannot be undone');
	}

}
