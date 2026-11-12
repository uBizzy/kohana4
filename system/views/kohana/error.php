<?php
/**
 * The error page shown while developing.
 *
 * Variables:
 *
 * - $error      the description of the error, from Kohana_Exception::describe()
 * - $reference  the code under which the error is in the log
 * - $status     the HTTP status of the response
 *
 * In production this page is never used: see kohana/error_production.
 */

// Prefix of the ids on this page
$uid = 'k'.bin2hex(random_bytes(4));

// Escapes text for HTML
$e = fn ($text): string => htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, Kohana::$charset);

// Levels of arrays and objects shown for each value. Enough to recognise
// it: a full dump of large objects (a request, a model) can take hundreds
// of megabytes and make the error page itself fail.
$depth = 3;

// The link that opens a file in the code editor (nothing when no editor is set)
$open = function (?string $file, ?int $line) use ($e): string
{
	$link = Debug::editor_link($file, $line);

	return ($link === null) ? '' : ' <a class="k-open" href="'.$e($link).'">'.$e(I18n::get('open')).'</a>';
};

// Numbers the parts that open and close
$part = 0;

// Was any value passed to a function recorded? (PHP can be set not to record them)
$has_arguments = false;

/**
 * Prints one error: its header, the source of the line, and the steps
 * that led to it. Called for the error itself and for each of its causes.
 */
$show = function (array $error, bool $is_cause) use (&$show, &$part, &$has_arguments, $uid, $e, $open, $reference, $status, $depth): void
{
	// The first step in the application's own code is shown open
	$open_step = null;

	foreach ($error['trace'] as $i => $step)
	{
		$has_arguments = ($has_arguments OR ! empty($step['args']));

		if ($open_step === null AND $step['app'])
		{
			$open_step = $i;
		}
	}
	?>
	<header class="k-head<?php echo $is_cause ? ' k-cause' : '' ?>">
		<<?php echo $is_cause ? 'h2' : 'h1' ?>>
			<span class="k-type"><?php echo $is_cause ? $e(I18n::get('Caused by')).': ' : '' ?><?php echo $e($error['class']) ?> [ <?php echo $e($error['code']) ?> ]:</span>
			<span class="k-message"><?php echo $e($error['message']) ?></span>
		</<?php echo $is_cause ? 'h2' : 'h1' ?>>
		<p class="k-where"><?php echo $e(Debug::path($error['file'])) ?> [ <?php echo $e($error['line']) ?> ]<?php echo $open($error['file'], $error['line']) ?></p>
		<?php if ( ! $is_cause): ?>
		<p class="k-meta">
			<span>HTTP <?php echo $e($status) ?></span>
			<span><?php echo $e(I18n::get('Reference')) ?>: <code><?php echo $e($reference) ?></code></span>
			<button type="button" class="k-button" data-k-copy="<?php echo $uid ?>plain" data-k-copied="<?php echo $e(I18n::get('Copied')) ?>"><?php echo $e(I18n::get('Copy as text')) ?></button>
			<?php echo Theme::button() ?>
		</p>
		<?php endif ?>
	</header>
	<div class="k-content">
		<?php echo Debug::source($error['file'], $error['line']) ?>

		<?php if ($error['fatal']): ?>
			<p class="k-note"><?php echo $e(I18n::get('PHP gives no trace for this kind of error.')) ?></p>
		<?php endif ?>

		<?php if ($error['trace']): ?>
		<ol class="k-trace">
			<?php foreach ($error['trace'] as $i => $step): ?>
			<li<?php echo $step['app'] ? ' class="k-app"' : '' ?>>
				<span class="k-step">
					<?php if ($step['file'] !== null): $source_id = $uid.'s'.(++$part); ?>
						<button type="button" data-k-toggle="<?php echo $source_id ?>" aria-expanded="<?php echo $i === $open_step ? 'true' : 'false' ?>"><?php echo $e(Debug::path($step['file'])) ?> [ <?php echo $e($step['line']) ?> ]</button><?php echo $open($step['file'], $step['line']) ?>
					<?php else: $source_id = null; ?>
						{<?php echo $e(I18n::get('PHP internal call')) ?>}
					<?php endif ?>
					&raquo;
					<code><?php echo $e($step['function']) ?></code>(<?php if ($step['args']): $args_id = $uid.'a'.(++$part); ?><button type="button" data-k-toggle="<?php echo $args_id ?>" aria-expanded="false"><?php echo $e(I18n::get('arguments')) ?></button><?php else: $args_id = null; endif ?>)
					<?php if ($step['app']): ?><span class="k-tag"><?php echo $e(I18n::get('application')) ?></span><?php endif ?>
				</span>
				<?php if ($args_id !== null): ?>
				<div id="<?php echo $args_id ?>" class="k-collapsed">
					<table class="k-values">
						<?php foreach ($step['args'] as $name => $arg): ?>
						<tr>
							<td><code><?php echo $e($name) ?></code></td>
							<td><pre><?php echo Debug::dump_named($name, $arg, $depth) ?></pre></td>
						</tr>
						<?php endforeach ?>
					</table>
				</div>
				<?php endif ?>
				<?php if ($source_id !== null): ?>
				<div id="<?php echo $source_id ?>"<?php echo $i === $open_step ? '' : ' class="k-collapsed"' ?>>
					<?php echo Debug::source($step['file'], (int) $step['line']) ?>
				</div>
				<?php endif ?>
			</li>
			<?php endforeach ?>
		</ol>
		<?php endif ?>
	</div>
	<?php

	if ($error['previous'] !== null)
	{
		$show($error['previous'], true);
	}
};

