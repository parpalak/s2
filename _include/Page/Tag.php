<?php
/**
 * Displays the list of pages and excerpts for a specified tag.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


class Page_Tag extends Page_Abstract
{
	public function __construct (array $params = array())
	{
		$this->page = self::make_tags_pages($params['name'], !empty($params['slash'])) + $this->page;
	}

	//
	// Builds tags pages
	//
	private static function make_tags_pages ($tag_name, $is_slash)
	{
		global $s2_db, $lang_common;

		// Tag preview

		$query = array(
			'SELECT'	=> 'tag_id, description, name',
			'FROM'		=> 'tags',
			'WHERE'		=> 'url = \''.$s2_db->escape($tag_name).'\''
		);
		($hook = s2_hook('fn_s2_make_tags_pages_pre_get_tag_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		if ($row = $s2_db->fetch_row($result))
			list($tag_id, $tag_description, $tag_name) = $row;
		else {
			s2_error_404();
			die;
		}

		if (!$is_slash)
			s2_redirect('/'.S2_TAGS_URL.'/'.urlencode($tag_name).'/');

		if ($tag_description)
			$tag_description .= '<hr class="tag-separator" />';

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
		($hook = s2_hook('fn_s2_make_tags_pages_pre_get_arts_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$urls = $parent_ids = $rows = array();
		while ($row = $s2_db->fetch_assoc($result))
		{
			$rows[] = $row;
			$urls[] = urlencode($row['url']);
			$parent_ids[] = $row['parent_id'];
		}

		$urls = Model::get_group_url($parent_ids, $urls);

		$subsection_text = '';
		$sections = $articles = $sort_array = array();
		foreach ($urls as $k => $url)
		{
			$row = $rows[$k];
			if ($row['children_exist'])
			{
				$sections[] = '<h3 class="subsection'.($row['favorite'] ? ' favorite-item' : '').'">'.($row['favorite'] ? s2_favorite_link() : '').'<a href="'.s2_link($url).(S2_USE_HIERARCHY ? '/' : '').'">'.s2_htmlencode($row['title']).'</a></h3>'."\n".
					($row['create_time'] ? '<div class="subsection date">'.s2_date($row['create_time']).'</div>'."\n" : '').
					(trim($row['excerpt']) ? '<p class="subsection">'.$row['excerpt'].'</p>'."\n" : '');

				($hook = s2_hook('fn_s2_make_tags_pages_add_subsection')) ? eval($hook) : null;
			}
			else
			{
				$articles[] = array(
					'title'		=> s2_htmlencode($row['title']),
					'time'		=> $row['create_time'],
					'excerpt'	=> $row['excerpt'],
					'favorite'	=> $row['favorite'],
					'url'		=> $url
				);
				$sort_array[] = $row['create_time'];

				($hook = s2_hook('fn_s2_make_tags_pages_add_subarticle')) ? eval($hook) : null;
			}
		}

		($hook = s2_hook('fn_s2_make_tags_pages_pre_merge')) ? eval($hook) : null;

		if (!empty($sections))
			$subsection_text = ($lang_common['Subsections'] ? '<h2 class="subsections">'.$lang_common['Subsections'].'</h2>'."\n" : '').implode('', $sections);

		$text = '';

		// There are articles having the tag
		if (!empty($articles))
		{
			array_multisort($sort_array, $sort_order, $articles);

			($hook = s2_hook('fn_s2_make_tags_pages_pre_add_html')) ? eval($hook) : null;

			foreach ($articles as $item)
				$text .= '<h3 class="article'.($item['favorite'] ? ' favorite-item' : '').'">'.($item['favorite'] ? s2_favorite_link() : '').'<a href="'.s2_link($item['url']).'">'.$item['title'].'</a></h3>'."\n".
					($item['time'] ? '<div class="article date">'.s2_date($item['time']).'</div>'."\n" : '').
					(trim($item['excerpt']) ? '<p class="article">'.$item['excerpt'].'</p>'."\n" : '');
		}

		$page = array(
			'text'			=> $tag_description.$text.$subsection_text,
			'date'			=> '',
			'title'			=> s2_htmlencode($tag_name),
			'path'			=> '<a href="'.s2_link('/').'">'.s2_htmlencode(Model::main_page_title()).'</a>'.$lang_common['Crumbs separator'].'<a href="'.s2_link('/'.S2_TAGS_URL.'/').'">'.$lang_common['Tags'].'</a>'.$lang_common['Crumbs separator'].s2_htmlencode($tag_name),
		);

		($hook = s2_hook('fn_s2_make_tags_pages_tag_end')) ? eval($hook) : null;

		return $page;
	}
}
