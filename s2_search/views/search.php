<?php
/**
 * @var $query string
 * @var $num int
 * @var $num_info string
 * @var $output string
 * @var $paging string
 * @var $tags string
 */

?>
<div class="search-results">
	<form class="search-form" method="get" action="<?php echo S2_URL_PREFIX ? S2_PATH.S2_URL_PREFIX : S2_PATH.'/search'; ?>">
		<div class="button">
			<input type="submit" name="search" value="<?php echo Lang::get('Search button', 's2_search'); ?>" />
		</div>
		<div class="wrap">
			<input class="search-input" id="s2_search_input_ext" type="text" name="q" value="<?php echo s2_htmlencode($query); ?>" />
		</div>
	</form>
<?php

echo $tags;

if ($num)
{
	if (!empty($num_info))
		echo '<p class="s2_search_found_num">'.$num_info.'</p>';

	echo $output, $paging;
}
else
	echo '<p class="s2_search_not_found">'.Lang::get('Not found', 's2_search').'</p>';

?>
</div>
