<?php
/**
 * Helper blog functions.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;


class Lib
{
	// '1' -> '01'
	public static function extend_number ($n)
	{
		return strlen($n) == 1 ? '0'.$n : $n;
	}

	public static function calendar ($year, $month, $day, $url = '', $day_flags = false)
	{
		global $s2_db, $lang_s2_blog, $lang_s2_blog_days;

		if ($month === '')
			$month = 1;

		$start_time = mktime(0, 0, 0, $month, 1, $year);
		$end_time = mktime(0, 0, 0, $month + 1, 1, $year);

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
				$month_name = '<a href="'.S2_BLOG_PATH.date('Y/m', $start_time).'/">'.$month_name.'</a>';
			$header = '<tr class="nav"><th colspan="7" align="center">'.$month_name.'</th></tr>';
		}
		else
		{
			if ($day != '')
				$month_name = '<a href="'.S2_BLOG_PATH.date('Y/m', $start_time).'/">'.$month_name.'</a>';

			// Links in the header
			$next_month = $end_time < time() ? '<a class="nav_mon" href="'.S2_BLOG_PATH.date('Y/m', $end_time).'/" title="'.s2_month(date('m', $end_time)).date(', Y', $end_time).'">&rarr;</a>' : '&rarr;';

			$prev_time = mktime(0, 0, 0, $month - 1, 1, $year);
			$prev_month = $prev_time >= mktime(0, 0, 0, 1, 1, S2_START_YEAR) ? '<a class="nav_mon" href="'.S2_BLOG_PATH.date('Y/m', $prev_time).'/" title="'.s2_month(date('m', $prev_time)).date(', Y', $prev_time).'">&larr;</a>' : '&larr;';

			$header = '<tr class="nav"><th>'.$prev_month.'</th><th align="center" colspan="5">'
				.$month_name.', <a href="'.S2_BLOG_PATH.$year.'/">'.$year.'</a></th><th>'.$next_month.'</th></tr>';
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
			$b = isset($day_flags[$i]) ? '<a href="'.S2_BLOG_PATH.$year.'/'.self::extend_number($month).'/'.self::extend_number($i).'/">'.$i.'</a>' : $i;
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

	public static function format_post ($author, $header, $date, $date_time, $body, $keywords, $comments, $favorite = 0)
	{
		global $s2_blog_fav_link, $lang_s2_blog;

		$html = '<div class="post author">%1$s</div>'."\n".
			'<h2 class="post head">%7$s%2$s</h2>'."\n".
			'<div class="post time">%4$s</div>'."\n".
			'<div class="post body">%5$s</div>'."\n".
			'<div class="post foot">%6$s</div>'."\n";

		($hook = s2_hook('fn_s2_blog_format_post_start')) ? eval($hook) : null;

		if (!S2_SHOW_COMMENTS)
			$comments = '';

		if ($keywords)
			$comments = sprintf($lang_s2_blog['Tags:'], $keywords).($comments ? ' | '.$comments : '');

		($hook = s2_hook('fn_s2_blog_format_post_end')) ? eval($hook) : null;

		return sprintf($html, $author, $header, $date, $date_time, $body, $comments, ($favorite ? $s2_blog_fav_link : ''));
	}

	public static function get_posts ($query_add, $sort_asc = true, $sort_field = 'create_time')
	{
		global $s2_db;

		// Obtaining posts

		$sub_query = array(
			'SELECT'	=> 'count(*)',
			'FROM'		=> 's2_blog_comments AS c',
			'WHERE'		=> 'c.post_id = p.id AND shown = 1',
		);
		$raw_query_comment = $s2_db->query_build($sub_query, true) or error(__FILE__, __LINE__);

		$sub_query = array(
			'SELECT'	=> 'u.name',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.id = p.user_id',
		);
		$raw_query_user = $s2_db->query_build($sub_query, true) or error(__FILE__, __LINE__);

		$query = array(
			'SELECT'	=> 'p.create_time, p.title, p.text, p.url, p.id, p.commented, p.favorite, ('.$raw_query_comment.') AS comment_num, ('.$raw_query_user.') AS author, p.label',
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
		Lib::posts_links($ids, $merge_labels, $see_also, $tags);

		array_multisort($sort_array, $sort_asc ? SORT_ASC : SORT_DESC, $ids);

		$output = '';
		foreach ($ids as $id)
		{
			$row = $posts[$id];
			$link = S2_BLOG_PATH.date('Y/m/d/', $row['create_time']).urlencode($row['url']);
			$header = '<a href="'.$link.'">'.s2_htmlencode($row['title']).'</a>';
			$date = s2_date($row['create_time']);
			$time = s2_date_time($row['create_time']);
			$post_tags = isset($tags[$id]) ? implode(', ', $tags[$id]) : '';
			$text = $row['text'];
			$author = isset($row['author']) ? s2_htmlencode($row['author']) : '';

			if (!empty($labels[$id]) && isset($see_also[$labels[$id]]))
			{
				$label_copy = $see_also[$labels[$id]];
				if (isset($label_copy[$id]))
					unset($label_copy[$id]);
				$text .= Lib::format_see_also($label_copy);
			}

			$comment = $row['commented'] ? '<a href="'.$link.'#comment">'.Lib::comment_text($row['comment_num']).'</a>' : '';

			($hook = s2_hook('fn_s2_blog_get_posts_loop_pre_post_merge')) ? eval($hook) : null;

			$output .= Lib::format_post($author, $header, $date, $time, $text, $post_tags, $comment, $row['favorite']);
		}

		return $output;
	}
/*
	public static function posts_by_timesdfsadf ($year, $month, $day = false)
	{
		global $s2_db, $lang_common, $lang_s2_blog;

		$link_nav = array();
		$paging = '';

		if ($day === false)
		{
			$start_time = mktime(0, 0, 0, $month, 1, $year);
			$end_time = mktime(0, 0, 0, $month + 1, 1, $year);
			$prev_time = mktime(0, 0, 0, $month - 1, 1, $year);

			$link_nav['up'] = S2_BLOG_PATH.date('Y/', $start_time);

			if ($prev_time >= mktime(0, 0, 0, 1, 1, S2_START_YEAR))
			{
				$link_nav['prev'] = S2_BLOG_PATH.date('Y/m/', $prev_time);
				$paging = '<a href="'.$link_nav['prev'].'">'.$lang_common['Here'].'</a> ';
			}
			if ($end_time < time())
			{
				$link_nav['next'] = S2_BLOG_PATH.date('Y/m/', $end_time);
				$paging .= '<a href="'.$link_nav['next'].'">'.$lang_common['There'].'</a>';
			}

			if ($paging)
				$paging = '<p class="s2_blog_pages">'.$paging.'</p>';
		}
		else
		{
			$start_time = mktime(0, 0, 0, $month, $day, $year);
			$end_time = mktime(0, 0, 0, $month, $day + 1, $year);
			$link_nav['up'] = S2_BLOG_PATH.date('Y/m/', $start_time);
		}

		$query_add = array(
			'WHERE'		=> 'p.create_time < '.$end_time.' AND p.create_time >= '.$start_time
		);
		$output = self::get_posts($query_add);

		if ($output == '')
		{
			s2_404_header();
			$output = '<p>'.$lang_s2_blog['Not found'].'</p>';
		}

		return array('text' => $output.$paging, 'link_navigation' => $link_nav);
	}
*/

	public static function comment_text ($n)
	{
		global $lang_s2_blog;

		return $n ? sprintf($lang_s2_blog['Comments'], $n) : (S2_ENABLED_COMMENTS ? $lang_s2_blog['Post comment'] : '');
	}

	// Returns an array containing info about 10 last posts
	public static function last_posts_array ($num_posts = 10, $skip = 0, $fake_last_post = false)
	{
		global $s2_db;

		if ($fake_last_post)
			$num_posts++;

		// Obtaining last posts
		$sub_query = array(
			'SELECT'	=> 'count(*)',
			'FROM'		=> 's2_blog_comments AS c',
			'WHERE'		=> 'c.post_id = p.id AND shown = 1',
		);
		$raw_query_comment = $s2_db->query_build($sub_query, true) or error(__FILE__, __LINE__);

		$sub_query = array(
			'SELECT'	=> 'u.name',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.id = p.user_id',
		);
		$raw_query_user = $s2_db->query_build($sub_query, true) or error(__FILE__, __LINE__);

		$query = array(
			'SELECT'	=> 'p.create_time, p.title, p.text, p.url, p.id, p.commented, p.modify_time, p.favorite, ('.$raw_query_comment.') AS comm_num, ('.$raw_query_user.') AS author, p.label',
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
		self::posts_links($ids, $merge_labels, $see_also, $tags);

		foreach ($posts as $i => $row)
		{
			if (!empty($labels[$i]) && isset($see_also[$labels[$i]]))
			{
				$label_copy = $see_also[$labels[$i]];
				if (isset($label_copy[$i]))
					unset($label_copy[$i]);
				$posts[$i]['text'] .= Lib::format_see_also($label_copy);
			}
			$posts[$i]['comments'] = $row['commented'] ? '<a href="'.S2_BLOG_PATH.date('Y/m/d/', $row['create_time']).urlencode($row['url']).'#comment">'.self::comment_text($posts[$i]['comm_num']).'</a>' : '';
			$posts[$i]['tags'] = isset($tags[$i]) ? implode(', ', $tags[$i]) : '';
			$posts[$i]['author'] = isset($posts[$i]['author']) ? $posts[$i]['author'] : '';
		}

		return $posts;
	}

	//
	// Fetching posts tags and labels
	// $ids = array (10, 15, 20);
	// $labels = array ('label1' => 1, 'label2' => 1, 'label3' => 1);
	//
	public static function posts_links ($ids, $labels, &$see_also, &$tags)
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
				$see_also[$row['label']][$row['id']] = '<a href="'.S2_BLOG_PATH.date('Y/m/d/', $row['create_time']).urlencode($row['url']).'">'.s2_htmlencode($row['title']).'</a>';
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
			$tags[$row['post_id']][] = '<a href="'.S2_BLOG_TAGS_PATH.urlencode($row['url']).'/">'.$row['name'].'</a>';
	}

	public static function format_see_also ($title_array)
	{
		global $lang_s2_blog;

		$html = '<p class="see_also"><b>%1$s</b><br />%2$s</p>';
		$title_separator = '<br />';

		($hook = s2_hook('fn_s2_blog_format_see_also_start')) ? eval($hook) : null;

		return sprintf($html, $lang_s2_blog['See also'], implode($title_separator, $title_array));
	}

}
