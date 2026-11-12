<?php

use PHPUnit\Framework\Attributes\Group;

require_once __DIR__.'/ORMDatabaseTest.php';

/**
 * Runs the ORM database tests on PostgreSQL, through the PDO driver, when
 * the environment variable KOHANA_TEST_PGSQL_HOST is set (see also _PORT,
 * _USER, _PASSWORD and _DATABASE).
 *
 * @package    Kohana/ORM
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.orm')]
class Kohana_ORMPgsqlTest extends Kohana_ORMDatabaseTest
{
	protected function config(): ?array
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

	protected function id_column(): string
	{
		return 'id SERIAL PRIMARY KEY';
	}

	protected function ignores_case(): bool
	{
		return false;
	}
}
