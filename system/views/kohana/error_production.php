<?php
/**
 * The error page shown in production.
 *
 * It must never say anything about the error, the code, the server or
 * the software: only that something went wrong, and a reference code.
 * The full error is in the log under that code.
 *
 * Variables:
 *
 * - $status     the HTTP status of the response
 * - $title      a general message ("Not Found", "An error occurred")
 * - $reference  the code under which the error is in the log
 */
$e = fn ($text): string => htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<title><?php echo $e($title) ?></title>
	<style nonce="<?php echo $e(Security::nonce()) ?>">
		<?php echo Theme::css() ?>

		html { color-scheme: var(--k-scheme); }
		body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--k-bg); color: var(--k-text); font: 16px/1.5 var(--k-font); }
		main { max-width: 32em; padding: 2em; text-align: center; }
		h1 { margin: 0 0 0.4em; font-size: 1.5em; font-weight: 600; }
		p { margin: 0.4em 0; color: var(--k-muted); }
		code { padding: 0.15em 0.5em; border-radius: var(--k-radius); background: var(--k-border); color: var(--k-text); font: 0.95em var(--k-font-code); }
		.k-theme-button { margin-top: 2em; font: inherit; font-size: 0.8em; padding: 0.1em 0.8em; border-radius: 999px; border: 1px solid var(--k-border); background: none; color: var(--k-muted); cursor: pointer; }
	</style>
	<script nonce="<?php echo $e(Security::nonce()) ?>"><?php echo Theme::script() ?></script>
</head>
<body>
<main>
	<h1><?php echo $e($title) ?></h1>
<?php if ($status >= 500): // Trying again does not help when the page is not there or not allowed ?>
	<p><?php echo $e(I18n::get('Please try again later.')) ?></p>
<?php endif ?>
	<p><?php echo $e(I18n::get('Reference')) ?>: <code><?php echo $e($reference) ?></code></p>
	<?php echo Theme::button() ?>

</main>
</body>
</html>
