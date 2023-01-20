<?php
/**
 * Hook s2_search_find_tags_pre_mrg
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

Lang::load('s2_blog', function ()
{
	if (file_exists(S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php'))
		return require S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php';
	else
		return require S2_ROOT.'/_extensions/s2_blog'.'/lang/English.php';
});

$s2_blog_search_sql = array(
	'SELECT'	=> '1',
	'FROM'		=> 's2_blog_post_tag AS pt',
	'JOINS'		=> array(
		array(
			'INNER JOIN'	=> 's2_blog_posts AS p',
			'ON'			=> 'p.id = pt.post_id'
		)
	),
	'WHERE'		=> 'pt.tag_id = t.tag_id AND p.published = 1',
	'LIMIT'		=> '1'
);
($hook = s2_hook('s2_blog_pre_find_tags_sub_qr')) ? eval($hook) : null;
$s2_blog_search_sub_sql = $s2_db->query_build($s2_blog_search_sql, true);

$s2_blog_search_sql = array(
	'SELECT' => 'tag_id, name, url, ('.$s2_blog_search_sub_sql.') AS used',
	'FROM'	 => 'tags AS t',
	'WHERE'  => implode(' OR ', $where),
);
($hook = s2_hook('s2_blog_pre_find_tags_qr')) ? eval($hook) : null;
$s2_blog_result = $s2_db->query_build($s2_blog_search_sql);

$s2_blog_found_tag = array();
while ($s2_blog_row = $s2_db->fetch_assoc($s2_blog_result))
{
	($hook = s2_hook('s2_blog_find_tags_get_res')) ? eval($hook) : null;

	if ($s2_blog_row['used'] && $this->tagIsSimilarToWords($s2_blog_row['name'], $words)) {
		$s2_blog_found_tag[] = '<a href="'.S2_BLOG_TAGS_PATH.urlencode($s2_blog_row['url']).'/">'.$s2_blog_row['name'].'</a>';
	}
}

if (!empty($s2_blog_found_tag))
{
	$s2_blog_search_found = count($tags);
	if ($s2_blog_search_found)
		$tags[$s2_blog_search_found - 1] .= sprintf(Lang::get('Found tags short', 's2_blog'), implode(', ', $s2_blog_found_tag));
	else
		$return .= '<p class="s2_search_found_tags">'.sprintf(Lang::get('Found tags', 's2_blog'), implode(', ', $s2_blog_found_tag)).'</p>';
}
