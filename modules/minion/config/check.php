<?php
/**
 * Settings of the task that checks the installation (./minion check).
 */
return [
	// Modules that are for development: the check warns when one of them
	// is enabled in production. A module adds itself to this list with a
	// config/check.php of its own that names it.
	'development_modules' => ['userguide', 'unittest', 'codebench'],
];
