<?php
/**
 * Hook fn_stat_info_end
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_counter
 */

 if (!defined('S2_ROOT')) {
     die;
}

$output .= '<p id="s2_counter_hits" style="height: 400px;"></p>';
$output .= '<p id="s2_counter_rss" style="height: 400px;"></p>';
