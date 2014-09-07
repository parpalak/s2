<?php
/**
 * Displays the list of favorite pages and excerpts.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


class Page_Favorite implements Page_Abstract
{
	public function render ($params)
	{
		global $template, $page;

		// We process tags pages in a different way
		$template_id = 'site.php';
		$page = self::s2_make_favorite_page();
		$template = s2_get_template($template_id);
	}

	//
	// Builds favorite page
	//
	private static function s2_make_favorite_page ()
	{
		global $s2_db, $lang_common;

		$subquery = array(
			'SELECT'	=> '1',
			'FROM'		=> 'articles AS a1',
			'WHERE'		=> 'a1.parent_id = a.id AND a1.published = 1',
			'LIMIT'		=> '1'
		);
		$raw_query1 = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

		$sort_order = SORT_DESC; // SORT_ASC also possible
		$query = array(
			'SELECT'	=> 'a.title, a.url, ('.$raw_query1.') IS NOT NULL AS children_exist, a.id, a.excerpt, a.create_time, a.parent_id',
			'FROM'		=> 'articles AS a',
			'WHERE'		=> 'a.favorite = 1 AND a.published = 1'
		);
		($hook = s2_hook('fn_s2_make_favorite_page_pre_get_arts_qr')) ? eval($hook) : null;
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
				$sections[] = '<h3 class="subsection"><a href="'.s2_link($url).(S2_USE_HIERARCHY ? '/' : '').'">'.s2_htmlencode($row['title']).'</a></h3>'."\n".
					($row['create_time'] ? '<div class="subsection date">'.s2_date($row['create_time']).'</div>'."\n" : '').
					(trim($row['excerpt']) ? '<p class="subsection">'.$row['excerpt'].'</p>'."\n" : '');

				($hook = s2_hook('fn_s2_make_favorite_page_add_subsection')) ? eval($hook) : null;
			}
			else
			{
				$articles[] = array(
					'title' => s2_htmlencode($row['title']),
					'time' => $row['create_time'],
					'excerpt' => $row['excerpt'],
					'url' => $url
				);
				$sort_array[] = $row['create_time'];

				($hook = s2_hook('fn_s2_make_favorite_page_add_subarticle')) ? eval($hook) : null;
			}
		}

		($hook = s2_hook('fn_s2_make_favorite_page_pre_merge')) ? eval($hook) : null;

		// There are favorite sections
		if (!empty($sections))
			$subsection_text = ($lang_common['Subsections'] ? '<h2 class="subsections">'.$lang_common['Subsections'].'</h2>'."\n" : '').implode('', $sections);

		$text = '';

		// There are favorite articles
		if (!empty($articles))
		{
			array_multisort($sort_array, $sort_order, $articles);
			foreach ($articles as $item)
				$text .= '<h3 class="article"><a href="'.s2_link($item['url']).'">'.$item['title'].'</a></h3>'."\n".
					($item['time'] ? '<div class="article date">'.s2_date($item['time']).'</div>'."\n" : '').
					(trim($item['excerpt']) ? '<p class="article">'.$item['excerpt'].'</p>'."\n" : '');
		}

		$page = array(
			'text'			=> $text.$subsection_text,
			'date'			=> '',
			'title'			=> $lang_common['Favorite'],
			'path'			=> '<a href="'.s2_link('/').'">'.s2_htmlencode(Model::main_page_title()).'</a>'.$lang_common['Crumbs separator'].$lang_common['Favorite'],
		);

		($hook = s2_hook('fn_s2_make_favorite_page_end')) ? eval($hook) : null;

		return $page;
	}
}
