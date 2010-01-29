<?php
/**
 * Helper functions for blog pages
 *
 * @copyright (C) 2007-2010 Roman Parpalak
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

function s2_blog_format_post ($header, $time, $body, $keywords, $comments, $favorite = 0, $show_fav = 1)
{
	global $s2_blog_fav_link, $lang_s2_blog;

	if ($keywords)
		$comments = sprintf($lang_s2_blog['Tags:'], $keywords).($comments && S2_SHOW_COMMENTS ? ' | '.$comments : '');

	return '<h2 class="post head">'.($favorite && $show_fav ? $s2_blog_fav_link : '').$header.'</h2>'."\n".
		'<div class="post time">'.$time.'</div>'."\n".
		'<div class="post body">'.$body.'</div>'."\n".
		'<div class="post foot">'.$comments.'</div>'."\n";
}

function s2_blog_format_see_also ($title_array)
{
	global $lang_s2_blog;

	return '<p class="see_also"><strong>'.$lang_s2_blog['See also'].'</strong><br />' . implode('<br />', $title_array) . '</p>';
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

	$query = array(
		'SELECT'	=> 'nick, time, email, show_email, good, text',
		'FROM'		=> 's2_blog_comments',
		'WHERE'		=> 'post_id = '.$id.' AND shown = 1',
		'ORDER BY'	=> 'time'
	);
	($hook = s2_hook('fn_blog_get_comments_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	for ($i = 1; $row = $s2_db->fetch_assoc($result); $i++)
	{
		$nick = s2_htmlencode($row['nick']);
		$name = '<strong>'.($row['show_email'] ? s2_js_mailto($nick, $row['email']) : $nick).'</strong>';
		$link = '<a name="'.$i.'" href="#'.$i.'">#'.$i.'</a>. ';

		$comments .= '<div class="reply_info">'.$link.sprintf($lang_common['Comment info format'], s2_date_time($row['time']), $name).'</div>'."\n".
			'<div class="reply'.($row['good'] ? ' good' : '').'">'.s2_bbcode_to_html(s2_htmlencode($row['text'])).'</div>';
	}

	return $comments ? '<h2 class="comment">'.$lang_common['Comments'].'</h2>'.$comments : '';
}

function s2_blog_get_posts ($sub_query, $desc = '')
{
	global $s2_db;

	$a = array();

	// Processing "see also" links

	$sub_query2 = array(
		'SELECT'	=> 'p1.id, label',
		'FROM'		=> 's2_blog_posts AS p1, ('.$sub_query.') AS temp',
		'WHERE'		=> 'p1.id = temp.id AND p1.label <> \'\'',
	);
	$raw_query2 = $s2_db->query_build($sub_query2, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'temp2.id, p2.title, p2.create_time AS time, p2.url',
		'FROM'		=> 's2_blog_posts AS p2, ('.$raw_query2.') AS temp2',
		'WHERE'		=> 'temp2.label = p2.label AND p2.id <> temp2.id AND published = 1',
		'ORDER BY'	=> 'time DESC'
	);
	($hook = s2_hook('fn_blog_get_posts_pre_get_similar_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	while ($row = $s2_db->fetch_assoc($result))
		$a[$row['id']]['see_also'][] = '<a href="'.BLOG_BASE.date('Y/m/d/', $row['time']).urlencode($row['url']).'">'.$row['title'].'</a>';

	// Tag titles and URLs

	$query = array(
		'SELECT'	=> 'temp.id, name, url',
		'FROM'		=> 'tags AS t, ('.$sub_query.') AS temp',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 's2_blog_post_tag AS pt',
				'ON'			=> 'temp.id = pt.post_id'
			)
		),
		'WHERE'		=> 'pt.tag_id = t.tag_id',
		'ORDER BY'	=> 'pt.id'
	);
	($hook = s2_hook('fn_blog_get_posts_pre_get_tags_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	while ($row = $s2_db->fetch_assoc($result))
		$a[$row['id']]['tags'][] = '<a href="'.BLOG_KEYWORDS.urlencode($row['url']).'/">'.$row['name'].'</a>';

	// Obtaining posts

	// SELECT for comments count
	$sub_query2 = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 's2_blog_comments AS c',
		'WHERE'		=> 'c.post_id = temp.id AND shown = 1',
	);
	$raw_query2 = $s2_db->query_build($sub_query2, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'create_time, title, text, url, p.id as id, commented, favorite, ('.$raw_query2.') AS comment_count',
		'FROM'		=> 's2_blog_posts AS p, ('.$sub_query.') AS temp',
		'WHERE'		=> 'temp.id = p.id',
		'ORDER BY'	=> 'create_time '.$desc
	);
	($hook = s2_hook('fn_blog_get_posts_pre_get_posts_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$output = '';
	while ($row = $s2_db->fetch_assoc($result))
	{
		$link = BLOG_BASE.date('Y/m/d/', $row['create_time']).urlencode($row['url']);
		$header = '<a href="'.$link.'">'.$row['title'].'</a>';
		$time = s2_date_time($row['create_time']);
		$tags = isset($a[$row['id']]['tags']) ? implode(', ', $a[$row['id']]['tags']) : '';
		$text = $row['text'];
		if (isset($a[$row['id']]['see_also']))
			$text .= s2_blog_format_see_also($a[$row['id']]['see_also']);
		$comment = $row['commented'] ? '<a href="'.$link.'#comment">'.s2_blog_comment_text($row['comment_count']).'</a>' : '';

		($hook = s2_hook('fn_blog_get_posts_loop_pre_post_merge')) ? eval($hook) : null;

		$output .= s2_blog_format_post($header, $time, $text, $tags, $comment, $row['favorite']);
	}

	return $output;
}

function s2_blog_posts_by_time ($year, $month, $day = false)
{
	global $s2_db, $lang_s2_blog;

	if ($day === false)
	{
		$start_time = mktime(0, 0, 0, $month, 1, $year);
		$end_time = mktime(0, 0, 0, $month + 1, 1, $year);
	}
	else
	{
		if ((int) $day <= 0)
			error_404();
		$start_time = mktime(0, 0, 0, $month, $day, $year);
		$end_time = mktime(0, 0, 0, $month, $day + 1, $year);
	}

	$subquery = array(
		'SELECT'	=> 'id',
		'FROM'		=> 's2_blog_posts',
		'WHERE'		=> 'create_time < '.$end_time.' AND create_time >= '.$start_time.' AND published = 1'
	);
	$raw_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);
	$output = s2_blog_get_posts($raw_query);
	if ($output != '')
		return $output;

	s2_404_header();
	return $lang_s2_blog['Not found'];
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
	($hook = s2_hook('fn_blog_get_post_pre_get_post_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	if (!$row = $s2_db->fetch_assoc($result))
	{
		s2_404_header();
		return array($lang_s2_blog['Not found'], $lang_s2_blog['Not found'], '', '');
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
		($hook = s2_hook('fn_blog_get_post_pre_get_labelled_posts_qr')) ? eval($hook) : null;
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
	($hook = s2_hook('fn_blog_get_post_pre_get_labelled_posts_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$tags = array();
	while ($row1 = $s2_db->fetch_assoc($result))
		$tags[] = '<a href="'.BLOG_KEYWORDS.urlencode($row1['url']).'/">'.$row1['name'].'</a>';

	$output = s2_blog_format_post(
		$row['title'],
		s2_date_time($row['create_time']),
		$row['text'],
		implode(', ', $tags),
		'',
		$row['favorite']
	);
	$output .= '<a name="comment"></a>';
	if ($row['commented'] && S2_SHOW_COMMENTS)
		$output .= s2_blog_get_comments($post_id);

	return array($output, $row['title'], $row['commented'], $post_id);
}

function s2_blog_posts_by_tag ($tag)
{
	global $s2_db, $lang_s2_blog;

	$query = array(
		'SELECT'	=> 'tag_id, description, name',
		'FROM'		=> 'tags',
		'WHERE'		=> 'url = \''.$s2_db->escape($tag).'\''
	);
	($hook = s2_hook('fn_blog_posts_by_tag_pre_get_tag_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$s2_db->num_rows($result))
		error_404();

	list($tag_id, $tag_descr, $tag_name) = $s2_db->fetch_row($result);

	if (!defined('S2_ARTICLES_FUNCTIONS_LOADED'))
		include S2_ROOT.'include/articles.php';
	$art_links = s2_articles_by_tag($tag_id);
	if (count($art_links))
		$tag_descr .= '<p>'.$lang_s2_blog['Articles by tag'].'<br />'.implode('<br />', $art_links).'</p>';

	if ($tag_descr)
		$tag_descr .= '<hr />';

	$subquery = array(
		'SELECT'	=> 'post_id AS id',
		'FROM'		=> 's2_blog_post_tag',
		'WHERE'		=> 'tag_id = '.$tag_id
	);
	$raw_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);
	$output = s2_blog_get_posts($raw_query, ' DESC');
	if ($output == '')
		error_404();

	return array($tag_descr.$output, $tag_name);
}

function s2_blog_get_favorite_posts ()
{
	global $s2_db, $s2_blog_fav_link, $ext_info;

	$s2_blog_fav_link = '<span class="favorite">*</span>';

	$subquery = array(
		'SELECT'	=> 'id',
		'FROM'		=> 's2_blog_posts',
		'WHERE'		=> 'favorite = 1 AND published = 1'
	);
	$raw_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);
	$output = s2_blog_get_posts($raw_query);
	if ($output == '')
		s2_404_header();

	return $output;
}

function s2_blog_calendar ($year, $month, $day, $url = '', $day_flags = false)
{
	global $s2_db, $lang_s2_blog, $lang_s2_blog_days;

	// Cleanup month value
	if ($month !== '')
	{
		$month = (int) $month;
		if ($month > 12 || $month < 1)
			error_404();
	}
	else 
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
		($hook = s2_hook('fn_blog_calendar_pre_get_days_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
		while ($row = $s2_db->fetch_row($result))
			$day_flags[1 + intval(($row[0] - $start_time) / 86400)] = 1;
	}

	// Header
	$month_name = s2_month($month);
	if ($day == '-1')
	{
		if ($start_time < time())
			$month_name = '<a href="'.BLOG_BASE.date('Y/m', $start_time).'/">'.$month_name.'</a>';
		$header = '<tr><th colspan="7" align="center">'.$month_name.'</th></tr>';
	}
	else
	{
		if ($day != '')
			$month_name = '<a href="'.BLOG_BASE.date('Y/m', $start_time).'/">'.$month_name.'</a>';

		// Links in the header
		$next_month = $end_time < time() ? '<a class="nav_mon" href="'.BLOG_BASE.date('Y/m', $end_time).'/" title="'.s2_month(date('m', $end_time)).date(', Y', $end_time).'">&rarr;</a>' : '&rarr;';

		$prev_time = mktime(0, 0, 0, $month - 1, 1, $year);
		$prev_month = $prev_time >= mktime(0, 0, 0, 1, 1, S2_START_YEAR) ? '<a class="nav_mon" href="'.BLOG_BASE.date('Y/m', $prev_time).'/" title="'.s2_month(date('m', $prev_time)).date(', Y', $prev_time).'">&larr;</a>' : '&larr;';

		$header = '<tr><th>'.$prev_month.'</th><th align="center" colspan="5">'
			.$month_name.', <a href="'.BLOG_BASE.$year.'/">'.$year.'</a></th><th>'.$next_month.'</th></tr>';
	}

	// Titles
	$output = '<table class="cal">'.$header.'<tr class="dw">';
	for ($i = 0; $i < 7; $i++)
		$output .= '<td'.($i % 7 == 5 || $i % 7 == 6 ? ' class="sun"' : '').'>'.$lang_s2_blog_days[$i].'</td>';
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
	($hook = s2_hook('fn_blog_year_posts_pre_get_days_qr')) ? eval($hook) : null;
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

// Returns an array containing info about 10 last posts
function s2_blog_last_posts_array ()
{
	global $s2_db;

	// Obtaining last posts
	$subquery = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 's2_blog_comments AS c',
		'WHERE'		=> 'c.post_id = p.id AND shown = 1',
	);
	$raw_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'create_time, title, text, url, id, commented, modify_time, favorite, ('.$raw_query.') AS comm_num',
		'FROM'		=> 's2_blog_posts AS p',
		'WHERE'		=> 'published = 1',
		'ORDER BY'	=> 'create_time DESC',
		'LIMIT'		=> '10'
	);
	($hook = s2_hook('fn_blog_last_posts_array_pre_get_ids_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$ids = array();
	while ($row = $s2_db->fetch_assoc($result))
	{
		$posts[$row['id']] = $row;
		$ids[] = $row['id'];
	}

	$ids = implode(', ', $ids);
	$see_also = array();

	// Processing "see also" links

	$subquery = array(
		'SELECT'	=> 'p1.id, label',
		'FROM'		=> 's2_blog_posts AS p1',
		'WHERE'		=> 'p1.id IN ('.$ids.') AND p1.label <> \'\'',
	);
	$raw_query = $s2_db->query_build($subquery, true) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'temp.id, p2.title, p2.create_time AS time, p2.url',
		'FROM'		=> 's2_blog_posts AS p2, ('.$raw_query.') AS temp',
		'WHERE'		=> 'temp.label = p2.label AND p2.id <> temp.id AND published = 1',
		'ORDER BY'	=> 'time DESC'
	);
	($hook = s2_hook('fn_blog_last_posts_array_pre_get_similar_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	while ($row = $s2_db->fetch_assoc($result))
		$see_also[$row['id']][] = '<a href="'.BLOG_BASE.date('Y/m/d/', $row['time']).urlencode($row['url']).'">'.$row['title'].'</a>';

	// Obtaining tags

	$query = array(
		'SELECT'	=> 'pt.post_id AS id, name, url',
		'FROM'		=> 'tags AS t',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 's2_blog_post_tag AS pt',
				'ON'			=> 'pt.tag_id = t.tag_id'
			)
		),
		'WHERE'		=> 'pt.post_id IN ('.$ids.')',
		'ORDER BY'	=> 'pt.id'
	);
	($hook = s2_hook('fn_blog_last_posts_array_pre_get_tags_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	while ($row = $s2_db->fetch_assoc($result))
		$posts[$row['id']]['tags'][] = '<a href="'.BLOG_KEYWORDS.urlencode($row['url']).'/">'.$row['name'].'</a>';

	foreach($posts as $i => $row)
	{
		if (isset($see_also[$i]))
			$posts[$i]['text'] .= s2_blog_format_see_also($see_also[$i]);
		$posts[$i]['comments'] = $row['commented'] ? '<a href="'.BLOG_BASE.date('Y/m/d/', $row['create_time']).urlencode($row['url']).'#comment">'.s2_blog_comment_text($posts[$i]['comm_num']).'</a>' : '';
		$posts[$i]['tags'] = isset($posts[$i]['tags']) ? implode(', ', $posts[$i]['tags']) : '';
	}

	return $posts;
}

function s2_blog_last_posts ()
{
	$posts = s2_blog_last_posts_array();

	$output = '';
	foreach ($posts as $post)
		$output .= s2_blog_format_post(
			'<a href="'.BLOG_BASE.date('Y/m/d/', $post['create_time']).urlencode($post['url']).'">'.$post['title'].'</a>',
			s2_date_time($post['create_time']),
			$post['text'],
			$post['tags'],
			$post['comments'],
			$post['favorite']
		);

	return $output;
}

// Fetching last post and comments (for template placeholders)

function s2_blog_last_post ()
{
	$posts = s2_blog_last_posts_array();

	$output = '';
	for ($i = 0; $i < 1; $i++)
	{
		$post = array_shift($posts);
		$link = BLOG_BASE.date('Y/m/d/', $post['create_time']).urlencode($post['url']);
		$output .= '<h3 class="post head">'.($post['tags'] ? '<small>'.preg_replace('/<a.*?>(.*?)<\/a>/', "\\1", $post['tags']).' &rarr;</small> ' : '').'<a href="'.$link.'">'.$post['title'].'</a></h3>'."\n".
			'<div class="post time">'.s2_date_time($post['create_time']).'</div>'."\n".
			'<div class="post body">'.$post['text'].'</div>'."\n";
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
	($hook = s2_hook('fn_blog_recent_comments_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$output = '';
	while ($row = $s2_db->fetch_assoc($result))
		$output .= '<li><a href="'.BLOG_BASE.date('Y/m/d/', $row['create_time']).urlencode($row['url']).'#'.$row['count'].'">'.$row['title'].'</a>, <em>'.$row['nick'].'</em></li>';

	return '<ul>'.$output.'</ul>';
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
		($hook = s2_hook('fn_blog_navigation_pre_is_favorite_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		if ($s2_db->fetch_row($result))
			$s2_blog_navigation['favorite'] = '<a href="'.BLOG_BASE.urlencode(S2_FAVORITE_URL).'/">'.$lang_s2_blog['Nav favorite'].'</a>';

		$query = array(
			'SELECT'	=> 'name, url',
			'FROM'		=> 'tags',
			'WHERE'		=> 's2_blog_important = 1',
			'ORDER BY'	=> 'name'
		);
		($hook = s2_hook('fn_blog_navigation_pre_get_tags_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$tags = '';
		while ($tag = $s2_db->fetch_assoc($result))
			$tags .= '<li><a href="'.BLOG_KEYWORDS.urlencode($tag['url']).'/">'.$tag['name'].'</a></li>';

		if ($tags != '')
			$s2_blog_navigation['tags'] = sprintf($lang_s2_blog['Nav tags'], BLOG_KEYWORDS).'<ul>'.$tags.'</ul>';

		// Output navigation array as PHP code
		$fh = @fopen(S2_CACHE_DIR.'s2_blog_navigation.php', 'wb');
		if ($fh)
		{
			fwrite($fh, '<?php'."\n\n".'$s2_blog_navigation_time = '.time().';'."\n\n".'$s2_blog_navigation = '.var_export($s2_blog_navigation, true).';');
			fclose($fh);
		}
	}

	$cur_url = str_replace('%2F', '/', urlencode($cur_url));
	$output = '<ul><li>'.implode('</li><li>', $s2_blog_navigation).'</li></ul>';
	$output = preg_replace('#<a href="'.$cur_url.'">(.*?)</a>#', '\\1', $output);
	return $output;
}

define('S2_BLOG_FUNCTIONS_LOADED', 1);