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

global $lang_s2_blog;

?>
<div class="post author"><?php if (!empty($author)) echo s2_htmlencode($author); ?></div>
<h2 class="post head">
<?php if (!empty($favorite) && $favorite != 2) {?>
	<a href="<?php echo S2_BLOG_PATH.urlencode(S2_FAVORITE_URL); ?>/" class="favorite-star" title="<?php echo $lang_s2_blog['Favorite']; ?>">*</a>
<?php } elseif (!empty($favorite)) {?>
	<span class="favorite-star" title="<?php echo $lang_s2_blog['Favorite']; ?>">*</span>
<?php } ?>
<?php if (!empty($title_link)) {?>
	<a href="<?php echo s2_htmlencode($title_link); ?>"><?php echo s2_htmlencode($title); ?></a>
<?php } else {?>
	<?php echo s2_htmlencode($title); ?>
<?php } ?>
</h2>
<div class="post time"><?php echo $time; ?></div>
<div class="post body"><?php echo $text; ?></div>
<div class="post foot">
<?php
	$footer = array();

	if (!empty($tags))
	{
		foreach ($tags as &$tag)
			$tag = '<a href="'.$tag['link'].'">'.$tag['title'].'</a>';
		unset($tag);

		$footer['tags'] = sprintf($lang_s2_blog['Tags:'], implode(', ', $tags));
	}

	if ($commented && S2_SHOW_COMMENTS)
		$footer['comments'] = '<a href="'.$link.'#comment">'.($comment_num ? sprintf($lang_s2_blog['Comments'], $comment_num) : (S2_ENABLED_COMMENTS ? $lang_s2_blog['Post comment'] : '')).'</a>';

	echo implode(' | ', $footer);
?>
</div>