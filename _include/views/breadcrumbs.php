<?php

/** @var array $breadcrumbs */

$num = 0;
foreach ($breadcrumbs as $item)
{
	if ($num > 0)
		echo ' &rarr; ';

	if (!empty($item['link']))
		echo '<a href="'.s2_htmlencode($item['link']).'">'.s2_htmlencode($item['title']).'</a>';
	else
		echo s2_htmlencode($item['title']);

	$num++;
}
