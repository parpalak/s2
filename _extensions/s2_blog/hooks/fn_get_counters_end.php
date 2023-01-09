<?php
/**
 * Hook fn_get_counters_end
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

$query = array(
	'SELECT'	=> 'count(*)',
	'FROM'		=> 's2_blog_posts',
	'WHERE'		=> 'published = 1'
);
($hook = s2_hook('blfn_get_counters_pre_get_posts_qr')) ? eval($hook) : null;
$result = $s2_db->query_build($query);

$counters[] = sprintf(Lang::get('Blog posts now', 's2_blog'), $s2_db->result($result));

$query = array(
	'SELECT'	=> 'count(*)',
	'FROM'		=> 's2_blog_comments AS c',
	'JOINS'		=> array(
		array(
			'INNER JOIN'	=> 's2_blog_posts AS p',
			'ON'			=> 'p.id = c.post_id'
		)
	),
	'WHERE'		=> 'c.shown = 1 AND p.published = 1'
);
($hook = s2_hook('blfn_get_counters_pre_get_comm_qr')) ? eval($hook) : null;
$result = $s2_db->query_build($query);

$counters[] = sprintf(Lang::get('Blog comments now', 's2_blog'), $s2_db->result($result));
