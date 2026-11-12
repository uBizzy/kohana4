<?php
/**
 * Thrown when a task is started with options that are not valid. The
 * errors themselves were already written; this only ends the task with an
 * error status.
 *
 * @package    Kohana/Minion
 *
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Minion_Exception_InvalidOptions extends Minion_Exception {

	public function format_for_cli(): string
	{
		// The errors were written one by one; this is the hint that follows
		return $this->getMessage();
	}

}
