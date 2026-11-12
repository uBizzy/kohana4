<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Config lib that's shipped with kohana
 *
 * @package    Kohana
 * @category   Tests
 *
 * @author     Jeremy Bush <contractfrombelow@gmail.com>
 * @author     Matt Button <matthew@sigswitch.com>
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.config')]
class Kohana_ConfigTest extends Unittest_TestCase
{

	/**
	 * When a config object is initially created there should be
	 * no readers attached
	 *
	 * @covers Config
	 */
	public function test_initially_there_are_no_sources()
	{
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('No configuration sources attached');

		$config = new Config;
		$config->load('invalid');
	}

	/**
	 * Test that calling attach() on a kohana config object
	 * adds the specified reader to the config object
	 *
	 * @covers Config::attach
	 */
	public function test_attach_adds_reader_and_returns_this()
	{
		$config = new Config;
		$reader = $this->createStub('Kohana_Config_Reader');

		$this->assertSame($config, $config->attach($reader));

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('Need to specify a config group');

		$config->load('');
	}

	/**
	 * Calling detach() on a config object should remove it from the queue of readers
	 *
	 * @covers Config::detach
	 */
	public function test_detach_removes_reader_and_returns_this()
	{
		$config  = new Config;

		// Due to the way phpunit mock generator works if you try and mock a class
		// that has already been used then it just re-uses the first's name

		// To get around this we have to specify a totally random name for the second mock object
		$reader1 = $this->createStub('Kohana_Config_Reader');
		$reader2 = $this->createStub('Kohana_Config_Reader');

		$config->attach($reader1);
		$config->attach($reader2);

        $this->assertSame($config, $config->detach($reader2));

        $this->assertSame($config, $config->detach($reader1));

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('No configuration sources attached');

		$config->load('invalid');
	}

	/**
	 * detach() should return $this even if the specified reader does not exist
	 *
	 * @covers Config::detach
	 */
	public function test_detach_returns_this_even_when_reader_dnx()
	{
		$config = new Config;
		$reader = $this->createStub('Kohana_Config_Reader');

		$this->assertSame($config, $config->detach($reader));
	}

	/**
	 * If we request a config variable with a dot path then
	 * Config::load() should load the group and return the requested variable
	 *
	 * @covers Config::load
	 */
	public function test_load_can_get_var_from_dot_path()
	{
		$config = new Config;

		$reader = $this->createMock('Kohana_Config_Reader');

		$reader
			->expects($this->once())
			->method('load')
			->with('beer')
			->willReturn(['stout' => 'Guinness']);

		$config->attach($reader);

		$this->assertSame('Guinness', $config->load('beer.stout'));
	}

	/**
	 * If we've already loaded a config group then the correct variable
	 * should be returned if we use the dot path notation to to request
	 * a var
	 *
	 * @covers Config::load
	 */
	public function test_load_can_get_var_from_dot_path_for_loaded_group()
	{
		$config = new Config;

		$reader = $this->createMock('Kohana_Config_Reader');

		$reader
			->expects($this->once())
			->method('load')
			->with('beer')
			->willReturn(['stout' => 'Guinness']);

		$config->attach($reader);

		$config->load('beer');

		$this->assertSame('Guinness', $config->load('beer.stout'));
	}

	/**
	 * If load() is called and there are no readers present then it should throw
	 * a kohana exception
	 *
	 * @covers Config::load
	 */
	public function test_load_throws_exception_if_there_are_no_sources()
	{
		$this->expectException(Kohana_Exception::class);

		// The following code should throw an exception and phpunit will catch / handle it
		$config = new Kohana_config;

		$config->load('random');
	}

	/**
	 * Provides test data for test_load_throws_exception_if_no_group_is_given()
	 *
	 * @return array
	 */
	public static function provider_load_throws_exception_if_no_group_is_given()
	{
		return [
			[null],
			[''],
			[[]],
			[['foo' => 'bar']],
			[new StdClass],
		];
	}

	/**
	 * If an invalid group name is specified then an exception should be thrown.
	 *
	 * Invalid means it's either a non-string value, or empty
	 *
	 * @covers Config::load
	 */
	#[DataProvider('provider_load_throws_exception_if_no_group_is_given')]
	public function test_load_throws_exception_if_invalid_group($value)
	{
		// An empty name is refused by the method; a value that is not a
		// string at all is refused by its parameter type
		$this->expectException(is_string($value) ? Kohana_Exception::class : TypeError::class);

		$config = new Kohana_Config;

		$reader = $this->createStub('Kohana_Config_Reader');

		$config->attach($reader);

		$config->load($value);
	}

