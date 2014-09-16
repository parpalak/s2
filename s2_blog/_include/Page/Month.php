<?php
/**
 * Blog posts for a month.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;


class Page_Month extends Page_Abstract
{
	public function body (array $params = array())
	{
		global $lang_s2_blog;

		$this->obtainTemplate(__DIR__.'/../../templates/');

		if (strpos($this->template, '<!-- s2_blog_calendar -->') !== false)
			$this->page['s2_blog_calendar'] = Lib::calendar($params['year'], $params['month'], 0);

		$this->page['title'] = '';

		// Posts of a month
		$this->page = self::posts_by_month($params['year'], $params['month']) + $this->page;
		$this->page['head_title'] = s2_month($params['month']).', '.$params['year'];

		// Bread crumbs
		if (S2_BLOG_CRUMBS)
			$this->page['path'][] = S2_BLOG_CRUMBS;
		if (S2_BLOG_URL)
			$this->page['path'][] = '<a href="'.S2_BLOG_PATH.'">'.$lang_s2_blog['Blog'].'</a>';
		$this->page['path'][] = '<a href="'.S2_BLOG_PATH.$params['year'].'/">'.$params['year'].'</a>';
		$this->page['path'][] = $params['month'];
	}

	public static function posts_by_month ($year, $month)
	{
		global $s2_db, $lang_common, $lang_s2_blog;

		$link_nav = array();
		$paging = '';

			$start_time = mktime(0, 0, 0, $month, 1, $year);
			$end_time = mktime(0, 0, 0, $month + 1, 1, $year);
			$prev_time = mktime(0, 0, 0, $month - 1, 1, $year);

			$link_nav['up'] = S2_BLOG_PATH.date('Y/', $start_time);

			if ($prev_time >= mktime(0, 0, 0, 1, 1, S2_START_YEAR))
			{
				$link_nav['prev'] = S2_BLOG_PATH.date('Y/m/', $prev_time);
				$paging = '<a href="'.$link_nav['prev'].'">'.$lang_common['Here'].'</a> ';
			}
			if ($end_time < time())
			{
				$link_nav['next'] = S2_BLOG_PATH.date('Y/m/', $end_time);
				$paging .= '<a href="'.$link_nav['next'].'">'.$lang_common['There'].'</a>';
			}

			if ($paging)
				$paging = '<p class="s2_blog_pages">'.$paging.'</p>';

		$query_add = array(
			'WHERE'		=> 'p.create_time < '.$end_time.' AND p.create_time >= '.$start_time
		);
		$output = Lib::get_posts($query_add);

		if ($output == '')
		{
			s2_404_header();
			$output = '<p>'.$lang_s2_blog['Not found'].'</p>';
		}

		return array('text' => $output.$paging, 'link_navigation' => $link_nav);
	}

}
