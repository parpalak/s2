<?php /** @noinspection PhpExpressionResultUnusedInspection */
/**
 * Ajax requests
 *
 * Processes ajax queries for the admin panel
 *
 * @copyright (C) 2007-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

use S2\Cms\Model\Model;
use S2\Cms\Model\ExtensionCache;
use S2\Cms\Pdo\DbLayer;

define('S2_ROOT', '../');
require S2_ROOT.'_include/common.php';

// IIS sets HTTPS to 'off' for non-SSL requests
if (defined('S2_FORCE_ADMIN_HTTPS') && ($_SERVER['HTTPS'] ?? 'off') === 'off') {
	header('Location: https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
	die();
}

require S2_ROOT.'_admin/lang/'.Lang::admin_code().'/admin.php';
require 'login.php';
require 'site_lib.php';

($hook = s2_hook('rq_start')) ? eval($hook) : null;

s2_no_cache();

header('X-Powered-By: S2/'.S2_VERSION);
header('Content-Type: text/html; charset=utf-8');

ob_start();
if (S2_COMPRESS)
	ob_start('ob_gzhandler');

/**
 * @var $s2_cookie_name
 * @var $lang_admin
 */
$session_id = isset($_COOKIE[$s2_cookie_name]) ? $_COOKIE[$s2_cookie_name] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

//=======================[Authorization]========================================

$s2_user = array();

if ($action == 'login')
{
	if ($session_id == '')
	{
		// We have POST data. Process it! Handle errors if they are.

		$login = isset($_POST['login']) ? (string) $_POST['login'] : '';
		$challenge = isset($_POST['challenge']) ? (string) $_POST['challenge'] : '';
		$key = isset($_POST['key']) ? (string) $_POST['key'] : '';

		echo s2_ajax_login($login, $challenge, $key);
	}
	else
		echo 'OK';
}

elseif ($action == 'logout')
{
	s2_logout($session_id);
}

else
{
	// Check the current user and fetch the user info
	$s2_user = s2_authenticate_user($session_id);
}

