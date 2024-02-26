<?php
/**
 * Blog posts for a month.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;

use Lang;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class Page_Month extends Page_HTML implements \Page_Routable
{
    public function body (Request $request): ?Response
    {
        $params = $request->attributes->all();

		if ($this->hasPlaceholder('<!-- s2_blog_calendar -->'))
			$this->page['s2_blog_calendar'] = Lib::calendar($params['year'], $params['month'], 0);

		$this->page['title'] = '';

		// Posts of a month
		$this->posts_by_month($params['year'], $params['month']);
		$this->page['head_title'] = \Lang::month($params['month']).', '.$params['year'];

		// Bread crumbs
		$this->page['path'][] = array(
			'title' => \Model::main_page_title(),
			'link'  => s2_link('/'),
		);
		if (S2_BLOG_URL)
		{
			$this->page['path'][] = array(
				'title' => Lang::get('Blog', 's2_blog'),
				'link' => S2_BLOG_PATH,
			);
		}

		$this->page['path'][] = array(
			'title' => $params['year'],
			'link'  => S2_BLOG_PATH.$params['year'].'/',
		);
		$this->page['path'][] = array(
			'title' => $params['month'],
		);

        return null;
	}

	public function posts_by_month ($year, $month)
	{
		$link_nav = array();
		$paging = '';

		$start_time = mktime(0, 0, 0, $month, 1, $year);
		$end_time = mktime(0, 0, 0, $month + 1, 1, $year);
		$prev_time = mktime(0, 0, 0, $month - 1, 1, $year);

		$link_nav['up'] = S2_BLOG_PATH.date('Y/', $start_time);

		if ($prev_time >= mktime(0, 0, 0, 1, 1, S2_START_YEAR))
		{
			$link_nav['prev'] = S2_BLOG_PATH.date('Y/m/', $prev_time);
			$paging = '<a href="'.$link_nav['prev'].'">'.Lang::get('Here').'</a> ';
		}
		if ($end_time < time())
		{
			$link_nav['next'] = S2_BLOG_PATH.date('Y/m/', $end_time);
			$paging .= '<a href="'.$link_nav['next'].'">'.Lang::get('There').'</a>';
			// TODO think about back_forward template
		}

		if ($paging)
			$paging = '<p class="s2_blog_pages">'.$paging.'</p>';

		$query_add = array(
			'WHERE'		=> 'p.create_time < '.$end_time.' AND p.create_time >= '.$start_time
		);
		$output = $this->get_posts($query_add);

		if ($output == '')
		{
			$this->s2_404_header();
			$output = '<p>'.Lang::get('Not found', 's2_blog').'</p>';
		}

		$this->page['text'] = $output.$paging;
		$this->page['link_navigation'] = $link_nav;
	}
}
