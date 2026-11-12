<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Codebench module.
 *
 * @package    Kohana/Codebench
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.codebench')]
class Kohana_CodebenchTest extends Unittest_TestCase
{
	/**
	 * Runs a request of the module (the test bootstrap sets the default route first).
	 */
	protected function _get(string $uri): Response
	{
		return Request::factory($uri, [], false, [Route::get('codebench')])->execute();
	}

	/**
	 * The sample benchmarks are found.
	 */
	public function test_benchmarks_are_listed(): void
	{
		$benchmarks = Codebench::benchmarks();

		$this->assertSame('Bench_LtrimDigits', $benchmarks['ltrimdigits']);
		$this->assertArrayNotHasKey('benchmarks', $benchmarks);
	}

	/**
	 * A run measures every bench_ method on every subject and compares them.
	 */
	public function test_run(): void
	{
		$bench = new Bench_LtrimDigits;
		$bench->loops = 10;

		$result = $bench->run();

		$this->assertSame('Bench_LtrimDigits', $result['class']);
		$this->assertSame(['bench_regex', 'bench_ltrim'], array_keys($result['benchmarks']));
		$this->assertSame(['base' => 10, 'total' => 40], $result['loops']);

		foreach ($result['benchmarks'] as $benchmark)
		{
			$this->assertGreaterThanOrEqual(100, round($benchmark['percent']));
			$this->assertContains($benchmark['grade'], ['A', 'B', 'C', 'D', 'E', 'F']);
			$this->assertSame('digits', $benchmark['subjects'][0]['return']);
			$this->assertSame('no-digits', $benchmark['subjects'][1]['return']);
		}

		// One of them is the fastest
		$this->assertContains(100.0, array_map(fn ($benchmark) => round($benchmark['percent'], 6), $result['benchmarks']));
	}

	/**
	 * The page lists the benchmarks and runs one by its name.
	 */
	public function test_pages(): void
	{
		$list = $this->_get('codebench');

		$this->assertSame(200, $list->status());
		$this->assertStringContainsString('LtrimDigits', $list->body());
		$this->assertStringNotContainsString(' style="', $list->body());
	}

	/**
	 * Only benchmark classes can be run: never a class named freely in the address.
	 */
	public function test_other_classes_are_refused(): void
	{
		$this->assertSame(404, $this->_get('codebench/Arr')->status());
		$this->assertSame(404, $this->_get('codebench/no_such_benchmark')->status());
		$this->assertSame(200, $this->_get('codebench/Bench_LtrimDigits')->status());
	}
}
