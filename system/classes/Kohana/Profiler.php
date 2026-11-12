<?php
/**
 * Provides simple benchmarking and profiling. To display the statistics that
 * have been collected, load the `profiler/stats` [View]:
 *
 *     echo View::factory('profiler/stats');
 *
 * @package    Kohana
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Profiler {

	/**
	 * @var  int   maximum number of application stats to keep
	 */
	public static int $rollover = 1000;

	/**
	 * @var  array<string, array{group: string, name: string, file: string|null, line: int|null, note: string, start_time: float, start_memory: int, stop_time: float|false, stop_memory: int|false}>  collected benchmarks
	 */
	protected static array $_marks = [];

	/**
	 * @var  float  Requests that take longer than this many seconds are
	 *              written to the log, with their time, memory and number
	 *              of database queries. 0 switches this off. It works in
	 *              every environment, also when profiling is off.
	 *
	 *     // In the bootstrap: log every request over one second
	 *     Profiler::$slow_request = 1.0;
	 */
	public static float $slow_request = 0.0;

	/**
	 * @var  array<string, mixed>  Data added with [Profiler::add], by title
	 */
	protected static array $_data = [];

	/**
	 * Starts a new benchmark and returns a unique token. The returned token
	 * _must_ be used when stopping the benchmark.
	 *
	 *     $token = Profiler::start('test', 'profiler');
	 *
	 * A benchmark can remember the place in the code it was started for,
	 * as the database drivers do for every query:
	 *
	 *     $token = Profiler::start('Database (default)', $sql, $file, $line);
	 *
	 * @param   string       $group  group name
	 * @param   string       $name   benchmark name
	 * @param   string|null  $file   file the benchmark was started for
	 * @param   int|null     $line   line in that file
	 * @return  string
	 */
	public static function start(string $group, string $name, ?string $file = null, ?int $line = null): string
	{
		/** @var int $counter */
		static $counter = 0;

		// Create a unique token based on the counter
		$token = 'kp/'.base_convert( (string) $counter++, 10, 32);

		Profiler::$_marks[$token] = [
			'group' => strtolower($group),
			'name'  => $name,

			// Where in the code, when the caller told
			'file'  => $file,
			'line'  => $line,

			// What came of it, see Profiler::note()
			'note'  => '',

			// Start the benchmark
			'start_time'   => microtime(true),
			'start_memory' => memory_get_usage(),

			// Set the stop keys without values
			'stop_time'    => false,
			'stop_memory'  => false,
		];

		return $token;
	}

	/**
	 * Stops a benchmark.
	 *
	 *     Profiler::stop($token);
	 *
	 * @param   string  $token
	 * @return  void
	 */
	public static function stop(string $token): void
	{
		// A benchmark that was deleted, or never started, cannot be stopped
		if ( ! isset(Profiler::$_marks[$token]))
			return;

		// Stop the benchmark
		Profiler::$_marks[$token]['stop_time']   = microtime(true);
		Profiler::$_marks[$token]['stop_memory'] = memory_get_usage();
	}

	/**
	 * Adds a short text to a benchmark that says what came of it: the
	 * file that was found, the number of rows, "passed" or "failed".
	 * Tools such as the debug bar show it beside the benchmark.
	 *
	 *     $token = Profiler::start('Search', $term);
	 *     $found = $index->search($term);
	 *     Profiler::stop($token);
	 *
	 *     Profiler::note($token, count($found).' results');
	 *
	 * @param   string  $token  token of the benchmark
	 * @param   string  $note   the text
	 * @return  void
	 */
	public static function note(string $token, string $note): void
	{
		if (isset(Profiler::$_marks[$token]))
		{
			Profiler::$_marks[$token]['note'] = $note;
		}
	}

	/**
	 * Deletes a benchmark. If an error occurs during the benchmark, it is
	 * recommended to delete the benchmark to prevent statistics from being
	 * adversely affected.
	 *
	 *     Profiler::delete($token);
	 *
	 * @param   string  $token
	 * @return  void
	 */
	public static function delete(string $token): void
	{
		// Remove the benchmark
		unset(Profiler::$_marks[$token]);
	}

	/**
	 * Returns all the benchmark tokens by group and name as an array.
	 *
	 *     $groups = Profiler::groups();
	 *
	 * @return  array<string, array<string, array<int, string>>>
	 */
	public static function groups(): array
	{
		$groups = [];

		foreach (Profiler::$_marks as $token => $mark)
		{
			// Sort the tokens by the group and name
			$groups[$mark['group']][$mark['name']][] = $token;
		}

		return $groups;
	}

	/**
	 * Gets the min, max, average and total of a set of tokens as an array.
	 *
	 *     $stats = Profiler::stats($tokens);
	 *
	 * @param   array<int, string>   $tokens profiler tokens
	 * @return  array<string, array<string, float|int|null>>   min, max, average, total (min and max are null without tokens)
	 * @uses    Profiler::total
	 */
	public static function stats(array $tokens): array
	{
		// Min and max are unknown by default
		$min = $max = [
			'time' => null,
			'memory' => null];

		// Total values are always integers
		$total = [
			'time' => 0,
			'memory' => 0];

		foreach ($tokens as $token)
		{
			// Get the total time and memory for this benchmark
			list($time, $memory) = Profiler::total($token);

			if ($max['time'] === null OR $time > $max['time'])
			{
				// Set the maximum time
				$max['time'] = $time;
			}

			if ($min['time'] === null OR $time < $min['time'])
			{
				// Set the minimum time
				$min['time'] = $time;
			}

			// Increase the total time
			$total['time'] += $time;

			if ($max['memory'] === null OR $memory > $max['memory'])
			{
				// Set the maximum memory
				$max['memory'] = $memory;
			}

			if ($min['memory'] === null OR $memory < $min['memory'])
			{
				// Set the minimum memory
				$min['memory'] = $memory;
			}

			// Increase the total memory
			$total['memory'] += $memory;
		}

		// Determine the number of tokens
		$count = max(1, count($tokens));

		// Determine the averages
		$average = [
			'time' => $total['time'] / $count,
			'memory' => $total['memory'] / $count];

		return [
			'min' => $min,
			'max' => $max,
			'total' => $total,
			'average' => $average];
	}

	/**
	 * Gets the min, max, average and total of profiler groups as an array.
	 *
	 *     $stats = Profiler::group_stats('test');
	 *
	 * @param   array<int, string>|string|null   $groups single group name string, or array with group names; all groups by default
	 * @return  array<string, array<string, array<string, float|int|null>>>   min, max, average, total
	 * @uses    Profiler::groups
	 * @uses    Profiler::stats
	 */
	public static function group_stats(string|array|null $groups = null): array
	{
		// Which groups do we need to calculate stats for?
		$groups = ($groups === null)
			? Profiler::groups()
			: array_intersect_key(Profiler::groups(), array_flip( (array) $groups));

		// All statistics
		$stats = [];

		foreach ($groups as $group => $names)
		{
			foreach ($names as $name => $tokens)
			{
				// Store the stats for each subgroup.
				// We only need the values for "total".
				$_stats = Profiler::stats($tokens);
				$stats[$group][$name] = $_stats['total'];
			}
		}

		// Group stats
		$groups = [];

		foreach ($stats as $group => $names)
		{
			// Min and max are unknown by default
			$groups[$group]['min'] = $groups[$group]['max'] = [
				'time' => null,
				'memory' => null];

			// Total values are always integers
			$groups[$group]['total'] = [
				'time' => 0,
				'memory' => 0];

			foreach ($names as $total)
			{
				if ( ! isset($groups[$group]['min']['time']) OR $groups[$group]['min']['time'] > $total['time'])
				{
					// Set the minimum time
					$groups[$group]['min']['time'] = $total['time'];
				}
				if ( ! isset($groups[$group]['min']['memory']) OR $groups[$group]['min']['memory'] > $total['memory'])
				{
					// Set the minimum memory
					$groups[$group]['min']['memory'] = $total['memory'];
				}

				if ( ! isset($groups[$group]['max']['time']) OR $groups[$group]['max']['time'] < $total['time'])
				{
					// Set the maximum time
					$groups[$group]['max']['time'] = $total['time'];
				}
				if ( ! isset($groups[$group]['max']['memory']) OR $groups[$group]['max']['memory'] < $total['memory'])
				{
					// Set the maximum memory
					$groups[$group]['max']['memory'] = $total['memory'];
				}

				// Increase the total time and memory
				$groups[$group]['total']['time']   += $total['time'];
				$groups[$group]['total']['memory'] += $total['memory'];
			}

			// Determine the number of names (subgroups)
			$count = count($names);

			// Determine the averages
			$groups[$group]['average']['time']   = $groups[$group]['total']['time'] / $count;
			$groups[$group]['average']['memory'] = $groups[$group]['total']['memory'] / $count;
		}

		return $groups;
	}

	/**
	 * Gets the total execution time and memory usage of a benchmark as a list.
	 *
	 *     list($time, $memory) = Profiler::total($token);
	 *
	 * @param   string  $token
	 * @return  array{float, int}   execution time, memory
	 */
	public static function total(string $token): array
	{
		// Import the benchmark data
		$mark = Profiler::$_marks[$token];

		if ($mark['stop_time'] === false)
		{
			// The benchmark has not been stopped yet
			$mark['stop_time']   = microtime(true);
			$mark['stop_memory'] = memory_get_usage();
		}

		return [
			// Total time in seconds
			$mark['stop_time'] - $mark['start_time'],

			// Amount of memory in bytes
			$mark['stop_memory'] - $mark['start_memory'],
		];
	}

	/**
	 * Gets the total application run time and memory usage. Caches the result
	 * so that it can be compared between requests.
	 *
	 *     list($time, $memory) = Profiler::application();
	 *
	 * @return  array<string, mixed>  execution time, memory
	 * @uses    Kohana::cache
	 */
	public static function application(): array
	{
		// Load the stats from cache, which is valid for 1 day
		$stats = Kohana::cache('profiler_application_stats', null, 3600 * 24);

		if ( ! is_array($stats) OR $stats['count'] > Profiler::$rollover)
		{
			// Initialize the stats array
			$stats = [
				'min'   => [
					'time'   => null,
					'memory' => null],
				'max'   => [
					'time'   => null,
					'memory' => null],
				'total' => [
					'time'   => null,
					'memory' => null],
				'count' => 0];
		}

		/** @var array{min: array{time: float|null, memory: int|null}, max: array{time: float|null, memory: int|null}, total: array{time: float|null, memory: int|null}, count: int} $stats */

		// Get the application run time
		$time = microtime(true) - KOHANA_START_TIME;

		// Get the total memory usage
		$memory = memory_get_usage() - KOHANA_START_MEMORY;

		// Calculate max time
		if ($stats['max']['time'] === null OR $time > $stats['max']['time'])
		{
			$stats['max']['time'] = $time;
		}

		// Calculate min time
		if ($stats['min']['time'] === null OR $time < $stats['min']['time'])
		{
			$stats['min']['time'] = $time;
		}

		// Add to total time
		$stats['total']['time'] += $time;

		// Calculate max memory
		if ($stats['max']['memory'] === null OR $memory > $stats['max']['memory'])
		{
			$stats['max']['memory'] = $memory;
		}

		// Calculate min memory
		if ($stats['min']['memory'] === null OR $memory < $stats['min']['memory'])
		{
			$stats['min']['memory'] = $memory;
		}

		// Add to total memory
		$stats['total']['memory'] += $memory;

		// Another mark has been added to the stats
		$stats['count']++;

		// Determine the averages
		$stats['average'] = [
			'time'   => $stats['total']['time'] / $stats['count'],
			'memory' => $stats['total']['memory'] / $stats['count']];

		// Cache the new stats
		Kohana::cache('profiler_application_stats', $stats);

		// Set the current application execution time and memory
		// Do NOT cache these, they are specific to the current request only
		$stats['current'] = ['time' => $time, 'memory' => $memory];

		// Return the total application run time and memory usage
		return $stats;
	}

	/**
	 * Runs a function and measures it as one benchmark. The benchmark is
	 * stopped even when the function throws, so it can never be left running.
	 *
	 *     $users = Profiler::measure('Reports', 'load users', fn () => ORM::factory('User')->find_all());
	 *
	 * Nothing is measured when [Kohana::$profiling] is off; the function
	 * still runs.
	 *
	 * @param   string    $group     group name
	 * @param   string    $name      benchmark name
	 * @param   callable  $callable  code to measure
	 * @return  mixed  what the function returned
	 */
	public static function measure(string $group, string $name, callable $callable): mixed
	{
		if ( ! Kohana::$profiling)
			return $callable();

		$token = Profiler::start($group, $name);

		try
		{
			return $callable();
		}
		finally
		{
			Profiler::stop($token);
		}
	}

	/**
	 * Returns every benchmark of the request, in the order they were
	 * started. For tools that draw a timeline.
	 *
	 *     foreach (Profiler::marks() as $token => $mark)
	 *     {
	 *         // group, name, start_time, stop_time, start_memory, stop_memory
	 *     }
	 *
	 * A benchmark that is still running has `false` as stop values.
	 *
	 * @return  array<string, array{group: string, name: string, file: string|null, line: int|null, note: string, start_time: float, start_memory: int, stop_time: float|false, stop_memory: int|false}>  benchmarks by their token
	 */
	public static function marks(): array
	{
		return Profiler::$_marks;
	}

	/**
	 * Keeps a piece of your own data with the profile of this request:
	 * a value you want to look at while developing. Tools such as the
	 * debug module show it; without such a tool nothing happens with it.
	 *
	 *     Profiler::add('Cart', $cart->as_array());
	 *     Profiler::add('Price rules', $rules);
	 *
	 * Nothing is kept when [Kohana::$profiling] is off, so the calls can
	 * stay in the code.
	 *
	 * @param   string  $title  name the data is shown under
	 * @param   mixed   $data   any value
	 * @return  void
	 */
	public static function add(string $title, mixed $data): void
	{
		if (Kohana::$profiling)
		{
			Profiler::$_data[$title] = $data;
		}
	}

	/**
	 * Returns the data added with [Profiler::add].
	 *
	 * @return  array<string, mixed>  data by title
	 */
	public static function data(): array
	{
		return Profiler::$_data;
	}

	/**
	 * Returns the main numbers of the request so far.
	 *
	 *     $summary = Profiler::summary();
	 *
	 * Key          | Value
	 * -------------|------
	 * time         | seconds since the request started
	 * memory       | most memory used, in bytes
	 * queries      | number of database queries (0 when profiling is off)
	 * query_time   | seconds spent in database queries
	 * files        | number of PHP files loaded
	 *
	 * @return  array<string, float|int>
	 */
	public static function summary(): array
	{
		$queries = 0;
		$query_time = 0.0;

		foreach (Profiler::$_marks as $mark)
		{
			// The database drivers name their group "database (name of the connection)"
			if (str_starts_with($mark['group'], 'database') AND $mark['stop_time'] !== false)
			{
				$queries++;
				$query_time += $mark['stop_time'] - $mark['start_time'];
			}
		}

		return [
			'time'       => microtime(true) - KOHANA_START_TIME,
			'memory'     => memory_get_peak_usage(),
			'queries'    => $queries,
			'query_time' => $query_time,
			'files'      => count(get_included_files()),
		];
	}

	/**
	 * Writes the request to the log when it took longer than
	 * [Profiler::$slow_request]. Called by the framework at the end of
	 * every request.
	 *
	 * @return  void
	 */
	public static function log_slow_request(): void
	{
		if (Profiler::$slow_request <= 0 OR ! is_object(Kohana::$log))
			return;

		$summary = Profiler::summary();

		if ($summary['time'] < Profiler::$slow_request)
			return;

		// Only the path: the query string can hold secrets
		$argv   = (array) ($_SERVER['argv'] ?? []);
		$method = $_SERVER['REQUEST_METHOD'] ?? null;
		$path   = $_SERVER['REQUEST_URI'] ?? null;

		$uri = (PHP_SAPI === 'cli')
			? 'cli '.(is_string($argv[1] ?? null) ? $argv[1] : '')
			: (is_string($method) ? $method : 'GET').' '.strtok(is_string($path) ? $path : '', '?');

		Kohana::$log->add(Log::WARNING, 'Slow request: :time s, :memory MB, :queries queries (:query_time s): :uri', [
			':time'       => number_format($summary['time'], 3),
			':memory'     => number_format($summary['memory'] / 1048576, 1),
			':queries'    => Kohana::$profiling ? $summary['queries'] : 'unknown number of',
			':query_time' => number_format($summary['query_time'], 3),
			':uri'        => $uri,
		]);
	}

}
