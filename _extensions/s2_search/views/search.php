<?php
/**
 * @var $trans callable
 * @var $action string
 * @var $query string
 * @var $num ?int
 * @var $num_info string
 * @var $output string
 * @var $paging string
 * @var $tags ?string
 */

?>
<form class="search-form" method="get" action="<?php echo s2_htmlencode($action); ?>">
    <input class="search-input" id="s2_search_input_ext" type="text" name="q" value="<?php echo s2_htmlencode($query); ?>" />
    <input class="search-button" type="submit" name="search" value="<?php echo $trans('Search button'); ?>" />
</form>
<?php

echo $tags ?? '';

if (isset($num)) {
    if ($num > 0) {
        if (!empty($num_info)) {
            echo '<p class="s2_search_found_num">' . $num_info . '</p>';
        }

        echo $output, $paging;
    }
    else {
        echo '<p class="s2_search_not_found">' . $trans('No results found') . '</p>';
    }
}
