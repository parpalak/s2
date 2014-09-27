<?php
/**
 * Blog posts for a tag.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;


class Page_Tag extends Page_Abstract
{
	public function body (array $params = array())
	{
		global $lang_s2_blog;

		$this->obtainTemplate(__DIR__.'/../../templates/');

		if (strpos($this->template, '<!-- s2_blog_calendar -->') !== false)
			$this->page['s2_blog_calendar'] = Lib::calendar(date('Y'), date('m'), '0');

		// A tag
		$page = $this->posts_by_tag($params['tag']);
		$this->page['title'] = $this->page['head_title'] = s2_htmlencode($page['title']);
		$this->page['text'] = $page['text'];

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
			'title' => $lang_s2_blog['Tags'],
			'link'  => S2_BLOG_TAGS_PATH,
		);
		$this->page['path'][] = array(
			'title' => $this->page['title'],
		);

		$this->page['link_navigation']['up'] = S2_BLOG_TAGS_PATH;
	}

	private function posts_by_tag ($tag)
	{
		global $s2_db, $lang_s2_blog;

		$query = array(
			'SELECT'	=> 'tag_id, description, name',
			'FROM'		=> 'tags',
			'WHERE'		=> 'url = \''.$s2_db->escape($tag).'\''
		);
		($hook = s2_hook('fn_s2_blog_posts_by_tag_pre_get_tag_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		if ($row = $s2_db->fetch_row($result))
			list($tag_id, $tag_descr, $tag_name) = $row;
		else {
			$this->error_404();
			die;
		}

		$art_links = \Page_Common::articles_by_tag($tag_id);
		if (count($art_links))
			$tag_descr .= '<p>'.$lang_s2_blog['Articles by tag'].'<br />'.implode('<br />', $art_links).'</p>';

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
		$output = Lib::get_posts($query_add, false);
		if ($output == '')
			$this->error_404();

		return array(
			'text'			=> $tag_descr.$output,
			'title'			=> $tag_name,
		);
	}
}
