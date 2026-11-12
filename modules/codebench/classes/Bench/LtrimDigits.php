<?php
/**
 * Sample benchmark: two ways to remove digits at the start of a text.
 *
 * @package    Kohana/Codebench
 * @category   Benchmarks
 * @author     Geert De Deckere <geert@idoe.be>
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Bench_LtrimDigits extends Codebench {

	public string $description = 'Removing digits at the start of a text: a pattern or ltrim()?';

	public int $loops = 100000;

	/**
	 * @var  array<int|string, mixed>
	 */
	public array $subjects = [
		'123digits',
		'no-digits',
	];

	public function bench_regex(string $subject): mixed
	{
		return preg_replace('/^\d+/', '', $subject);
	}

	public function bench_ltrim(string $subject): mixed
	{
		return ltrim($subject, '0..9');
	}

}
