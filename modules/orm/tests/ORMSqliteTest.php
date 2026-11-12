<?php

use PHPUnit\Framework\Attributes\Group;

require_once __DIR__.'/ORMDatabaseTest.php';

/**
 * Runs the ORM database tests on SQLite, through the PDO driver. Needs no
 * server, so these tests always run.
 *
 * @package    Kohana/ORM
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.orm')]
class Kohana_ORMSqliteTest extends Kohana_ORMDatabaseTest
{
	protected function config(): ?array
	{
		if ( ! extension_loaded('pdo_sqlite'))
			return null;

		return [
			'type'         => 'PDO',
			'connection'   => ['dsn' => 'sqlite::memory:'],
			'identifier'   => '"',
			'table_prefix' => '',
		];
	}

	protected function id_column(): string
	{
		return 'id INTEGER PRIMARY KEY AUTOINCREMENT';
	}

	protected function ignores_case(): bool
	{
		return false;
	}
}
