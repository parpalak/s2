<?php
/**
 * Hook fn_get_options_pre_comment_fs
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

 if (!defined('S2_ROOT')) {
     die;
}

Lang::load('s2_search', function () use ($ext_info)
{
	if (file_exists(S2_ROOT.'/_extensions/s2_search'.'/lang/'.S2_LANGUAGE.'.php'))
		return require S2_ROOT.'/_extensions/s2_search'.'/lang/'.S2_LANGUAGE.'.php';
	else
		return require S2_ROOT.'/_extensions/s2_search'.'/lang/English.php';
});
$fieldset = array(
	'S2_SEARCH_QUICK' => s2_get_checkbox('S2_SEARCH_QUICK', $options['S2_SEARCH_QUICK'], Lang::get('Quick search', 's2_search'), Lang::get('Quick search label', 's2_search')),
);
($hook = s2_hook('s2_search_opt_pre_fs_merge')) ? eval($hook) : null;
$output .= '<fieldset><legend>'.Lang::get('Search', 's2_search').'</legend>'.implode('', $fieldset).'</fieldset>';
