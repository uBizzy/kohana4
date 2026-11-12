<?php
/**
 * Loads every class of the framework, its modules and the application, one PHP process per
 * class, and reports the ones that fail. PHP only checks that a child class
 * is compatible with its parent when the class is loaded, so this finds
 * signature and property-type mismatches that a syntax check cannot.
 *
 *     php docker/load-all-classes.php            (run from the project root)
 */
$root = dirname(__DIR__);

// Modules that are not maintained yet
$skip = [];

if (isset($argv[1]))
{
	// Child process: boot the framework and load the one class given
	ob_start();
	require $root.'/modules/unittest/bootstrap.php';
	ob_end_clean();
	$loaded = (class_exists($argv[1]) OR interface_exists($argv[1]) OR trait_exists($argv[1]));

	exit($loaded ? 0 : 1);
}

$failed = 0; $total = 0;

foreach (array_merge([$root.'/system/classes', $root.'/application/classes'], glob($root.'/modules/*/classes')) as $dir)
{
	if (in_array(basename(dirname($dir)), $skip, true))
		continue;

	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file)
	{
		if ($file->getExtension() !== 'php')
			continue;

		// classes/Kohana/Session/Native.php is the class Kohana_Session_Native
		$class = str_replace('/', '_', substr($file->getPathname(), strlen($dir) + 1, -4));
		$total++;

		$output = [];
		exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($class).' 2>&1', $output, $status);

		if ($status !== 0)
		{
			$failed++;
			// Keep the first meaningful line of the error
			$lines = array_values(array_filter(array_map('strip_tags', $output), fn ($l) => trim($l) !== ''));
			echo $class.': '.trim($lines[0] ?? 'could not be loaded').PHP_EOL;
		}
	}
}

echo $total.' classes checked, '.$failed.' failed'.PHP_EOL;
exit($failed ? 1 : 0);
