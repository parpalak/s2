<?php
/**
 * Hook fn_get_options_pre_comment_fs
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_wysiwyg
 */

 if (!defined('S2_ROOT')) {
     die;
}

Lang::load('s2_wysiwyg', function ()
{
	if (file_exists(S2_ROOT.'/_extensions/s2_wysiwyg'.'/lang/'.S2_LANGUAGE.'.php'))
		return require S2_ROOT.'/_extensions/s2_wysiwyg'.'/lang/'.S2_LANGUAGE.'.php';
	else
		return require S2_ROOT.'/_extensions/s2_wysiwyg'.'/lang/English.php';
});
$fieldset = array(
	'S2_WYSIWYG_TYPE' => s2_get_checkbox('S2_WYSIWYG_TYPE', $options['S2_WYSIWYG_TYPE'], Lang::get('WYSIWYG type', 's2_wysiwyg'), Lang::get('WYSIWYG type label', 's2_wysiwyg')),
);
($hook = s2_hook('s2_wysiwyg_opt_pre_fs_merge')) ? eval($hook) : null;
$output .= '<fieldset><legend>'.Lang::get('WYSIWYG', 's2_wysiwyg').'</legend>'.implode('', $fieldset).'</fieldset>';
