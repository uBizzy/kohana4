<?php
/**
 * Tiny HTTP endpoint used by the external request tests: it answers with a
 * JSON description of the request it received. Served by PHP's built-in
 * web server; it is not part of the framework.
 *
 * What the query string can ask for:
 *
 *   wait=2            answer after two seconds (to test timeouts)
 *   redirect=URL      answer with a redirect to that address
 *   fail=N&id=X       answer with 503 the first N times this id is asked for
 *   status=404        answer with that status
 *   bytes=5000        answer with that many bytes instead of the description
 */
if (($_GET['wait'] ?? 0) > 0)
{
	sleep((int) $_GET['wait']);
}

if (isset($_GET['redirect']))
{
	header('Location: '.$_GET['redirect'], true, 302);
	exit;
}

if (isset($_GET['fail'], $_GET['id']))
{
	// Count the requests for this id in a file
	$counter = sys_get_temp_dir().'/kohana_http_echo_'.preg_replace('/\W/', '', $_GET['id']);
	$count   = (int) @file_get_contents($counter) + 1;

	file_put_contents($counter, (string) $count);

	if ($count <= (int) $_GET['fail'])
	{
		http_response_code(503);
		header('X-Attempt: '.$count);
		echo 'busy';
		exit;
	}

	header('X-Attempt: '.$count);
}

if (isset($_GET['status']))
{
	http_response_code((int) $_GET['status']);
}

header('X-Echo: yes');

if (isset($_GET['bytes']))
{
	header('Content-Type: application/octet-stream');
	echo str_repeat('x', (int) $_GET['bytes']);
	exit;
}

header('Content-Type: application/json');

$files = [];

foreach ($_FILES as $field => $file)
{
	$files[$field] = ['name' => $file['name'], 'type' => $file['type'], 'size' => $file['size'], 'content' => file_get_contents($file['tmp_name'])];
}

echo json_encode([
	'method'        => $_SERVER['REQUEST_METHOD'],
	'query'         => $_GET,
	'post'          => $_POST,
	'files'         => $files,
	'body'          => file_get_contents('php://input'),
	'content_type'  => $_SERVER['CONTENT_TYPE'] ?? null,
	'custom'        => $_SERVER['HTTP_X_CUSTOM'] ?? null,
	'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
]);
