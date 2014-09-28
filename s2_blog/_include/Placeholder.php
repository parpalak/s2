<?php
/**
 * Content for blog placeholders.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;


class Placeholder
{
	function recent_comments ()
	{
		global $s2_db, $request_uri;

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

		$output = array();
		while ($row = $s2_db->fetch_assoc($result))
		{
			$cur_url = S2_BLOG_PATH . date('Y/m/d/', $row['create_time']) . urlencode($row['url']);
			$output[] = array(
				'title'      => $row['title'],
				'link'       => $cur_url . '#' . $row['count'],
				'author'     => $row['nick'],
				'is_current' => $request_uri == $cur_url,
			);
		}

		return $output;
	}

	function recent_discussions ()
	{
		global $s2_db, $request_uri;

		if (!S2_SHOW_COMMENTS)
			return '';

		$subquery1 = array(
			'SELECT'	=> 'c.post_id AS post_id, count(c.post_id) AS comment_num,  max(c.id) AS max_id',
			'FROM'		=> 's2_blog_comments AS c',
			'WHERE'		=> 'c.shown = 1 AND c.time > '.strtotime('-1 month midnight'),
			'GROUP BY'	=> 'c.post_id',
			'ORDER BY'	=> 'comment_num DESC',
		);
		$raw_query1 = $s2_db->query_build($subquery1, true) or error(__FILE__, __LINE__);

		$query = array(
			'SELECT'	=> 'p.create_time, p.url, p.title, c1.comment_num AS comment_num, c2.nick, c2.time',
			'FROM'		=> 's2_blog_posts AS p, ('.$raw_query1.') AS c1',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 's2_blog_comments AS c2',
					'ON'			=> 'c2.id = c1.max_id'
				),
			),
			'WHERE'		=> 'c1.post_id = p.id AND p.commented = 1 AND p.published = 1',
			'LIMIT'		=> '10',
		);
		($hook = s2_hook('fn_s2_blog_recent_discussions_pre_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$output = array();
		while ($row = $s2_db->fetch_assoc($result))
		{
			$cur_url = S2_BLOG_PATH.date('Y/m/d/', $row['create_time']).urlencode($row['url']);
			$output[] = array(
				'title' => $row['title'],
				'link' => $cur_url,
				'hint' => $row['nick'].' ('.s2_date_time($row['time']).')',
				'is_current' => $request_uri == $cur_url,
			);
		}

		return $output;
	}
}
