<?php
/**
 * Hook s2_search_fetcher_process_end
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

global $s2_db;
$query = array (
	'SELECT'	=> 'id, title, text, create_time, url',
	'FROM'		=> 's2_blog_posts',
	'WHERE'		=> 'published = 1'
);
($hook = s2_hook('s2_blog_pre_index_fetch')) ? eval($hook) : null;
$result = $s2_db->query_build($query);

return function () use ($result, $s2_db) {
	while ($s2_blog_post = $s2_db->fetch_assoc($result))
	{
		$indexable = new \S2\Rose\Entity\Indexable('s2_blog_'.$s2_blog_post['id'], $s2_blog_post['title'], $s2_blog_post['text']);
		$indexable
			->setDate($s2_blog_post['create_time'] > 0 ? new \DateTime('@' . $s2_blog_post['create_time']) : null)
			->setUrl(str_replace(urlencode('/'), '/', urlencode(S2_BLOG_URL)).date('/Y/m/d', $s2_blog_post['create_time']).'/'.$s2_blog_post['url'])
		;

		yield $indexable;
	}
};