<?php
/**
 * This file builds blog pages.
 *
 * @copyright (C) 2007-2012 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

$s2_blog_path = substr($request_uri, strlen(S2_BLOG_URL));
$s2_blog_path = explode('/', $s2_blog_path);   //   []/[2006]/[12]/[31]/[newyear]

$page['commented'] = 0;

if (count($s2_blog_path) <= 1 || $s2_blog_path[1] == '' || $s2_blog_path[1] == 'skip')
{
	// Main page
	$s2_blog_skip = 0;
	if (isset($s2_blog_path[1]) && $s2_blog_path[1] == 'skip')
	{
		if (count($s2_blog_path) != 3 || !ctype_digit($s2_blog_path[2]))
			return false;
		$s2_blog_skip = (int) $s2_blog_path[2];
	}

	// Getting page template
	$template = s2_get_template($s2_blog_skip ? 'blog.php' : 'blog_main.php', $ext_info['path'].'/templates/');

	if (strpos($template, '<!-- s2_blog_calendar -->') !== false)
		$page['s2_blog_calendar'] = s2_blog_calendar(date('Y'), date('m'), '0');

	$page = s2_blog_last_posts($s2_blog_skip) + $page;
	$page['head_title'] = '';

	// Bread crumbs
	if (S2_BLOG_CRUMBS)
		$page['path'][] = S2_BLOG_CRUMBS;
	if (S2_BLOG_URL)
		$page['path'][] = $s2_blog_skip ? '<a href="'.S2_BLOG_PATH.'">'.$lang_s2_blog['Blog'].'</a>' : $lang_s2_blog['Blog'];

	if ($s2_blog_skip)
		$page['link_navigation']['up'] = S2_BLOG_PATH;
	elseif (S2_BLOG_URL && S2_BLOG_CRUMBS && preg_match('#href="(.*?)"#', S2_BLOG_CRUMBS, $s2_blog_matches))
		$page['link_navigation']['up'] = $s2_blog_matches[1];
}
elseif ($s2_blog_path[1] == S2_FAVORITE_URL)
{
	if (count($s2_blog_path) > 3 || isset($s2_blog_path[2]) && $s2_blog_path[2] !== '')
		return false;

	// Getting page template
	$template = s2_get_template('blog.php', $ext_info['path'].'/templates/');

	if (strpos($template, '<!-- s2_blog_calendar -->') !== false)
		$page['s2_blog_calendar'] = s2_blog_calendar(date('Y'), date('m'), '0');

	$page['text'] = s2_blog_get_favorite_posts();

	// Bread crumbs
	if (S2_BLOG_CRUMBS)
		$page['path'][] = S2_BLOG_CRUMBS;
	if (S2_BLOG_URL)
		$page['path'][] = '<a href="'.S2_BLOG_PATH.'">'.$lang_s2_blog['Blog'].'</a>';
	$page['path'][] = $lang_common['Favorite'];

	$page['head_title'] = $page['title'] = $lang_common['Favorite'];
	$page['link_navigation']['up'] = S2_BLOG_PATH;
}
elseif ($s2_blog_path[1] == S2_TAGS_URL)
{
	if (count($s2_blog_path) > 4 || isset($s2_blog_path[3]) && $s2_blog_path[3] !== '')
		return false;

	if (count($s2_blog_path) == 2)
		$s2_blog_path[2] = '';

	// Getting page template
	$template = s2_get_template('blog.php', $ext_info['path'].'/templates/');

	if (strpos($template, '<!-- s2_blog_calendar -->') !== false)
		$page['s2_blog_calendar'] = s2_blog_calendar(date('Y'), date('m'), '0');

	if ($s2_blog_path[2])
	{
		// A tag
		$page = s2_blog_posts_by_tag($s2_blog_path[2]) + $page;

		// Bread crumbs
		if (S2_BLOG_CRUMBS)
			$page['path'][] = S2_BLOG_CRUMBS;
		if (S2_BLOG_URL)
			$page['path'][] = '<a href="'.S2_BLOG_PATH.'">'.$lang_s2_blog['Blog'].'</a>';
		$page['path'][] = '<a href="'.S2_BLOG_TAGS_PATH.'">'.$lang_s2_blog['Tags'].'</a>';
		$page['path'][] = $page['title'];

		$page['link_navigation']['up'] = S2_BLOG_TAGS_PATH;
	}
	else
	{
		// The list of tags
		$page['text'] = s2_blog_all_tags();

		// Bread crumbs
		if (S2_BLOG_CRUMBS)
			$page['path'][] = S2_BLOG_CRUMBS;
		if (S2_BLOG_URL)
			$page['path'][] = '<a href="'.S2_BLOG_PATH.'">'.$lang_s2_blog['Blog'].'</a>';
		$page['path'][] = $lang_s2_blog['Tags'];

		$page['head_title'] = $page['title'] = $lang_s2_blog['Tags'];
		$page['link_navigation']['up'] = S2_BLOG_PATH;
	}
}
else
{
	// []/[2006]/[12]/[31]/[newyear]
	if ($s2_blog_path[1] && !ctype_digit($s2_blog_path[1]) || count($s2_blog_path) > 5)
		return false;

	$s2_blog_path[1] = (int) $s2_blog_path[1];
	if ($s2_blog_path[1] < S2_START_YEAR || $s2_blog_path[1] > (int) date('Y'))
		return false;

	if (count($s2_blog_path) == 2)
		$s2_blog_path[2] = '';
	elseif ($s2_blog_path[2] !== '' && (!ctype_digit($s2_blog_path[2]) || $s2_blog_path[2] > 12 || $s2_blog_path[2] < 1))
		return false;

	if (count($s2_blog_path) == 3)
		$s2_blog_path[3] = '';
	elseif ($s2_blog_path[3] !== '' && (!ctype_digit($s2_blog_path[3]) || !checkdate($s2_blog_path[2], $s2_blog_path[3], $s2_blog_path[1])))
		return false;

	if (count($s2_blog_path) == 4)
		$s2_blog_path[4] = '';

	// Getting page template
	$template = s2_get_template('blog.php', $ext_info['path'].'/templates/');

	if (strpos($template, '<!-- s2_blog_calendar -->') !== false)
		$page['s2_blog_calendar'] = s2_blog_calendar($s2_blog_path[1], $s2_blog_path[2], $s2_blog_path[3], $s2_blog_path[4]);

	$page['title'] = '';

	if (!$s2_blog_path[2])
	{
		// Posts of a year
		$page = s2_blog_year_posts($s2_blog_path[1]) + $page;

		// Bread crumbs
		if (S2_BLOG_CRUMBS)
			$page['path'][] = S2_BLOG_CRUMBS;
		if (S2_BLOG_URL)
			$page['path'][] = '<a href="'.S2_BLOG_PATH.'">'.$lang_s2_blog['Blog'].'</a>';
		$page['path'][] = $s2_blog_path[1];
	}
	elseif (!$s2_blog_path[3])
	{
		// Posts of a month
		$page = s2_blog_posts_by_time($s2_blog_path[1], $s2_blog_path[2]) + $page;
		$page['head_title'] = s2_month($s2_blog_path[2]).', '.$s2_blog_path[1];

		// Bread crumbs
		if (S2_BLOG_CRUMBS)
			$page['path'][] = S2_BLOG_CRUMBS;
		if (S2_BLOG_URL)
			$page['path'][] = '<a href="'.S2_BLOG_PATH.'">'.$lang_s2_blog['Blog'].'</a>';
		$page['path'][] = '<a href="'.S2_BLOG_PATH.$s2_blog_path[1].'/">'.$s2_blog_path[1].'</a>';
		$page['path'][] = $s2_blog_path[2];
	}
	elseif ($s2_blog_path[4] === '')
	{
		// Posts of a day
		$page = s2_blog_posts_by_time($s2_blog_path[1], $s2_blog_path[2], $s2_blog_path[3]) + $page;
		$page['head_title'] = s2_date(mktime(0, 0, 0, $s2_blog_path[2], $s2_blog_path[3], $s2_blog_path[1]));

		// Bread crumbs
		if (S2_BLOG_CRUMBS)
			$page['path'][] = S2_BLOG_CRUMBS;
		if (S2_BLOG_URL)
			$page['path'][] = '<a href="'.S2_BLOG_PATH.'">'.$lang_s2_blog['Blog'].'</a>';
		$page['path'][] = '<a href="'.S2_BLOG_PATH.$s2_blog_path[1].'/">'.$s2_blog_path[1].'</a>';
		$page['path'][] = '<a href="'.S2_BLOG_PATH.$s2_blog_path[1].'/'.$s2_blog_path[2].'/">'.$s2_blog_path[2].'</a>';
		$page['path'][] = $s2_blog_path[3];
	}
	else
	{
		// A post
		$page = s2_blog_get_post($s2_blog_path[1], $s2_blog_path[2], $s2_blog_path[3], $s2_blog_path[4]) + $page;

		// Bread crumbs
		if (S2_BLOG_CRUMBS)
			$page['path'][] = S2_BLOG_CRUMBS;
		if (S2_BLOG_URL)
			$page['path'][] = '<a href="'.S2_BLOG_PATH.'">'.$lang_s2_blog['Blog'].'</a>';
		$page['path'][] = '<a href="'.S2_BLOG_PATH.$s2_blog_path[1].'/">'.$s2_blog_path[1].'</a>';
		$page['path'][] = '<a href="'.S2_BLOG_PATH.$s2_blog_path[1].'/'.$s2_blog_path[2].'/">'.$s2_blog_path[2].'</a>';
		$page['path'][] = '<a href="'.S2_BLOG_PATH.$s2_blog_path[1].'/'.$s2_blog_path[2].'/'.$s2_blog_path[3].'/">'.$s2_blog_path[3].'</a>';
	}
}

if (isset($page['path']))
	$page['path'] = implode('&nbsp;&rarr; ', $page['path']);
$page['meta_description'] = S2_BLOG_TITLE;
$page['head_title'] = empty($page['head_title']) ? S2_BLOG_TITLE : $page['head_title'].' - '.S2_BLOG_TITLE;

if (strpos($template, '<!-- s2_menu -->') !== false)
	$page['menu']['s2_blog_navigation'] = '<div class="header">'.$lang_s2_blog['Navigation'].'</div>'.s2_blog_navigation($request_uri);

define('S2_BLOG_HANDLED', 1);

return true;