if ($action == 'close_other_sessions')
{
	$is_permission = true;
	($hook = s2_hook('rq_action_close_other_sessions_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	s2_close_other_sessions($session_id);
}

//=======================[Managing items]=======================================

// Drag & Drop
elseif ($action == 'move')
{
	$is_permission = ($s2_user['create_articles'] || $s2_user['edit_site']);
	($hook = s2_hook('rq_action_drag_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['source_id']) || !isset($_GET['new_parent_id']) || !isset($_GET['new_pos']))
		die('Error in GET parameters.');

	$source_id = (int) $_GET['source_id'];
	$new_parent_id = (int) $_GET['new_parent_id'];
	$new_pos = (int) $_GET['new_pos'];

	require 'tree.php';
	s2_move_branch($source_id, $new_parent_id, $new_pos);

	header('Content-Type: application/json; charset=utf-8');
	echo s2_json_encode(array('status' => 1));
}

elseif ($action == 'delete')
{
	$is_permission = ($s2_user['create_articles'] || $s2_user['edit_site']);
	($hook = s2_hook('rq_action_delete_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = (int) $_GET['id'];

	require 'tree.php';
	s2_delete_branch($id);

	header('Content-Type: application/json; charset=utf-8');
	echo s2_json_encode(array('status' => 1));
}

elseif ($action == 'rename')
{
	$is_permission = ($s2_user['create_articles'] || $s2_user['edit_site']);
	($hook = s2_hook('rq_action_rename_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']) || !isset($_POST['title']))
		die('Error in GET and POST parameters.');
	$id = (int) $_GET['id'];
	$title = $_POST['title'];

	require 'tree.php';
	s2_rename_article($id, $title);

	header('Content-Type: application/json; charset=utf-8');
	echo s2_json_encode(array('status' => 1));
}

elseif ($action == 'create')
{
	$is_permission = $s2_user['create_articles'];
	($hook = s2_hook('rq_action_create_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = (int) $_GET['id'];
	$title = $_GET['title'];

	require 'tree.php';
	$new_id = s2_create_article($id, $title);

	header('Content-Type: application/json; charset=utf-8');
	echo s2_json_encode(array('status' => 1, 'id' => $new_id));
}

// Load folder tree
elseif ($action == 'load_tree')
{
	$is_permission = $s2_user['view'];
	($hook = s2_hook('rq_action_load_tree_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');

	require 'tree.php';

	header('Content-Type: application/json; charset=utf-8');
	echo s2_json_encode(s2_get_child_branches((int)$_GET['id'], true, trim($_GET['search'])));
}

//=======================[Pages editor]=========================================

// Loading data from DB into the editor form
elseif ($action == 'load')
{
	$is_permission = $s2_user['view'];
	($hook = s2_hook('rq_action_load_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = (int) $_GET['id'];

	($hook = s2_hook('rq_action_load_pre_output')) ? eval($hook) : null;

	require 'edit.php';

	header('Content-Type: application/json; charset=utf-8');
	echo s2_json_encode(s2_output_article_form($id));
}

// Saving data to DB
elseif ($action == 'save')
{
	$is_permission = ($s2_user['create_articles'] || $s2_user['edit_site']);
	($hook = s2_hook('rq_action_save_article_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_POST['page']))
		die('Error in POST parameters.');

	if (!isset($_POST['page']['url']))
		$_POST['page']['url'] = '';

	require 'edit.php';

	list($parent_id, $return['revision'], $return['status']) = s2_save_article($_POST['page'], isset($_POST['flags']) ? $_POST['flags'] : array());
	if ($return['status'] == 'ok')
		$return['url_status'] = s2_check_url_status($parent_id, $_POST['page']['url']);

	($hook = s2_hook('rq_action_save_article_end')) ? eval($hook) : null;

	header('Content-Type: application/json; charset=utf-8');
	echo s2_json_encode($return);
}

elseif ($action == 'preview')
{
	$is_permission = $s2_user['view'];
	($hook = s2_hook('rq_action_preview_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');

	$path = Model::path_from_id((int) $_GET['id'], true);
	if ($path === false)
		echo $lang_admin['Preview not found'];
	else
		header('Location: '.s2_link($path ?: '/'));
}

//=======================[Comment management]===================================

elseif ($action == 'load_comments')
{
	$is_permission = $s2_user['view'];
	($hook = s2_hook('rq_action_load_comments_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');

	require 'comments.php';

	echo s2_comment_menu_links();
	echo s2_show_comments('all', (int) $_GET['id']);
}

elseif ($action == 'load_hidden_comments')
{
	$is_permission = $s2_user['view_hidden'];
	($hook = s2_hook('rq_action_load_hidden_comments_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	require 'comments.php';

	echo s2_comment_menu_links('hidden');
	echo s2_show_comments('hidden');

	($hook = s2_hook('rq_action_load_hidden_comments_end')) ? eval($hook) : null;
}

elseif ($action == 'load_last_comments')
{
	$is_permission = $s2_user['view'];
	($hook = s2_hook('rq_action_load_last_comments_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	require 'comments.php';

	echo s2_comment_menu_links('last');
	echo s2_show_comments('last');

	($hook = s2_hook('rq_action_load_last_comments_end')) ? eval($hook) : null;
}

elseif ($action == 'load_new_comments')
{
	$is_permission = $s2_user['view_hidden'];
	($hook = s2_hook('rq_action_load_new_comments_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	require 'comments.php';

	echo s2_comment_menu_links('new');
	echo s2_show_comments('new');

	($hook = s2_hook('rq_action_load_new_comments_end')) ? eval($hook) : null;
}

elseif ($action == 'delete_comment')
{
	$is_permission = $s2_user['edit_comments'];
	($hook = s2_hook('rq_action_delete_comment_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']) || !isset($_GET['mode']))
		die('Error in GET parameters.');

	require 'comments.php';

	$article_id = s2_delete_comment((int) $_GET['id']);

	echo s2_comment_menu_links($_GET['mode']);
	echo s2_show_comments($_GET['mode'], $article_id);
}

elseif ($action == 'edit_comment')
{
	$is_permission = $s2_user['edit_comments'];
	($hook = s2_hook('rq_action_edit_comment_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']) || !isset($_GET['mode']))
		die('Error in GET parameters.');

	require 'comments.php';

	$comment = s2_get_comment((int) $_GET['id']);
	if (!$comment)
		die('Comment not found!');

	s2_output_comment_form($comment, $_GET['mode'], 'site');
}

elseif ($action == 'save_comment')
{
	$is_permission = $s2_user['edit_comments'];
	($hook = s2_hook('rq_action_save_comment_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_POST['comment']['nick']) || !isset($_POST['comment']['email']) || !isset($_POST['comment']['text']) || !isset($_POST['comment']['id']) || !isset($_POST['mode']))
		die('Error in POST data.');

	require 'comments.php';

	$article_id = s2_save_comment($_POST['comment']);

	echo s2_comment_menu_links($_POST['mode']);
	echo s2_show_comments($_POST['mode'], $article_id);
}

elseif ($action == 'hide_comment')
{
	$is_permission = $s2_user['hide_comments'];
	($hook = s2_hook('rq_action_hide_comment_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']) || !isset($_GET['mode']))
		die('Error in GET parameters.');

	require 'comments.php';

	$article_id = s2_toggle_hide_comment((int)$_GET['id'], (bool)($_GET['leave_hidden'] ?? false));

	echo s2_comment_menu_links($_GET['mode']);
	echo s2_show_comments($_GET['mode'], $article_id);
}

elseif ($action == 'mark_comment')
{
	$is_permission = $s2_user['edit_comments'];
	($hook = s2_hook('rq_action_mark_comment_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']) || !isset($_GET['mode']))
		die('Error in GET parameters.');

	require 'comments.php';

	$article_id = s2_toggle_mark_comment((int) $_GET['id']);

	echo s2_comment_menu_links($_GET['mode']);
	echo s2_show_comments($_GET['mode'], $article_id);
}

//=======================[User management]======================================

elseif ($action == 'load_userlist')
{
	$is_permission = ($s2_user['view_hidden'] || $s2_user['edit_users']);
	($hook = s2_hook('rq_action_load_userlist_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	echo s2_get_user_list();
}

elseif ($action == 'add_user')
{
	$is_permission = $s2_user['edit_users'];
	($hook = s2_hook('rq_action_add_user_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['name']))
		die('Error in GET parameters.');

    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

    $login = $s2_db->escape($_GET['name']);

	// Verify if login entered already exists
	$query = array(
		'SELECT'	=> 'login',
		'FROM'		=> 'users',
		'WHERE'		=> 'login = \''.$login.'\''
	);

	($hook = s2_hook('rq_action_add_user_pre_login_verify_qr')) ? eval($hook) : null;
	$result = $s2_db->buildAndQuery($query);
	if ($s2_db->fetchRow($result))
	{
		// Exists
		header('X-S2-Status: Error');
		die(sprintf($lang_admin['Username exists'], $_GET['name']));
	}
	else
	{
		// New login, Ok
		$query = array(
			'INSERT'	=> 'login, password',
			'INTO'		=> 'users',
			'VALUES'	=> '\''.$login.'\', \''.md5('Life is not so easy :-)' . time() . mt_rand()).'\''
		);
		($hook = s2_hook('rq_action_add_user_pre_ins_qr')) ? eval($hook) : null;
		$s2_db->buildAndQuery($query);
	}

	echo s2_get_user_list();
}

elseif ($action == 'delete_user')
{
	$is_permission = $s2_user['edit_users'];
	($hook = s2_hook('rq_action_delete_user_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['name']))
		die('Error in GET parameters.');

    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

	$login = $_GET['name'];

	$query = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 'users',
		'WHERE'		=> 'edit_users = 1 AND NOT login = \''.$s2_db->escape($login).'\'',
	);
	($hook = s2_hook('rq_action_delete_user_pre_get_qr')) ? eval($hook) : null;
	$result = $s2_db->buildAndQuery($query);
	$allow = $s2_db->result($result) > 0;

	if ($allow)
	{
		$query = array(
			'DELETE'	=> 'users',
			'WHERE'		=> 'login = \''.$s2_db->escape($login).'\''
		);
		($hook = s2_hook('rq_action_delete_user_pre_del_qr')) ? eval($hook) : null;
		$s2_db->buildAndQuery($query);
	}
	else
		echo '<div class="info-box"><p>'.$lang_admin['No other admin delete'].'</p></div>';

	echo s2_get_user_list();
}

elseif ($action == 'user_set_password')
{
	$is_permission = $s2_user['view_hidden'];
	($hook = s2_hook('rq_action_user_set_password_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['name']) || !isset($_POST['pass']))
		die('Error in GET and POST parameters.');

	// We allow usual users to change only their passwords
	if ($s2_user['login'] == $_GET['name'] || $s2_user['edit_users'])
	{
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

        $query = array(
			'SELECT'	=> 'password',
			'FROM'		=> 'users',
			'WHERE'		=> 'login = \''.$s2_db->escape($_GET['name']).'\'',
		);
		($hook = s2_hook('rq_action_user_set_password_pre_qr')) ? eval($hook) : null;
		$result = $s2_db->buildAndQuery($query);

		if ($s2_db->result($result) != $_POST['pass'])
		{
			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'password = \''.$s2_db->escape($_POST['pass']).'\'',
				'WHERE'		=> 'login = \''.$s2_db->escape($_GET['name']).'\'',
			);
			($hook = s2_hook('rq_action_user_set_password_pre_upd_qr')) ? eval($hook) : null;
            $result = $s2_db->buildAndQuery($query);
            if ($s2_db->affectedRows($result) <= 0)
				echo 'Error while changing password';
			else
				echo $lang_admin['Password changed'];
		}
		else
			echo $lang_admin['Password unchanged'];
	}
	else
	{
		header('X-S2-Status: Forbidden');
		die($lang_admin['No permission']);
	}
}

elseif ($action == 'user_set_email')
{
	$is_permission = $s2_user['view_hidden'];
	($hook = s2_hook('rq_action_user_set_email_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['login']) || !isset($_GET['email']))
		die('Error in GET parameters.');

	// We allow usual users to change only their emails
	if ($s2_user['login'] != $_GET['login'] && !$s2_user['edit_users'])
	{
		header('X-S2-Status: Forbidden');
		die($lang_admin['No permission']);
	}

    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

    $query = array(
		'UPDATE'	=> 'users',
		'SET'		=> 'email = \''.$s2_db->escape($_GET['email']).'\'',
		'WHERE'		=> 'login = \''.$s2_db->escape($_GET['login']).'\'',
	);
	($hook = s2_hook('rq_action_user_set_email_pre_upd_qr')) ? eval($hook) : null;
	$s2_db->buildAndQuery($query);

	echo s2_get_user_list();
}

elseif ($action == 'user_set_name')
{
	$is_permission = $s2_user['view_hidden'];
	($hook = s2_hook('rq_action_user_set_name_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['login']) || !isset($_GET['name']))
		die('Error in GET parameters.');

	// We allow usual users to change only their names
	if ($s2_user['login'] != $_GET['login'] && !$s2_user['edit_users'])
	{
		header('X-S2-Status: Forbidden');
		die($lang_admin['No permission']);
	}

    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

    $query = array(
		'UPDATE'	=> 'users',
		'SET'		=> 'name = \''.$s2_db->escape($_GET['name']).'\'',
		'WHERE'		=> 'login = \''.$s2_db->escape($_GET['login']).'\'',
	);
	($hook = s2_hook('rq_action_user_set_name_pre_upd_qr')) ? eval($hook) : null;
	$s2_db->buildAndQuery($query);

	echo s2_get_user_list();
}

elseif ($action == 'user_set_permission')
{
	$is_permission = $s2_user['edit_users'];
	($hook = s2_hook('rq_action_user_set_permission_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['name']) || !isset($_GET['permission']))
		die('Error in GET parameters.');

	$login = $_GET['name'];
	$permission = $_GET['permission'];

    /** @var DbLayer $s2_db */
    $s2_db = \Container::get(DbLayer::class);

    $allow = true;

	if ($permission == 'edit_users')
	{
		$query = array(
			'SELECT'	=> 'count(*)',
			'FROM'		=> 'users',
			'WHERE'		=> 'edit_users = 1 AND NOT login = \''.$s2_db->escape($login).'\'',
		);
		($hook = s2_hook('rq_action_user_set_permission_pre_get_qr')) ? eval($hook) : null;
		$result = $s2_db->buildAndQuery($query);
		$allow = $s2_db->result($result) > 0;
	}

	if ($allow)
	{
		$query = array(
			'UPDATE'	=> 'users',
			'SET'		=> $s2_db->escape($permission).' = 1 - '.$s2_db->escape($permission),
			'WHERE'		=> 'login = \''.$s2_db->escape($login).'\'',
		);
		($hook = s2_hook('rq_action_user_set_permission_pre_upd_qr')) ? eval($hook) : null;
		$s2_db->buildAndQuery($query);
	}
	else
		echo '<div class="info-box"><p>'.$lang_admin['No other admin'].'</p></div>';

	echo s2_get_user_list();
}

//=======================[Tags tab]=============================================

elseif ($action == 'load_tags')
{
	$is_permission = $s2_user['view'];
	($hook = s2_hook('rq_action_load_tags_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	require 'tags.php';

	$tag = array('id' => 0, 'name' => '', 'description' => '', 'url' => '');
	s2_output_tag_form($tag, s2_html_time_from_timestamp(time()));
}

elseif ($action == 'load_tag')
{
	$is_permission = $s2_user['view'];
	($hook = s2_hook('rq_action_load_tag_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');

	require 'tags.php';

	$tag = s2_load_tag((int) $_GET['id']);
	if (!$tag)
		die('Item not found!');

	s2_output_tag_form($tag, s2_html_time_from_timestamp($tag['modify_time']));
}

elseif ($action == 'save_tag')
{
	$is_permission = ($s2_user['create_articles'] || $s2_user['edit_site']);
	($hook = s2_hook('rq_action_save_tag_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_POST['tag']))
		die('Error in POST parameters.');

	require 'tags.php';

	$id = s2_save_tag($_POST['tag']);
	$tag = s2_load_tag($id);
	if (!$tag)
		die('Item not found!');

	s2_output_tag_form($tag, s2_html_time_from_timestamp($tag['modify_time']));
}

elseif ($action == 'delete_tag')
{
	$is_permission = $s2_user['edit_site'];
	($hook = s2_hook('rq_action_delete_tag_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');

	require 'tags.php';

	s2_delete_tag((int) $_GET['id']);

	$tag = array('id' => 0, 'name' => '', 'description' => '', 'url' => '');
	s2_output_tag_form($tag, s2_html_time_from_timestamp(time()));
}

//=======================[Options tab]==========================================

elseif ($action == 'load_options')
{
	$is_permission = $s2_user['view_hidden'];
	($hook = s2_hook('rq_action_load_options_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	require 'options.php';
	require S2_ROOT.'_admin/lang/'.Lang::admin_code().'/admin_opt.php';

	echo s2_get_options();
}

elseif ($action == 'save_options')
{
	$is_permission = $s2_user['edit_users'];
	($hook = s2_hook('rq_action_save_options_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	require 'options.php';
	require S2_ROOT.'_admin/lang/'.Lang::admin_code().'/admin_opt.php';

	$return = s2_save_options($_POST['opt']);
	if ($return)
	{
		header('X-S2-Status: Error');
		echo $return;
	}
}

//=======================[Extensions tab]=======================================

elseif ($action == 'load_extensions')
{
	$is_permission = $s2_user['view_hidden'];
	($hook = s2_hook('rq_action_load_extensions_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	require 'extensions.php';

	echo s2_extension_list();
}

elseif ($action == 'flip_extension')
{
	$is_permission = $s2_user['edit_users'];
	($hook = s2_hook('rq_action_flip_extension_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = $_GET['id'];

	require 'extensions.php';

	$message = s2_flip_extension($id);
	if (!$message)
		echo s2_extension_list();
	else
	{
		header('X-S2-Status: Error');
		echo $message;
	}
}

elseif ($action === 'refresh_hooks') {
    $is_permission = $s2_user['edit_users'];
    ($hook = s2_hook('rq_action_refresh_hooks_start')) ? eval($hook) : null;
    s2_test_user_rights($is_permission);

    // Regenerate the hooks cache
    /** @var ExtensionCache $cache */
    $cache = \Container::get(ExtensionCache::class);
    $cache->generateHooks();
    $cache->generateEnabledExtensionClassNames();
}

elseif ($action == 'uninstall_extension')
{
	$is_permission = $s2_user['edit_users'];
	($hook = s2_hook('rq_action_uninstall_extension_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = $_GET['id'];

	require 'extensions.php';

	$messages = s2_uninstall_extension($id);
	if (empty($messages))
		echo s2_extension_list();
	else
	{
		header('X-S2-Status: Error');
		echo implode("\n", $messages);
	}
}

elseif ($action == 'install_extension')
{
	$is_permission = $s2_user['edit_users'];
	($hook = s2_hook('rq_action_install_extension_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = $_GET['id'];

	require 'extensions.php';

	$messages = s2_install_extension($id);
	if (empty($messages))
		echo s2_extension_list();
	else
	{
		header('X-S2-Status: Error');
		echo implode("\n", $messages);
	}
}

elseif ($action == 'load_stat_info')
{
	$is_permission = $s2_user['view_hidden'];
	($hook = s2_hook('rq_action_load_stat_info_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	require 'info.php';

	echo s2_stat_info();
}

elseif ($action == 'phpinfo')
{
	$is_permission = $s2_user['view_hidden'];
	($hook = s2_hook('rq_action_phpinfo_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

    /** @noinspection ForgottenDebugOutputInspection */
    phpinfo();
}


($hook = s2_hook('rq_custom_action')) ? eval($hook) : null;

/** @var ?DbLayer $s2_db */
$s2_db = \Container::getIfInstantiated(DbLayer::class);
if ($s2_db) {
    $s2_db->close();
}

if (S2_COMPRESS)
	ob_end_flush();

header('Content-Length: '.ob_get_length());
ob_end_flush();
