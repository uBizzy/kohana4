<?php
/**
 * Shows the benchmarks and runs one of them.
 *
 * @package    Kohana/Codebench
 * @category   Controllers
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Controller_Codebench extends Controller {

	/**
	 * Lists the benchmarks; with a name in the address, runs that one.
	 *
	 * @return  void
	 */
	public function action_index(): void
	{
		$benchmarks = Codebench::benchmarks();

		// Only a class from that list is ever made: never one named freely in the address
		$name = strtolower( (string) preg_replace('/^bench_/i', '', is_string($class = $this->request->param('class')) ? $class : ''));
		/** @var class-string<Codebench>|null $class  a class of classes/Bench */
		$class = $benchmarks[$name] ?? null;

		if ($name !== '' AND $class === null)
			throw HTTP_Exception::factory(404, 'There is no benchmark named :name', [':name' => $name]);

		$this->response->body(View::factory('codebench', [
			'benchmarks' => $benchmarks,
			'class'      => $class,
			'result'     => ($class !== null) ? (new $class)->run() : null,
			'expand_all' => (bool) Kohana::$config->load('codebench.expand_all'),
		]));
	}

}
