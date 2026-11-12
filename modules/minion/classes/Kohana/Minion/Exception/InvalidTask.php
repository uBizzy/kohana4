<?php

/**
 * Invalid Task Exception
 *
 * @package    Kohana/Minion
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Minion_Exception_InvalidTask extends Minion_Exception {

	public function format_for_cli(): string
	{
		return $this->getMessage();
	}

}
