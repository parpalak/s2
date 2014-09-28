<?php

/** @var array $menu */
/** @var string $title */

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
	if (!empty($item['is_current']))
	{
?>
	<li class="active"><span><?php echo s2_htmlencode($item['title']); ?></span></li>
<?php
	}
	else
	{
?>
	<li>
		<a href="<?php echo s2_htmlencode($item['link']); ?>"<?php if (!empty($item['hint'])) echo ' title="', s2_htmlencode($item['hint']), '"'; ?>>
			<?php echo s2_htmlencode($item['title']); ?>
		</a>
	</li>
<?php
	}
}

?>
</ul>
