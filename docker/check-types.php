<?php
/**
 * Reports functions, parameters, properties and constants of the framework
 * that have no type. PHP does not ask for types and the static analysis
 * accepts a type that is only written in a comment, so this is checked
 * on its own.
 *
 *     php docker/check-types.php            (run from the project root)
 *     php docker/check-types.php system/classes/Kohana/File.php
 *
 * Without arguments the classes of the system and of every module are
 * checked. Ends with an error status when something is found.
 */
$root  = dirname(__DIR__);
$paths = array_slice($argv, 1) ?: array_merge([$root.'/system/classes'], glob($root.'/modules/*/classes') ?: []);
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
		if ($file->getExtension() === 'php')
		{
			$files[] = $file->getPathname();
		}
	}
}

sort($files);

// Nothing to look at is a mistake in the call, not a clean result
if ( ! $files)
{
	fwrite(STDERR, 'No PHP file to check'.PHP_EOL);
	exit(2);
}

$total = 0;

foreach ($files as $file)
{
	// The code without comments and texts, which may hold the word "function"
	$code = '';

	foreach (token_get_all((string) file_get_contents($file)) as $token)
	{
		if (is_string($token))
		{
			$code .= $token;
		}
		elseif ( ! in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML], true))
		{
			$code .= $token[1];
		}
	}

	$found = [];

	// Named functions: name, parameter list, return type when there is one
	preg_match_all('/function\s+&?\s*(\w+)\s*\(((?:[^()]|\([^()]*\))*)\)\s*(:\s*[^{;]+)?/s', $code, $functions, PREG_SET_ORDER);

	foreach ($functions as $function)
	{
		if ( ! isset($function[3]) AND ! in_array($function[1], ['__construct', '__destruct', '__clone'], true))
		{
			$found[] = $function[1].'() has no return type';
		}

		// Split at the commas between parameters, not those inside a default value
		foreach (preg_split('/,(?![^\[(]*[\])])/', $function[2]) ?: [] as $parameter)
		{
			if (preg_match('/^(?:(?:public|protected|private|readonly)\s+)*(&?\s*\.{0,3}\$\w+)/', trim($parameter), $match))
			{
				$found[] = $function[1].'() has the untyped parameter '.$match[1];
			}
		}
	}

	preg_match_all('/^\s*(?:public|protected|private|var)(?:\s+static)?\s+(\$\w+)/m', $code, $properties);

	foreach ($properties[1] as $property)
	{
		$found[] = 'the property '.$property.' has no type';
	}

	preg_match_all('/^\s*(?:(?:public|protected|private|final)\s+)*const\s+(\w+)\s*=/m', $code, $constants);

	foreach ($constants[1] as $constant)
	{
		$found[] = 'the constant '.$constant.' has no type';
	}

	foreach ($found as $problem)
	{
		echo (str_starts_with($file, $root.'/') ? substr($file, strlen($root) + 1) : $file).': '.$problem.PHP_EOL;
	}

	$total += count($found);
}

echo count($files).' files checked, '.$total.' without a type'.PHP_EOL;

exit($total ? 1 : 0);
