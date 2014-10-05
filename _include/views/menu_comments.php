<?php
/**
 * Sidebar block of comments
 *
 * @var string $title block title
 * @var array $menu commented items
 */

?>
<div class="header"><?php echo $title; ?></div>
<?php

if (empty($menu))
	return;

?>
<ul>
<?php

foreach ($menu as $item)
{

?>
	<li>
<?php if (empty($item['is_current'])) { ?>
		<a href="<?php echo s2_htmlencode($item['link']); ?>"><?php echo s2_htmlencode($item['title']); ?></a>,
<?php } else { ?>
		<span><?php echo s2_htmlencode($item['title']); ?></span>,
<?php } ?>
		<em><?php echo s2_htmlencode($item['author']); ?></em>
	</li>
<?php

}

?>
</ul>
