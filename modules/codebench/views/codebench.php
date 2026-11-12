<?php
/**
 * Page of the Codebench module: the list of benchmarks and the result of one.
 *
 * Variables: $benchmarks (name => class), $class (the one that ran, or
 * null), $result (from Codebench::run(), or null), $expand_all.
 */
$e = fn ($text): string => HTML::chars($text);
$nonce = $e(Security::nonce());
$route = Route::get('codebench');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<title><?php echo ($class !== null) ? $e($class).' | ' : '' ?>Codebench</title>
	<?php echo Theme::favicon() ?>

	<style nonce="<?php echo $nonce ?>">
		<?php echo Theme::css() ?>

		html { color-scheme: var(--k-scheme); }
		* { box-sizing: border-box; }
		body { margin: 0; font: 15px/1.5 var(--k-font); color: var(--k-text); background: var(--k-bg); }
		code, pre, .mono { font: 13px/1.45 var(--k-font-code); }
		a { color: var(--k-link); }
		header { display: flex; align-items: center; padding: 0.7rem 1.2rem; background: var(--k-bar); color: var(--k-bar-text); font-weight: 700; font-size: 1.1rem; }
		.k-theme-button { margin-left: auto; font: inherit; font-size: 0.8rem; font-weight: 400; padding: 0.1em 0.8em; border: 1px solid var(--k-edge); border-radius: 999px; background: var(--k-glass); color: inherit; cursor: pointer; }
		header a { color: inherit; text-decoration: none; display: flex; align-items: center; gap: 0.6rem; }
		header .k-logo { height: 1.7rem; width: auto; }
		.page { display: grid; grid-template-columns: 15rem minmax(0, 1fr); max-width: 80rem; margin: 0 auto; }
		nav { padding: 1.2rem; }
		nav strong { display: block; margin-bottom: 0.4rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--k-muted); }
		nav ul { margin: 0; padding: 0; list-style: none; }
		nav a { display: block; padding: 0.15em 0.6em; border-radius: var(--k-radius); color: var(--k-text); text-decoration: none; }
		nav a:hover { background: var(--k-border); }
		nav a.current { background: var(--k-app); color: var(--k-app-text); font-weight: 600; }
		main { min-height: 90vh; padding: 1.5rem 2rem 3rem; background: var(--k-surface); border-left: 1px solid var(--k-border); }
		h1 { margin: 0 0 0.5rem; font-size: 1.6rem; }
		p.meta { color: var(--k-muted); }
		details { margin-top: 0.6rem; border: 1px solid var(--k-border); border-radius: 6px; overflow: hidden; }
		summary { display: grid; grid-template-columns: 2.2rem minmax(8rem, 16rem) 1fr 9rem; gap: 0.8rem; align-items: center; padding: 0.5rem 0.8rem; cursor: pointer; }
		summary::-webkit-details-marker { display: none; }
		.grade { display: inline-block; width: 1.9rem; height: 1.9rem; line-height: 1.9rem; text-align: center; border-radius: 50%; font-weight: 700; }
		.grade-A { background: var(--k-app); } .grade-B { background: color-mix(in srgb, var(--k-app), var(--k-highlight)); } .grade-C { background: var(--k-highlight); }
		.grade-D { background: color-mix(in srgb, var(--k-highlight), var(--k-cause)); } .grade-E { background: var(--k-cause); } .grade-F { background: var(--k-error); }
		progress { width: 100%; height: 0.9rem; accent-color: var(--k-chart-1); }
		.numbers { text-align: right; color: var(--k-muted); font-size: 0.85rem; white-space: nowrap; }
		table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
		th, td { padding: 0.35em 0.8em; border-top: 1px solid var(--k-border); text-align: left; vertical-align: top; }
		th { background: var(--k-bg); font-weight: 600; }
		td.right, th.right { text-align: right; white-space: nowrap; }
		td pre { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; }
		@media (max-width: 50rem) { .page { display: block; } main { border: 0; padding: 1rem; } summary { grid-template-columns: 2.2rem 1fr; } }
	</style>
	<script nonce="<?php echo $nonce ?>"><?php echo Theme::script() ?></script>
