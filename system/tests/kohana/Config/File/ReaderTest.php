<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Config file reader that's shipped with kohana
 *
 * @package    Unittest
 *
 * @author     Jeremy Bush <contractfrombelow@gmail.com>
 * @author     Matt Button <matthew@sigswitch.com>
 * @copyright  (c) 2007-2012 Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.config')]
class Kohana_Config_File_ReaderTest extends Kohana_Unittest_TestCase {

	/**
	 * If the config dir does not exist then the function should just
	 * return an empty array
	 *
	 * @covers Kohana_Config_File_Reader::load
	 */
	public function test_load_returns_empty_array_if_conf_dir_dnx()
	{
		$config = new Kohana_Config_File('gafloogle');

		self::assertSame([], $config->load('values'));
	}

	/**
	 * If the requested config group does not exist then the reader
	 * should return an empty array
	 *
	 * @covers Kohana_Config_File_Reader::load
	 */
	public function test_load_returns_empty_array_if_conf_dnx()
	{
		$config = new Kohana_Config_File;

		self::assertSame([], $config->load('gafloogle'));
	}

	/**
	 * Test that the load() function is actually loading the
	 * configuration from the files.
	 *
	 * @param array $configuration Configuration Details
	 *
	 * @covers Kohana_Config_File_Reader::load
	 * @covers Kohana_Config_File_Reader::read_from_ob
	 * @throws Kohana_Exception
	 */
	#[DataProvider('provider_configs')]
	public function test_load_config_from_files($configuration)
	{
		if ( ! extension_loaded('yaml'))
		{
			self::markTestSkipped('PHP YAML required to execute this test.');
		}

		$config = new Kohana_Config_File;

		// Generate Paths for Configuration Storage
		$path = APPPATH . 'config' . DIRECTORY_SEPARATOR;
		$json_file = $path.'test.json';
		$yaml_file = $path.'test2.yaml';

		// Generate Json
		$json = json_encode($configuration['value']);

		// Check if files are writable
		if ( ! touch($json_file) || ! touch($yaml_file))
		{
			self::fail('Could not write configuration files inside APPATH/config');
		}

		// Write json config, remove on error
		if (file_put_contents($json_file, $json) === false)
		{
			unlink($json_file);
			self::fail('Could not write into config file ' . $json_file);
		}

		// Write yaml config, remove on error
		if ( ! yaml_emit_file($yaml_file, $configuration['value'], YAML_UTF8_ENCODING))
		{
			unlink($yaml_file);
			self::fail('Could not write into config file ' . $yaml_file);
		}

		// Check if values are like expected
		self::assertSame($config->load('test')['database']['host'], $configuration['expected']);
		self::assertSame($config->load('test2')['database']['host'], $configuration['expected'] . '...');

		// Remove both files
		unlink($json_file);
		unlink($yaml_file);

		// Test PHP Configs
		$values = $config->load('inflector');

		// Due to the way the cascading filesystem works there could be
		// any number of modifications to the system config in the
		// actual output.  Therefore to increase compatability we just
		// check that we've got an array and that it's not empty
		self::assertNotSame([], $values);
		self::assertIsArray($values);
	}

	/**
	 * Data Provider for Configurations
	 * @return array
	 */
	public static function provider_configs() : array
	{
		return [
			[
				[
					'value' => [
						'database' => [
							'host' => "<?php echo 'test1'; ?>"
						]
					],
					'expected' => 'test1'
				]
			]
		];
	}
}
