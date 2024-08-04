<?php

/** @var callable $trans */
/** @var $author string */
/** @var $title string */
/** @var $title_link string */
/** @var $time string */
/** @var $text string */
/** @var $tags array */
/** @var $link string */
/** @var $commented bool */
/** @var $comment_num int */
/** @var $favorite bool */
/** @var string $favoritePostsUrl */

?>
<div class="post author"><?php if (!empty($author)) echo s2_htmlencode($author); ?></div>
<h2 class="post head">
<?php if (!empty($title_link)) {?>
	<a href="<?php echo s2_htmlencode($title_link); ?>"><?php echo s2_htmlencode($title); ?></a>
<?php } else {?>
	<?php echo s2_htmlencode($title); ?>
<?php } ?>
<?php if (!empty($favorite) && $favorite != 2) {?>
    <a href="<?php echo $favoritePostsUrl; ?>" class="favorite-star" title="<?php echo $trans('Favorite posts'); ?>">★</a>
<?php } elseif (!empty($favorite)) {?>
    <span class="favorite-star" title="<?php echo $trans('Favorite posts'); ?>">★</span>
<?php } ?>
</h2>
<div class="post time"><?php echo $time; ?></div>
<?php
	echo $text;
	if (!empty($see_also))
		include 'see_also.php';
?>
<div class="post foot">
<?php
	$footer = array();

	if (!empty($tags))
	{
		foreach ($tags as &$tag)
			$tag = '<a href="'.$tag['link'].'">'.$tag['title'].'</a>';
		unset($tag);

		$footer['tags'] = $trans('Tags') . ': ' . implode(', ', $tags);
	}

	if ($commented && S2_SHOW_COMMENTS) {
        if ($comment_num) {
            $footer['comments'] = '<a href="' . $link . '#comment">' . $trans('N Comments', ['%count%' => $comment_num, '{{ count }}' => $comment_num]) . '</a>';
        } else {
            $footer['comments'] = '<a href="' . $link . '#add-comment">' . (S2_ENABLED_COMMENTS ? $trans('Post comment') : '') . '</a>';
        }
    }

	echo implode(' | ', $footer);
?>
</div>