	/**
	 * Make sure that _write_config() passes the changed configuration to all
	 * writers in the queue
	 *
	 * @covers Kohana_Config
	 */
	public function test_write_config_passes_changed_config_to_all_writers()
	{
		$config = new Kohana_Config;

		$reader1 = $this->createStub('Kohana_Config_Reader');
		$writer1 = $this->createMock('Kohana_Config_Writer');
		$writer2 = $this->createMock('Kohana_Config_Writer');

		$writer1
			->expects($this->once())
			->method('write')
			->with('some_group', 'key', 'value');

		$writer2
			->expects($this->once())
			->method('write')
			->with('some_group', 'key', 'value');

		$config->attach($reader1)->attach($writer1)->attach($writer2);

		$config->_write_config('some_group', 'key', 'value');
	}

	/**
	 * Config sources are stored in a stack, make sure that config at the bottom
	 * of the stack is overriden by config at the top
	 *
	 * @covers Config::load
	 */
	public function test_config_is_loaded_from_top_to_bottom_of_stack()
	{
		$group_name =  'lolumns';

		$reader1 = $this->createMock('Kohana_Config_Reader');
		$reader2 = $this->createMock('Kohana_Config_Reader');

		$reader1
			->expects($this->once())
			->method('load')
			->with($group_name)
			->willReturn(['foo' => 'bar', 'kohana' => 'awesome', 'life' => ['normal', 'fated']]);

		$reader2
			->expects($this->once())
			->method('load')
			->with($group_name)
			->willReturn(['kohana' => 'sweet', 'music' => 'tasteful', 'life' => ['extraordinary', 'destined']]);

		$config = new Kohana_Config;

		// Attach $reader1 at the "top" and reader2 at the "bottom"
		$config->attach($reader1)->attach($reader2, false);

		$this->assertSame(
			[
				'kohana' => 'awesome',
				'music'  => 'tasteful',
				'life'   => [
					'extraordinary',
					'destined',
					'normal',
					'fated',
				],
				'foo'    => 'bar',
			],
			$config->load($group_name)->as_array()
		);
	}

	/**
	 * load() should keep a record of what config groups have been requested and if
	 * a group is requested more than once the first instance should be returned
	 *
	 * @covers Config::load
	 */
	public function test_load_reuses_config_groups()
	{
		$reader = $this->createMock('Kohana_Config_Reader');
		$reader
			->expects($this->once())
			->method('load')
			->with('something')
			->willReturn([]);

		$config = new Kohana_Config;

		$config->attach($reader);

		$group = $config->load('something');

		$this->assertSame($group, $config->load('something'));
	}

	/**
	 * When we call copy() we expect it to copy the merged config to all writers
	 *
	 * @TODO This test sucks due to limitations in the phpunit mock generator.  MAKE THIS AWESOME AGAIN!
	 * @covers Kohana_Config::copy
	 */
	public function test_copy_copies_merged_config_to_all_writers()
	{
		$config = new Kohana_Config;

		$reader1 = $this->createMock('Kohana_Config_Reader');
		$reader2 = $this->createMock('Kohana_Config_Reader');

		$reader1
			->expects($this->once())
			->method('load')
			->with('something')
			->willReturn(['pie' => 'good', 'kohana' => 'awesome']);

		$reader2
			->expects($this->once())
			->method('load')
			->with('something')
			->willReturn(['kohana' => 'good']);

		$writer1 = $this->createMock('Kohana_Config_Writer');
		$writer2 = $this->createMock('Kohana_Config_Writer');

		// Due to crazy limitations in phpunit's mocking engine we have to be fairly
		// liberal here as to what order we receive the config items
		// Good news is that order shouldn't matter *yay*
		//
		// Now save your eyes and skip the next... 13 lines!
		$key = $this->logicalOr('pie', 'kohana');
		$val = $this->logicalOr('good', 'awesome');

		$writer1
			->expects($this->exactly(2))
			->method('write')
			->with('something', clone $key, clone $val);

		$writer2
			->expects($this->exactly(2))
			->method('write')
			->with('something', clone $key, clone $val);

		$config
			->attach($reader1)->attach($reader2, false)
			->attach($writer1)->attach($writer2);

		// Now let's get this thing going!
		$config->copy('something');
	}
}
