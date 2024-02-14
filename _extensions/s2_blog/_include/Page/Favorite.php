<?php
/**
 * Favorite blog posts.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;
use \Lang;


class Page_Favorite extends Page_HTML implements \Page_Routable
{
	public function __construct (array $params = array())
	{
		if (empty($params['slash'])) {
			s2_permanent_redirect(S2_BLOG_URL.'/'.S2_FAVORITE_URL.'/');
		}

		parent::__construct($params);
	}

	public function body (array $params = array())
	{
		$this->ensureTemplateIsLoaded();

		if ($this->inTemplate('<!-- s2_blog_calendar -->'))
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
				'title' => Lang::get('Blog', 's2_blog'),
				'link' => S2_BLOG_PATH,
			);
		}
		$this->page['path'][] = array(
			'title' => Lang::get('Favorite'),
		);

		$this->page['head_title'] = $this->page['title'] = Lang::get('Favorite');
		$this->page['link_navigation']['up'] = S2_BLOG_PATH;
	}

	public function favorite_posts ()
	{
		$query_add = array(
			'SELECT' => '2 AS favorite',
			'WHERE'  => 'favorite = 1',
		);
		$output = $this->get_posts($query_add, false);

		if ($output == '')
			$this->s2_404_header();
		// TODO Why 404 in favorite? Where is the message?

		$this->page['text'] = $output;
	}
}
