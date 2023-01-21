<?php
/**
 * Hook fn_get_template_start
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_tpl_edit
 */

 if (!defined('S2_ROOT')) {
     die;
}

$s2_tpl_edit_cached = false;
if (file_exists(S2_CACHE_DIR.'s2_tpl_edit_'.S2_STYLE.'_'.$template_id))
{
	$path = S2_CACHE_DIR.'s2_tpl_edit_'.S2_STYLE.'_'.$template_id;
	$s2_tpl_edit_cached = true;
}