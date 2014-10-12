<?php
/**
 * Blog posts for a day.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;


class Page_Day extends Page_HTML implements \Page_Routable
{
	public function body (array $params = array())
	{
		global $lang_s2_blog;

		if ($this->inTemplate('<!-- s2_blog_calendar -->'))
			$this->page['s2_blog_calendar'] = Lib::calendar($params['year'], $params['month'], $params['day']);

		$this->page['title'] = '';

		// Posts of a day
		$this->posts_by_day($params['year'], $params['month'], $params['day']);
		$this->page['head_title'] = s2_date(mktime(0, 0, 0, $params['month'], $params['day'], $params['year']));

		// Bread crumbs
		$this->page['path'][] = array(
			'title' => \Model::main_page_title(),
			'link'  => s2_link('/'),
		);
		if (S2_BLOG_URL)
		{
			$this->page['path'][] = array(
				'title' => $lang_s2_blog['Blog'],
				'link' => S2_BLOG_PATH,
			);
		}

		$this->page['path'][] = array(
			'title' => $params['year'],
			'link'  => S2_BLOG_PATH.$params['year'].'/',
		);
		$this->page['path'][] = array(
			'title' => $params['month'],
			'link'  => S2_BLOG_PATH.$params['year'].'/'.$params['month'].'/',
		);
		$this->page['path'][] = array(
			'title' => $params['day'],
		);
	}

	public function posts_by_day ($year, $month, $day)
	{
		global $lang_s2_blog;

		$start_time = mktime(0, 0, 0, $month, $day, $year);
		$end_time = mktime(0, 0, 0, $month, $day + 1, $year);

		$query_add = array(
			'WHERE'		=> 'p.create_time < '.$end_time.' AND p.create_time >= '.$start_time
		);
		$output = $this->get_posts($query_add);

		if ($output == '')
		{
			s2_404_header();
			$output = '<p>'.$lang_s2_blog['Not found'].'</p>';
		}

		$this->page['text'] = $output;
		$this->page['link_navigation'] = array('up' => S2_BLOG_PATH.date('Y/m/', $start_time));
	}
}
