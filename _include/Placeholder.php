<?php
/**
 * Builds placeholders content for templates.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


class Placeholder
{
	//
	// Fetching last articles info (for template placeholders and RSS)
	//
	public static function last_articles_array ($limit = '5')
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

		$urls = Model::get_group_url($parent_ids, $urls);

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
	public static function last_articles ($num)
	{
		$return = ($hook = s2_hook('fn_last_articles_start')) ? eval($hook) : null;
		if ($return)
			return $return;

		$articles = self::last_articles_array($num);

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

	// Makes tags list for the tags page and the placeholder
	public static function tags_list ()
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
	// Fetching last comments (for template placeholders)
	//
	public static function last_article_comments ()
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

		$urls = Model::get_group_url($parent_ids, $urls);

		$output = '';
		foreach ($urls as $k => $url)
			$output .= '<li><a href="'.s2_link($url).'#'.$counts[$k].'">'.s2_htmlencode($titles[$k]).'</a>, <em>'.s2_htmlencode($nicks[$k]).'</em></li>';

		($hook = s2_hook('fn_last_article_comments_end')) ? eval($hook) : null;
		return $output ? '<ul>'.$output.'</ul>' : '';
	}

	public static function last_discussions ()
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

		$urls = Model::get_group_url($parent_ids, $urls);

		$output = '';
		foreach ($urls as $k => $url)
			$output .= '<li><a href="'.s2_link($url).'" title="'.s2_htmlencode($nicks[$k].' ('.s2_date_time($time[$k]).')').'">'.s2_htmlencode($titles[$k]).'</a></li>';

		($hook = s2_hook('fn_last_discussions_end')) ? eval($hook) : null;
		return $output ? '<ul>'.$output.'</ul>' : '';
	}
}