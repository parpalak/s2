<?php
/**
 * Helper blog functions.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;
use \Lang;


class Lib
{
	// '1' -> '01'
	public static function extend_number ($n)
	{
		return strlen($n) == 1 ? '0'.$n : $n;
	}

	public static function calendar ($year, $month, $day, $url = '', $day_flags = false)
	{
		global $s2_db;

		if ($month === '')
			$month = 1;

		$start_time = mktime(0, 0, 0, $month, 1, $year);
		$end_time = mktime(0, 0, 0, $month + 1, 1, $year);

		// Dealing with week days
		$n = date('w', $start_time);
		if (Lang::get('Sunday starts week', 's2_blog') != '1')
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
			$result = $s2_db->query_build($query);
			while ($row = $s2_db->fetch_row($result))
				$day_flags[1 + intval(($row[0] - $start_time) / 86400)] = 1;
		}

		// Header
		$month_name = \Lang::month($month);
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
			$next_month = $end_time < time() ? '<a class="nav_mon" href="'.S2_BLOG_PATH.date('Y/m', $end_time).'/" title="'.\Lang::month(date('m', $end_time)).date(', Y', $end_time).'">&rarr;</a>' : '&rarr;';

			$prev_time = mktime(0, 0, 0, $month - 1, 1, $year);
			$prev_month = $prev_time >= mktime(0, 0, 0, 1, 1, S2_START_YEAR) ? '<a class="nav_mon" href="'.S2_BLOG_PATH.date('Y/m', $prev_time).'/" title="'.\Lang::month(date('m', $prev_time)).date(', Y', $prev_time).'">&larr;</a>' : '&larr;';

			$header = '<tr class="nav"><th>'.$prev_month.'</th><th align="center" colspan="5">'
				.$month_name.', <a href="'.S2_BLOG_PATH.$year.'/">'.$year.'</a></th><th>'.$next_month.'</th></tr>';
		}

		// Titles
		$output = '<table class="cal">'.$header.'<tr>';
		for ($i = 0; $i < 7; $i++)
			$output .= '<th'.($i % 7 == 5 || $i % 7 == 6 ? ' class="sun"' : '').'>'.Lang::get('Days', 's2_blog').'</th>';
		$output .= '</tr><tr>';

		// Empty cells before
		for ($i = 0; $i < $n; $i++)
			$output .= '<td'.($i % 7 == 5 || $i % 7 == 6 && Lang::get('Sunday starts week', 's2_blog') != '1' || $i % 7 == 0 && Lang::get('Sunday starts week', 's2_blog') == '1' ? ' class="sun"' : '').'></td>';

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
			if ($n % 7 == 0 || ($n % 7 == 6) && Lang::get('Sunday starts week', 's2_blog') != '1' || ($n % 7 == 1) && Lang::get('Sunday starts week', 's2_blog') == '1')
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
			$output .= '<td'.($n % 7 == 0 || $n % 7 == 6 && Lang::get('Sunday starts week', 's2_blog') != '1' || $n % 7 == 1 && Lang::get('Sunday starts week', 's2_blog') == '1' ? ' class="sun"' : '').'></td>';
		}

		$output .= '</tr></table>';
		return $output;
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
		$raw_query_comment = $s2_db->query_build($sub_query, true);

		$sub_query = array(
			'SELECT'	=> 'u.name',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.id = p.user_id',
		);
		$raw_query_user = $s2_db->query_build($sub_query, true);

		$query = array(
			'SELECT'	=> 'p.create_time, p.title, p.text, p.url, p.id, p.commented, p.modify_time, p.favorite, ('.$raw_query_comment.') AS comment_num, ('.$raw_query_user.') AS author, p.label',
			'FROM'		=> 's2_blog_posts AS p',
			'WHERE'		=> 'published = 1',
			'ORDER BY'	=> 'create_time DESC',
			'LIMIT'		=> ((int) $num_posts).' OFFSET '.((int) $skip)
		);
		($hook = s2_hook('fn_s2_blog_last_posts_array_pre_get_ids_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query);

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

		foreach ($posts as $i => &$post)
		{
			$posts[$i]['see_also'] = array();
			if (!empty($labels[$i]) && isset($see_also[$labels[$i]]))
			{
				$label_copy = $see_also[$labels[$i]];
				if (isset($label_copy[$i]))
					unset($label_copy[$i]);
				$posts[$i]['see_also'] = $label_copy;
			}

			$post['tags'] = isset($tags[$i]) ? $tags[$i] : array();
			if (!isset($post['author']))
				$post['author'] = '';

			$link = S2_BLOG_PATH . date('Y/m/d/', $post['create_time']) . urlencode($post['url']);
			$post['title_link'] = $link;
			$post['link'] = $link;
			$post['time'] = s2_date_time($post['create_time']);
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
			$result = $s2_db->query_build($query);

			$rows = $sort_array = array();
			while ($row = $s2_db->fetch_assoc($result))
			{
				$rows[] = $row;
				$sort_array[] = $row['create_time'];
			}

			array_multisort($sort_array, SORT_DESC, $rows);

			foreach ($rows as $row)
				$see_also[$row['label']][$row['id']] = array(
					'title' => $row['title'],
					'link'  => S2_BLOG_PATH.date('Y/m/d/', $row['create_time']).urlencode($row['url']),
				);
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
		$result = $s2_db->query_build($query);

		$rows = $sort_array = array();
		while ($row = $s2_db->fetch_assoc($result))
		{
			$rows[] = $row;
			$sort_array[] = $row['pt_id'];
		}

		array_multisort($sort_array, $rows);

		$tags = array();
		foreach ($rows as $row)
			$tags[$row['post_id']][] = array(
				'title' => $row['name'],
				'link'  => S2_BLOG_TAGS_PATH.urlencode($row['url']).'/',
			);
	}
}
