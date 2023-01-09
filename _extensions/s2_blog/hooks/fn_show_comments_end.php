<?php
/**
 * Hook fn_show_comments_end
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

if ($mode == 'hidden' || $mode == 'new' || $mode == 'last')
{
	Lang::load('s2_blog', function ()
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/English.php';
	});
	$output .= s2_show_comments('s2_blog_'.$mode);
}
