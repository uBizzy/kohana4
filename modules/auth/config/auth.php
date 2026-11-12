<?php

return [

	'driver'  => 'File',
	// Passwords are hashed with PHP's password_hash(). These two settings
	// are only used to check passwords stored by versions before 4.0;
	// keep your old values here until all users have logged in once.
	'hash_method' => 'sha256',
	'hash_key' => null,
	'lifetime' => 1209600,
	'session_type' => Session::$default,
	'session_key' => 'auth_user',
	// For Auth_Bcrypt
	'cost' => 10,

	// Username/password combinations for the Auth File driver
	'users' => [
		// The value is the output of Auth::instance()->hash('the password'):
		// 'admin' => '$2y$12$...',
	],

];
