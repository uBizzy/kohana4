<?php

use PHPUnit\Framework\Attributes\Group;

require_once __DIR__.'/AuthBcryptTest.php';
require_once __DIR__.'/AuthORMPgsqlTest.php';

/**
 * Runs the tests of the Bcrypt auth driver on PostgreSQL.
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
class Kohana_AuthBcryptPgsqlTest extends Kohana_AuthBcryptTest
{
	protected function config(): ?array
	{
		return Kohana_AuthORMPgsqlTest::settings();
	}
}
