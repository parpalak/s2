<?php
/**
 * Hook s2_search_fetcher_chapter_start
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 *
 * @var \s2_extensions\s2_search\Fetcher $this
 */

 if (!defined('S2_ROOT')) {
     die;
}

if (substr($id, 0, 8) == 's2_blog_')
{
	$query = array (
		'SELECT'	=> 'id, title, text, create_time, url',
		'FROM'		=> 's2_blog_posts',
		'WHERE'		=> 'published = 1 AND id = '.intval(substr($id, 8)),
	);
	($hook = s2_hook('s2_blog_pre_get_chapter_qr')) ? eval($hook) : null;
	$result = $this->db->buildAndQuery($query);
	$s2_blog_post = $this->db->fetchAssoc($result);
	if (!$s2_blog_post) {
		return null;
	}

	$indexable = new \S2\Rose\Entity\Indexable('s2_blog_'.$s2_blog_post['id'], $s2_blog_post['title'], $s2_blog_post['text']);
	$indexable
		->setDate($s2_blog_post['create_time'] > 0 ? (new \DateTime('@' . $s2_blog_post['create_time']))->setTimezone((new \DateTime())->getTimezone()) : null)
		->setUrl(str_replace(urlencode('/'), '/', urlencode(S2_BLOG_URL)).date('/Y/m/d', $s2_blog_post['create_time']).'/'.$s2_blog_post['url'])
	;

	return $indexable;
}
