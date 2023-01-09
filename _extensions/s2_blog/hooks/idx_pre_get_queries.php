<?php
/**
 * Hook idx_pre_get_queries
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

$s2_blog_placehoders = array();

foreach (array('s2_blog_last_comments', 's2_blog_last_discussions', 's2_blog_last_post') as $s2_blog_placehoder)
	if ($this->inTemplate('<!-- ' . $s2_blog_placehoder . ' -->'))
		$s2_blog_placehoders[$s2_blog_placehoder] = 1;

if (!empty($s2_blog_placehoders))
	Lang::load('s2_blog', function () use ($ext_info)
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/English.php';
	});

if (isset($s2_blog_placehoders['s2_blog_last_comments']))
{
	$s2_blog_recent_comments = s2_extensions\s2_blog\Placeholder::recent_comments();
	$replace['<!-- s2_blog_last_comments -->'] = empty($s2_blog_recent_comments) ? '' : $this->renderPartial('menu_comments', array(
		'title' => Lang::get('Last comments', 's2_blog'),
		'menu'  => $s2_blog_recent_comments,
	));
}
if (isset($s2_blog_placehoders['s2_blog_last_discussions']))
{
	$s2_blog_last_discussions = s2_extensions\s2_blog\Placeholder::recent_discussions();
	$replace['<!-- s2_blog_last_discussions -->'] = empty($s2_blog_last_discussions) ? '' : $this->renderPartial('menu_block', array(
		'title' => Lang::get('Last discussions', 's2_blog'),
		'menu'  => $s2_blog_last_discussions,
		'class' => 's2_blog_last_discussions',
	));
}
if (isset($s2_blog_placehoders['s2_blog_last_post']))
{
	$s2_blog_viewer = new Viewer('s2_extensions\s2_blog');
	$s2_blog_data = s2_extensions\s2_blog\Lib::last_posts_array(1);
	foreach($s2_blog_data as &$s2_blog_post)
		$s2_blog_post = $s2_blog_viewer->render('post_short', $s2_blog_post);
	unset($s2_blog_post);
	$replace['<!-- s2_blog_last_post -->'] = implode('', $s2_blog_data);
}
$replace['<!-- s2_blog_tags -->'] = isset($page['s2_blog_tags']) ? $page['s2_blog_tags'] : '';
$replace['<!-- s2_blog_calendar -->'] = isset($page['s2_blog_calendar']) ? $page['s2_blog_calendar'] : '';
$replace['<!-- s2_blog_navigation -->'] = isset($page['s2_blog_navigation']) ? $page['s2_blog_navigation'] : '';
$replace['<!-- s2_blog_back_forward -->'] = isset($page['s2_blog_back_forward']) ? $page['s2_blog_back_forward'] : '';
