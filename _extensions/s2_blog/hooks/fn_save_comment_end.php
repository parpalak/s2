<?php
/**
 * Hook fn_save_comment_end
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

if ($type == 'blog')
{
	// Does the comment exist?
	// We need post id for displaying comments
	$query = array(
		'SELECT'	=> 'post_id',
		'FROM'		=> 's2_blog_comments',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('blfn_save_comment_pre_get_pid_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query);

	if ($row = $s2_db->fetch_row($result))
		$post_id = $row[0];
	else
		die('Comment not found!');

	// Save comment
	$query = array(
		'UPDATE'	=> 's2_blog_comments',
		'SET'		=> "nick = '$nick', email = '$email', text = '$text', show_email = '$show_email', subscribed = '$subscribed'",
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('blfn_save_comment_pre_upd_qr')) ? eval($hook) : null;
	$s2_db->query_build($query);

	Lang::load('s2_blog', function () use ($ext_info)
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/English.php';
	});

	$article_id = $post_id;
}
