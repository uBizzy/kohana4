<?php
/**
 * Abstract controller class for automatic templating.
 *
 * @package    Kohana
 * @category   Controller
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Controller_Template extends Controller {

	/**
	 * @var  View  page template
	 */
	public string|View $template = 'template';

	/**
	 * @var  bool  auto render template
	 **/
	public bool $auto_render = true;

	/**
	 * Loads the template [View] object.
	 */
	public function before(): void
	{
		parent::before();

		if ($this->auto_render === true)
		{
			// Load the template
			$this->template = View::factory($this->template);
		}
	}

	/**
	 * Assigns the template [View] as the request response.
	 */
	public function after(): void
	{
		if ($this->auto_render === true)
		{
			$this->response->body($this->template->render());
		}

		parent::after();
	}

}
