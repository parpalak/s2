<?php
/**
 * Hook fn_get_options_pre_comment_fs
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

Lang::load('s2_blog', function () use ($ext_info)
{
	if (file_exists(S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php'))
		return require S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php';
	else
		return require S2_ROOT.'/_extensions/s2_blog'.'/lang/English.php';
});
$fieldset = array(
	'S2_BLOG_TITLE' => s2_get_input('S2_BLOG_TITLE', $options['S2_BLOG_TITLE'], Lang::get('Blog title', 's2_blog'), Lang::get('Blog title label', 's2_blog')),
	'S2_BLOG_URL' => s2_get_input('S2_BLOG_URL', $options['S2_BLOG_URL'], Lang::get('Blog URL', 's2_blog'), Lang::get('Blog URL label', 's2_blog')),
);
($hook = s2_hook('s2_blog_opt_pre_blog_fs_merge')) ? eval($hook) : null;
$output .= '<fieldset><legend>'.Lang::get('Blog', 's2_blog').'</legend>'.implode('', $fieldset).'</fieldset>';
