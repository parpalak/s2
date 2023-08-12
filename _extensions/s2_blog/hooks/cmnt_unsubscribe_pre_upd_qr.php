<?php
/**
 * Hook cmnt_unsubscribe_pre_upd_qr
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 *
 * @var \S2\Cms\Pdo\DbLayer $s2_db
 */

 if (!defined('S2_ROOT')) {
     die;
}

if ($class == 's2_blog')
	$query = array(
		'UPDATE'	=> 's2_blog_comments',
		'SET'		=> 'subscribed = 0',
		'WHERE'		=> 'post_id = '.$id.' and subscribed = 1 and email = \''.$s2_db->escape($_GET['mail']).'\''
	);
