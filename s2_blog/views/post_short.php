<?php

/** @var $author string */
/** @var $title string */
/** @var $title_link string */
/** @var $time string */
/** @var $text string */
/** @var $tags array */
/** @var $commented bool */
/** @var $comment_num int */
/** @var $favorite bool */

foreach ($tags as &$tag)
	$tag = '<a class="preview_section" href="'.$tag['link'].'">'.$tag['title'].'</a>';
unset($tag);

?>
<h2 class="preview">
<?php if (!empty($tags)) { ?>
	<small><?php echo implode(', ', $tags); ?> &rarr;</small>
<?php } ?>
<?php if (!empty($title_link)) {?>
	<a href="<?php echo s2_htmlencode($title_link); ?>"><?php echo s2_htmlencode($title); ?></a>
<?php } else {?>
	<?php echo s2_htmlencode($title); ?>
<?php } ?>
</h2>
<div class="preview time"><?php echo $time; ?></div>
<div class="post body"><?php echo $text; ?></div>
