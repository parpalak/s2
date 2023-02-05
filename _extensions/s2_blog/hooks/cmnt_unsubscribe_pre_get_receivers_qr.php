<?php
/**
 * Hook cmnt_unsubscribe_pre_get_receivers_qr
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

if ($class == 's2_blog')
	$query = array(
		'SELECT'	=> 'id, nick, email, ip, time',
		'FROM'		=> 's2_blog_comments',
		'WHERE'		=> 'post_id = '.$id.' and subscribed = 1 and email = \''.$s2_db->escape($_GET['mail']).'\''
	);
