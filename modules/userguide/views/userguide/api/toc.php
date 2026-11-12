<h1>API Browser</h1>

<p><input type="search" id="kodoc-api-filter" placeholder="Filter classes and methods" aria-label="Filter classes and methods"></p>

<div class="class-list">
	<?php foreach ($classes as $class => $methods): $link = $route->uri(['class' => $class]) ?>
	<section class="class">
		<h2><?php echo HTML::anchor($link, $class, null, null, true) ?></h2>
		<ul class="methods">
		<?php foreach ($methods as $method): ?>
			<li><?php echo HTML::anchor($link.'#'.$method, $method.'()', null, null, true) ?></li>
		<?php endforeach ?>
		</ul>
	</section>
	<?php endforeach ?>
</div>
