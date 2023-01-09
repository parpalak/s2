<?php
/**
 * Hook fn_get_template_pre_includes_merge
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

 if (!defined('S2_ROOT')) {
     die;
}

$includes['css'][] = S2_PATH.'/_extensions/s2_search'.'/style.css';
if (S2_SEARCH_QUICK)
{
	$includes['js'][] = S2_PATH.'/_extensions/s2_search'.'/autosearch.js';
	$includes['js_inline'][] = '<script>var s2_search_url = "'.S2_PATH.'/_extensions/s2_search'.'";</script>';
}
