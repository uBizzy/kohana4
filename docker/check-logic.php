<?php
/**
 * Reports assignments that do not assign what they seem to.
 *
 * In PHP "=" binds tighter than the words "and", "or" and "xor":
 *
 *     $allowed = $is_admin OR $is_owner;    // assigns $is_admin only
 *
 * The line reads as "one or the other" and does something else, without
 * an error or a warning. Write the right side in brackets, or put the
 * test in the "if" itself:
 *
 *     $allowed = ($is_admin OR $is_owner);
 *
 * Only statements are looked at. Inside the brackets of an "if", an
 * assignment followed by "and" is written on purpose and does what it
 * says: "if ($row = $result->current() AND $row->active)".
 *
 *     php docker/check-logic.php            (run from the project root)
 *     php docker/check-logic.php extra
 *
 * Without arguments the system, the modules, the extra modules, the
 * application and this directory are checked, tests included. Ends with
 * an error status when something is found.
 */
$root  = dirname(__DIR__);
$paths = array_slice($argv, 1) ?: [$root.'/system', $root.'/modules', $root.'/extra', $root.'/application', $root.'/docker', $root.'/public'];
$files = [];

foreach ($paths as $path)
{
	if ( ! file_exists($path))
	{
		fwrite(STDERR, 'Not found: '.$path.PHP_EOL);
		exit(2);
	}

	if (is_file($path))
	{
		$files[] = $path;
		continue;
	}

	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file)
	{
		if ($file->getExtension() === 'php' AND ! str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR))
		{
			$files[] = $file->getPathname();
		}
	}
}

sort($files);

if ( ! $files)
{
	fwrite(STDERR, 'No PHP file to check'.PHP_EOL);
	exit(2);
}

// The tokens that assign
$assignments = [
	T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL, T_CONCAT_EQUAL, T_MOD_EQUAL, T_AND_EQUAL,
	T_OR_EQUAL, T_XOR_EQUAL, T_SL_EQUAL, T_SR_EQUAL, T_POW_EQUAL, T_COALESCE_EQUAL,
];

$total = 0;

foreach ($files as $file)
{
	// How deep in brackets each open statement is, and whether it has
	// assigned at that depth. A statement ends at ";", "{" or "}".
	$depth    = 0;
	$assigned = [];

	foreach (token_get_all((string) file_get_contents($file)) as $token)
	{
		$id   = is_array($token) ? $token[0] : $token;
		$line = is_array($token) ? $token[2] : null;

		if ($id === '(' OR $id === '[')
		{
			$depth++;
		}
		elseif ($id === ')' OR $id === ']')
		{
			unset($assigned[$depth]);

			$depth = max(0, $depth - 1);
		}
		elseif ($id === ';' OR $id === '{' OR $id === '}' OR $id === ',')
		{
			// A new statement, or a new argument inside brackets
			unset($assigned[$depth]);
		}
		elseif ($id === '=' OR in_array($id, $assignments, true))
		{
			$assigned[$depth] = true;
		}
		elseif ($depth === 0 AND in_array($id, [T_LOGICAL_AND, T_LOGICAL_OR, T_LOGICAL_XOR], true) AND isset($assigned[0]))
		{
			echo (str_starts_with($file, $root.'/') ? substr($file, strlen($root) + 1) : $file).':'.$line
				.': the assignment ends before "'.$token[1].'": put the right side in brackets'.PHP_EOL;

			$total++;

			// One report for each statement
			unset($assigned[$depth]);
		}
	}
}

echo count($files).' files checked, '.$total.' misleading '.(($total === 1) ? 'assignment' : 'assignments').PHP_EOL;

exit($total ? 1 : 0);
