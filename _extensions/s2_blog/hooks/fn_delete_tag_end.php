<?php
/**
 * Hook fn_delete_tag_end
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

$query = array(
	'DELETE'	=> 's2_blog_post_tag',
	'WHERE'		=> 'tag_id = '.$id,
);
($hook = s2_hook('blrq_action_delete_tag_pre_del_links_qr')) ? eval($hook) : null;
$s2_db->query_build($query);
