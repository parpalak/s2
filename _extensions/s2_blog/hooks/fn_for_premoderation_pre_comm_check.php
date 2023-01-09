<?php
/**
 * Hook fn_for_premoderation_pre_comm_check
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

// Check if there are new comments
$query = array(
	'SELECT'	=> 'count(id)',
	'FROM'		=> 's2_blog_comments',
	'WHERE'		=> 'shown = 0 AND sent = 0'
);
($hook = s2_hook('blfn_for_premoderation_pre_comm_check_qr')) ? eval($hook) : null;
$result = $s2_db->query_build($query);
$new_comment_count += $s2_db->result($result);
