<?php

return [
	// Is the guide available? It shows the whole code of the application,
	// so by default it is not in production.
	'enabled' => Kohana::$environment !== Kohana::PRODUCTION,

	// Show the API browser?
	'api_browser' => true,

	// Packages shown in the API browser: true for all, or a comma separated
	// list of @package names ('None' for classes without one).
	// Example: 'api_packages' => 'Kohana,Kohana/Database,Kohana/ORM,None',
	'api_packages' => true,

	// The guides. Every module adds itself here, in its own config/userguide.php
	'modules' => [

		// The key is the name of the module's directory in "guide/"
		'userguide' => [

			// Show the guide of this module?
			'enabled' => true,

			// Name shown in the list of guides
			'name' => 'Userguide',

			// One line about the module, shown in the list of guides
			'description' => 'Documentation viewer and API browser.',

			// Shown at the bottom of the pages of this guide
			'copyright' => '&copy; 2008–2016 Kohana Team, 2016–2026 Koseven Team, since 2026 Konine Team',
		],
	],

	// Classes with these prefixes are base classes that are used through a
	// class without the prefix (Kohana_Arr through Arr): the API browser
	// shows them as one class.
	'transparent_prefixes' => [
		'Kohana' => true,
	],
];
