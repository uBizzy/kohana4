<ul>
<?php foreach ($menu as $package => $categories): ksort($categories) ?>
	<li><?php echo HTML::chars($package) ?>
		<ul>
		<?php foreach ($categories as $category => $classes): sort($classes) ?>
			<?php if ($category === 'Base'): ?>
				<?php foreach ($classes as $class): ?>
				<li><?php echo $class ?></li>
				<?php endforeach ?>
			<?php else: ?>
				<li><?php echo HTML::chars($category) ?>
					<ul>
					<?php foreach ($classes as $class): ?>
						<li><?php echo $class ?></li>
					<?php endforeach ?>
					</ul>
				</li>
			<?php endif ?>
		<?php endforeach ?>
		</ul>
	</li>
<?php endforeach ?>
</ul>
