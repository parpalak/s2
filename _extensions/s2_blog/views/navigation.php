<?php

/** @var $title string */
/** @var $last array */
/** @var $favorite array */
/** @var $tags array */
/** @var $tags_header array */

?>
<div class="header s2_blog_navigation">
	<?php echo $title; ?>
</div>
<ul class="s2_blog_navigation">
	<li>
<?php if (empty($last['is_current'])) {?>
		<a href="<?php echo s2_htmlencode($last['link']); ?>"><?php echo $last['title']; ?></a>
<?php } else { ?>
		<?php echo $last['title']; ?>
<?php } ?>
	</li>
<?php if(isset($favorite)): ?>
	<li>
<?php if (empty($favorite['is_current'])) {?>
		<a href="<?php echo s2_htmlencode($favorite['link']); ?>"><?php echo $favorite['title']; ?></a>
<?php } else { ?>
		<?php echo $favorite['title']; ?>
<?php } ?>
	</li>
<?php endif ?>
	<li>
<?php if (empty($tags_header['is_current'])) {?>
			<?php printf($tags_header['title'], '<a href="'.$tags_header['link'].'">', '</a>'); ?>
<?php } else { ?>
			<?php printf($tags_header['title'], '', ''); ?>
<?php } ?>
		<ul class="nav-tags-list">
<?php
foreach ($tags as $tag)
	if (!$tag['is_current'])
		echo '<li class="nav-tag"><a href="'.s2_htmlencode($tag['link']).'">'.s2_htmlencode($tag['title']).'</a></li>';
	else
		echo '<li class="nav-tag">', s2_htmlencode($tag['title']), '</li>';
?>
		</ul>
	</li>
</ul>
