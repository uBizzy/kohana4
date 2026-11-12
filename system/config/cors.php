<?php
/**
 * Cross-Origin Resource Sharing: which other sites may call this
 * application from JavaScript in a browser. See the CORS class.
 *
 * Copy this file to application/config/cors.php to change it.
 */
return [

	/**
	 * Sites that are allowed, each written as scheme://host (and :port if
	 * it is not the default one), e.g. 'https://app.example.com'.
	 * ['*'] allows every site. An empty list switches CORS off.
	 */
	'origins' => [],

	/**
	 * HTTP methods those sites may use.
	 */
	'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

	/**
	 * Request headers those sites may send.
	 */
	'headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],

	/**
	 * Response headers their scripts may read, besides the basic ones.
	 */
	'expose' => [],

	/**
	 * May the browser send cookies and login information with the request?
	 * Only switch this on for sites you trust: they can then act as the
	 * logged-in user. Never combine it with the origins ['*'].
	 */
	'credentials' => false,

	/**
	 * Seconds a browser may remember the answer to a preflight request.
	 */
	'max_age' => 600,

];
