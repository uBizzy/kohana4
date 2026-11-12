<?php
/**
 * @package    Kohana
 * @category   Exceptions
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Session_Exception extends Kohana_Exception {

	// No longer thrown: a corrupt session is now destroyed and restarted.
	// The constant is kept so existing code that refers to it still loads.
	const int SESSION_CORRUPT = 1;

}