/**
 * Prints a table of names and values, hiding the secret ones.
 */
$values = function (iterable $rows) use ($e, $depth): void
{
	?>
	<table class="k-values">
		<?php foreach ($rows as $name => $value): ?>
		<tr>
			<td><code><?php echo $e($name) ?></code></td>
			<td><pre><?php echo Debug::dump_named($name, $value, $depth) ?></pre></td>
		</tr>
		<?php endforeach ?>
	</table>
	<?php
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="<?php echo $e(Kohana::$charset) ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<title><?php echo $e($error['class']) ?>: <?php echo $e($error['message']) ?></title>
	<style nonce="<?php echo $e(Security::nonce()) ?>"><?php echo Theme::css() ?><?php include Kohana::find_file('views', 'kohana/debug', 'css') ?></style>
	<script nonce="<?php echo $e(Security::nonce()) ?>"><?php echo Theme::script() ?></script>
</head>
<body class="k-debug">
<main id="kohana_error">
	<?php $show($error, false) ?>

	<?php if ( ! $has_arguments AND ini_get('zend.exception_ignore_args')): ?>
		<p class="k-content k-note"><?php echo $e(I18n::get('The values passed to each function are not shown: PHP is set not to record them (zend.exception_ignore_args).')) ?></p>
	<?php endif ?>

	<?php if (Kohana::$notices): ?>
	<h2 class="k-bar"><button type="button" data-k-toggle="<?php echo $uid ?>notices" aria-expanded="false"><?php echo $e(I18n::get('Notices of this request')) ?> (<?php echo count(Kohana::$notices) ?>)</button></h2>
	<div id="<?php echo $uid ?>notices" class="k-content k-collapsed">
		<?php foreach (Kohana::$notices as $notice): ?>
			<p><strong><?php echo $e($notice['code']) ?>:</strong> <?php echo $e($notice['message']) ?><br><small><?php echo $e(Debug::path($notice['file'])) ?> [ <?php echo $e($notice['line']) ?> ]</small></p>
		<?php endforeach ?>
	</div>
	<?php endif ?>

	<h2 class="k-bar"><button type="button" data-k-toggle="<?php echo $uid ?>env" aria-expanded="false"><?php echo $e(I18n::get('Environment')) ?></button></h2>
	<div id="<?php echo $uid ?>env" class="k-content k-collapsed">
		<?php $included = get_included_files() ?>
		<h3><button type="button" data-k-toggle="<?php echo $uid ?>files" aria-expanded="false"><?php echo $e(I18n::get('Included files')) ?></button> (<?php echo count($included) ?>)</h3>
		<div id="<?php echo $uid ?>files" class="k-collapsed">
			<table class="k-values">
				<?php foreach ($included as $included_file): ?>
				<tr><td><code><?php echo $e(Debug::path($included_file)) ?></code></td></tr>
				<?php endforeach ?>
			</table>
		</div>

		<?php $extensions = get_loaded_extensions() ?>
		<h3><button type="button" data-k-toggle="<?php echo $uid ?>ext" aria-expanded="false"><?php echo $e(I18n::get('Loaded extensions')) ?></button> (<?php echo count($extensions) ?>)</h3>
		<div id="<?php echo $uid ?>ext" class="k-collapsed">
			<table class="k-values">
				<?php foreach ($extensions as $extension): ?>
				<tr><td><code><?php echo $e($extension) ?></code></td></tr>
				<?php endforeach ?>
			</table>
		</div>

		<?php foreach (['_SESSION', '_GET', '_POST', '_FILES', '_COOKIE', '_SERVER'] as $global): ?>
			<?php if (empty($GLOBALS[$global]) OR ! is_array($GLOBALS[$global])) continue ?>
			<h3><button type="button" data-k-toggle="<?php echo $uid.strtolower($global) ?>" aria-expanded="false">$<?php echo $e($global) ?></button> (<?php echo count($GLOBALS[$global]) ?>)</h3>
			<div id="<?php echo $uid.strtolower($global) ?>" class="k-collapsed">
				<?php
				// Every cookie can identify the visitor: none of their values is shown
				$values($global === '_COOKIE' ? array_fill_keys(array_map(fn ($name) => 'cookie '.$name, array_keys($GLOBALS[$global])), '-') : $GLOBALS[$global]);
				?>
			</div>
		<?php endforeach ?>
	</div>
</main>
<textarea id="<?php echo $uid ?>plain" hidden readonly><?php echo $e(Kohana_Exception::plain($error)) ?></textarea>
<script nonce="<?php echo $e(Security::nonce()) ?>"><?php include Kohana::find_file('views', 'kohana/debug', 'js') ?></script>
</body>
</html>
