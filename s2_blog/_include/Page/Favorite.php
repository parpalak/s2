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
		global $lang_s2_blog, $lang_common, $page;

		$this->obtainTemplate(__DIR__.'/../../templates/');

		if (strpos($this->template, '<!-- s2_blog_calendar -->') !== false)
			$page['s2_blog_calendar'] = Lib::calendar(date('Y'), date('m'), '0');

		$page['text'] = self::favorite_posts();

		// Bread crumbs
		if (S2_BLOG_CRUMBS)
			$page['path'][] = S2_BLOG_CRUMBS;
		if (S2_BLOG_URL)
			$page['path'][] = '<a href="'.S2_BLOG_PATH.'">'.$lang_s2_blog['Blog'].'</a>';
		$page['path'][] = $lang_common['Favorite'];

		$page['head_title'] = $page['title'] = $lang_common['Favorite'];
		$page['link_navigation']['up'] = S2_BLOG_PATH;
	}

	public static function favorite_posts ()
	{
		global $s2_blog_fav_link;

		$s2_blog_fav_link = '<span class="favorite-star">*</span>';

		$query_add = array(
			'WHERE'		=> 'favorite = 1'
		);
		$output = Lib::get_posts($query_add);

		if ($output == '')
			s2_404_header();

		return $output;
	}
}
