<?php
/**
 * Displays the list of pages and excerpts for a specified tag.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


class Page_Tag extends Page_HTML implements Page_Routable
{
	public function __construct (array $params = array())
	{
		parent::__construct();
		$this->make_tags_pages($params['name'], !empty($params['slash']));
	}

	//
	// Builds tags pages
	//
	private function make_tags_pages ($tag_name, $is_slash)
	{
		global $s2_db, $lang_common;

		// Tag preview

		$query = array(
			'SELECT'	=> 'tag_id, description, name',
			'FROM'		=> 'tags',
			'WHERE'		=> 'url = \''.$s2_db->escape($tag_name).'\''
		);
		($hook = s2_hook('pt_make_tags_pages_pre_get_tag_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		if ($row = $s2_db->fetch_row($result))
			list($tag_id, $tag_description, $tag_name) = $row;
		else {
			$this->error_404();
			die;
		}

		if (!$is_slash)
			s2_redirect('/'.S2_TAGS_URL.'/'.urlencode($tag_name).'/');

		$subquery = array(
			'SELECT'	=> '1',
			'FROM'		=> 'articles AS a1',
			'WHERE'		=> 'a1.parent_id = a.id AND a1.published = 1',
			'LIMIT'		=> '1'
		);
		$raw_query1 = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

		$sort_order = SORT_DESC; // SORT_ASC also possible
		$query = array(
			'SELECT'	=> 'a.title, a.url, ('.$raw_query1.') IS NOT NULL AS children_exist, a.id, a.excerpt, a.favorite, a.create_time, a.parent_id',
			'FROM'		=> 'article_tag AS at',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'articles AS a',
					'ON'			=> 'a.id = at.article_id'
				),
			),
			'WHERE'		=> 'at.tag_id = '.$tag_id.' AND a.published = 1'
		);
		($hook = s2_hook('pt_make_tags_pages_pre_get_arts_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$urls = $parent_ids = $rows = array();
		while ($row = $s2_db->fetch_assoc($result))
		{
			$rows[] = $row;
			$urls[] = urlencode($row['url']);
			$parent_ids[] = $row['parent_id'];
		}

		$urls = Model::get_group_url($parent_ids, $urls);

		$sections = $articles = $articles_sort_array = $sections_sort_array = array();
		foreach ($urls as $k => $url)
		{
			$row = $rows[$k];
			if ($row['children_exist'])
			{
				$item = array(
					'id'       => $row['id'],
					'title'    => $row['title'],
					'link'     => s2_link($url.(S2_USE_HIERARCHY ? '/' : '')),
					'date'     => s2_date($row['create_time']),
					'excerpt'  => $row['excerpt'],
					'favorite' => $row['favorite'],
				);
				$sort_field = $row['create_time'];

				($hook = s2_hook('pt_make_tags_pages_add_section')) ? eval($hook) : null;

				$sections[] = $item;
				$sections_sort_array[] = $sort_field;
			}
			else
			{
				$item = array(
					'id'       => $row['id'],
					'title'    => $row['title'],
					'link'     => s2_link($url),
					'date'     => s2_date($row['create_time']),
					'excerpt'  => $row['excerpt'],
					'favorite' => $row['favorite'],
				);
				$sort_field = $row['create_time'];

				($hook = s2_hook('pt_make_tags_pages_add_article')) ? eval($hook) : null;

				$articles[] = $item;
				$articles_sort_array[] = $sort_field;
			}
		}

		($hook = s2_hook('pt_make_tags_pages_pre_merge')) ? eval($hook) : null;

		$section_text = '';
		if (!empty($sections))
		{
			// There are sections having the tag
			array_multisort($sections_sort_array, $sort_order, $sections);
			foreach ($sections as $item)
				$section_text .= $this->renderPartial('subarticles_item', $item);
		}

		$article_text = '';
		if (!empty($articles))
		{
			// There are articles having the tag
			array_multisort($articles_sort_array, $sort_order, $articles);
			foreach ($articles as $item)
				$article_text .= $this->renderPartial('subarticles_item', $item);
		}

		$page = array(
			'path'  => array(
				array(
					'title' => Model::main_page_title(),
					'link'  => s2_link('/'),
				),
				array(
					'link'  => s2_link('/' . S2_TAGS_URL . '/'),
					'title' => $lang_common['Tags'],
				),
				array(
					'title' => $tag_name,
				),
			),
			'title' => s2_htmlencode($tag_name),
			'date'  => '',
			'text'  => $this->renderPartial('list_text', array(
				'description' => $tag_description,
				'articles'    => $article_text,
				'sections'    => $section_text,
			)),
		);

		($hook = s2_hook('fn_s2_make_tags_pages_end')) ? eval($hook) : null;

		$this->page = $page;
	}
}
