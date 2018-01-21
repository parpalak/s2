<?php
/**
 * Content of <!-- s2_blog_back_forward --> placeholder
 *
 * @var array $back
 * @var array $forward
 */

?>
<ul class="back_forward">
<?php if (!empty($back)) { ?>
	<li class="back">
		<span class="arrow">&larr;</span>
		<a href="<?php echo s2_htmlencode($back['link']); ?>"><?php echo s2_htmlencode($back['title']); ?></a>
	</li>
<?php } else { ?>
	<li class="back empty">
		<span class="arrow">&larr;</span>
	</li>
<?php } ?>
<?php if (!empty($forward)) { ?>
	<li class="forward">
		<span class="arrow">&rarr;</span>
		<a href="<?php echo s2_htmlencode($forward['link']); ?>"><?php echo s2_htmlencode($forward['title']); ?></a>
	</li>
<?php } else { ?>
	<li class="forward empty">
		<span class="arrow">&rarr;</span>
	</li>
<?php } ?>
</ul>
