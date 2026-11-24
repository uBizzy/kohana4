<?php
/**
 * The secrets of the application. Copy this file to
 * application/config/app.php and fill it in there: application/config/
 * is kept out of version control, so a secret never ends up in a
 * repository. No other file of the application should hold one.
 */
return [
	/**
	 * The key that signs cookies: a long random value, only for this
	 * site. Without it no cookie can be set. Make one with:
	 *
	 *     php -r 'echo bin2hex(random_bytes(32)), "\n";'
	 */
	'cookie_salt' => null,
];
