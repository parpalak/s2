<?php
/**
 * Blog posts for a day.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;


class Page_Day extends Page_Abstract
{
	public function body (array $params = array())
	{
		global $page, $lang_s2_blog;

		$this->obtainTemplate(__DIR__.'/../../templates/');

		if (strpos($this->template, '<!-- s2_blog_calendar -->') !== false)
			$page['s2_blog_calendar'] = Lib::calendar($params['year'], $params['month'], $params['day']);

		$page['title'] = '';

		// Posts of a day
		$page = self::posts_by_day($params['year'], $params['month'], $params['day']) + $page;
		$page['head_title'] = s2_date(mktime(0, 0, 0, $params['month'], $params['day'], $params['year']));

		// Bread crumbs
		if (S2_BLOG_CRUMBS)
			$page['path'][] = S2_BLOG_CRUMBS;
		if (S2_BLOG_URL)
			$page['path'][] = '<a href="'.S2_BLOG_PATH.'">'.$lang_s2_blog['Blog'].'</a>';
		$page['path'][] = '<a href="'.S2_BLOG_PATH.$params['year'].'/">'.$params['year'].'</a>';
		$page['path'][] = '<a href="'.S2_BLOG_PATH.$params['year'].'/'.$params['month'].'/">'.$params['month'].'</a>';
		$page['path'][] = $params['day'];
	}

	public static function posts_by_day ($year, $month, $day)
	{
		global $lang_s2_blog;

		$link_nav = array();

		$start_time = mktime(0, 0, 0, $month, $day, $year);
		$end_time = mktime(0, 0, 0, $month, $day + 1, $year);
		$link_nav['up'] = S2_BLOG_PATH.date('Y/m/', $start_time);

		$query_add = array(
			'WHERE'		=> 'p.create_time < '.$end_time.' AND p.create_time >= '.$start_time
		);
		$output = Lib::get_posts($query_add);

		if ($output == '')
		{
			s2_404_header();
			$output = '<p>'.$lang_s2_blog['Not found'].'</p>';
		}

		return array('text' => $output, 'link_navigation' => $link_nav);
	}
}
