<?php
/**
 * Hook fn_preload_editor_start
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 *
 * @var DBLayer_Abstract $s2_db
 */

 if (!defined('S2_ROOT')) {
     die;
}

if (!empty($_GET['path']) && ($_GET['path'] == S2_BLOG_URL.'/' || $_GET['path'] == S2_BLOG_URL))
{
	echo 'document.location.hash = "#blog";';
	return true;
}
elseif (!empty($_GET['path']) && substr($_GET['path'], 0, strlen(S2_BLOG_URL)) == S2_BLOG_URL)
{
	$path = substr($_GET['path'], strlen(S2_BLOG_URL));
	$path = explode('/', $path);   //   []/[2006]/[12]/[31]/[newyear]
	if (count($path) < 5)
		return true;

	$start_time = mktime(0, 0, 0, $path[2], $path[3], $path[1]);
	$end_time = mktime(0, 0, 0, $path[2], $path[3]+1, $path[1]);

	$query = array (
		'SELECT'	=> 'id',
		'FROM'		=> 's2_blog_posts',
		'WHERE'		=> 'create_time < '.$end_time.' AND create_time >= '.$start_time.' AND url=\''.$s2_db->escape($path[4]).'\''
	);
	($hook = s2_hook('blfn_preload_editor_loop_pre_get_post_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	if ($row = $s2_db->fetch_assoc($result))
		echo 'document.location.hash = "#edit";'."\n".'setTimeout(function () { EditRecord('.$row['id'].'); }, 0);'."\n";

	($hook = s2_hook('blfn_preload_editor_end')) ? eval($hook) : null;

	return true;
}
