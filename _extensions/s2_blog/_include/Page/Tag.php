<?php
/**
 * Blog posts for a tag.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;
use Lang;


class Page_Tag extends Page_HTML implements \Page_Routable
{
	public function body (array $params = array())
	{
		if ($this->inTemplate('<!-- s2_blog_calendar -->'))
			$this->page['s2_blog_calendar'] = Lib::calendar(date('Y'), date('m'), '0');

		// A tag
		$this->posts_by_tag($params['tag'], !empty($params['slash']));
		$this->page['title'] = $this->page['head_title'] = s2_htmlencode($this->page['title']);

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
			'title' => Lang::get('Tags'),
			'link'  => S2_BLOG_TAGS_PATH,
		);
		$this->page['path'][] = array(
			'title' => $this->page['title'],
		);

		$this->page['link_navigation']['up'] = S2_BLOG_TAGS_PATH;
	}

	private function posts_by_tag ($tag, $is_slash)
	{
		global $s2_db;

		$query = array(
			'SELECT'	=> 'tag_id, description, name, url',
			'FROM'		=> 'tags',
			'WHERE'		=> 'url = \''.$s2_db->escape($tag).'\''
		);
		($hook = s2_hook('fn_s2_blog_posts_by_tag_pre_get_tag_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query);

		if ($row = $s2_db->fetch_row($result))
			list($tag_id, $tag_descr, $tag_name, $tag_url) = $row;
		else {
			$this->error_404();
			die;
		}

		if (!$is_slash)
			s2_permanent_redirect(S2_BLOG_URL.'/'.S2_TAGS_URL.'/'.urlencode($tag_url).'/');

		$art_links = self::articles_by_tag($tag_id);
		if (count($art_links))
			$tag_descr .= '<p>'.Lang::get('Articles by tag', 's2_blog').'<br />'.implode('<br />', $art_links).'</p>';

		if ($tag_descr)
			$tag_descr .= '<hr />';

		$query_add = array(
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 's2_blog_post_tag AS pt',
					'ON'			=> 'pt.post_id = p.id'
				)
			),
			'WHERE'		=> 'pt.tag_id = '.$tag_id
		);
		$output = $this->get_posts($query_add, false);
		if ($output == '')
			$this->error_404();

		$this->page['title'] = $tag_name;
		$this->page['text'] = $tag_descr.$output;
	}

	/**
	 * Returns the array of links to the articles with the tag specified
	 *
	 * @param $tag_id
	 * @return array
	 */
	private static function articles_by_tag ($tag_id)
	{
		global $s2_db;

		$subquery = array(
			'SELECT'	=> '1',
			'FROM'		=> 'articles AS a1',
			'WHERE'		=> 'a1.parent_id = a.id AND a1.published = 1',
			'LIMIT'		=> '1'
		);
		$raw_query1 = $s2_db->query_build($subquery, true);

		$query = array(
			'SELECT'	=> 'a.id, a.url, a.title, a.parent_id, ('.$raw_query1.') IS NOT NULL AS children_exist',
			'FROM'		=> 'articles AS a',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'article_tag AS atg',
					'ON'			=> 'atg.article_id = a.id'
				),
			),
			'WHERE'		=> 'atg.tag_id = '.$tag_id.' AND a.published = 1',
		);
		($hook = s2_hook('fn_articles_by_tag_pre_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query);

		$title = $urls = $parent_ids = array();

		while ($row = $s2_db->fetch_assoc($result))
		{
			$urls[] = urlencode($row['url']).(S2_USE_HIERARCHY && $row['children_exist'] ? '/' : '');
			$parent_ids[] = $row['parent_id'];
			$title[] = $row['title'];
		}
		$urls = \Model::get_group_url($parent_ids, $urls);

		foreach ($urls as $k => $v)
			$urls[$k] = '<a href="'.s2_link($v).'">'.$title[$k].'</a>';

		return $urls;
	}
}
