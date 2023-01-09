<?php
/**
 * Hook pr_render_start
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_counter
 */

 if (!defined('S2_ROOT')) {
     die;
}

if (!defined('S2_COUNTER_FUNCTIONS_LOADED'))
	include S2_ROOT.'/_extensions/s2_counter'.'/functions.php';

s2_counter_rss_count(S2_ROOT.'/_extensions/s2_counter');
