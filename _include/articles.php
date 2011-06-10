<?php
/**
 * Loads functions for displaying articles.
 *
 * @copyright (C) 2007-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

($hook = s2_hook('art_start')) ? eval($hook) : null;

//
// Get URLs for some article as if there is one article.
// Returns an array containing full URLs, keys are preserved.
// If somewhere is a hidden parent, the URL is removed from the returning array.
//
// Actually it's one of the best things in S2! :)
//
function s2_get_group_url ($parent_ids, $urls)
{
	global $s2_db;

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

		// Thread was cutted (published = 0). Remove the entry in $url.
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
		'SELECT'	=> 'title',
		'FROM'		=> 'articles AS a1',
		'WHERE'		=> 'a1.id = a.parent_id'
	);
	$raw_query_parent_title = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$subquery = array(
		'SELECT'	=> 'a2.id',
		'FROM'		=> 'articles AS a2',
		'WHERE'		=> 'a2.parent_id = a.id AND a2.published = 1',
		'LIMIT'		=> '1'
	);
	$raw_query_child_num = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'id, title, create_time, modify_time, excerpt, url, parent_id, ('.$raw_query_parent_title.') AS ptitle',
		'FROM'		=> 'articles AS a',
		'ORDER BY'	=> 'create_time DESC',
		'WHERE'		=> '('.$raw_query_child_num.') IS NULL',
		'LIMIT'		=> $limit
	);

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
		$last[$i]['time'] = $row['create_time'];
		$last[$i]['modify_time'] = $row['modify_time'];
		$last[$i]['text'] = $row['excerpt'];
	}

	$urls = s2_get_group_url($parent_ids, $urls);

	foreach ($last as $k => $v)
		if (isset($urls[$k]))
			$last[$k]['rel_path'] = $urls[$k];
		else
			unset($last[$k]);

	return $last;
}

//
// Formatting last articles (for template placeholders)
//
function s2_last_articles ()
{
	$return = ($hook = s2_hook('fn_last_articles_start')) ? eval($hook) : null;
	if ($return)
		return $return;

	$articles = s2_last_articles_array();

	$output = '';
	foreach ($articles as $item)
		$output .= '<h2 class="preview"><small>'.$item['ptitle'].' &rarr;</small> <a href="'.S2_PATH.S2_URL_PREFIX.$item['rel_path'].'">'.$item['title'].'</a></h2>'.
			 '<div class="preview time">'.s2_date($item['time']).'</div>'.
			 '<div class="preview cite">'.$item['text'].'</div>';

	return $output;
}

//
// Fetching last comments (for template placeholders)
//
function s2_last_artilce_comments ()
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
		'SELECT'	=> 'time, url, title, nick, parent_id, ('.$raw_query1.') AS count',
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
		$output .= '<li><a href="'.S2_PATH.S2_URL_PREFIX.$url.'#'.$counts[$k].'">'.$titles[$k].'</a>, <em>'.$nicks[$k].'</em></li>';

	($hook = s2_hook('fn_last_article_comments_end')) ? eval($hook) : null;
	return $output ? '<ul>'.$output.'</ul>' : '';
}

function s2_last_discussions ()
{
	if (!S2_SHOW_COMMENTS)
		return '';

	global $s2_db;

	$subquery1 = array(
		'SELECT'	=> 'c.article_id AS article_id, count(c.article_id) AS comment_num',
		'FROM'		=> 'art_comments AS c',
		'WHERE'		=> 'c.shown = 1 AND c.time > '.((intval(time() / 86400) - 31)*86400),
		'GROUP BY'	=> 'c.article_id',
		'ORDER BY'	=> 'comment_num DESC',
	);
	$raw_query1 = $s2_db->query_build($subquery1, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'url, title, parent_id',
		'FROM'		=> 'articles AS a, ('.$raw_query1.') AS c1',
		'WHERE'		=> 'c1.article_id = a.id AND a.commented = 1 AND a.published = 1',
		'LIMIT'		=> '10',
	);
	($hook = s2_hook('fn_last_discussions_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$titles = $parent_ids = $urls = array();
	while ($row = $s2_db->fetch_assoc($result))
	{
		$titles[] = $row['title'];
		$parent_ids[] = $row['parent_id'];
		$urls[] = urlencode($row['url']);
	}

	$urls = s2_get_group_url($parent_ids, $urls);

	$output = '';
	foreach ($urls as $k => $url)
		$output .= '<li><a href="'.S2_PATH.S2_URL_PREFIX.$url.'">'.$titles[$k].'</a></li>';

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
		'SELECT'	=> 'a1.id',
		'FROM'		=> 'articles AS a1',
		'WHERE'		=> 'a1.parent_id = a.id AND a1.published = 1',
		'LIMIT'		=> '1'
	);
	$raw_query1 = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'a.id, url, title, parent_id, ('.$raw_query1.') IS NOT NULL AS children_exist',
		'FROM'		=> 'articles AS a',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'article_tag AS atg',
				'ON'			=> 'atg.article_id = a.id'
			),
		),
		'WHERE'		=> 'atg.tag_id = '.$tag_id.' AND published = 1',
	);
	($hook = s2_hook('fn_articles_by_tag_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$urls = $parent_ids = array();

	for ($i = 0; $row = $s2_db->fetch_assoc($result); $i++)
	{
		$urls[$i] = urlencode($row['url']).($row['children_exist'] ? '/' : '');
		$parent_ids[$i] = $row['parent_id'];
		$title[$i] = $row['title'];
	}
	$urls = s2_get_group_url($parent_ids, $urls);

	foreach ($urls as $k => $v)
		$urls[$k] = '<a href="'.S2_PATH.S2_URL_PREFIX.$v.'">'.$title[$k].'</a>';

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

//
// Builds tags pages
//
function s2_make_tags_pages ($request_array)
{
	global $s2_db, $lang_common;

	if (!isset($request_array[2]) || $request_array[2] == '')
	{
		// Tags list

		$query = array(
			'SELECT'	=> 'tag_id, name, url',
			'FROM'		=> 'tags'
		);
		($hook = s2_hook('fn_s2_make_tags_pages_pre_get_tags_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

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
		($hook = s2_hook('fn_s2_make_tags_pages_pre_get_posts_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		while ($row = $s2_db->fetch_row($result))
			$tag_count[$row[0]]++;

		arsort($tag_count);

		$tags = array();
		foreach ($tag_count as $id => $num)
			if ($num)
				$tags[] = '<a href="'.S2_PATH.S2_URL_PREFIX.'/'.S2_TAGS_URL.'/'.urlencode($tag_url[$id]).'/">'.$tag_name[$id].'</a>';

		($hook = s2_hook('fn_s2_make_tags_pages_tags_end')) ? eval($hook) : null;

		return array(
			'text'			=> implode('<br />', $tags),
			'date'			=> '',
			'head_title'	=> $lang_common['Tags'],
			'title'			=> $lang_common['Tags'],
			'path'			=> '<a href="'.S2_PATH.S2_URL_PREFIX.'/">'.s2_htmlencode(s2_main_page_title()).'</a>'.$lang_common['Crumbs separator'].$lang_common['Tags'],
		);
	}
	else
	{
		// Tag preview

		$query = array(
			'SELECT'	=> 'tag_id, description, name',
			'FROM'		=> 'tags',
			'WHERE'		=> 'url = \''.$s2_db->escape($request_array[2]).'\''
		);
		($hook = s2_hook('fn_make_tags_pages_pre_get_tag_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
		if (!$s2_db->num_rows($result))
			error_404();

		list($tag_id, $tag_description, $tag_name) = $s2_db->fetch_row($result);

		if ($tag_description)
			$tag_description .= '<hr />';

		$subquery = array(
			'SELECT'	=> 'a1.id',
			'FROM'		=> 'articles AS a1',
			'WHERE'		=> 'a1.parent_id = a.id AND a1.published = 1',
			'LIMIT'		=> '1'
		);
		$raw_query1 = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

		$query = array(
			'SELECT'	=> 'a.title, a.url, ('.$raw_query1.') IS NOT NULL AS children_exist, a.id, a.excerpt, a.create_time, a.parent_id',
			'FROM'		=> 'article_tag AS at',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'articles AS a',
					'ON'			=> 'a.id = at.article_id'
				),
			),
			'WHERE'		=> 'at.tag_id = '.$tag_id.' AND published = 1'
		);
		($hook = s2_hook('fn_make_tags_pages_pre_get_arts_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$urls = $parent_ids = $is_section = $excerpts = array();
		while ($row = $s2_db->fetch_assoc($result))
		{
			$urls[] = urlencode($row['url']);
			$is_section[] = $row['children_exist'];
			$time[] = $row['create_time'];
			$excerpts[] = $row['excerpt'];
			$titles[] = $row['title'];
			$parent_ids[] = $row['parent_id'];
		}

		$urls = s2_get_group_url($parent_ids, $urls);

		$subsection_text = '';
		$subarticles = array();
		foreach ($urls as $k => $url)
		{
			if ($is_section[$k])
				$subsection_text .= '<p class="subsection"><a href="'.S2_PATH.S2_URL_PREFIX.$url.'">'.s2_htmlencode($titles[$k]).'</a></p>';
			else
				$subarticles[] = array(
					'title' => s2_htmlencode($titles[$k]),
					'time' => $time[$k],
					'excerpt' => $excerpts[$k],
					'url' => $urls[$k]
				);
		}

		if ($subsection_text)
			$subsection_text = '<h2 class="subsections">'.$lang_common['Subsections'].'</h2>'.$subsection_text;

		$text = '';

		// There are articles in the section
		if (!empty($subarticles))
		{
			// Ordering articles by date
			$max = count($subarticles);
			for ($i = 0; $i < $max - 1; $i++)
				for ($j = $i + 1; $j < $max; $j++)
					if ($subarticles[$i]['time'] < $subarticles[$j]['time'])
					{
						$temp = $subarticles[$i];
						$subarticles[$i] = $subarticles[$j];
						$subarticles[$j] = $temp;
					}

			foreach ($subarticles as $item)
				$text .= '<h3 class="article"><a href="'.S2_PATH.S2_URL_PREFIX.$item['url'].'">'.$item['title'].'</a></h3>'."\n".
					'<div class="article date">'.s2_date($item['time']).'</div>'."\n".
					'<p class="article">'.$item['excerpt'].'</p>'."\n";
		}

		($hook = s2_hook('fn_s2_make_tags_pages_tag_end')) ? eval($hook) : null;

		return array(
			'text'			=> $tag_description.$text.$subsection_text,
			'date'			=> '',
			'head_title'	=> s2_htmlencode($tag_name),
			'title'			=> s2_htmlencode($tag_name),
			'path'			=> '<a href="'.S2_PATH.S2_URL_PREFIX.'/">'.s2_htmlencode(s2_main_page_title()).'</a>'.$lang_common['Crumbs separator'].'<a href="'.S2_PATH.S2_URL_PREFIX.'/'.S2_TAGS_URL.'/">'.$lang_common['Tags'].'</a>'.$lang_common['Crumbs separator'].s2_htmlencode($tag_name),
		);
	}
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
	if (!$s2_db->num_rows($result))
		return;

	$tag_names = $tag_urls = array();
	while ($row = $s2_db->fetch_assoc($result))
	{
		($hook = s2_hook('fn_tagged_articles_loop_get_tags')) ? eval($hook) : null;

		$tag_names[$row['tag_id']] = $row['name'];
		$tag_urls[$row['tag_id']] = $row['url'];
	}

	$subquery = array(
		'SELECT'	=> 'a1.id',
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
		'WHERE'		=> 'atg.tag_id IN ('.implode(', ', array_keys($tag_names)).') AND a.published = 1',
		'ORDER BY'	=> 'create_time'
	);
	($hook = s2_hook('fn_tagged_articles_pre_get_articles_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$s2_db->num_rows($result))
		return;

	// Build article lists that have the same tags as our article

	$create_tag_list = false;

	while ($row = $s2_db->fetch_assoc($result))
	{
		($hook = s2_hook('fn_tagged_articles_get_articles_loop')) ? eval($hook) : null;

		if ($id <> $row['id'])
			$create_tag_list = true;
		$titles[] = $row['title'];
		$parent_ids[] = $row['parent_id'];
		$urls[] = urlencode($row['url']).($row['children_exist'] ? '/' : '');
		$tag_ids[] = $row['tag_id'];
		$original_ids[] = $row['id'];
	}

	if ($create_tag_list)
		$urls = s2_get_group_url($parent_ids, $urls);

	// Sorting all obtained article links into groups by each tag
	$art_by_tags = array();

	foreach ($urls as $k => $url)
		$art_by_tags[$tag_ids[$k]][] = ($original_ids[$k] == $id) ?
			'<li class="active"><span>'.s2_htmlencode($titles[$k]).'</span></li>' :
			'<li><a href="'.S2_PATH.S2_URL_PREFIX.$urls[$k].'">'.s2_htmlencode($titles[$k]).'</a></li>';

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
		$output[] = '<div class="header">'.sprintf($lang_common['With this tag'], $tag_names[$tag_id]).'</div>'."\n".
			'<ul>' . $articles . '</ul>'."\n";

	($hook = s2_hook('fn_tagged_articles_end')) ? eval($hook) : null;
	return !empty($output) ? implode("\n", $output) : '';
}

// Processes site pages
function s2_parse_page_url ($request_uri)
{
	global $page, $s2_db, $template, $lang_common;

	$request_array = explode('/', $request_uri);   //   []/[dir1]/[dir2]/[dir3]/[file1]

	if (isset($request_array[1]) && $request_array[1] == S2_TAGS_URL)
	{
		// We process tags pages in a different way
		$page = s2_make_tags_pages($request_array);
		$template = s2_get_template('site.php');
		return;
	}

	$was_end_slash = '/' == substr($request_uri, -1);

	$bread_crumbs_links = $bread_crumbs_titles = array();

	$parent_path = '';
	$parent_id = S2_ROOT_ID;
	$max = count($request_array) - 1 - (int) $was_end_slash;

	$template_id = '';

	($hook = s2_hook('fn_s2_parse_page_url_start')) ? eval($hook) : null;

	// Walking through the page parents
	// 1. We ensure all of them are published
	// 2. We build "bread crumbs"
	// 3. We determine the template of the page
	for ($i = 0; $i < $max; $i++)
	{
		$parent_path .= urlencode($request_array[$i]).'/';

		$query = array(
			'SELECT'	=> 'id, title, template',
			'FROM'		=> 'articles',
			'WHERE'		=> 'url=\''.$s2_db->escape($request_array[$i]).'\' AND parent_id='.$parent_id.' AND published=1'
		);
		($hook = s2_hook('fn_s2_parse_page_url_loop_pre_get_parents_query')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$match_num = $s2_db->num_rows($result);
		if (!$match_num)
			error_404();
		if ($match_num > 1)
			error($lang_common['DB repeat items'] . (defined('S2_DEBUG') ? ' (parent_id='.$parent_id.', url="'.s2_htmlencode($request_array[$i]).'")' : ''));

		$row = $s2_db->fetch_assoc($result);

		($hook = s2_hook('fn_s2_parse_page_url_loop_pre_build_stuff')) ? eval($hook) : null;

		$bread_crumbs_titles[] = s2_htmlencode($row['title']);
		$parent_id = $row['id'];
		if ($row['template'] != '')
			$template_id = $row['template'];

		$bread_crumbs_links[] = '<a href="'.S2_PATH.S2_URL_PREFIX.$parent_path.'">'.s2_htmlencode($row['title']).'</a>';
	}

	// Path to the requested page (without trailing slash)
	$current_path = $parent_path.urlencode($request_array[$i]);

	$subquery = array(
		'SELECT'	=> 'a1.id',
		'FROM'		=> 'articles AS a1',
		'WHERE'		=> 'a1.parent_id = a.id AND a1.published = 1',
		'LIMIT'		=> '1'
	);
	$raw_query1 = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'id, title, meta_keys, meta_desc, pagetext, create_time, commented, template, children_preview, ('.$raw_query1.') IS NOT NULL AS children_exist',
		'FROM'		=> 'articles AS a',
		'WHERE'		=> 'url=\''.$s2_db->escape($request_array[$i]).'\' AND parent_id='.$parent_id.' AND published=1'
	);
	($hook = s2_hook('fn_s2_parse_page_url_pre_get_page')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	// Error handling
	$match_num = $s2_db->num_rows($result);
	if (!$match_num)
		error_404();
	if ($match_num > 1)
		error($lang_common['DB repeat items'] . (defined('S2_DEBUG') ? ' (parent_id='.$parent_id.', url="'.$request_array[$i].'")' : ''));

	$row = $s2_db->fetch_assoc($result);

	if ($row['template'])
		$template_id = $row['template'];

	if (!$template_id)
	{
		$bread_crumbs_links[] = '<a href="'.S2_PATH.S2_URL_PREFIX.$parent_path.'">'.s2_htmlencode($row['title']).'</a>';
		error(sprintf($lang_common['Error no template'], implode('<br />', $bread_crumbs_links)));
	}

	// Correcting URL
	if (!$row['children_exist'] && $was_end_slash)
	{
		// "file" - has no children
		header('HTTP/1.1 301');
		header('Location: '.S2_BASE_URL.S2_URL_PREFIX.$current_path);
		die;
	}
	if ($row['children_exist'] && !$was_end_slash)
	{
		// "folder" - has children
		header('HTTP/1.1 301'); 
		header('Location: '.S2_BASE_URL.S2_URL_PREFIX.$current_path.'/');
		die;
	}

	$id = $row['id'];

	$bread_crumbs_links[] = $bread_crumbs_titles[] = s2_htmlencode($row['title']);

	$page['children_exist'] = $row['children_exist'];
	$page['text'] = $row['pagetext'];
	$page['meta_keywords'] = $row['meta_keys'];
	$page['meta_description'] = $row['meta_desc'];
	$page['title'] = s2_htmlencode($row['title']);
	$page['date'] = !$page['children_exist'] ? $row['create_time'] : '';
	$page['commented'] = $row['commented'];
	$page['children_preview'] = $row['children_preview'];
	$page['id'] = $id;

	$page['path'] = implode($lang_common['Crumbs separator'], $bread_crumbs_links);

	// Getting page template
	$template = s2_get_template($template_id);

	$is_menu = strpos($template, '<!-- s2_menu -->') !== false;

	// Dealing with sections, subsections, neighbours
	if ($page['children_exist'] && (($page['children_preview'] && strpos($template, '<!-- s2_subarticles -->') !== false) || $is_menu))
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

		$query = array(
			'SELECT'	=> 'title, url, ('.$raw_query1.') IS NOT NULL AS children_exist, id, excerpt, create_time, parent_id',
			'FROM'		=> 'articles AS a',
			'WHERE'		=> 'parent_id = '.$id.' AND published = 1',
			'ORDER BY'	=> 'priority'
		);
		($hook = s2_hook('fn_s2_parse_page_url_pre_get_children_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$subarticles = $subsections = $menu_subsections = $menu_subarticles = array();
		while ($row = $s2_db->fetch_assoc($result))
		{
			if ($row['children_exist'])
			{
				// The child is a subsection
				$subsections[] = array(
					'title' => s2_htmlencode($row['title']),
					'url' => $current_path.'/'.urlencode($row['url']).'/'
				);
				$menu_subsections[] = '<li><a href="'.S2_PATH.S2_URL_PREFIX.$current_path.'/'.urlencode($row['url']).'/">'.s2_htmlencode($row['title']).'</a></li>';

				($hook = s2_hook('fn_s2_parse_page_url_add_subsection')) ? eval($hook) : null;
			}
			else
			{
				// The child is an article
				$subarticles[] = array(
					'title' => s2_htmlencode($row['title']),
					'time' => $row['create_time'],
					'excerpt' => $row['excerpt'],
					'url' => $current_path.'/'.urlencode($row['url'])
				);
				$menu_subarticles[] = '<li><a href="'.S2_PATH.S2_URL_PREFIX.$current_path.'/'.urlencode($row['url']).'">'.s2_htmlencode($row['title']).'</a></li>';

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

			if ($page['children_preview'])
			{
				// ... and to the page text
				$page['subcontent'] = '<h2 class="subsections">'.$lang_common['Subsections'].'</h2>';

				foreach ($subsections as $item)
					$page['subcontent'] .= '<p class="subsection"><a href="'.S2_PATH.S2_URL_PREFIX.$item['url'].'">'.$item['title'].'</a></p>';
			}
		}

		// There are articles in the section
		if (!empty($menu_subarticles))
		{
			// Add them to the menu...
			$page['menu']['articles'] = '<div class="header">'.$lang_common['In this section'].'</div>'."\n".
				'<ul>'.implode("\n", $menu_subarticles).'</ul>'."\n";

			if ($page['children_preview'])
			{
				// ... and to the page text
				$page['subcontent'] .= '<h2 class="articles">'.$lang_common['Read in this section'].'</h2>'."\n";

				// Ordering articles by date
				$max = count($subarticles);
				for ($i = 0; $i < $max - 1; $i++)
					for ($j = $i + 1; $j < $max; $j++)
						if ($subarticles[$i]['time'] < $subarticles[$j]['time'])
						{
							$temp = $subarticles[$i];
							$subarticles[$i] = $subarticles[$j];
							$subarticles[$j] = $temp;
						}

				foreach ($subarticles as $item)
					$page['subcontent'] .= '<h3 class="article"><a href="'.S2_PATH.S2_URL_PREFIX.$item['url'].'">'.$item['title'].'</a></h3>'."\n".
						'<div class="article date">'.s2_date($item['time']).'</div>'."\n".
						'<p class="article">'.$item['excerpt'].'</p>'."\n";
			}
		}
	}

	if (!$page['children_exist'] && ($is_menu || strpos($template, '<!-- s2_back_forward -->') !== false))
	{
		// It's an article. We have to fetch other articles in the parent section

		// Fetching "brothers"
		$query = array(
			'SELECT'	=> 'title, url, id, excerpt, create_time, parent_id',
			'FROM'		=> 'articles AS a',
			'WHERE'		=> 'parent_id = '.$parent_id.' AND published=1 AND (SELECT id FROM '.$s2_db->prefix.'articles i WHERE i.parent_id = a.id AND i.published = 1 LIMIT 1) IS NULL',
			'ORDER BY'	=> 'priority'
		);
		($hook = s2_hook('fn_s2_parse_page_url_pre_get_neighbours_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$menu_articles = array();
		$i = 0;
		$curr_item = -1;
		while ($row = $s2_db->fetch_assoc($result))
		{
			// A neighbour
			$menu_articles[] = ($id == $row['id']) ?
				'<li class="active"><span>'.s2_htmlencode($row['title']).'</span></li>' :
				'<li><a href="'.S2_PATH.S2_URL_PREFIX.$parent_path.urlencode($row['url']).'">'.s2_htmlencode($row['title']).'</a></li>';

			if ($id == $row['id'])
				$curr_item = $i;

			($hook = s2_hook('fn_s2_parse_page_url_add_neighbour')) ? eval($hook) : null;

			$i++;
		}

		$page['menu']['articles'] = '<div class="header">'.sprintf($lang_common['More in this section'], '<a href="'.S2_PATH.S2_URL_PREFIX.$parent_path.'">'.$bread_crumbs_titles[count($bread_crumbs_titles) - 2].'</a>').'</div>'."\n".
			'<ul>'."\n".implode("\n", $menu_articles)."\n".'</ul>'."\n";

		if ($curr_item != -1)
			$page['back_forward'] = '<ul class="back_forward">'.
				'<li class="up"><span class="arrow">&uarr;</span> <a href="'.S2_PATH.S2_URL_PREFIX.$parent_path.'">'.$bread_crumbs_titles[count($bread_crumbs_titles) - 2].'</a></li>'.
				(isset($menu_articles[$curr_item - 1]) ? str_replace('<li>', '<li class="back"><span class="arrow">&larr;</span> ', $menu_articles[$curr_item - 1]) : '<li class="back"><span class="arrow">&larr;</span> </li>').
				(isset($menu_articles[$curr_item + 1]) ? str_replace('<li>', '<li class="forward"><span class="arrow">&rarr;</span> ', $menu_articles[$curr_item + 1]) : '<li class="forward"><span class="arrow">&rarr;</span> </li>').
				'</ul>';
	}

	// Tags
	if (strpos($template, '<!-- s2_article_tags -->') !== false)
		$page['article_tags'] = s2_tagged_articles($id);

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