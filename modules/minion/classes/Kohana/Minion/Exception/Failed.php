<?php
/**
 * Thrown by a task that ran and has to report that the result is bad,
 * such as a check that did not pass. Only the message is written: there
 * is no error in the code to point at. The command ends with an error
 * status, so a script that called it stops.
 *
 *     throw new Minion_Exception_Failed('2 checks failed.');
 *
 * @package    Kohana/Minion
 *
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Minion_Exception_Failed extends Minion_Exception {

	public function format_for_cli(): string
	{
		return $this->getMessage();
	}

}
