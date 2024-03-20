<?php
/**
 * Content for blog placeholders.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;


use S2\Cms\Pdo\DbLayer;

class Placeholder
{
	public static function recent_comments ()
	{
		global $request_uri;

        if (!S2_SHOW_COMMENTS)
            return '';

        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

        $subquery1 = array(
			'SELECT'	=> 'count(*) + 1',
			'FROM'		=> 's2_blog_comments AS c1',
			'WHERE'		=> 'shown = 1 AND c1.post_id = c.post_id AND c1.time < c.time'
		);
		$raw_query1 = $s2_db->build($subquery1);

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
		$result = $s2_db->buildAndQuery($query);

		$output = array();
		while ($row = $s2_db->fetchAssoc($result))
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

	public static function recent_discussions ()
	{
		global $request_uri;

        if (!S2_SHOW_COMMENTS)
            return '';

        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

        $subquery1 = array(
			'SELECT'	=> 'c.post_id AS post_id, count(c.post_id) AS comment_num,  max(c.id) AS max_id',
			'FROM'		=> 's2_blog_comments AS c',
			'WHERE'		=> 'c.shown = 1 AND c.time > '.strtotime('-1 month midnight'),
			'GROUP BY'	=> 'c.post_id',
			'ORDER BY'	=> 'comment_num DESC',
		);
		$raw_query1 = $s2_db->build($subquery1);

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
		$result = $s2_db->buildAndQuery($query);

		$output = array();
		while ($row = $s2_db->fetchAssoc($result))
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

	public static function blog_tags ($id)
	{
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

		$subquery = array(
			'SELECT'	=> 'p.id',
			'FROM'		=> 's2_blog_posts AS p',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 's2_blog_post_tag AS pt',
					'ON'			=> 'p.id = pt.post_id AND p.published = 1'
				)
			),
			'WHERE'		=> 'pt.tag_id = atg.tag_id',
			'LIMIT'		=> '1'
		);
		$raw_query = $s2_db->build($subquery);

		$query = array(
			'SELECT'	=> 't.name, t.url as url',
			'FROM'		=> 'tags AS t',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'article_tag AS atg',
					'ON'			=> 'atg.tag_id = t.tag_id'
				)
			),
			'WHERE'		=> 'atg.article_id = ' . (int) $id . ' AND ('.$raw_query.') IS NOT NULL',
		);

		$result = $s2_db->buildAndQuery($query);

		$s2_blog_links = array();
		while ($row = $s2_db->fetchAssoc($result))
		{
			$s2_blog_links[] = array(
				'title' => $row['name'],
				'link'  => S2_BLOG_TAGS_PATH.urlencode($row['url']).'/',
			);
		}

		return $s2_blog_links;
	}
}
