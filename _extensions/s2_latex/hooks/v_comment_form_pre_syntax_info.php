<?php
/**
 * Hook v_comment_form_pre_syntax_info
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_latex
 */

 if (!defined('S2_ROOT')) {
     die;
}

Lang::load('s2_latex', function ()
{
	if (file_exists(S2_ROOT.'/_extensions/s2_latex'.'/lang/'.S2_LANGUAGE.'.php'))
		return require S2_ROOT.'/_extensions/s2_latex'.'/lang/'.S2_LANGUAGE.'.php';
	else
		return require S2_ROOT.'/_extensions/s2_latex'.'/lang/English.php';
});
echo Lang::get('Comment syntax', 's2_latex');
