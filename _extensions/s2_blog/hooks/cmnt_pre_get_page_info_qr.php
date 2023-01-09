<?php
/**
 * Hook cmnt_pre_get_page_info_qr
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
		'SELECT'	=> 'create_time, url, title, 0 AS parent_id',
		'FROM'		=> 's2_blog_posts',
		'WHERE'		=> 'id = '.$id.' AND published = 1 AND commented = 1'
	);
