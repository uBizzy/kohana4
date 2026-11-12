# Codebench

Codebench answers "which of these ways to write it is faster?". You write each way as a method; it runs them all many times on the same inputs and shows the times side by side.

```php
// application/bootstrap.php: only while developing
Kohana::modules([
	'codebench' => MODPATH.'codebench',
]);
```

Open `/codebench` and choose a benchmark. Three samples come with the module.

![The result of a benchmark](codebench.png)

## Writing a benchmark

A benchmark is a class `Bench_<Name>` in `classes/Bench/` that extends `Codebench`. Every public method whose name starts with `bench_` is one way to do the job.

```php
// application/classes/Bench/LtrimDigits.php
class Bench_LtrimDigits extends Codebench {

	// Shown above the results. Addresses become links
	public string $description = 'Removing digits at the start: a pattern or ltrim()?';

	// How often each method runs for each subject
	public int $loops = 100000;

	// The inputs: every method gets each of them
	public array $subjects = [
		'123digits',
		'no-digits',
	];

	public function bench_regex(mixed $subject): mixed
	{
		return preg_replace('/^\d+/', '', $subject);
	}

	public function bench_ltrim(mixed $subject): mixed
	{
		return ltrim($subject, '0..9');
	}
}
```

Open `/codebench/ltrimdigits`. The types of the three properties, and `(mixed $subject): mixed` on the methods, are needed as shown.

## Reading the result

- Each method gets a grade and a bar: the fastest is 100 %, the others show how much slower they are. Grade A is within 125 % of the fastest, F is more than five times slower.
- Open a method to see the time for each subject, and **what the method returned** for it.

[!!] Look at the returned values first. A method that is fast but gives another result is not an alternative.

## Good benchmarks

- **Several subjects**: short and long, matching and not matching. A way that wins on one input can lose on another.
- **Enough loops** that a run takes a noticeable time; too few and you measure noise.
- **Only the code in question** inside the methods. Prepare data in the constructor or in `$subjects`.
- Run it more than once, and on the PHP version of your server.
- A difference of a few percent is not worth uglier code. Look for the cases where one way is several times faster.

## Settings

```php
// application/config/codebench.php
return [
	// Seconds a benchmark may run; 0 is no limit
	'max_execution_time' => 0,

	// Show the details of every method opened
	'expand_all' => false,
];
```

## Codebench or the profiler?

| Question | Tool |
|---|---|
| Which of these functions is faster? | Codebench |
| Where does my page spend its time? | The [profiler](../kohana/profiling) |

[!!] Codebench runs code on request. Never enable the module in production.
