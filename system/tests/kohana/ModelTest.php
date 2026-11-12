<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * This test only really exists for code coverage.
 *
 * @package    Kohana
 * @category   Tests
 *
 * @author     BRMatt <matthew@sigswitch.com>
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.model')]
class Kohana_ModelTest extends Unittest_TestCase
{
	/**
	 * Test the model's factory.
	 *
	 * @covers Model::factory
	 */
	public function test_create()
	{
		$foobar = Model::factory('Foobar');

		$this->assertEquals(true, $foobar instanceof Model);
	}
}

class Model_Foobar extends Model
{

}
