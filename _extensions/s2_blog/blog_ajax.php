<?php
/**
 * Processes ajax queries for blog administrating.
 *
 * @copyright (C) 2007-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */


if (!defined('S2_ROOT'))
	die;

if ($action == 'load_blog_posts')
{
	$is_permission = $s2_user['view'];
	($hook = s2_hook('blrq_action_load_blog_posts_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	Lang::load('s2_blog', function ()
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/English.php';
	});
	require S2_ROOT.'/_extensions/s2_blog'.'/blog_lib.php';

	s2_blog_output_post_list($_POST['posts']);
}

elseif ($action == 'edit_blog_post')
{
	$is_permission = $s2_user['view'];
	($hook = s2_hook('blrq_action_edit_blog_post_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = (int) $_GET['id'];

	Lang::load('s2_blog', function ()
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/English.php';
	});
	require S2_ROOT.'/_extensions/s2_blog'.'/blog_lib.php';

	($hook = s2_hook('blrq_action_edit_blog_post_pre_output')) ? eval($hook) : null;

	header('Content-Type: application/json; charset=utf-8');
	echo s2_json_encode(s2_blog_edit_post_form($id));
}

// Saving data to DB
elseif ($action == 'save_blog')
{
	$is_permission = ($s2_user['create_articles'] || $s2_user['edit_site']);
	($hook = s2_hook('rq_action_save_blog_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_POST['page']) && !isset($_POST['flags']))
		die('Error in POST parameters.');

	Lang::load('s2_blog', function ()
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/English.php';
	});
	require S2_ROOT.'/_extensions/s2_blog'.'/blog_lib.php';

	list($create_time, $result['revision'], $result['status']) = s2_blog_save_post($_POST['page'], $_POST['flags']);
	if ($result['status'] == 'ok')
		$result['url_status'] = s2_blog_check_url_status($create_time, $_POST['page']['url']);

	header('Content-Type: application/json; charset=utf-8');
	echo s2_json_encode($result);
}

elseif ($action == 'create_blog_post')
{
	$is_permission = $s2_user['create_articles'];
	($hook = s2_hook('blrq_action_create_blog_post_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	require S2_ROOT.'/_extensions/s2_blog'.'/blog_lib.php';

	$s2_blog_post_id = s2_blog_create_post();

	echo $s2_blog_post_id;
}

elseif ($action == 'delete_blog_post')
{
	$is_permission = ($s2_user['create_articles'] || $s2_user['edit_site']);
	($hook = s2_hook('blrq_action_delete_blog_post_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');

	require S2_ROOT.'/_extensions/s2_blog'.'/blog_lib.php';

	s2_blog_delete_post((int) $_GET['id']);
}

elseif ($action == 'flip_favorite_post')
{
	$is_permission = $s2_user['edit_site'];
	($hook = s2_hook('blrq_action_edit_blog_post_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');

	require S2_ROOT.'/_extensions/s2_blog'.'/blog_lib.php';

	s2_blog_flip_favorite((int)$_GET['id']);
}

//=======================[Blog comments]========================================

elseif ($action == 'load_blog_comments')
{
	$is_permission = $s2_user['view'];
	($hook = s2_hook('blrq_action_edit_blog_post_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = (int)$_GET['id'];

	require 'comments.php';
	Lang::load('s2_blog', function ()
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/English.php';
	});

	echo s2_comment_menu_links();
	echo s2_show_comments('s2_blog', $id);
}

elseif ($action == 'delete_blog_comment')
{
	$is_permission = $s2_user['edit_comments'];
	($hook = s2_hook('blrq_action_delete_blog_comment_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']) || !isset($_GET['mode']))
		die('Error in GET parameters.');

	Lang::load('s2_blog', function ()
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/English.php';
	});
	require S2_ROOT.'/_extensions/s2_blog'.'/blog_lib.php';
	require 'comments.php';

	$post_id = s2_blog_delete_comment((int) $_GET['id']);

	echo s2_comment_menu_links($_GET['mode']);
	echo s2_show_comments($_GET['mode'], $post_id);
}

elseif ($action == 'edit_blog_comment')
{
	$is_permission = $s2_user['edit_comments'];
	($hook = s2_hook('blrq_action_edit_blog_comment_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']) || !isset($_GET['mode']))
		die('Error in GET parameters.');

	require S2_ROOT.'/_extensions/s2_blog'.'/blog_lib.php';

	$comment = s2_blog_get_comment((int) $_GET['id']);
	if (!$comment)
		die('Comment not found!');

	require 'comments.php';

	s2_output_comment_form($comment, $_GET['mode'], 'blog');
}

elseif ($action == 'hide_blog_comment')
{
	$is_permission = $s2_user['hide_comments'];
	($hook = s2_hook('blrq_action_hide_blog_comment_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']) || !isset($_GET['mode']))
		die('Error in GET parameters.');

	Lang::load('s2_blog', function ()
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/English.php';
	});
	require S2_ROOT.'/_extensions/s2_blog'.'/blog_lib.php';
	require 'comments.php';

	$post_id = s2_blog_hide_comment((int)$_GET['id'], (bool)($_GET['leave_hidden'] ?? false));

	echo s2_comment_menu_links($_GET['mode']);
	echo s2_show_comments($_GET['mode'], $post_id);
}

elseif ($action == 'mark_blog_comment')
{
	$is_permission = $s2_user['edit_comments'];
	($hook = s2_hook('blrq_action_mark_blog_comment_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']) || !isset($_GET['mode']))
		die('Error in GET parameters.');

	require S2_ROOT.'/_extensions/s2_blog'.'/blog_lib.php';

	$post_id = s2_blog_mark_comment((int)$_GET['id']);

	Lang::load('s2_blog', function ()
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_blog'.'/lang/English.php';
	});
	require 'comments.php';

	echo s2_comment_menu_links($_GET['mode']);
	echo s2_show_comments($_GET['mode'], $post_id);
}
