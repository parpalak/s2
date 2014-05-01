<?php
/**
 * Loads functions for displaying articles.
 *
 * @copyright (C) 2007-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


if (!defined('S2_ROOT'))
	die;

($hook = s2_hook('art_start')) ? eval($hook) : null;

//
// Get URLs for some articles as if there is only one.
// Returns an array containing full URLs, keys are preserved.
// If somewhere is a hidden parent, the URL is removed from the returning array.
//
// Actually it's one of the best things in S2! :)
//
function s2_get_group_url ($parent_ids, $urls)
{
	global $s2_db;

	if (!S2_USE_HIERARCHY)
	{
		// Flat urls
		foreach ($urls as $k => $url)
			$urls[$k] = '/'.$url;

		return $urls;
	}

	while (count($parent_ids))
	{
		$flags = array();
		foreach ($parent_ids as $k => $v)
			$flags[$k] = 1;

		$query = array(
			'SELECT'	=> 'id, parent_id, url',
			'FROM'		=> 'articles',
			'WHERE'		=> 'id IN ('.implode(', ', array_unique($parent_ids)).') AND published = 1'
		);
		($hook = s2_hook('fn_get_cascade_urls_loop_pre_query')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		while ($row = $s2_db->fetch_assoc($result))
			// So, the loop may seem not pretty much.
			// But $parent_ids values don't have to be unique.
			foreach ($parent_ids as $k => $v)
				if ($parent_ids[$k] == $row['id'] && $flags[$k])
				{
					$parent_ids[$k] = $row['parent_id'];
					$urls[$k] = urlencode($row['url']).'/'.$urls[$k];
					$flags[$k] = 0;
					if ($row['parent_id'] == S2_ROOT_ID)
						// Thread finished - we are at the root.
						unset($parent_ids[$k]);
				}

		// Thread was cut (published = 0). Remove the entry in $url.
		foreach ($flags as $k => $flag)
			if ($flag)
			{
				unset($urls[$k]);
				unset($parent_ids[$k]);
			}
	}

	return $urls;
}

//
// Fetching last articles info (for template placeholders and RSS)
//
function s2_last_articles_array ($limit = '5')
{
	global $s2_db;

	$subquery = array(
		'SELECT'	=> '1',
		'FROM'		=> 'articles AS a2',
		'WHERE'		=> 'a2.parent_id = a.id AND a2.published = 1',
		'LIMIT'		=> '1'
	);
	$raw_query_child_num = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$subquery = array(
		'SELECT'	=> 'u.name',
		'FROM'		=> 'users AS u',
		'WHERE'		=> 'u.id = a.user_id'
	);
	$raw_query_user = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'a.id, a.title, a.create_time, a.modify_time, a.excerpt, a.favorite, a.url, a.parent_id, a1.title AS ptitle, a1.url AS p_url, ('.$raw_query_user.') AS author',
		'FROM'		=> 'articles AS a',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'articles AS a1',
				'ON'			=> 'a1.id = a.parent_id'
			)
		),
		'ORDER BY'	=> 'a.create_time DESC',
		'WHERE'		=> '('.$raw_query_child_num.') IS NULL AND (a.create_time <> 0 OR a.modify_time <> 0) AND a.published = 1',
	);

	if ($limit)
		$query['LIMIT'] = $limit;

	($hook = s2_hook('fn_last_articles_array_pre_get_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$last = $urls = $parent_ids = array();
	for ($i = 0; $row = $s2_db->fetch_assoc($result); $i++)
	{
		($hook = s2_hook('fn_last_articles_array_loop')) ? eval($hook) : null;

		$urls[$i] = urlencode($row['url']);
		$parent_ids[$i] = $row['parent_id'];

		$last[$i]['title'] = $row['title'];
		$last[$i]['ptitle'] = $row['ptitle'];
		$last[$i]['p_url'] = $row['p_url'];
		$last[$i]['time'] = $row['create_time'];
		$last[$i]['modify_time'] = $row['modify_time'];
		$last[$i]['favorite'] = $row['favorite'];
		$last[$i]['text'] = $row['excerpt'];
		$last[$i]['author'] = isset($row['author']) ? $row['author'] : '';
	}

	$urls = s2_get_group_url($parent_ids, $urls);

	foreach ($last as $k => $v)
		if (isset($urls[$k]))
			$last[$k]['rel_path'] = $urls[$k];
		else
			unset($last[$k]);

	($hook = s2_hook('fn_last_articles_array_end')) ? eval($hook) : null;

	return $last;
}

//
// Formatting last articles (for template placeholders)
//
function s2_last_articles ($num)
{
	$return = ($hook = s2_hook('fn_last_articles_start')) ? eval($hook) : null;
	if ($return)
		return $return;

	$articles = s2_last_articles_array($num);

	$output = '';
	foreach ($articles as $item)
	{
		($hook = s2_hook('fn_last_articles_loop')) ? eval($hook) : null;

		$output .= '<h2 class="preview'.($item['favorite'] ? ' favorite-item' : '').'">'.($item['favorite'] ? s2_favorite_link() : '').
			'<small><a class="preview_section" href="'.s2_link(S2_USE_HIERARCHY ? preg_replace('#[^/]*$#', '', $item['rel_path']) : $item['p_url']).'">'.$item['ptitle'].'</a> &rarr;</small> <a href="'.s2_link($item['rel_path']).'">'.s2_htmlencode($item['title']).'</a></h2>'.
			'<div class="preview time">'.s2_date($item['time']).'</div>'.
			'<div class="preview cite">'.$item['text'].'</div>';
	}

	($hook = s2_hook('fn_last_articles_end')) ? eval($hook) : null;

	return $output;
}

//
// Fetching last comments (for template placeholders)
//
function s2_last_article_comments ()
{
	if (!S2_SHOW_COMMENTS)
		return '';

	global $s2_db;

	$subquery1 = array(
		'SELECT'	=> 'count(*) + 1',
		'FROM'		=> 'art_comments AS c1',
		'WHERE'		=> 'shown = 1 AND c1.article_id = c.article_id AND c1.time < c.time'
	);
	$raw_query1 = $s2_db->query_build($subquery1, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'c.time, a.url, a.title, c.nick, a.parent_id, ('.$raw_query1.') AS count',
		'FROM'		=> 'articles AS a',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'art_comments AS c',
				'ON'			=> 'c.article_id = a.id'
			),
		),
		'WHERE'		=> 'published = 1 AND commented = 1 AND shown = 1',
		'ORDER BY'	=> 'time DESC',
		'LIMIT'		=> '5'
	);
	($hook = s2_hook('fn_last_article_comments_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$nicks = $titles = $parent_ids = $urls = $counts = array();
	while ($row = $s2_db->fetch_assoc($result))
	{
		$nicks[] = $row['nick'];
		$titles[] = $row['title'];
		$parent_ids[] = $row['parent_id'];
		$urls[] = urlencode($row['url']);
		$counts[] = $row['count'];
	}

	$urls = s2_get_group_url($parent_ids, $urls);

	$output = '';
	foreach ($urls as $k => $url)
		$output .= '<li><a href="'.s2_link($url).'#'.$counts[$k].'">'.s2_htmlencode($titles[$k]).'</a>, <em>'.s2_htmlencode($nicks[$k]).'</em></li>';

	($hook = s2_hook('fn_last_article_comments_end')) ? eval($hook) : null;
	return $output ? '<ul>'.$output.'</ul>' : '';
}

function s2_last_discussions ()
{
	if (!S2_SHOW_COMMENTS)
		return '';

	global $s2_db;

	$subquery1 = array(
		'SELECT'	=> 'c.article_id AS article_id, count(c.article_id) AS comment_num, max(c.id) AS max_id',
		'FROM'		=> 'art_comments AS c',
		'WHERE'		=> 'c.shown = 1 AND c.time > '.strtotime('-1 month midnight'),
		'GROUP BY'	=> 'c.article_id',
		'ORDER BY'	=> 'comment_num DESC',
	);
	$raw_query1 = $s2_db->query_build($subquery1, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'a.url, a.title, a.parent_id, c2.nick, c2.time',
		'FROM'		=> 'articles AS a, ('.$raw_query1.') AS c1',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'art_comments AS c2',
				'ON'			=> 'c2.id = c1.max_id'
			),
		),
		'WHERE'		=> 'c1.article_id = a.id AND a.commented = 1 AND a.published = 1',
		'LIMIT'		=> '10',
	);
	($hook = s2_hook('fn_last_discussions_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$titles = $parent_ids = $urls = $nicks = $time = array();
	while ($row = $s2_db->fetch_assoc($result))
	{
		$titles[] = $row['title'];
		$parent_ids[] = $row['parent_id'];
		$urls[] = urlencode($row['url']);
		$nicks[] = $row['nick'];
		$time[] = $row['time'];
	}

	$urls = s2_get_group_url($parent_ids, $urls);

	$output = '';
	foreach ($urls as $k => $url)
		$output .= '<li><a href="'.s2_link($url).'" title="'.s2_htmlencode($nicks[$k].' ('.s2_date_time($time[$k]).')').'">'.s2_htmlencode($titles[$k]).'</a></li>';

	($hook = s2_hook('fn_last_discussions_end')) ? eval($hook) : null;
	return $output ? '<ul>'.$output.'</ul>' : '';
}

//
// Returns the array of links to the articles with the tag specified
//
function s2_articles_by_tag ($tag_id)
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
	$urls = s2_get_group_url($parent_ids, $urls);

	foreach ($urls as $k => $v)
		$urls[$k] = '<a href="'.s2_link($v).'">'.$title[$k].'</a>';

	return $urls;
}

//
// Returns the title of the main page
//
function s2_main_page_title ()
{
	global $s2_db;

	$query = array(
		'SELECT'	=> 'title',
		'FROM'		=> 'articles',
		'WHERE'		=> 'parent_id = '.S2_ROOT_ID,
	);

	($hook = s2_hook('fn_s2_main_page_title_qr')) ? eval($hook) : null;

	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	$main_title = $s2_db->result($result);
	return $main_title;
}

function s2_favorite_link ()
{
	global $lang_common;

	$return = ($hook = s2_hook('fn_s2_favorite_link')) ? eval($hook) : null;
	if ($return)
		return $return;

	return '<a href="'.s2_link('/'.S2_FAVORITE_URL.'/').'" class="favorite-star" title="'.$lang_common['Favorite'].'">*</a>';
}


// Makes tags list for the tags page and the placeholder
function s2_tags_list ()
{
	global $s2_db;

	static $tags = array();
	static $ready = false;

	if (!$ready)
	{
		$query = array(
			'SELECT'	=> 'tag_id, name, url',
			'FROM'		=> 'tags'
		);
		($hook = s2_hook('fn_s2_tags_list_pre_get_tags_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$tag_count = $tag_name = $tag_url = array();
		while ($row = $s2_db->fetch_assoc($result))
		{
			$tag_name[$row['tag_id']] = $row['name'];
			$tag_url[$row['tag_id']] = $row['url'];
			$tag_count[$row['tag_id']] = 0;
		}

		// Well, it's a hack because we don't check parents' "published" property 
		$query = array(
			'SELECT'	=> 'at.tag_id',
			'FROM'		=> 'article_tag AS at',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'articles AS a',
					'ON'			=> 'a.id = at.article_id'
				)
			),
			'WHERE'		=> 'a.published = 1'
		);
		($hook = s2_hook('fn_s2_tags_list_pre_get_posts_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		while ($row = $s2_db->fetch_row($result))
			$tag_count[$row[0]]++;

		arsort($tag_count);

		foreach ($tag_count as $id => $num)
			if ($num)
				$tags[] = '<a href="'.s2_link('/'.S2_TAGS_URL.'/'.urlencode($tag_url[$id])).'/">'.$tag_name[$id].'</a>';

		$ready = true;
	}

	$output = implode('<br />', $tags);

	($hook = s2_hook('fn_s2_tags_list_end')) ? eval($hook) : null;

	return $output;
}

//
// Builds tags pages
//
function s2_make_tags_pages ($request_array)
{
	global $s2_db, $lang_common;

	if (!isset($request_array[2]) || $request_array[2] === '')
	{
		// Tag list

		$page = array(
			'text'			=> '<div class="tags_list">'.s2_tags_list().'</div>',
			'date'			=> '',
			'title'			=> $lang_common['Tags'],
			'path'			=> '<a href="'.s2_link('/').'">'.s2_htmlencode(s2_main_page_title()).'</a>'.$lang_common['Crumbs separator'].$lang_common['Tags'],
		);

		($hook = s2_hook('fn_s2_make_tags_pages_tags_end')) ? eval($hook) : null;

		return $page;
	}
	else
	{
		// Tag preview

		$query = array(
			'SELECT'	=> 'tag_id, description, name',
			'FROM'		=> 'tags',
			'WHERE'		=> 'url = \''.$s2_db->escape($request_array[2]).'\''
		);
		($hook = s2_hook('fn_s2_make_tags_pages_pre_get_tag_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		if ($row = $s2_db->fetch_row($result))
			list($tag_id, $tag_description, $tag_name) = $row;
		else
			s2_error_404();

		if (!isset($request_array[3]) || $request_array[3] !== '' || isset($request_array[4]))
		{
			// Correcting trailing slash
			header($_SERVER['SERVER_PROTOCOL'].' 301 Moved Permanently');
			header('Location: '.s2_abs_link('/'.S2_TAGS_URL.'/'.$request_array[2].'/'));
			die;
		}

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

		$urls = s2_get_group_url($parent_ids, $urls);

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
			'path'			=> '<a href="'.s2_link('/').'">'.s2_htmlencode(s2_main_page_title()).'</a>'.$lang_common['Crumbs separator'].'<a href="'.s2_link('/'.S2_TAGS_URL.'/').'">'.$lang_common['Tags'].'</a>'.$lang_common['Crumbs separator'].s2_htmlencode($tag_name),
		);

		($hook = s2_hook('fn_s2_make_tags_pages_tag_end')) ? eval($hook) : null;

		return $page;
	}
}

//
// Builds favorite page
//
function s2_make_favorite_page ($request_array)
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

	$urls = s2_get_group_url($parent_ids, $urls);

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
		'path'			=> '<a href="'.s2_link('/').'">'.s2_htmlencode(s2_main_page_title()).'</a>'.$lang_common['Crumbs separator'].$lang_common['Favorite'],
	);

	($hook = s2_hook('fn_s2_make_favorite_page_end')) ? eval($hook) : null;

	return $page;
}

//
// Functions below build every site page
//

function s2_tagged_articles ($id)
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
		$urls = s2_get_group_url($parent_ids, $urls);

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

function s2_get_tags ($id)
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
function s2_parse_page_url ($request_uri)
{
	global $page, $s2_db, $template, $lang_common;

	$request_array = explode('/', $request_uri);   //   []/[dir1]/[dir2]/[dir3]/[file1]

	if (isset($request_array[1]) && $request_array[1] == S2_TAGS_URL)
	{
		if (!isset($request_array[2]))
		{
			// Correcting trailing slash
			header($_SERVER['SERVER_PROTOCOL'].' 301 Moved Permanently');
			header('Location: '.s2_abs_link('/'.S2_TAGS_URL.'/'));
			die;
		}

		// We process tags pages in a different way
		$template_id = 'site.php';

		$return = ($hook = s2_hook('fn_s2_parse_page_url_pre_tags')) ? eval($hook) : null;
		if ($return)
			$page = $return;
		else
			$page = s2_make_tags_pages($request_array);

		$template = s2_get_template($template_id);
		return;
	}

	if (isset($request_array[1]) && $request_array[1] == S2_FAVORITE_URL)
	{
		if (!isset($request_array[2]) || $request_array[2] !== '' || count($request_array) > 3)
		{
			// Correcting trailing slash and the rest of URL
			header($_SERVER['SERVER_PROTOCOL'].' 301 Moved Permanently');
			header('Location: '.s2_abs_link('/'.S2_FAVORITE_URL.'/'));
			die;
		}

		// We process the favorite page in a different way
		$template_id = 'site.php';

		$return = ($hook = s2_hook('fn_s2_parse_page_url_pre_favorite')) ? eval($hook) : null;
		if ($return)
			$page = $return;
		else
			$page = s2_make_favorite_page($request_array);

		$template = s2_get_template($template_id);
		return;
	}

	if (!S2_USE_HIERARCHY && count($request_array) > 2)
	{
		// Correcting trailing slash and the rest of URL
		header('HTTP/1.1 301');
		header('Location: '.s2_abs_link('/'.$request_array[1]));
		die;
	}

	$was_end_slash = '/' == substr($request_uri, -1);

	$bread_crumbs_links = $bread_crumbs_titles = array();

	$parent_path = '';
	$parent_id = S2_ROOT_ID;
	$parent_num = count($request_array) - 1 - (int) $was_end_slash;

	$template_id = '';

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
				$template_id = $row['template'];

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
		$template_id = $page['template'];

	if (!$template_id)
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
	$template = s2_get_template($template_id);

	$is_menu = strpos($template, '<!-- s2_menu -->') !== false;

	// Dealing with sections, subsections, neighbours
	if (S2_USE_HIERARCHY && $page['children_exist'] && (strpos($template, '<!-- s2_subarticles -->') !== false || $is_menu || strpos($template, '<!-- s2_navigation_link -->') !== false))
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

	if (S2_USE_HIERARCHY && !$page['children_exist'] && ($is_menu || strpos($template, '<!-- s2_back_forward -->') !== false || strpos($template, '<!-- s2_navigation_link -->') !== false))
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
	if (strpos($template, '<!-- s2_article_tags -->') !== false)
		$page['article_tags'] = s2_tagged_articles($id);

	if (strpos($template, '<!-- s2_tags -->') !== false)
		$page['tags'] = s2_get_tags($id);

	// Comments
	if ($page['commented'] && S2_SHOW_COMMENTS && strpos($template, '<!-- s2_comments -->') !== false)
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

define('S2_ARTICLES_FUNCTIONS_LOADED', 1);