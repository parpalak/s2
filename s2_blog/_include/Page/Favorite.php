<?php
/**
 * Favorite blog posts.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;

class Page_Favorite extends Page_Abstract
{
	public function body (array $params = array())
	{
		global $lang_s2_blog, $lang_common;

		$this->obtainTemplate(__DIR__.'/../../templates/');

		if (strpos($this->template, '<!-- s2_blog_calendar -->') !== false)
			$this->page['s2_blog_calendar'] = Lib::calendar(date('Y'), date('m'), '0');

		$this->favorite_posts();

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
			'title' => $lang_common['Favorite'],
		);

		$this->page['head_title'] = $this->page['title'] = $lang_common['Favorite'];
		$this->page['link_navigation']['up'] = S2_BLOG_PATH;
	}

	public function favorite_posts ()
	{
		global $s2_blog_fav_link;

		$s2_blog_fav_link = '<span class="favorite-star">*</span>';

		$query_add = array(
			'WHERE'		=> 'favorite = 1'
		);
		$output = $this->get_posts($query_add);

		if ($output == '')
			s2_404_header();
		// TODO Why 404 in favorite? Where is the message?

		$this->page['text'] = $output;
	}
}
