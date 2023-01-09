<?php
/**
 * Hook fn_s2_parse_page_url_end
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

if ($this->inTemplate('<!-- s2_blog_tags -->'))
{
	Lang::load('s2_blog', function ()
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/English.php';
	});

	$s2_blog_tags = s2_extensions\s2_blog\Placeholder::blog_tags($id);
	$page['s2_blog_tags'] = empty($s2_blog_tags) ? '' : $this->renderPartial('menu_block', array(
		'title' => Lang::get('See in blog', 's2_blog'),
		'menu'  => $s2_blog_tags,
		'class' => 's2_blog_tags',
	));
}
