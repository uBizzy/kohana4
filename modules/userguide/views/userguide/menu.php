<ul>
	<li>Guides
		<ul>
		<?php foreach ($modules as $url => $options): ?>
			<li><?php echo HTML::anchor(Route::get('docs/guide')->uri(['module' => $url]), HTML::chars($options['name']), null, null, true) ?></li>
		<?php endforeach ?>
		</ul>
	</li>
</ul>
