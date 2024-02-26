<?php
/**
 * Blog posts for a day.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;
use \Lang;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class Page_Day extends Page_HTML implements \Page_Routable
{
	public function body (Request $request): ?Response
    {
        $params = $request->attributes->all();

		if ($this->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $this->page['s2_blog_calendar'] = Lib::calendar($params['year'], $params['month'], $params['day']);
        }

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
			'link'  => S2_BLOG_PATH.$params['year'].'/'.$params['month'].'/',
		);
		$this->page['path'][] = array(
			'title' => $params['day'],
		);

        return null;
	}

	public function posts_by_day ($year, $month, $day)
	{
		$start_time = mktime(0, 0, 0, $month, $day, $year);
		$end_time = mktime(0, 0, 0, $month, $day + 1, $year);

		$query_add = array(
			'WHERE'		=> 'p.create_time < '.$end_time.' AND p.create_time >= '.$start_time
		);
		$output = $this->get_posts($query_add);

		if ($output == '')
		{
			$this->s2_404_header();
			$output = '<p>'.Lang::get('Not found', 's2_blog').'</p>';
		}

		$this->page['text'] = $output;
		$this->page['link_navigation'] = array('up' => S2_BLOG_PATH.date('Y/m/', $start_time));
	}
}
