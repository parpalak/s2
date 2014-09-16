<?php
/**
 * Displays a page stored in DB.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


class Page_Common extends Page_Abstract
{
	public function __construct (array $params = array())
	{
		$this->s2_parse_page_url($params['request_uri']);
	}

	//
	// Returns the array of links to the articles with the tag specified
	// TODO move
	//
	public static function articles_by_tag ($tag_id)
	{
		global $s2_db;

		$subquery = array(
			'SELECT'	=> '1',
			'FROM'		=> 'articles AS a1',
			'WHERE'		=> 'a1.parent_id = a.id AND a1.published = 1',
			'LIMIT'		=> '1'
		);
		$raw_query1 = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

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
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$title = $urls = $parent_ids = array();

		while ($row = $s2_db->fetch_assoc($result))
		{
			$urls[] = urlencode($row['url']).(S2_USE_HIERARCHY && $row['children_exist'] ? '/' : '');
			$parent_ids[] = $row['parent_id'];
			$title[] = $row['title'];
		}
		$urls = Model::get_group_url($parent_ids, $urls);

		foreach ($urls as $k => $v)
			$urls[$k] = '<a href="'.s2_link($v).'">'.$title[$k].'</a>';

		return $urls;
	}

	private static function tagged_articles ($id)
	{
		global $s2_db, $lang_common;

		$query = array(
			'SELECT'	=> 't.tag_id as tag_id, name, t.url as url',
			'FROM'		=> 'tags AS t',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'article_tag AS atg',
					'ON'			=> 'atg.tag_id = t.tag_id'
				)
			),
			'WHERE'		=> 'atg.article_id = '.$id
		);
		($hook = s2_hook('fn_tagged_articles_pre_get_tags_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$tag_names = $tag_urls = array();
		while ($row = $s2_db->fetch_assoc($result))
		{
			($hook = s2_hook('fn_tagged_articles_loop_get_tags')) ? eval($hook) : null;

			$tag_names[$row['tag_id']] = $row['name'];
			$tag_urls[$row['tag_id']] = $row['url'];
		}

		if (empty($tag_urls))
			return '';

		$subquery = array(
			'SELECT'	=> '1',
			'FROM'		=> 'articles AS a1',
			'WHERE'		=> 'a1.parent_id = atg.article_id AND a1.published = 1',
			'LIMIT'		=> '1'
		);
		$raw_query1 = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

		$query = array(
			'SELECT'	=> 'title, tag_id, parent_id, url, a.id AS id, ('.$raw_query1.') IS NOT NULL AS children_exist',
			'FROM'		=> 'articles AS a',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'article_tag AS atg',
					'ON'			=> 'a.id = atg.article_id'
				),
			),
			'WHERE'		=> 'atg.tag_id IN ('.implode(', ', array_keys($tag_names)).') AND a.published = 1'
//		'ORDER BY'	=> 'create_time'  // no temp table is created but order by ID is almost the same
		);
		($hook = s2_hook('fn_tagged_articles_pre_get_articles_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		// Build article lists that have the same tags as our article

		$create_tag_list = false;

		$titles = $parent_ids = $urls = $tag_ids = $original_ids = array();
		while ($row = $s2_db->fetch_assoc($result))
		{
			($hook = s2_hook('fn_tagged_articles_get_articles_loop')) ? eval($hook) : null;

			if ($id <> $row['id'])
				$create_tag_list = true;
			$titles[] = $row['title'];
			$parent_ids[] = $row['parent_id'];
			$urls[] = urlencode($row['url']).(S2_USE_HIERARCHY && $row['children_exist'] ? '/' : '');
			$tag_ids[] = $row['tag_id'];
			$original_ids[] = $row['id'];
		}

		if (empty($urls))
			return '';

		if ($create_tag_list)
			$urls = Model::get_group_url($parent_ids, $urls);

		// Sorting all obtained article links into groups by each tag
		$art_by_tags = array();

		foreach ($urls as $k => $url)
			$art_by_tags[$tag_ids[$k]][] = ($original_ids[$k] == $id) ?
				'<li class="active"><span>'.s2_htmlencode($titles[$k]).'</span></li>' :
				'<li><a href="'.s2_link($url).'">'.s2_htmlencode($titles[$k]).'</a></li>';

		($hook = s2_hook('fn_tagged_articles_pre_art_by_tags_merge')) ? eval($hook) : null;

		// Remove tags that have only one article
		foreach ($art_by_tags as $tag_id => $title_array)
			if (count($title_array) > 1)
				$art_by_tags[$tag_id] = implode ('', $title_array);
			else
				unset($art_by_tags[$tag_id]);

		$output = array();
		($hook = s2_hook('fn_tagged_articles_pre_menu_merge')) ? eval($hook) : null;
		foreach ($art_by_tags as $tag_id => $articles)
			$output[] = '<div class="header">'.sprintf($lang_common['With this tag'], '<a href="'.s2_link('/'.S2_TAGS_URL.'/'.urlencode($tag_urls[$tag_id]).'/').'">'.$tag_names[$tag_id].'</a>').'</div>'."\n".
				'<ul>' . $articles . '</ul>'."\n";

		($hook = s2_hook('fn_tagged_articles_end')) ? eval($hook) : null;
		return !empty($output) ? implode("\n", $output) : '';
	}

	private static function get_tags ($id)
	{
		global $s2_db, $lang_common;

		$query = array(
			'SELECT'	=> 'name, url',
			'FROM'		=> 'tags AS t',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'article_tag AS at',
					'ON'			=> 'at.tag_id = t.tag_id'
				)
			),
			'WHERE'		=> 'at.article_id = '.$id
		);
		($hook = s2_hook('fn_tags_list_pre_get_tags_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$tags = array();
		while ($row = $s2_db->fetch_assoc($result))
			$tags[] = '<a href="'.s2_link('/'.S2_TAGS_URL.'/'.urlencode($row['url']).'/').'">'.$row['name'].'</a>';

		if (empty($tags))
			return '';

		return '<p class="article_tags">'.sprintf($lang_common['Tags:'], implode($lang_common['Tags separator:'], $tags)).'</p>';
	}


	// Processes site pages
	private function s2_parse_page_url ($request_uri)
	{
		global $s2_db, $lang_common;

		$page = &$this->page;

		$request_array = explode('/', $request_uri);   //   []/[dir1]/[dir2]/[dir3]/[file1]

		// Correcting trailing slash and the rest of URL
		if (!S2_USE_HIERARCHY && count($request_array) > 2)
			s2_redirect('/'.$request_array[1]);

		$was_end_slash = '/' == substr($request_uri, -1);

		$bread_crumbs_links = $bread_crumbs_titles = array();

		$parent_path = '';
		$parent_id = Model::ROOT_ID;
		$parent_num = count($request_array) - 1 - (int) $was_end_slash;

		$this->template_id = '';

		($hook = s2_hook('fn_s2_parse_page_url_start')) ? eval($hook) : null;

		if (S2_USE_HIERARCHY)
		{
			// Walking through the page parents
			// 1. We ensure all of them are published
			// 2. We build "bread crumbs"
			// 3. We determine the template of the page
			for ($i = 0; $i < $parent_num; $i++)
			{
				$parent_path .= urlencode($request_array[$i]).'/';

				$query = array(
					'SELECT'	=> 'id, title, template',
					'FROM'		=> 'articles',
					'WHERE'		=> 'url=\''.$s2_db->escape($request_array[$i]).'\' AND parent_id='.$parent_id.' AND published=1'
				);
				($hook = s2_hook('fn_s2_parse_page_url_loop_pre_get_parents_query')) ? eval($hook) : null;
				$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

				$row = $s2_db->fetch_assoc($result);
				if (!$row)
					s2_error_404();
				if ($s2_db->fetch_assoc($result))
					error($lang_common['DB repeat items'] . (defined('S2_DEBUG') ? ' (parent_id='.$parent_id.', url="'.s2_htmlencode($request_array[$i]).'")' : ''));

				($hook = s2_hook('fn_s2_parse_page_url_loop_pre_build_stuff')) ? eval($hook) : null;

				$bread_crumbs_titles[] = s2_htmlencode($row['title']);
				$parent_id = $row['id'];
				if ($row['template'] != '')
					$this->template_id = $row['template'];

				$bread_crumbs_links[] = '<a href="'.s2_link($parent_path).'">'.s2_htmlencode($row['title']).'</a>';
			}
		}
		else
		{
			$parent_path = '/';
			$i = 1;
		}
		// Path to the requested page (without trailing slash)
		$current_path = $parent_path.urlencode($request_array[$i]);

		$subquery = array(
			'SELECT'	=> '1',
			'FROM'		=> 'articles AS a1',
			'WHERE'		=> 'a1.parent_id = a.id AND a1.published = 1',
			'LIMIT'		=> '1'
		);
		$raw_query_children = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

		$subquery = array(
			'SELECT'	=> 'u.name',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.id = a.user_id'
		);
		$raw_query_author = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

		$query = array(
			'SELECT'	=> 'a.id, a.title, a.meta_keys as meta_keywords, a.meta_desc as meta_description, a.excerpt as excerpt, a.pagetext as text, a.create_time as date, favorite, commented, template, ('.$raw_query_children.') IS NOT NULL AS children_exist, ('.$raw_query_author.') AS author',
			'FROM'		=> 'articles AS a',
			'WHERE'		=> 'url=\''.$s2_db->escape($request_array[$i]).'\''.(S2_USE_HIERARCHY ? ' AND parent_id='.$parent_id : '').' AND published=1'
		);
		($hook = s2_hook('fn_s2_parse_page_url_pre_get_page')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$page = $s2_db->fetch_assoc($result);

		// Error handling
		if (!$page)
			s2_error_404();
		if ($s2_db->fetch_assoc($result))
			error($lang_common['DB repeat items'] . (defined('S2_DEBUG') ? ' (parent_id='.$parent_id.', url="'.$request_array[$i].'")' : ''));

		if ($page['template'])
			$this->template_id = $page['template'];

		if (!$this->template_id)
		{
			if (S2_USE_HIERARCHY)
			{
				$bread_crumbs_links[] = '<a href="'.s2_link($parent_path).'">'.s2_htmlencode($page['title']).'</a>';
				error(sprintf($lang_common['Error no template'], implode('<br />', $bread_crumbs_links)));
			}
			else
				error($lang_common['Error no template flat']);
		}

		if (S2_USE_HIERARCHY && $parent_num && $page['children_exist'] != $was_end_slash)
		{
			// Correcting trailing slash
			header($_SERVER['SERVER_PROTOCOL'].' 301 Moved Permanently');
			header('Location: '.s2_abs_link($current_path.(!$was_end_slash ? '/' : '')));
			die;
		}

		$id = $page['id'];
		$page['title'] = $bread_crumbs_links[] = $bread_crumbs_titles[] = s2_htmlencode($page['title']);
		if (!empty($page['author']))
			$page['author'] = s2_htmlencode($page['author']);

		if (!empty($page['favorite']))
			$page['title_prefix'][] = s2_favorite_link();

		if (S2_USE_HIERARCHY)
		{
			$page['path'] = implode($lang_common['Crumbs separator'], $bread_crumbs_links);

			$page['link_navigation']['top'] = s2_link('/');
			if (count($bread_crumbs_titles) > 1)
			{
				$page['link_navigation']['up'] = s2_link($parent_path);
				$page['section_link'] = '<a href="'.s2_link($parent_path).'">'.$bread_crumbs_titles[count($bread_crumbs_titles) - 2].'</a>';
			}
		}

		($hook = s2_hook('fn_s2_parse_page_url_pre_get_tpl')) ? eval($hook) : null;

		// Getting page template
		$this->obtainTemplate();

		$is_menu = strpos($this->template, '<!-- s2_menu -->') !== false;

		// Dealing with sections, subsections, neighbours
		if (S2_USE_HIERARCHY && $page['children_exist'] && (strpos($this->template, '<!-- s2_subarticles -->') !== false || $is_menu || strpos($this->template, '<!-- s2_navigation_link -->') !== false))
		{
			// It's a section. We have to fetch subsections and articles.

			// Fetching children
			$subquery = array(
				'SELECT'	=> 'a1.id',
				'FROM'		=> 'articles AS a1',
				'WHERE'		=> 'a1.parent_id = a.id AND a1.published = 1',
				'LIMIT'		=> '1'
			);
			$raw_query1 = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

			$sort_order = SORT_DESC;
			$query = array(
				'SELECT'	=> 'title, url, ('.$raw_query1.') IS NOT NULL AS children_exist, id, excerpt, favorite, create_time, parent_id',
				'FROM'		=> 'articles AS a',
				'WHERE'		=> 'parent_id = '.$id.' AND published = 1',
				'ORDER BY'	=> 'priority'
			);
			($hook = s2_hook('fn_s2_parse_page_url_pre_get_children_qr')) ? eval($hook) : null;
			$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

			$subarticles = $subsections = $menu_subsections = $menu_subarticles = $sort_array = array();
			while ($row = $s2_db->fetch_assoc($result))
			{
				if ($row['children_exist'])
				{
					// The child is a subsection
					$subsections[] = array(
						'title'		=> s2_htmlencode($row['title']),
						'time'		=> $row['create_time'],
						'excerpt'	=> $row['excerpt'],
						'favorite'	=> $row['favorite'],
						'url'		=> $current_path.'/'.urlencode($row['url']).'/'
					);
					$menu_subsections[] = '<li><a href="'.s2_link($current_path.'/'.urlencode($row['url']).'/').'">'.s2_htmlencode($row['title']).'</a></li>';

					($hook = s2_hook('fn_s2_parse_page_url_add_subsection')) ? eval($hook) : null;
				}
				else
				{
					// The child is an article
					$subarticles[] = array(
						'title'		=> s2_htmlencode($row['title']),
						'time'		=> $row['create_time'],
						'excerpt'	=> $row['excerpt'],
						'favorite'	=> $row['favorite'],
						'url'		=> $current_path.'/'.urlencode($row['url'])
					);
					$sort_array[] = $row['create_time'];
					$menu_subarticles[] = '<li><a href="'.s2_link($current_path.'/'.urlencode($row['url'])).'">'.s2_htmlencode($row['title']).'</a></li>';

					($hook = s2_hook('fn_s2_parse_page_url_add_subarticle')) ? eval($hook) : null;
				}
			}

			$page['menu']['articles'] = ''; // moves articles up :)
			$page['subcontent'] = '';

			// There are subsections in the section
			if (!empty($menu_subsections))
			{
				// Add them to the menu...
				$page['menu']['subsections'] = '<div class="header">'.$lang_common['Subsections'].'</div>'."\n".
					'<ul>'.implode("\n", $menu_subsections).'</ul>'."\n";

				// ... and to the page text
				$page['subcontent'] = $lang_common['Subsections'] ? '<h2 class="subsections">'.$lang_common['Subsections'].'</h2>'."\n" : '';

				foreach ($subsections as $item)
					$page['subcontent'] .= '<h3 class="subsection'.($item['favorite'] ? ' favorite-item' : '').'">'.($item['favorite'] ? s2_favorite_link() : '').
						'<a href="'.s2_link($item['url']).'">'.$item['title'].'</a></h3>'."\n".
						($item['time'] ? '<div class="subsection date">'.s2_date($item['time']).'</div>'."\n" : '').
						(trim($item['excerpt']) ? '<p class="subsection">'.$item['excerpt'].'</p>'."\n" : '');
			}

			// There are articles in the section
			if (!empty($menu_subarticles))
			{
				// Add them to the menu...
				$page['menu']['articles'] = '<div class="header">'.$lang_common['In this section'].'</div>'."\n".
					'<ul>'.implode("\n", $menu_subarticles).'</ul>'."\n";

				// ... and to the page text
				$page['subcontent'] .= $lang_common['Read in this section'] ? '<h2 class="articles">'.$lang_common['Read in this section'].'</h2>'."\n" : '';

				($sort_order == SORT_DESC) ? arsort($sort_array) : asort($sort_array);

				if (S2_MAX_ITEMS)
				{
					// Paging navigation
					$page_num = isset($_GET['~']) ? intval($_GET['~']) - 1 : 0;
					if ($page_num < 0)
						$page_num = 0;

					$start = $page_num * S2_MAX_ITEMS;
					if ($start >= count($subarticles))
						$page_num = $start = 0;

					$total_pages = ceil(1.0 * count($subarticles) / S2_MAX_ITEMS);

					$link_nav = array();
					$paging = s2_paging($page_num + 1, $total_pages, s2_link(str_replace('%', '%%', $current_path.'/'), array('~=%d')), $link_nav)."\n";
					foreach ($link_nav as $rel => $href)
						$page['link_navigation'][$rel] = $href;

					$i = 0;
					foreach ($sort_array as $index => $value)
					{
						if ($i < $start || $i >= $start + S2_MAX_ITEMS)
							unset($sort_array[$index]);
						$i++;
					}
				}

				foreach ($sort_array as $index => $value)
				{
					$item = $subarticles[$index];
					$page['subcontent'] .= '<h3 class="article'.($item['favorite'] ? ' favorite-item' : '').'">'.($item['favorite'] ? s2_favorite_link() : '').
						'<a href="'.s2_link($item['url']).'">'.$item['title'].'</a></h3>'."\n".
						($item['time'] ? '<div class="article date">'.s2_date($item['time']).'</div>'."\n" : '').
						(trim($item['excerpt']) ? '<p class="article">'.$item['excerpt'].'</p>'."\n" : '');
				}

				if (S2_MAX_ITEMS)
					$page['subcontent'] .= $paging;
			}
		}

		if (S2_USE_HIERARCHY && !$page['children_exist'] && ($is_menu || strpos($this->template, '<!-- s2_back_forward -->') !== false || strpos($this->template, '<!-- s2_navigation_link -->') !== false))
		{
			// It's an article. We have to fetch other articles in the parent section

			// Fetching "brothers"
			$subquery = array(
				'SELECT'	=> '1',
				'FROM'		=> 'articles AS a2',
				'WHERE'		=> 'a2.parent_id = a.id AND a2.published = 1',
				'LIMIT'		=> '1'
			);
			$raw_query_child_num = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

			$query = array(
				'SELECT'	=> 'title, url, id, excerpt, create_time, parent_id',
				'FROM'		=> 'articles AS a',
				'WHERE'		=> 'parent_id = '.$parent_id.' AND published=1 AND ('.$raw_query_child_num.') IS NULL',
				'ORDER BY'	=> 'priority'
			);
			($hook = s2_hook('fn_s2_parse_page_url_pre_get_neighbours_qr')) ? eval($hook) : null;
			$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

			$neighbour_urls = $menu_articles = array();
			$i = 0;
			$curr_item = -1;
			while ($row = $s2_db->fetch_assoc($result))
			{
				// A neighbour
				$url = s2_link($parent_path.urlencode($row['url']));
				if ($id == $row['id'])
				{
					$menu_articles[] = '<li class="active"><span>'.s2_htmlencode($row['title']).'</span></li>';
					$curr_item = $i;
				}
				else
					$menu_articles[] = '<li><a href="'.$url.'">'.s2_htmlencode($row['title']).'</a></li>';

				$neighbour_urls[] = $url;

				($hook = s2_hook('fn_s2_parse_page_url_add_neighbour')) ? eval($hook) : null;

				$i++;
			}

			if (count($bread_crumbs_titles) > 1)
				$page['menu']['articles'] = '<div class="header">'.sprintf($lang_common['More in this section'], '<a href="'.s2_link($parent_path).'">'.$bread_crumbs_titles[count($bread_crumbs_titles) - 2].'</a>').'</div>'."\n".
					'<ul>'."\n".implode("\n", $menu_articles)."\n".'</ul>'."\n";


			if ($curr_item != -1)
			{
				if (isset($neighbour_urls[$curr_item - 1]))
					$page['link_navigation']['prev'] = $neighbour_urls[$curr_item - 1];
				if (isset($neighbour_urls[$curr_item + 1]))
					$page['link_navigation']['next'] = $neighbour_urls[$curr_item + 1];

				$page['back_forward'] = '<ul class="back_forward">'.
					'<li class="up"><span class="arrow">&uarr;</span>'.(count($bread_crumbs_titles) > 1 ? ' <a href="'.s2_link($parent_path).'">'.$bread_crumbs_titles[count($bread_crumbs_titles) - 2].'</a>' : '').'</li>'.
					(isset($menu_articles[$curr_item - 1]) ? str_replace('<li>', '<li class="back"><span class="arrow">&larr;</span> ', $menu_articles[$curr_item - 1]) : '<li class="back empty"><span class="arrow">&larr;</span> </li>').
					(isset($menu_articles[$curr_item + 1]) ? str_replace('<li>', '<li class="forward"><span class="arrow">&rarr;</span> ', $menu_articles[$curr_item + 1]) : '<li class="forward empty"><span class="arrow">&rarr;</span> </li>').
					'</ul>';
			}
		}

		// Tags
		if (strpos($this->template, '<!-- s2_article_tags -->') !== false)
			$page['article_tags'] = self::tagged_articles($id);

		if (strpos($this->template, '<!-- s2_tags -->') !== false)
			$page['tags'] = self::get_tags($id);

		// Comments
		if ($page['commented'] && S2_SHOW_COMMENTS && strpos($this->template, '<!-- s2_comments -->') !== false)
		{
			$query = array(
				'SELECT'	=> 'nick, time, email, show_email, good, text',
				'FROM'		=> 'art_comments',
				'WHERE'		=> 'article_id = '.$id.' AND shown = 1',
				'ORDER BY'	=> 'time'
			);
			($hook = s2_hook('fn_s2_parse_page_url_pre_get_comm_qr')) ? eval($hook) : null;
			$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

			$comments = '';

			for ($i = 1; $row = $s2_db->fetch_assoc($result); $i++)
			{
				$nick = s2_htmlencode($row['nick']);
				$name = '<strong>'.($row['show_email'] ? s2_js_mailto($nick, $row['email']) : $nick).'</strong>';
				$link = '<a name="'.$i.'" href="#'.$i.'">#'.$i.'</a>. ';

				($hook = s2_hook('fn_s2_parse_page_url_pre_comment_merge')) ? eval($hook) : null;

				$comments .= '<div class="reply_info'.($row['good'] ? ' good' : '').'">'.$link.sprintf($lang_common['Comment info format'], s2_date_time($row['time']), $name).'</div>'."\n".
					'<div class="reply'.($row['good'] ? ' good' : '').'">'.s2_bbcode_to_html(s2_htmlencode($row['text'])).'</div>';
			}

			if ($comments)
				$page['comments'] = '<h2 class="comment">'.$lang_common['Comments'].'</h2>'.$comments;
		}

		($hook = s2_hook('fn_s2_parse_page_url_end')) ? eval($hook) : null;
	}
}
