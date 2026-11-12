<?php
/**
 * Sample benchmark: ways to check a hexadecimal colour (#fc0, #ffcc00).
 *
 * Shows why subjects matter: the methods differ most on the values that
 * are not colours.
 *
 * @package    Kohana/Codebench
 * @category   Benchmarks
 * @author     Geert De Deckere <geert@idoe.be>
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Bench_ValidColor extends Codebench {

	public string $description = 'Checking a hexadecimal colour: patterns, with and without capturing, or plain text functions?';

	public int $loops = 20000;

	/**
	 * @var  array<int|string, mixed>
	 */
	public array $subjects = [
		// Colours
		'aaA',
		'123',
		'000000',
		'#123456',
		'#abcdef',

		// Not colours
		'ggg',
		'1234',
		'#1234567',
		"#000\n",
	];

	// With capturing groups
	public function bench_regex_capturing(string $subject): mixed
	{
		return (bool) preg_match('/^#?([0-9a-f]){3}(([0-9a-f]){3})?$/iD', $subject);
	}

	// Without capturing groups
	public function bench_regex(string $subject): mixed
	{
		return (bool) preg_match('/^#?[0-9a-f]{3}(?:[0-9a-f]{3})?$/iD', $subject);
	}

	// A possessive "#": the pattern gives up sooner on a value that is not a colour
	public function bench_regex_possessive(string $subject): mixed
	{
		return (bool) preg_match('/^#?+[0-9a-f]{3}(?:[0-9a-f]{3})?$/iD', $subject);
	}

	// No pattern at all
	public function bench_text_functions(string $subject): mixed
	{
		$subject = ltrim($subject, '#');
		$length = strlen($subject);

		return ($length === 3 OR $length === 6) AND ctype_xdigit($subject);
	}

}
