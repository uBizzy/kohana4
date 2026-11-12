<?php
/**
 * Class for formatting REST-Response bodies as HTML
 *
 * @package        Kohana\REST
 *
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license        https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_REST_Format_HTML extends REST_Format {

	/**
	 * Format function
	 *
	 * @throws REST_Exception
	 *
	 * @return string
	 */
	public function format(): string
	{
		// Filter the path parts, remove empty ones
		$path = array_filter([
			$this->_request->directory(),
			$this->_request->controller(),
			$this->_request->action(),
		]);

		$path = strtolower(implode(DIRECTORY_SEPARATOR, $path));

		// Try to find view, change view name if not found
		// e.g Controller: Welcome, check views/Welcome.php else views/welcome/{index,update,create,delete}.php
		if (Kohana::find_file('views', dirname($path)))
		{
			$path = dirname($path);
		}

		// Try to initialize View
		try
		{
			/** @var iterable<string, mixed> $data */
			$data = is_iterable($this->_body) ? $this->_body : [];

			return View::factory($path, $data)->render();
		}
		catch (View_Exception $e)
		{
			throw new REST_Exception($e->getMessage());
		}
	}

}
