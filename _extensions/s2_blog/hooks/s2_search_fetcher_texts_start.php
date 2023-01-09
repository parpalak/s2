<?php
/**
 * Hook s2_search_fetcher_texts_start
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

$s2_blog_ids = array();
foreach ($ids as $k => $v)
	if (substr($v, 0, 8) == 's2_blog_')
	{
		unset($ids[$k]);
		$s2_blog_ids[] = (int) substr($v, 8);
	}

if (count($s2_blog_ids))
{
	$query = array (
		'SELECT'	=> 'id, text',
		'FROM'		=> 's2_blog_posts',
		'WHERE'		=> 'published = 1 AND id IN ('.implode(', ', $s2_blog_ids).')',
	);
	($hook = s2_hook('s2_blog_pre_get_snippets_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);
	while ($s2_blog_post = $s2_db->fetch_assoc($result))
		$articles['s2_blog_'.$s2_blog_post['id']] = $s2_blog_post['text'];
}
