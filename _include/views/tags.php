<?php
/**
 * Content of <!-- s2_last_articles --> placeholder
 *
 * @var string $title
 * @var array $tags
 */

foreach ($tags as &$tag)
	$tag = '<a href="'.s2_htmlencode($tag['link']).'">'.s2_htmlencode($tag['title']).'</a>';

?>
<p class="article_tags">
	<?php echo $title; ?>:
	<?php echo implode(', ', $tags); ?>
</p>
