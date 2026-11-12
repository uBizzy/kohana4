<?php
/**
 * The profiler: time and memory of every benchmark of the request.
 *
 *     echo View::factory('profiler/stats');
 */
?>
<style nonce="<?php echo HTML::chars(Security::nonce()) ?>">
<?php echo Theme::css() ?>
<?php include Kohana::find_file('views', 'kohana/debug', 'css') ?>
<?php include Kohana::find_file('views', 'profiler/style', 'css') ?>
</style>
<script nonce="<?php echo HTML::chars(Security::nonce()) ?>"><?php echo Theme::script() ?></script>

<?php
$group_stats      = Profiler::group_stats();
$group_cols       = ['min', 'max', 'average', 'total'];
$application_cols = ['min', 'max', 'average', 'current'];
?>

<div class="kohana k-debug">
	<?php foreach (Profiler::groups() as $group => $benchmarks): ?>
	<table class="profiler">
		<tr class="group">
			<th class="name" rowspan="2"><?php echo HTML::chars(I18n::get(ucfirst($group))) ?></th>
			<td class="time" colspan="4"><?php echo number_format($group_stats[$group]['total']['time'], 6) ?> <abbr title="seconds">s</abbr></td>
		</tr>
		<tr class="group">
			<td class="memory" colspan="4"><?php echo number_format($group_stats[$group]['total']['memory'] / 1024, 4) ?> <abbr title="kilobyte">kB</abbr></td>
		</tr>
		<tr class="headers">
			<th class="name"><?php echo I18n::get('Benchmark') ?></th>
			<?php foreach ($group_cols as $key): ?>
			<th class="<?php echo $key ?>"><?php echo I18n::get(ucfirst($key)) ?></th>
			<?php endforeach ?>
		</tr>
		<?php foreach ($benchmarks as $name => $tokens): ?>
		<tr class="mark time">
			<?php $stats = Profiler::stats($tokens) ?>
			<th class="name" rowspan="2" scope="rowgroup"><?php echo HTML::chars($name), ' (', count($tokens), ')' ?></th>
			<?php foreach ($group_cols as $key): ?>
			<td class="<?php echo $key ?>">
				<div>
					<div class="value"><?php echo number_format($stats[$key]['time'], 6) ?> <abbr title="seconds">s</abbr></div>
					<?php if ($key === 'total'): ?>
						<div class="graph" data-k-left="<?php echo $group_stats[$group]['max']['time'] ? max(0, 100 - $stats[$key]['time'] / $group_stats[$group]['max']['time'] * 100) : '0' ?>"></div>
					<?php endif ?>
				</div>
			</td>
			<?php endforeach ?>
		</tr>
		<tr class="mark memory">
			<?php foreach ($group_cols as $key): ?>
			<td class="<?php echo $key ?>">
				<div>
					<div class="value"><?php echo number_format($stats[$key]['memory'] / 1024, 4) ?> <abbr title="kilobyte">kB</abbr></div>
					<?php if ($key === 'total'): ?>
						<div class="graph" data-k-left="<?php echo $group_stats[$group]['max']['memory'] ? max(0, 100 - $stats[$key]['memory'] / $group_stats[$group]['max']['memory'] * 100) : '0' ?>"></div>
					<?php endif ?>
				</div>
			</td>
			<?php endforeach ?>
		</tr>
		<?php endforeach ?>
	</table>
	<?php endforeach ?>

	<table class="profiler">
		<?php $stats = Profiler::application() ?>
		<tr class="final mark time">
			<th class="name" rowspan="2" scope="rowgroup"><?php echo I18n::get('Application Execution').' ('.$stats['count'].')' ?></th>
			<?php foreach ($application_cols as $key): ?>
			<td class="<?php echo $key ?>"><?php echo number_format($stats[$key]['time'], 6) ?> <abbr title="seconds">s</abbr></td>
			<?php endforeach ?>
		</tr>
		<tr class="final mark memory">
			<?php foreach ($application_cols as $key): ?>
			<td class="<?php echo $key ?>"><?php echo number_format($stats[$key]['memory'] / 1024, 4) ?> <abbr title="kilobyte">kB</abbr></td>
			<?php endforeach ?>
		</tr>
	</table>
</div>
<script nonce="<?php echo HTML::chars(Security::nonce()) ?>"><?php include Kohana::find_file('views', 'kohana/debug', 'js') ?></script>
