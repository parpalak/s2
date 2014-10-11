<?php
/**
 * Content of <!-- s2_tags_list --> placeholder and the tags list page text.
 *
 * @var array $tags
 */

foreach ($tags as &$tag)
	$tag = '<a href="'.s2_htmlencode($tag['link']).'">'.s2_htmlencode($tag['title']).'</a>'.(isset($tag['num']) ? ' ('.$tag['num'].')' : '');
unset($tag);

?>
<div class="tags_list">
	<?php echo implode('<br/>', $tags); ?>
</div>
