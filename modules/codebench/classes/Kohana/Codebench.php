<?php
/**
 * Codebench compares the speed of several ways to do the same thing.
 *
 * A benchmark is a class `Bench_<Name>` that extends this one. Each public
 * method whose name starts with "bench_" is one way; each is run
 * [Codebench::$loops] times on every subject.
 *
 *     class Bench_LtrimDigits extends Codebench {
 *
 *         public string $description = 'Removing digits at the start';
 *
 *         public array $subjects = ['123digits', 'no-digits'];
 *
 *         public function bench_regex(mixed $subject): mixed
 *         {
 *             return preg_replace('/^\d+/', '', $subject);
 *         }
 *
 *         public function bench_ltrim(mixed $subject): mixed
 *         {
 *             return ltrim($subject, '0..9');
 *         }
 *     }
 *
 * @package    Kohana/Codebench
 * @category   Base
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Codebench {

	/**
	 * @var  string  What the benchmark is about. Shown as text; addresses become links.
	 */
	public string $description = '';

	/**
	 * @var  int  How many times each method runs for each subject.
	 */
	public int $loops = 1000;

	/**
	 * @var  array<int|string, mixed>  The inputs. Every method gets each of them.
	 */
	public array $subjects = [];

	/**
	 * @var  array<int, string>  Highest score (percent of the fastest time) of each grade.
	 */
	public array $grades = [
		125 => 'A',
		150 => 'B',
		200 => 'C',
		300 => 'D',
		500 => 'E',
	];

	/**
	 * Returns the benchmarks that exist: the classes in the "classes/Bench" directories.
	 *
	 *     $benchmarks = Codebench::benchmarks();
	 *
	 * @return  array<string, string>  name in an address (lowercase) => class name
	 */
	public static function benchmarks(): array
	{
		$benchmarks = [];

		$collect = function (array $files) use (&$collect, &$benchmarks): void
		{
			foreach ($files as $name => $path)
			{
				if (is_array($path))
				{
					$collect($path);
				}
				elseif (str_ends_with($name, EXT))
				{
					// classes/Bench/Text/Limit.php is the class Bench_Text_Limit
					$class = str_replace(DIRECTORY_SEPARATOR, '_', substr($name, 8, -strlen(EXT)));

					if (is_subclass_of($class, 'Codebench'))
					{
						$benchmarks[strtolower(substr($class, 6))] = $class;
					}
				}
			}
		};

		$collect(Kohana::list_files('classes/Bench'));

		ksort($benchmarks);

		return $benchmarks;
	}

	/**
	 * Sets the time limit of the benchmark from the codebench config.
	 */
	public function __construct()
	{
		$limit = Kohana::$config->load('codebench.max_execution_time');

		set_time_limit(is_numeric($limit) ? (int) $limit : 0);
	}

	/**
	 * Runs every method on every subject and returns the measurements.
	 *
	 * Each method and each subject has `time` (seconds), `memory` (bytes),
	 * `percent` (time compared with the fastest: 100 is the fastest) and
	 * `grade`; a subject also has `return`, what the method returned for it.
	 *
	 * @return  array<string, mixed>  class, description, loops, subjects and benchmarks (method => measurements)
	 */
	public function run(): array
	{
		// The public methods whose name starts with "bench_"
		$methods = array_filter(get_class_methods($this), fn ($method) => str_starts_with($method, 'bench_'));

		// Run at least once, also when no subjects were given
		$subjects = $this->subjects ?: ['null' => null];

		$benchmarks = [];

		foreach ($methods as $method)
		{
			$benchmarks[$method] = $this->_measure($method, $subjects);
		}

		return [
			'class'       => static::class,
			'description' => $this->description,
			'loops'       => ['base' => $this->loops, 'total' => $this->loops * count($subjects) * count($methods)],
			'subjects'    => $subjects,
			'benchmarks'  => $this->_compare($benchmarks),
		];
	}

	/**
	 * Measures one method on every subject.
	 *
	 * @param   string  $method    name of the method
	 * @param   array<int|string, mixed>   $subjects  the inputs
	 * @return  array<string, mixed>   time, memory and the measurements of each subject
	 */
	protected function _measure(string $method, array $subjects): array
	{
		$result = ['time' => 0.0, 'memory' => 0, 'subjects' => []];

		// A closure is called the same way whatever the name of the method
		// is, so the length of the name does not change the times
		$call = (new ReflectionMethod($this, $method))->getClosure($this);

		foreach ($subjects as $key => $subject)
		{
			// One run before measuring: loading classes is not part of the
			// benchmark, and what the method returns is kept to show it
			$return = $call($subject);

			$token = Profiler::start('codebench', $method.$key);

			for ($i = 0; $i < $this->loops; ++$i)
			{
				$call($subject);
			}

			[$time, $memory] = Profiler::total($token);

			$result['subjects'][$key] = ['return' => $return, 'time' => $time, 'memory' => $memory];
			$result['time'] += $time;
			$result['memory'] += $memory;
		}

		return $result;
	}

	/**
	 * Adds to every method and subject how it compares with the fastest:
	 * `percent` (100 is the fastest) and `grade`.
	 *
	 * @param   array<string, mixed>  $benchmarks  measurements from [Codebench::_measure], by method
	 * @return  array<int|string, mixed>
	 */
	protected function _compare(array $benchmarks): array
	{
		if ( ! $benchmarks)
			return [];

		/** @var array<string, array{time: float, subjects: array<int|string, array{time: float}>}> $benchmarks */

		// The fastest method, and the fastest single subject of all methods
		$fastest_method = min(array_column($benchmarks, 'time') ?: [0]);
		$fastest_subject = min(array_map(fn ($benchmark) => min(array_column($benchmark['subjects'], 'time') ?: [0]), $benchmarks) ?: [0]);

		foreach ($benchmarks as & $benchmark)
		{
			$benchmark['percent'] = $fastest_method > 0 ? $benchmark['time'] / $fastest_method * 100 : 100.0;
			$benchmark['grade'] = $this->_grade($benchmark['percent']);

			foreach ($benchmark['subjects'] as & $subject)
			{
				$subject['percent'] = $fastest_subject > 0 ? $subject['time'] / $fastest_subject * 100 : 100.0;
				$subject['grade'] = $this->_grade($subject['percent']);
			}
		}

		return $benchmarks;
	}

	/**
	 * Returns the grade letter of a score.
	 *
	 * @param   float  $score  percent of the fastest time
	 * @return  string  "A" to "E", or "F" for anything slower
	 */
	protected function _grade(float $score): string
	{
		foreach ($this->grades as $max => $grade)
		{
			if ($score <= $max)
				return $grade;
		}

		return 'F';
	}

}
