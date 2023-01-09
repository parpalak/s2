<?php
/**
 * Hook fn_toolbar_end
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_highlight
 */

 if (!defined('S2_ROOT')) {
     die;
}

Lang::load('s2_highlight', function ()
{
	if (file_exists(S2_ROOT.'/_extensions/s2_highlight'.'/lang/'.S2_LANGUAGE.'.php'))
		return require S2_ROOT.'/_extensions/s2_highlight'.'/lang/'.S2_LANGUAGE.'.php';
	else
		return require S2_ROOT.'/_extensions/s2_highlight'.'/lang/English.php';
});

$toolbar = str_replace('</div>', '<img id="s2_highlight_toggle_button" src="i/1.gif" alt="'.Lang::get('Highlight html', 's2_highlight').'" />'."\n\t".'</div>', $toolbar);
