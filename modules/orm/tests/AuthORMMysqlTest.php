<?php

use PHPUnit\Framework\Attributes\Group;

require_once __DIR__.'/AuthORMTest.php';

/**
 * Runs the tests of the ORM auth driver, and of the migration that makes
 * its tables, on MySQL/MariaDB, through the MySQLi driver.
 *
 * Runs when the environment variable KOHANA_TEST_MYSQL_HOST is set.
 *
 * @package    Kohana/ORM
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.orm')]
#[Group('kohana.auth')]
class Kohana_AuthORMMysqlTest extends Kohana_AuthORMTest
{
	protected function config(): ?array
	{
		return static::settings();
	}

	/**
	 * The settings of the test database, also used by AuthBcryptMysqlTest.
	 */
	public static function settings(): ?array
	{
		if ( ! getenv('KOHANA_TEST_MYSQL_HOST'))
			return null;

		return [
			'type'       => 'MySQLi',
			'connection' => [
				'hostname' => getenv('KOHANA_TEST_MYSQL_HOST'),
				'port'     => getenv('KOHANA_TEST_MYSQL_PORT') ?: 3306,
				'username' => getenv('KOHANA_TEST_MYSQL_USER') ?: 'root',
				'password' => (string) getenv('KOHANA_TEST_MYSQL_PASSWORD'),
				'database' => getenv('KOHANA_TEST_MYSQL_DATABASE') ?: 'kohana_test',
			],
			'charset'      => 'utf8mb4',
			'table_prefix' => '',
		];
	}
}
