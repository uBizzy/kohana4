<?php $properties = $doc->properties(); $methods = $doc->methods() ?>
<h1>
	<?php echo $doc->modifiers, $doc->class->name ?>
	<?php foreach ($doc->parents as $parent): ?>
	<small class="extends">extends <?php echo HTML::anchor($route->uri(['class' => $parent->name]), $parent->name, null, null, true) ?></small>
	<?php endforeach ?>
</h1>

<?php if ($interfaces = $doc->class->getInterfaceNames()): ?>
<p class="interfaces"><small>Implements:
	<?php echo implode(' | ', array_map(fn ($name) => HTML::anchor($route->uri(['class' => $name]), $name, null, null, true), $interfaces)) ?>
</small></p>
<?php endif ?>

<?php if ($child = $doc->is_transparent($doc->class->name)): ?>
<p class="note">
	This is the base class of <?php echo HTML::anchor($route->uri(['class' => $child]), $child, null, null, true) ?>:
	use <code><?php echo $child ?></code> in your code, not this class.
</p>
<?php endif ?>

<?php echo $doc->description() ?>

<?php if ($doc->tags): ?>
<dl class="tags">
<?php foreach ($doc->tags() as $name => $set): ?>
	<dt><?php echo HTML::chars($name) ?></dt>
	<?php foreach ($set as $tag): ?>
	<dd><?php echo $tag ?></dd>
	<?php endforeach ?>
<?php endforeach ?>
</dl>
<?php endif ?>

<p class="declared"><small>
<?php if ($path = $doc->class->getFileName()): ?>
	Declared in <code><?php echo HTML::chars(Debug::path($path)) ?></code> on line <?php echo $doc->class->getStartLine() ?>.
<?php else: ?>
	A class of PHP itself: see the <?php echo HTML::anchor('https://www.php.net/manual/class.'.strtolower($doc->class->name).'.php', 'PHP manual') ?>.
<?php endif ?>
</small></p>

<div class="toc">
	<div>
		<h3>Constants</h3>
		<ul>
		<?php foreach ($doc->constants as $name => $value): ?>
			<li><a href="#constant:<?php echo $name ?>"><?php echo $name ?></a></li>
		<?php endforeach ?>
		<?php if ( ! $doc->constants): ?><li><em>None</em></li><?php endif ?>
		</ul>
	</div>
	<div>
		<h3>Properties</h3>
		<ul>
		<?php foreach ($properties as $prop): ?>
			<li><a href="#property:<?php echo $prop->property->name ?>">$<?php echo $prop->property->name ?></a></li>
		<?php endforeach ?>
		<?php if ( ! $properties): ?><li><em>None</em></li><?php endif ?>
		</ul>
	</div>
	<div>
		<h3>Methods</h3>
		<ul>
		<?php foreach ($methods as $method): ?>
			<li><a href="#<?php echo $method->method->name ?>"><?php echo $method->method->name ?>()</a></li>
		<?php endforeach ?>
		<?php if ( ! $methods): ?><li><em>None</em></li><?php endif ?>
		</ul>
	</div>
</div>

<?php if ($doc->constants): ?>
<h2 id="constants">Constants</h2>
<dl class="constants">
<?php foreach ($doc->constants() as $name => $value): ?>
	<dt id="constant:<?php echo $name ?>"><?php echo $name ?></dt>
	<dd><?php echo $value ?></dd>
<?php endforeach ?>
</dl>
<?php endif ?>

<?php if ($properties): ?>
<h2 id="properties">Properties</h2>
<dl class="properties">
<?php foreach ($properties as $prop): ?>
	<dt id="property:<?php echo $prop->property->name ?>"><?php echo $prop->modifiers ?> <code><?php echo HTML::chars($prop->type ?? 'mixed') ?></code> $<?php echo $prop->property->name ?></dt>
	<dd><?php echo $prop->description ?></dd>
	<?php if ($prop->value !== null): ?>
	<dd><?php echo $prop->value ?></dd>
	<?php endif ?>
	<?php if ($prop->default !== $prop->value): ?>
	<dd><small>Default value:</small> <?php echo $prop->default ?></dd>
	<?php endif ?>
<?php endforeach ?>
</dl>
<?php endif ?>

<?php if ($methods): ?>
<h2 id="methods">Methods</h2>
<div class="methods">
<?php foreach ($methods as $method): ?>
<?php echo View::factory('userguide/api/method')->set('doc', $method)->set('route', $route) ?>
<?php endforeach ?>
</div>
<?php endif ?>
