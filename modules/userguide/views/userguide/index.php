<h1>User Guide</h1>

<?php if ($modules): ?>
<ul class="kodoc-guides">
	<?php foreach ($modules as $url => $options): ?>
	<li>
		<?php echo HTML::anchor(Route::get('docs/guide')->uri(['module' => $url]), HTML::chars($options['name']), null, null, true) ?>
		<span><?php echo HTML::chars($options['description'] ?? '') ?></span>
	</li>
	<?php endforeach ?>
</ul>
<?php else: ?>
<p class="note">No module has a guide.</p>
<?php endif ?>
