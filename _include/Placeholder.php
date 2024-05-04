<?php

use S2\Cms\Model\Model;
use S2\Cms\Pdo\DbLayer;

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
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

		$subquery = array(
			'SELECT'	=> '1',
			'FROM'		=> 'articles AS a2',
			'WHERE'		=> 'a2.parent_id = a.id AND a2.published = 1',
			'LIMIT'		=> '1'
		);
		$raw_query_child_num = $s2_db->build($subquery);

		$subquery = array(
			'SELECT'	=> 'u.name',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.id = a.user_id'
		);
		$raw_query_user = $s2_db->build($subquery);

		$query = array(
			'SELECT'	=> 'a.id, a.title, a.create_time, a.modify_time, a.excerpt, a.favorite, a.url, a.parent_id, a1.title AS parent_title, a1.url AS p_url, ('.$raw_query_user.') AS author',
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
		$result = $s2_db->buildAndQuery($query);

		$last = $urls = $parent_ids = array();
		for ($i = 0; $row = $s2_db->fetchAssoc($result); $i++)
		{
			($hook = s2_hook('fn_last_articles_array_loop')) ? eval($hook) : null;

			$urls[$i] = urlencode($row['url']);
			$parent_ids[$i] = $row['parent_id'];

			$last[$i]['title'] = $row['title'];
			$last[$i]['parent_title'] = $row['parent_title'];
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
	public static function last_articles (\S2\Cms\Template\Viewer $viewer, $limit)
	{
		$return = ($hook = s2_hook('fn_last_articles_start')) ? eval($hook) : null;
		if ($return)
			return $return;

		$articles = self::last_articles_array($limit);

		$output = '';
		foreach ($articles as &$item)
		{
			$item['date'] = s2_date($item['time']);
			$item['link'] = s2_link($item['rel_path']);
			$item['parent_link'] = s2_link(S2_USE_HIERARCHY ? preg_replace('#[^/]*$#', '', $item['rel_path']) : $item['p_url']);

			($hook = s2_hook('fn_last_articles_loop')) ? eval($hook) : null;

			$output .= $viewer->render('last_articles_item', $item);
		}

		($hook = s2_hook('fn_last_articles_end')) ? eval($hook) : null;

		return $output;
	}
}
