<?php
/**
 * Helper functions for blog pages
 *
 * @copyright (C) 2007-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */


// '1' -> '01'
function s2_blog_extent_number ($n)
{
	return strlen($n) == 1 ? '0'.$n : $n;
}

//
// HTML formatting
//

$s2_blog_fav_link = '<a href="'.BLOG_BASE.urlencode(S2_FAVORITE_URL).'/" class="favorite" title="'.$lang_s2_blog['Favorite'].'">*</a>';

function s2_blog_format_post ($header, $date, $date_time, $body, $keywords, $comments, $favorite = 0)
{
	global $s2_blog_fav_link, $lang_s2_blog;

	$html = '<h2 class="post head">%6$s%1$s</h2>'."\n".
		'<div class="post time">%3$s</div>'."\n".
		'<div class="post body">%4$s</div>'."\n".
		'<div class="post foot">%5$s</div>'."\n";

	($hook = s2_hook('fn_s2_blog_format_post_start')) ? eval($hook) : null;

	if (!S2_SHOW_COMMENTS)
		$comments = '';

	if ($keywords)
		$comments = sprintf($lang_s2_blog['Tags:'], $keywords).($comments ? ' | '.$comments : '');

	($hook = s2_hook('fn_s2_blog_format_post_end')) ? eval($hook) : null;

	return sprintf($html, $header, $date, $date_time, $body, $comments, ($favorite ? $s2_blog_fav_link : ''));
}

function s2_blog_format_see_also ($title_array)
{
	global $lang_s2_blog;

	$html = '<p class="see_also"><b>%1$s</b><br />%2$s</p>';
	$title_separator = '<br />';

	($hook = s2_hook('fn_s2_blog_format_see_also_start')) ? eval($hook) : null;

	return sprintf($html, $lang_s2_blog['See also'], implode($title_separator, $title_array));
}

//
// Content for different blog blocks and pages
//

