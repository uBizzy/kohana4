<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Minion library
 *
 * @package    Koseven
 * @category   Tests
 *
 * @author     Piotr Gołasz <pgolasz@gmail.com>
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.config')]
class MinionTaskTest extends Unittest_TestCase {

	/**
	 * Tests that Minion Task Help works assuming all other tasks work aswell
	 */
	public function test_minion_runnable()
	{
		$minion_response = Minion_Task::factory(['task' => 'help']);
		$this->assertInstanceOf('Task_Help', $minion_response);
	}
}
