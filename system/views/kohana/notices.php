<?php
/**
 * The panel that lists the notices of a request (deprecations, by
 * default) at the bottom of the page, while developing.
 *
 * Variables:
 *
 * - $notices  list of notices, each with code, message, file and line
 */
$uid = 'k'.bin2hex(random_bytes(4));

$e = fn ($text): string => htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, Kohana::$charset);
?>
<style nonce="<?php echo $e(Security::nonce()) ?>"><?php echo Theme::css() ?><?php include Kohana::find_file('views', 'kohana/debug', 'css') ?></style>
<script nonce="<?php echo $e(Security::nonce()) ?>"><?php echo Theme::script() ?></script>
<div id="kohana_notices" class="k-debug">
	<h2 class="k-bar"><button type="button" data-k-toggle="<?php echo $uid ?>list" aria-expanded="false"><?php echo $e(I18n::get('PHP notices')) ?> (<?php echo count($notices) ?>)</button> <?php echo Theme::button() ?></h2>
	<ul id="<?php echo $uid ?>list" class="k-collapsed">
		<?php foreach ($notices as $notice): ?>
		<li>
			<strong><?php echo $e($notice['code']) ?>:</strong> <?php echo $e($notice['message']) ?><br>
			<small><?php echo $e(Debug::path($notice['file'])) ?> [ <?php echo $e($notice['line']) ?> ]</small>
		</li>
		<?php endforeach ?>
	</ul>
</div>
<script nonce="<?php echo $e(Security::nonce()) ?>"><?php include Kohana::find_file('views', 'kohana/debug', 'js') ?></script>
