<?php
/**
 * Hook cmnt_pre_get_subscribers_qr
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
		'SELECT'	=> 'id, nick, email, ip, time',
		'FROM'		=> 's2_blog_comments',
		'WHERE'		=> 'post_id = '.$id.' AND subscribed = 1 AND shown = 1 AND email <> \''.$s2_db->escape($email).'\''
	);
