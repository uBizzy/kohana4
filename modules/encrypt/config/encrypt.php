<?php
/**
 * The following options must be set:
 * string   type    Driver Type
 * string   key     Secret Passphrase
 */
return [
	'default' => [
		'type'	 => 'openssl',
		'key'	 => null,
		'cipher' => Encrypt_Engine_Openssl::AES_256_CBC
	],
//	  'sodium' => [
//		'type'    => 'sodium',
//		'key'     => null,
//		'cipher'  => Encrypt_Engine_Sodium::AES_256_GCM
//    ]
];
