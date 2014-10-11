<?php
/**
 * @var $links array
 */

foreach ($links as &$link)
	$link = '<a class="preview_section" href="'.$link['link'].'">'.$link['title'].'</a>';
unset($link);

global $lang_s2_blog;

?>
<p class="see_also"><b><?php echo $lang_s2_blog['See also']; ?></b><br /><?php echo implode('<br />', $links); ?></p>
