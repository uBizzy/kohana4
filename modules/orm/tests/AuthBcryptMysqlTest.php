<?php

use PHPUnit\Framework\Attributes\Group;

require_once __DIR__.'/AuthBcryptTest.php';
require_once __DIR__.'/AuthORMMysqlTest.php';

/**
 * Runs the tests of the Bcrypt auth driver on MySQL/MariaDB.
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
class Kohana_AuthBcryptMysqlTest extends Kohana_AuthBcryptTest
{
	protected function config(): ?array
	{
		return Kohana_AuthORMMysqlTest::settings();
	}
}
