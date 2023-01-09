<?php
/**
 * Hook cmnt_pre_get_comment_count_qr
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

if ($class == 's2_blog')
	$query = array(
		'SELECT'	=> 'count(id)',
		'FROM'		=> 's2_blog_comments',
		'WHERE'		=> 'post_id = '.$id.' AND shown = 1'
	);
