<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Test case for Kohana_ORM
 *
 * @package    Kohana/ORM
 * @category   Test
 * @author     Craig Duncan <git@duncanc.co.uk>
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.orm')]
class Kohana_ORMTest extends Unittest_TestCase
{
	/**
	 * Ensure has() doesn't attempt to count non-countables.
	 *
	 * @covers ORM::has
	 */
	public function test_has()
	{
		$orm = new ORM_Example;

		$result = $orm->has('children', false);

		$this->assertSame(false, $result);
	}

	/**
	 * has(), add() and remove() say clearly when the relation is unknown
	 * or has no "through" table.
	 */
	public function test_unknown_relation_is_reported(): void
	{
		$orm = new ORM_Example;

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('not a "has many through" relation');

		$orm->has('nothing');
	}

	/**
	 * The Guid behavior leaves the model alone when "guid_only" is off.
	 */
	public function test_guid_lookup_can_be_switched_off(): void
	{
		$behavior = ORM_Behavior::factory('Guid', ['guid_only' => false]);

		// true means: carry on with the normal loading by primary key
		$this->assertTrue($behavior->on_construct(new ORM_Example, 'not-a-uuid'));
	}
}

class ORM_Example extends ORM
{
	protected array $_has_many = [
		'children' => ['through' => 'examples_children', 'foreign_key' => 'example_id', 'far_key' => 'child_id'],
	];

	public function __construct()
	{
	}
}