</head>
<body>
<header><a href="<?php echo $e(URL::site($route->uri())) ?>"><?php echo Theme::logo() ?> Codebench</a><?php echo Theme::button() ?></header>

<div class="page">
	<nav aria-label="Benchmarks">
		<strong>Benchmarks</strong>
		<ul>
		<?php foreach ($benchmarks as $name => $benchmark_class): ?>
			<li><a href="<?php echo $e(URL::site($route->uri(['class' => $name]))) ?>"<?php echo ($benchmark_class === $class) ? ' class="current" aria-current="page"' : '' ?>><?php echo $e(substr($benchmark_class, 6)) ?></a></li>
		<?php endforeach ?>
		</ul>
	</nav>

	<main>
	<?php if ($result === null): ?>

		<h1>Codebench</h1>
		<?php if ($benchmarks): ?>
			<p>Choose a benchmark at the left. It runs when the page loads, so the page can take a while.</p>
		<?php else: ?>
			<p>There are no benchmarks yet. Add a class <code>Bench_&lt;Name&gt;</code> that extends <code>Codebench</code> in <code>application/classes/Bench/</code>.</p>
		<?php endif ?>

	<?php else: ?>

		<h1><?php echo $e(substr($result['class'], 6)) ?></h1>

		<?php if ($result['description'] !== ''): ?>
			<?php // The description is text; only addresses in it become links ?>
			<p><?php echo Text::auto_link($e($result['description'])) ?></p>
		<?php endif ?>

		<p class="meta">
			<?php echo count($result['benchmarks']) ?> methods &times; <?php echo count($result['subjects']) ?> subjects &times;
			<?php echo number_format($result['loops']['base']) ?> loops = <?php echo number_format($result['loops']['total']) ?> runs.
			The fastest method is 100&nbsp;%.
		</p>

		<?php if ( ! $result['benchmarks']): ?>
			<p>This class has no public method whose name starts with <code>bench_</code>.</p>
		<?php endif ?>

		<?php // The slowest method fills the bar ?>
		<?php $slowest = $result['benchmarks'] ? max(array_column($result['benchmarks'], 'percent')) : 100 ?>

		<?php foreach ($result['benchmarks'] as $method => $benchmark): ?>
		<details<?php echo $expand_all ? ' open' : '' ?>>
			<summary>
				<span class="grade grade-<?php echo $e($benchmark['grade']) ?>"><?php echo $e($benchmark['grade']) ?></span>
				<span class="mono"><?php echo $e($method) ?></span>
				<progress value="<?php echo round($benchmark['percent'], 1) ?>" max="<?php echo round($slowest, 1) ?>"><?php echo round($benchmark['percent']) ?> %</progress>
				<span class="numbers"><?php echo number_format($benchmark['percent']) ?>&nbsp;% &middot; <?php echo number_format($benchmark['time'], 4) ?>&nbsp;s</span>
			</summary>
			<table>
				<thead>
					<tr><th>Subject</th><th>Returned</th><th class="right">Time</th><th class="right">Of the fastest</th><th>Grade</th></tr>
				</thead>
				<tbody>
				<?php foreach ($benchmark['subjects'] as $key => $subject): ?>
					<tr>
						<td><pre><?php echo $e(var_export($result['subjects'][$key], true)) ?></pre></td>
						<td><pre><?php echo $e(var_export($subject['return'], true)) ?></pre></td>
						<td class="right"><?php echo number_format($subject['time'], 6) ?> s</td>
						<td class="right"><?php echo number_format($subject['percent']) ?> %</td>
						<td><span class="grade grade-<?php echo $e($subject['grade']) ?>"><?php echo $e($subject['grade']) ?></span></td>
					</tr>
				<?php endforeach ?>
				</tbody>
			</table>
		</details>
		<?php endforeach ?>

	<?php endif ?>
	</main>
</div>
</body>
</html>
