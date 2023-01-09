<?php
/**
 * Hook cmnt_pre_save_comment_qr
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

if ($class == 's2_blog')
{
	$query['INSERT'] = 'post_id, time, ip, nick, email, show_email, subscribed, sent, shown, good, text';
	$query['INTO'] = 's2_blog_comments';
}
