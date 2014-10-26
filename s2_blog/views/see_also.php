<?php
/**
 * @var $see_also array
 */

foreach ($see_also as &$see_also_item)
	$see_also_item = '<a href="'.$see_also_item['link'].'">'.$see_also_item['title'].'</a>';
unset($see_also_item);

?>
<p class="see_also">
	<b><?php echo Lang::get('See also', 's2_blog'); ?></b><br />
	<?php echo implode('<br />'."\n", $see_also); ?>
</p>
