<?php
/**
 * Page of the user guide and of the API browser.
 *
 * Variables: $title, $breadcrumb (address => text; the last has no
 * address), $menu (HTML), $content (HTML), $toc (headings of the page),
 * $copyright (HTML or null), $styles and $scripts (addresses).
 */
$nonce = HTML::chars(Security::nonce());
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<title><?php echo HTML::chars($title) ?> | Kohana User Guide</title>
	<?php echo Theme::favicon() ?>

	<style nonce="<?php echo $nonce ?>"><?php echo Theme::css() ?></style>
	<script nonce="<?php echo $nonce ?>"><?php echo Theme::script() ?></script>
	<?php foreach ($styles as $style): ?>
	<link rel="stylesheet" href="<?php echo HTML::chars(URL::site($style)) ?>" nonce="<?php echo $nonce ?>">
	<?php endforeach ?>
</head>
<body data-search="<?php echo HTML::chars(Route::url('docs/search')) ?>">

<header id="kodoc-header">
	<button type="button" id="kodoc-menu-button" aria-controls="kodoc-topics" aria-expanded="false">Menu</button>
	<a id="kodoc-logo" href="<?php echo HTML::chars(Route::url('docs/guide')) ?>"><?php echo Theme::logo() ?> <small><?php echo HTML::chars(Kohana::VERSION) ?></small></a>
	<nav id="kodoc-nav">
		<a href="<?php echo HTML::chars(Route::url('docs/guide')) ?>">User Guide</a>
		<?php if (Kohana::$config->load('userguide.api_browser') === true): ?>
		<a href="<?php echo HTML::chars(Route::url('docs/api')) ?>">API Browser</a>
		<?php endif ?>
	</nav>
	<?php echo Theme::button() ?>
	<div id="kodoc-search">
		<input type="search" id="kodoc-search-box" placeholder="Search the guide (press /)" autocomplete="off" aria-label="Search the guide">
		<ul id="kodoc-search-results" hidden></ul>
	</div>
</header>

<div id="kodoc-page">
	<nav id="kodoc-topics" aria-label="Pages">
		<?php echo $menu ?>
	</nav>

	<main id="kodoc-body">
		<?php if (count($breadcrumb) > 1): ?>
		<ol id="kodoc-breadcrumb">
			<?php foreach ($breadcrumb as $link => $text): ?>
			<li><?php echo is_string($link) ? HTML::anchor($link, HTML::chars($text), null, null, true) : HTML::chars($text) ?></li>
			<?php endforeach ?>
		</ol>
		<?php endif ?>

		<?php echo $content ?>
	</main>

	<?php // The headings of the page, without its title ?>
	<?php $toc = array_filter($toc, fn ($heading) => $heading['level'] > 1 AND $heading['level'] < 4) ?>
	<?php if (count($toc) > 1): ?>
	<nav id="kodoc-toc" aria-label="On this page">
		<strong>On this page</strong>
		<ul>
			<?php foreach ($toc as $heading): ?>
			<li class="level-<?php echo (int) $heading['level'] ?>"><a href="#<?php echo HTML::chars($heading['id']) ?>"><?php echo HTML::chars($heading['name']) ?></a></li>
			<?php endforeach ?>
		</ul>
	</nav>
	<?php endif ?>
</div>

<footer id="kodoc-footer">
	<span><?php echo $copyright ?? '' ?></span>
	<span>Kohana <?php echo HTML::chars(Kohana::VERSION) ?></span>
</footer>

<?php foreach ($scripts as $script): ?>
<script src="<?php echo HTML::chars(URL::site($script)) ?>" nonce="<?php echo $nonce ?>"></script>
<?php endforeach ?>
</body>
</html>
