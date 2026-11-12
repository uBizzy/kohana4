<?php
/**
 * Sample benchmark: three ways to remove NUL bytes from a text.
 *
 * @package    Kohana/Codebench
 * @category   Benchmarks
 * @author     Geert De Deckere <geert@idoe.be>
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Bench_StripNullBytes extends Codebench {

	public string $description = 'Removing NUL bytes: str_replace(), strtr() or a pattern?';

	public int $loops = 100000;

	/**
	 * @var  array<int|string, mixed>
	 */
	public array $subjects = [
		"\0",
		"\0\0\0\0\0\0\0\0\0\0",
		"bla\0bla\0bla\0bla\0bla\0bla\0bla\0bla\0bla\0bla",
		'blablablablablablablablablablablablablablablabla',
	];

	public function bench_str_replace(string $subject): mixed
	{
		return str_replace("\0", '', $subject);
	}

	public function bench_strtr(string $subject): mixed
	{
		return strtr($subject, ["\0" => '']);
	}

	public function bench_preg_replace(string $subject): mixed
	{
		return preg_replace('~\0+~', '', $subject);
	}

}
