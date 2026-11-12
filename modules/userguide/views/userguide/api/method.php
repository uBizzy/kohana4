<?php $declares = $doc->method->getDeclaringClass() ?>
<section class="method">

<h3 id="<?php echo $doc->method->name ?>">
	<?php echo $doc->modifiers, $doc->method->name ?>( <?php echo $doc->params_short() ?>)
	<small>(defined in <?php echo HTML::anchor($route->uri(['class' => $declares->name]), $declares->name, null, null, true) ?>)</small>
</h3>

<div class="description">
<?php echo $doc->description ?>
</div>

<?php if ($doc->params): ?>
<h4>Parameters</h4>
<ul>
<?php foreach ($doc->params as $param): ?>
	<li>
		<code><?php echo HTML::chars($param->type ?? 'mixed') ?></code>
		<strong><?php echo ($param->reference ? '&amp;' : ''), ($param->variadic ? '...' : ''), '$', $param->name ?></strong>
		<?php echo ($param->default !== null) ? '<small> = '.$param->default.'</small>' : ($param->optional ? '' : '<small>required</small>') ?>
		<?php echo ($param->description !== '') ? ' - '.HTML::chars($param->description) : '' ?>
	</li>
<?php endforeach ?>
</ul>
<?php endif ?>

<?php if ($doc->tags) echo View::factory('userguide/api/tags')->set('tags', $doc->tags) ?>

<?php if ($doc->return): ?>
<h4>Returns</h4>
<ul class="return">
<?php foreach ($doc->return as [$type, $text]): ?>
	<li><code><?php echo HTML::chars($type) ?></code><?php if ($text) echo ' - '.HTML::chars(ucfirst($text)) ?></li>
<?php endforeach ?>
</ul>
<?php endif ?>

<?php if ($doc->source): ?>
<details class="method-source">
	<summary>Source code</summary>
	<?php echo Kodoc_Markdown::markdown("```php\n".$doc->source."\n```") ?>
</details>
<?php endif ?>

</section>
