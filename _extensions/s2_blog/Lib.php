<?php
/**
 * Helper blog functions.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;

use S2\Cms\Pdo\DbLayer;

class Lib
{
	// Returns an array containing info about 10 last posts
	public static function last_posts_array ($num_posts = 10, $skip = 0, $fake_last_post = false)
	{
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

		if ($fake_last_post)
			$num_posts++;

		// Obtaining last posts
		$sub_query = array(
			'SELECT'	=> 'count(*)',
			'FROM'		=> 's2_blog_comments AS c',
			'WHERE'		=> 'c.post_id = p.id AND shown = 1',
		);
		$raw_query_comment = $s2_db->build($sub_query);

		$sub_query = array(
			'SELECT'	=> 'u.name',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.id = p.user_id',
		);
		$raw_query_user = $s2_db->build($sub_query);

		$query = array(
			'SELECT'	=> 'p.create_time, p.title, p.text, p.url, p.id, p.commented, p.modify_time, p.favorite, ('.$raw_query_comment.') AS comment_num, ('.$raw_query_user.') AS author, p.label',
			'FROM'		=> 's2_blog_posts AS p',
			'WHERE'		=> 'published = 1',
			'ORDER BY'	=> 'create_time DESC',
			'LIMIT'		=> ((int) $num_posts).' OFFSET '.((int) $skip)
		);
		($hook = s2_hook('fn_s2_blog_last_posts_array_pre_get_ids_qr')) ? eval($hook) : null;
		$result = $s2_db->buildAndQuery($query);

		$posts = $merge_labels = $labels = $ids = array();
		$i = 0;
		while ($row = $s2_db->fetchAssoc($result))
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
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

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
			$result = $s2_db->buildAndQuery($query);

			$rows = $sort_array = array();
			while ($row = $s2_db->fetchAssoc($result))
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
		$result = $s2_db->buildAndQuery($query);

		$rows = $sort_array = array();
		while ($row = $s2_db->fetchAssoc($result))
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
