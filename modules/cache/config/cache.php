<?php
return [
/*
	'default' => 'file',                            // allows to specify default cache directl from config file
	'prefix'  => 'cache1_',                          //used to avoid duplicates when using _sanitize_id
	'apcu'   => [
		'driver'             => 'apcu',
		'default_expire'     => 3600,
		'prefix'             => 'cache_apcu_',       // if set uses this prefix instead of global 'prefix'
	],
	'sqlite'   => [
		'driver'             => 'sqlite',
		'default_expire'     => 3600,
		'database'           => APPPATH.'cache/kohana-cache.sql3',
		'schema'             => 'CREATE TABLE caches(id VARCHAR(127) PRIMARY KEY, tags VARCHAR(255), expiration INTEGER, cache TEXT)',
	],
	'file'    => [
		'driver'             => 'file',
		'cache_dir'          => APPPATH.'cache',
		'default_expire'     => 3600,
		'ignore_on_delete'   => array(
			'.gitignore',
			'.git',
			'.svn'
		)
	],
	'redis' => [
		'driver'             => 'redis',
		'default_expire'     => 3600,
		'cache_prefix'       => 'cache',
		'tag_prefix'         => '_tag',
		'servers' => [
			'local' => [
				//'host'     => 'unix:///var/run/redis/redis.sock',
				'host'       => 'localhost',
				'port'       => 6379,
				'persistent' => false,
				'prefix'     => '',
				'password'   => '',
			],
		],
	]
*/
];
