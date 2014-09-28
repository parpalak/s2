<?php

/** @var array $links */

?>
<ul class="back_forward">
<?php if (!empty($links['up'])) { ?>
	<li class="up">
		<span class="arrow">&uarr;</span>
		<a href="<?php echo s2_htmlencode($links['up']['link']); ?>"><?php echo s2_htmlencode($links['up']['title']); ?></a>
	</li>
<?php } else { ?>
	<li class="up empty">
		<span class="arrow">&uarr;</span>
	</li>
<?php } ?>
<?php if (!empty($links['back'])) { ?>
	<li class="back">
		<span class="arrow">&larr;</span>
		<a href="<?php echo s2_htmlencode($links['back']['link']); ?>"><?php echo s2_htmlencode($links['back']['title']); ?></a>
	</li>
<?php } else { ?>
	<li class="back empty">
		<span class="arrow">&larr;</span>
	</li>
<?php } ?>
<?php if (!empty($links['forward'])) { ?>
	<li class="forward">
		<span class="arrow">&rarr;</span>
		<a href="<?php echo s2_htmlencode($links['forward']['link']); ?>"><?php echo s2_htmlencode($links['forward']['title']); ?></a>
	</li>
<?php } else { ?>
	<li class="forward empty">
		<span class="arrow">&rarr;</span>
	</li>
<?php } ?>
</ul>
