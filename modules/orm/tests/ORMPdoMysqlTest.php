<?php

use PHPUnit\Framework\Attributes\Group;

require_once __DIR__.'/ORMDatabaseTest.php';

/**
 * Runs the ORM database tests on MySQL/MariaDB through the PDO driver.
 * Uses the same server settings as ORMDatabaseTest.
 *
 * @package    Kohana/ORM
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.orm')]
class Kohana_ORMPdoMysqlTest extends Kohana_ORMDatabaseTest
{
	protected function config(): ?array
	{
		if ( ! getenv('KOHANA_TEST_MYSQL_HOST') OR ! extension_loaded('pdo_mysql'))
			return null;

		return [
			'type'       => 'PDO',
			'connection' => [
				'dsn' => 'mysql:host='.getenv('KOHANA_TEST_MYSQL_HOST')
					.';port='.(getenv('KOHANA_TEST_MYSQL_PORT') ?: 3306)
					.';dbname='.(getenv('KOHANA_TEST_MYSQL_DATABASE') ?: 'kohana_test')
					.';charset=utf8mb4',
				'username' => getenv('KOHANA_TEST_MYSQL_USER') ?: 'root',
				'password' => (string) getenv('KOHANA_TEST_MYSQL_PASSWORD'),
			],
			'identifier'   => '`',
			'table_prefix' => '',
		];
	}
}
