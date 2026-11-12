<h4>Tags</h4>
<ul class="tags">
<?php foreach ($tags as $name => $set): ?>
	<li><?php echo HTML::chars(ucfirst($name)), ($set ? ' - '.implode(', ', $set) : '') ?></li>
<?php endforeach ?>
</ul>
