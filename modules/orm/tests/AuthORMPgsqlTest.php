<?php

use PHPUnit\Framework\Attributes\Group;

require_once __DIR__.'/AuthORMTest.php';

/**
 * Runs the tests of the ORM auth driver, and of the migration that makes
 * its tables, on PostgreSQL, through the PDO driver.
 *
 * Runs when the environment variable KOHANA_TEST_PGSQL_HOST is set.
 *
 * @package    Kohana/ORM
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.orm')]
#[Group('kohana.auth')]
class Kohana_AuthORMPgsqlTest extends Kohana_AuthORMTest
{
	protected function config(): ?array
	{
		return static::settings();
	}

	/**
	 * The settings of the test database, also used by AuthBcryptPgsqlTest.
	 */
	public static function settings(): ?array
	{
		if ( ! getenv('KOHANA_TEST_PGSQL_HOST') OR ! extension_loaded('pdo_pgsql'))
			return null;

		return [
			'type'       => 'PDO',
			'connection' => [
				'dsn' => 'pgsql:host='.getenv('KOHANA_TEST_PGSQL_HOST')
					.';port='.(getenv('KOHANA_TEST_PGSQL_PORT') ?: 5432)
					.';dbname='.(getenv('KOHANA_TEST_PGSQL_DATABASE') ?: 'kohana_test'),
				'username' => getenv('KOHANA_TEST_PGSQL_USER') ?: 'postgres',
				'password' => (string) getenv('KOHANA_TEST_PGSQL_PASSWORD'),
			],
			'identifier'   => '"',
			'table_prefix' => '',
		];
	}
}