function s2_blog_all_tags ()
{
	global $s2_db;

	$query = array(
		'SELECT'	=> 'tag_id, name, url',
		'FROM'		=> 'tags'
	);
	($hook = s2_hook('fn_s2_blog_all_tags_pre_get_tags_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	while ($row = $s2_db->fetch_assoc($result))
	{
		$tag_name[$row['tag_id']] = $row['name'];
		$tag_url[$row['tag_id']] = $row['url'];
		$tag_count[$row['tag_id']] = 0;
	}

	$query = array(
		'SELECT'	=> 'pt.tag_id',
		'FROM'		=> 's2_blog_post_tag AS pt',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 's2_blog_posts AS p',
				'ON'			=> 'p.id = pt.post_id'
			)
		),
		'WHERE'		=> 'p.published = 1'
	);
	($hook = s2_hook('fn_s2_blog_all_tags_pre_get_posts_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	while ($row = $s2_db->fetch_row($result))
		$tag_count[$row[0]]++;

	arsort($tag_count);

	$tags = array();
	foreach ($tag_count as $id => $num)
		if ($num)
			$tags[] = '<a href="'.BLOG_KEYWORDS.urlencode($tag_url[$id]).'/">'.$tag_name[$id].'</a> ('.$num.')';

	($hook = s2_hook('fn_s2_blog_all_tags_end')) ? eval($hook) : null;
	return implode('<br />', $tags);
}

function s2_blog_comment_text ($n)
{
	global $lang_s2_blog;

	return $n ? sprintf($lang_s2_blog['Comments'], $n) : (S2_ENABLED_COMMENTS ? $lang_s2_blog['Post comment'] : '');
}

function s2_blog_get_comments ($id)
{
	global $s2_db, $lang_common;

	$comments = '';
	$html_comment = '<div class="reply_info"><a name="%1$s" href="#%1$s">#%1$s</a>. %2$s</div>'."\n".
		'<div class="reply%3$s">%4$s</div>'."\n";

	$query = array(
		'SELECT'	=> 'nick, time, email, show_email, good, text',
		'FROM'		=> 's2_blog_comments',
		'WHERE'		=> 'post_id = '.$id.' AND shown = 1',
		'ORDER BY'	=> 'time'
	);
	($hook = s2_hook('fn_s2_blog_get_comments_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	for ($i = 1; $row = $s2_db->fetch_assoc($result); $i++)
	{
		$nick = s2_htmlencode($row['nick']);
		$name = '<strong>'.($row['show_email'] ? s2_js_mailto($nick, $row['email']) : $nick).'</strong>';

		($hook = s2_hook('fn_s2_blog_get_comments_pre_comment_merge')) ? eval($hook) : null;

		$comments .= sprintf($html_comment,
			$i,
			sprintf($lang_common['Comment info format'], s2_date_time($row['time']), $name),
			($row['good'] ? ' good' : ''),
			s2_bbcode_to_html(s2_htmlencode($row['text']))
		);
	}

	return $comments ? '<h2 class="comment">'.$lang_common['Comments'].'</h2>'."\n".$comments : '';
}

function s2_blog_get_posts ($query_add, $sort_asc = true, $sort_field = 'create_time')
{
	global $s2_db;

	// Obtaining posts

	// SELECT for comments number
	$sub_query = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 's2_blog_comments AS c',
		'WHERE'		=> 'c.post_id = p.id AND shown = 1',
	);
	$raw_query = $s2_db->query_build($sub_query, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'p.create_time, p.title, p.text, p.url, p.id, p.commented, p.favorite, ('.$raw_query.') AS comment_num, p.label',
		'FROM'		=> 's2_blog_posts AS p',
		'WHERE'		=> 'p.published = 1'.(!empty($query_add['WHERE']) ? ' AND '.$query_add['WHERE'] : '')
	);
	if (!empty($query_add['JOINS']))
		$query['JOINS'] = $query_add['JOINS'];

	($hook = s2_hook('fn_s2_blog_get_posts_pre_get_posts_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$posts = $merge_labels = $labels = $ids = $sort_array = array();
	while ($row = $s2_db->fetch_assoc($result))
	{
		$posts[$row['id']] = $row;
		$ids[] = $row['id'];
		$sort_array[] = $row[$sort_field];
		$labels[$row['id']] = $row['label'];
		if ($row['label'])
			$merge_labels[$row['label']] = 1;
	}
	if (empty($posts))
		return '';

	$see_also = $tags = array();
	s2_blog_posts_links($ids, $merge_labels, $see_also, $tags);

	array_multisort($sort_array, $sort_asc ? SORT_ASC : SORT_DESC, $ids);

	$output = '';
	foreach ($ids as $id)
	{
		$row = $posts[$id];
		$link = BLOG_BASE.date('Y/m/d/', $row['create_time']).urlencode($row['url']);
		$header = '<a href="'.$link.'">'.$row['title'].'</a>';
		$date = s2_date($row['create_time']);
		$time = s2_date_time($row['create_time']);
		$post_tags = isset($tags[$id]) ? implode(', ', $tags[$id]) : '';
		$text = $row['text'];

		if (!empty($labels[$id]) && isset($see_also[$labels[$id]]))
		{
			$label_copy = $see_also[$labels[$id]];
			if (isset($label_copy[$id]))
				unset($label_copy[$id]);
			$text .= s2_blog_format_see_also($label_copy);
		}

		$comment = $row['commented'] ? '<a href="'.$link.'#comment">'.s2_blog_comment_text($row['comment_num']).'</a>' : '';

		($hook = s2_hook('fn_s2_blog_get_posts_loop_pre_post_merge')) ? eval($hook) : null;

		$output .= s2_blog_format_post($header, $date, $time, $text, $post_tags, $comment, $row['favorite']);
	}

	return $output;
}

function s2_blog_posts_by_time ($year, $month, $day = false)
{
	global $s2_db, $lang_s2_blog;

	$link_nav = array();
	$paging = '';

	if ($day === false)
	{
		$start_time = mktime(0, 0, 0, $month, 1, $year);
		$end_time = mktime(0, 0, 0, $month + 1, 1, $year);
		$prev_time = mktime(0, 0, 0, $month - 1, 1, $year);

		$link_nav['up'] = BLOG_BASE.date('Y/', $start_time);

		if ($prev_time >= mktime(0, 0, 0, 1, 1, S2_START_YEAR))
		{
			$link_nav['prev'] = BLOG_BASE.date('Y/m/', $prev_time);
			$paging = '<a href="'.$link_nav['prev'].'">'.$lang_s2_blog['Here'].'</a> ';
		}
		if ($end_time < time())
		{
			$link_nav['next'] = BLOG_BASE.date('Y/m/', $end_time);
			$paging .= '<a href="'.$link_nav['next'].'">'.$lang_s2_blog['There'].'</a>';
		}

		if ($paging)
			$paging = '<p class="s2_blog_pages">'.$paging.'</p>';
	}
	else
	{
		if ((int) $day <= 0)
			error_404();
		$start_time = mktime(0, 0, 0, $month, $day, $year);
		$end_time = mktime(0, 0, 0, $month, $day + 1, $year);
		$link_nav['up'] = BLOG_BASE.date('Y/m/', $start_time);
	}

	$query_add = array(
		'WHERE'		=> 'p.create_time < '.$end_time.' AND p.create_time >= '.$start_time
	);
	$output = s2_blog_get_posts($query_add);

	if ($output == '')
	{
		s2_404_header();
		$output = $lang_s2_blog['Not found'];
	}

	return array('text' => $output.$paging, 'link_navigation' => $link_nav);
}

function s2_blog_get_post ($year, $month, $day, $url)
{
	global $s2_db, $lang_s2_blog;

	if (((int) $day) <= 0)
		error_404();

	$start_time = mktime(0, 0, 0, $month, $day, $year);
	$end_time = mktime(0, 0, 0, $month, $day+1, $year);

	$query = array(
		'SELECT'	=> 'create_time, title, text, id, commented, label, favorite',
		'FROM'		=> 's2_blog_posts',
		'WHERE'		=> 'create_time < '.$end_time.' AND create_time >= '.$start_time.' AND url = \''.$s2_db->escape($url).'\' AND published = 1'
	);
	($hook = s2_hook('fn_s2_blog_get_post_pre_get_post_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	if (!$row = $s2_db->fetch_assoc($result))
	{
		s2_404_header();
		return array(
			'text'				=> $lang_s2_blog['Not found'],
			'head_title'		=> $lang_s2_blog['Not found'],
			'link_navigation'	=> array('up' => BLOG_BASE.date('Y/m/d/', $start_time))
		);
	}

	$post_id = $row['id'];
	$label = $row['label'];

	if ($label)
	{
		// Getting posts that have the same label
		$query = array(
			'SELECT'	=> 'title, create_time, url',
			'FROM'		=> 's2_blog_posts',
			'WHERE'		=> 'label = \''.$s2_db->escape($label).'\' AND id <> '.$post_id.' AND published = 1',
			'ORDER BY'	=> 'create_time DESC'
		);
		($hook = s2_hook('fn_s2_blog_get_post_pre_get_labelled_posts_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$links = array();
		while ($row1 = $s2_db->fetch_assoc($result))
			$links[] = '<a href="'.BLOG_BASE.date('Y/m/d/', $row1['create_time']).urlencode($row1['url']).'">'.$row1['title'].'</a>';

		if (!empty($links))
			$row['text'] .= s2_blog_format_see_also($links);
	}

	// Getting tags
	$query = array(
		'SELECT'	=> 'name, url',
		'FROM'		=> 'tags AS t',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 's2_blog_post_tag AS pt',
				'ON'			=> 'pt.tag_id = t.tag_id'
			)
		),
		'WHERE'		=> 'post_id = '.$post_id,
		'ORDER BY'	=> 'pt.id'
	);
	($hook = s2_hook('fn_s2_blog_get_post_pre_get_labelled_posts_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$tags = array();
	while ($row1 = $s2_db->fetch_assoc($result))
		$tags[] = '<a href="'.BLOG_KEYWORDS.urlencode($row1['url']).'/">'.$row1['name'].'</a>';

	$output = s2_blog_format_post(
		$row['title'],
		s2_date($row['create_time']),
		s2_date_time($row['create_time']),
		$row['text'],
		implode(', ', $tags),
		'',
		$row['favorite']
	);
	$output .= '<a name="comment"></a>';
	if ($row['commented'] && S2_SHOW_COMMENTS)
		$output .= s2_blog_get_comments($post_id);

	return array(
		'text'				=> $output,
		'head_title'		=> $row['title'],
		'commented'			=> $row['commented'],
		'id'				=> $post_id,
		'link_navigation'	=> array('up' => BLOG_BASE.date('Y/m/d/', $start_time))
	);
}

function s2_blog_posts_by_tag ($tag)
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
	else
		error_404();

	if (!defined('S2_ARTICLES_FUNCTIONS_LOADED'))
		include S2_ROOT.'_include/articles.php';
	$art_links = s2_articles_by_tag($tag_id);
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
	$output = s2_blog_get_posts($query_add, false);
	if ($output == '')
		error_404();

	return array($tag_descr.$output, s2_htmlencode($tag_name));
}

function s2_blog_get_favorite_posts ()
{
	global $s2_db, $s2_blog_fav_link, $ext_info;

	$s2_blog_fav_link = '<span class="favorite">*</span>';

	$query_add = array(
		'WHERE'		=> 'favorite = 1'
	);
	$output = s2_blog_get_posts($query_add);
	if ($output == '')
		s2_404_header();

	return $output;
}

function s2_blog_calendar ($year, $month, $day, $url = '', $day_flags = false)
{
	global $s2_db, $lang_s2_blog, $lang_s2_blog_days;

	if ($month === '')
		$month = 1;

	$start_time = mktime(0, 0, 0, $month, 1, $year);
	$end_time = mktime(0, 0, 0, $month + 1, 1, $year);

	if ($start_time === false)
		error_404();

	// Dealing with week days
	$n = date('w', $start_time);
	if ($lang_s2_blog['Sunday starts week'] != '1')
	{
		$n -= 1;
		if ($n == -1) $n = 6;
	}

	// How many days have the month?
	$day_count = (int) date('j', mktime(0, 0, 0, $month + 1, 0, $year)); // day = 0

	// Flags for the days when posts have been written
	if ($day_flags === false)
	{
		$query = array(
			'SELECT'	=> 'create_time',
			'FROM'		=> 's2_blog_posts',
			'WHERE'		=> 'create_time < '.$end_time.' AND create_time >= '.$start_time.' AND published = 1'
		);
		($hook = s2_hook('fn_s2_blog_calendar_pre_get_days_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
		while ($row = $s2_db->fetch_row($result))
			$day_flags[1 + intval(($row[0] - $start_time) / 86400)] = 1;
	}

	// Header
	$month_name = s2_month($month);
	if ($day == '-1')
	{
		// One of 12 year tables
		if ($start_time < time())
			$month_name = '<a href="'.BLOG_BASE.date('Y/m', $start_time).'/">'.$month_name.'</a>';
		$header = '<tr class="nav"><th colspan="7" align="center">'.$month_name.'</th></tr>';
	}
	else
	{
		if ($day != '')
			$month_name = '<a href="'.BLOG_BASE.date('Y/m', $start_time).'/">'.$month_name.'</a>';

		// Links in the header
		$next_month = $end_time < time() ? '<a class="nav_mon" href="'.BLOG_BASE.date('Y/m', $end_time).'/" title="'.s2_month(date('m', $end_time)).date(', Y', $end_time).'">&rarr;</a>' : '&rarr;';

		$prev_time = mktime(0, 0, 0, $month - 1, 1, $year);
		$prev_month = $prev_time >= mktime(0, 0, 0, 1, 1, S2_START_YEAR) ? '<a class="nav_mon" href="'.BLOG_BASE.date('Y/m', $prev_time).'/" title="'.s2_month(date('m', $prev_time)).date(', Y', $prev_time).'">&larr;</a>' : '&larr;';

		$header = '<tr class="nav"><th>'.$prev_month.'</th><th align="center" colspan="5">'
			.$month_name.', <a href="'.BLOG_BASE.$year.'/">'.$year.'</a></th><th>'.$next_month.'</th></tr>';
	}

	// Titles
	$output = '<table class="cal">'.$header.'<tr>';
	for ($i = 0; $i < 7; $i++)
		$output .= '<th'.($i % 7 == 5 || $i % 7 == 6 ? ' class="sun"' : '').'>'.$lang_s2_blog_days[$i].'</th>';
	$output .= '</tr><tr>';

	// Empty cells before
	for ($i = 0; $i < $n; $i++)
		$output .= '<td'.($i % 7 == 5 || $i % 7 == 6 && $lang_s2_blog['Sunday starts week'] != '1' || $i % 7 == 0 && $lang_s2_blog['Sunday starts week'] == '1' ? ' class="sun"' : '').'></td>';

	// Days
	for ($i = 1; $i <= $day_count; $i++)
	{
		$n++;
		// Are there posts?
		$b = isset($day_flags[$i]) ? '<a href="'.BLOG_BASE.$year.'/'.s2_blog_extent_number($month).'/'.s2_blog_extent_number($i).'/">'.$i.'</a>' : $i;
		$classes = array();
		if ($i == $day)
			// Current day
			$classes[] = 'cur';
		if ($n % 7 == 0 || ($n % 7 == 6) && $lang_s2_blog['Sunday starts week'] != '1' || ($n % 7 == 1) && $lang_s2_blog['Sunday starts week'] == '1')
			// Weekend
			$classes[] = 'sun';
		$output .= '<td'.(!empty($classes) ? ' class="'.implode(' ', $classes).'"' : '').'>'.($i == $day && !$url ? $i : $b).'</td>';
		if (!($n % 7) && ($i != $day_count))
			$output .='</tr><tr>';
	}

	// Empty cells in the end
	while ($n % 7)
	{
		$n++;
		$output .= '<td'.($n % 7 == 0 || $n % 7 == 6 && $lang_s2_blog['Sunday starts week'] != '1' || $n % 7 == 1 && $lang_s2_blog['Sunday starts week'] == '1' ? ' class="sun"' : '').'></td>';
	}

	$output .= '</tr></table>';
	return $output;
}

function s2_blog_year_posts ($year)
{
	global $s2_db;

	$start_time = mktime(0, 0, 0, 1, 1, $year);
	$end_time = mktime(0, 0, 0, 1, 1, $year + 1);

	$query = array(
		'SELECT'	=> 'create_time',
		'FROM'		=> 's2_blog_posts',
		'WHERE'		=> 'create_time < '.$end_time.' AND create_time >= '.$start_time.' AND published = 1'
	);
	($hook = s2_hook('fn_s2_blog_year_posts_pre_get_days_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$day_flags = array_fill(1, 12, '');
	while ($row = $s2_db->fetch_row($result))
		$day_flags[(int) date('m', $row[0])][(int) date('j', $row[0])] = 1;

	$output = '<table class="yc" align="center"><tr>';
	for ($i = 1; $i <= 12; $i++)
	{
		$output .= '<td>'.s2_blog_calendar($year, s2_blog_extent_number($i), '-1', '', $day_flags[$i]).'</td>';
		if (!($i % 2) && ($i != 12))
			$output .= '</tr><tr>';
	}
	$output .= '</tr></table>';

	return $output;
}

//
// Fetching posts tags and labels
// $ids = array (10, 15, 20);
// $labels = array ('label1' => 1, 'label2' => 1, 'label3' => 1);
//
function s2_blog_posts_links ($ids, $labels, &$see_also, &$tags)
{
	global $s2_db;

	$ids = implode(', ', $ids);

	// Processing labels
	if (count($labels))
	{
		$query = array(
			'SELECT'	=> 'p.id, p.label, p.title, p.create_time, p.url',
			'FROM'		=> 's2_blog_posts AS p',
			'WHERE'		=> 'p.label IN (\''.implode('\', \'', array_keys($labels)).'\') AND p.published = 1'
		);
		($hook = s2_hook('fn_s2_blog_posts_links_pre_get_labels_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$rows = $sort_array = array();
		while ($row = $s2_db->fetch_assoc($result))
		{
			$rows[] = $row;
			$sort_array[] = $row['create_time'];
		}

		array_multisort($sort_array, SORT_DESC, $rows);

		foreach ($rows as $row)
			$see_also[$row['label']][$row['id']] = '<a href="'.BLOG_BASE.date('Y/m/d/', $row['create_time']).urlencode($row['url']).'">'.$row['title'].'</a>';
	}

	// Obtaining tags
	$query = array(
		'SELECT'	=> 'pt.post_id, t.name, t.url, pt.id AS pt_id',
		'FROM'		=> 'tags AS t',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 's2_blog_post_tag AS pt',
				'ON'			=> 'pt.tag_id = t.tag_id'
			)
		),
		'WHERE'		=> 'pt.post_id IN ('.$ids.')'
	);
	($hook = s2_hook('fn_s2_blog_posts_links_pre_get_tags_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$rows = $sort_array = array();
	while ($row = $s2_db->fetch_assoc($result))
	{
		$rows[] = $row;
		$sort_array[] = $row['pt_id'];
	}

	array_multisort($sort_array, $rows);

	foreach ($rows as $row)
		$tags[$row['post_id']][] = '<a href="'.BLOG_KEYWORDS.urlencode($row['url']).'/">'.$row['name'].'</a>';
}

// Returns an array containing info about 10 last posts
function s2_blog_last_posts_array ($num_posts = 10, $skip = 0, $fake_last_post = false)
{
	global $s2_db;

	if ($fake_last_post)
		$num_posts++;

	// Obtaining last posts
	$subquery = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 's2_blog_comments AS c',
		'WHERE'		=> 'c.post_id = p.id AND shown = 1',
	);
	$raw_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'create_time, title, text, url, id, commented, modify_time, favorite, ('.$raw_query.') AS comm_num, label',
		'FROM'		=> 's2_blog_posts AS p',
		'WHERE'		=> 'published = 1',
		'ORDER BY'	=> 'create_time DESC',
		'LIMIT'		=>((int) $num_posts).' OFFSET '.((int) $skip)
	);
	($hook = s2_hook('fn_s2_blog_last_posts_array_pre_get_ids_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$posts = $merge_labels = $labels = $ids = array();
	$i = 0;
	while ($row = $s2_db->fetch_assoc($result))
	{
		$i++;
		$posts[$row['id']] = $row;

		if ($i >= $num_posts && $fake_last_post)
			continue;

		$ids[] = $row['id'];
		$labels[$row['id']] = $row['label'];
		if ($row['label'])
			$merge_labels[$row['label']] = 1;
	}
	if (!$i)
		return array();

	$see_also = $tags = array();
	s2_blog_posts_links($ids, $merge_labels, $see_also, $tags);

	foreach ($posts as $i => $row)
	{
		if (!empty($labels[$i]) && isset($see_also[$labels[$i]]))
		{
			$label_copy = $see_also[$labels[$i]];
			if (isset($label_copy[$i]))
				unset($label_copy[$i]);
			$posts[$i]['text'] .= s2_blog_format_see_also($label_copy);
		}
		$posts[$i]['comments'] = $row['commented'] ? '<a href="'.BLOG_BASE.date('Y/m/d/', $row['create_time']).urlencode($row['url']).'#comment">'.s2_blog_comment_text($posts[$i]['comm_num']).'</a>' : '';
		$posts[$i]['tags'] = isset($tags[$i]) ? implode(', ', $tags[$i]) : '';
	}

	return $posts;
}

function s2_blog_last_posts ($skip = 0)
{
	global $s2_db, $lang_s2_blog;

	if ($skip < 0)
		$skip = 0;

	$posts_per_page = S2_MAX_ITEMS ? S2_MAX_ITEMS : 10;
	$posts = s2_blog_last_posts_array($posts_per_page, $skip, true);

	$output = '';
	$i = 0;
	foreach ($posts as $post)
	{
		$i++;
		if ($i > $posts_per_page)
			break;

		$output .= s2_blog_format_post(
			'<a href="'.BLOG_BASE.date('Y/m/d/', $post['create_time']).urlencode($post['url']).'">'.$post['title'].'</a>',
			s2_date($post['create_time']),
			s2_date_time($post['create_time']),
			$post['text'],
			$post['tags'],
			$post['comments'],
			$post['favorite']
		);
	}

	$paging = '';

	$link_nav = array();
	if ($skip > 0)
	{
		$link_nav['prev'] = BLOG_BASE.($skip > $posts_per_page ? 'skip/'.($skip - $posts_per_page) : '');
		$paging = '<a href="'.$link_nav['prev'].'">'.$lang_s2_blog['Here'].'</a> ';
	}
	if ($i > $posts_per_page)
	{
		$link_nav['next'] = BLOG_BASE.'skip/'.($skip + $posts_per_page);
		$paging .= '<a href="'.$link_nav['next'].'">'.$lang_s2_blog['There'].'</a>';
	}

	if ($paging)
		$output .= '<p class="s2_blog_pages">'.$paging.'</p>';

	return array('text' => $output, 'link_navigation' => $link_nav);
}

// Fetching last post and comments (for template placeholders)

function s2_blog_last_post ($num_post)
{
	$posts = s2_blog_last_posts_array($num_post);
	if (!count($posts))
		return '';

	$html = '<h2 class="preview">%1$s<a href="%2$s">%3$s</a></h2>'."\n".
		'<div class="preview time">%5$s</div>'."\n".
		'<div class="post body">%6$s</div>'."\n";

	($hook = s2_hook('fn_s2_blog_last_post_start')) ? eval($hook) : null;

	$output = '';
	foreach ($posts as $post)
	{
		$link = BLOG_BASE.date('Y/m/d/', $post['create_time']).urlencode($post['url']);
		$tag_prefix = $post['tags'] ? '<small>'.preg_replace('/<a.*?>(.*?)<\/a>/', "\\1", $post['tags']).' &rarr;</small> ' : '';

		($hook = s2_hook('fn_s2_blog_last_post_pre_post_merge')) ? eval($hook) : null;

		$output .= sprintf($html,
			$tag_prefix,
			$link,
			$post['title'],
			s2_date($post['create_time']),
			s2_date_time($post['create_time']),
			$post['text']
		);
	}

	return $output;
}

function s2_blog_recent_comments ()
{
	global $s2_db;

	if (!S2_SHOW_COMMENTS)
		return '';

	$subquery1 = array(
		'SELECT'	=> 'count(*) + 1',
		'FROM'		=> 's2_blog_comments AS c1',
		'WHERE'		=> 'shown = 1 AND c1.post_id = c.post_id AND c1.time < c.time'
	);
	$raw_query1 = $s2_db->query_build($subquery1, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'time, url, title, nick, create_time, ('.$raw_query1.') AS count',
		'FROM'		=> 's2_blog_comments AS c',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 's2_blog_posts AS p',
				'ON'			=> 'c.post_id = p.id'
			)
		),
		'WHERE'		=> 'commented = 1 AND published = 1 AND shown = 1',
		'ORDER BY'	=> 'time DESC',
		'LIMIT'		=> '5'
	);
	($hook = s2_hook('fn_s2_blog_recent_comments_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$output = '';
	while ($row = $s2_db->fetch_assoc($result))
		$output .= '<li><a href="'.BLOG_BASE.date('Y/m/d/', $row['create_time']).urlencode($row['url']).'#'.$row['count'].'">'.$row['title'].'</a>, <em>'.$row['nick'].'</em></li>';

	return $output ? '<ul>'.$output.'</ul>' : '';
}

function s2_blog_recent_discussions ($cur_url = '---')
{
	global $s2_db;

	if (!S2_SHOW_COMMENTS)
		return '';

	$subquery1 = array(
		'SELECT'	=> 'c.post_id AS post_id, count(c.post_id) AS comment_num',
		'FROM'		=> 's2_blog_comments AS c',
		'WHERE'		=> 'c.shown = 1 AND c.time > '.((intval(time() / 86400) - 31)*86400),
		'GROUP BY'	=> 'c.post_id',
		'ORDER BY'	=> 'comment_num DESC',
	);
	$raw_query1 = $s2_db->query_build($subquery1, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'create_time, url, title, c1.comment_num AS comment_num',
		'FROM'		=> 's2_blog_posts AS p, ('.$raw_query1.') AS c1',
		'WHERE'		=> 'c1.post_id = p.id AND p.commented = 1 AND p.published = 1',
		'LIMIT'		=> '10',
	);
	($hook = s2_hook('fn_s2_blog_recent_discussions_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$output = '';
	while ($row = $s2_db->fetch_assoc($result))
		$output .= '<li title="'.$row['comment_num'].'"><a href="'.BLOG_BASE.date('Y/m/d/', $row['create_time']).urlencode($row['url']).'">'.$row['title'].'</a></li>';
	$output = preg_replace('#<a href="'.preg_quote($cur_url, '#').'">(.*?)</a>#', '\\1', $output);
	return $output ? '<ul>'.$output.'</ul>' : '';
}

function s2_blog_navigation ($cur_url)
{
	global $s2_db, $lang_s2_blog;

	if (file_exists(S2_CACHE_DIR.'s2_blog_navigation.php'))
		include S2_CACHE_DIR.'s2_blog_navigation.php';

	if (empty($s2_blog_navigation) || !isset($s2_blog_navigation_time) || $s2_blog_navigation_time < time() - 900)
	{
		$s2_blog_navigation = array('last' => '<a href="'.BLOG_BASE.'">'.$lang_s2_blog['Nav last'].'</a>');

		$query = array(
			'SELECT'	=> '1',
			'FROM'		=> 's2_blog_posts',
			'WHERE'		=> 'published = 1 AND favorite = 1',
			'LIMIT'		=> '1'
		);
		($hook = s2_hook('fn_s2_blog_navigation_pre_is_favorite_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		if ($s2_db->fetch_row($result))
			$s2_blog_navigation['favorite'] = '<a href="'.BLOG_BASE.urlencode(S2_FAVORITE_URL).'/">'.$lang_s2_blog['Nav favorite'].'</a>';

		$query = array(
			'SELECT'	=> 't.name, t.url, count(t.tag_id)',
			'FROM'		=> 'tags AS t',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 's2_blog_post_tag AS pt',
					'ON'			=> 't.tag_id = pt.tag_id'
				),
				array(
					'INNER JOIN'	=> 's2_blog_posts AS p',
					'ON'			=> 'p.id = pt.post_id'
				)
			),
			'WHERE'		=> 't.s2_blog_important = 1 AND p.published = 1',
			'GROUP BY'	=> 't.tag_id',
			'ORDER BY'	=> '3 DESC',
		);
		($hook = s2_hook('fn_s2_blog_navigation_pre_get_tags_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$tags = '';
		while ($tag = $s2_db->fetch_assoc($result))
			$tags .= '<li><a href="'.BLOG_KEYWORDS.urlencode($tag['url']).'/">'.$tag['name'].'</a></li>';

		if ($tags != '')
			$s2_blog_navigation['tags'] = sprintf($lang_s2_blog['Nav tags'], BLOG_KEYWORDS).'<ul>'.$tags.'</ul>';

		// Output navigation array as PHP code
		$fh = @fopen(S2_CACHE_DIR.'s2_blog_navigation.php', 'ab+');
		if ($fh)
		{
			if (flock($fh, LOCK_EX | LOCK_NB))
			{
				ftruncate($fh, 0);
				fwrite($fh, '<?php'."\n\n".'$s2_blog_navigation_time = '.time().';'."\n\n".'$s2_blog_navigation = '.var_export($s2_blog_navigation, true).';');
				fflush($fh);
				fflush($fh);
				flock($fh, LOCK_UN);
			}
			fclose($fh);
		}
	}

	$cur_url = str_replace('%2F', '/', urlencode($cur_url));
	$output = '<ul><li>'.implode('</li><li>', $s2_blog_navigation).'</li></ul>';
	$output = preg_replace('#<a href="'.preg_quote($cur_url, '#').'">(.*?)</a>#', '\\1', $output);
	return $output;
}

define('S2_BLOG_FUNCTIONS_LOADED', 1);