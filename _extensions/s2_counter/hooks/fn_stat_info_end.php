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

$output .= '<fieldset><legend>Traffic</legend><div id="s2_counter_hits" style="height: 300px; background: #fff;"></div></fieldset>';
$output .= '<fieldset><legend>RSS readers</legend><div id="s2_counter_rss" style="height: 300px; background: #fff;"></div></fieldset>';
