<?php
/**
 * Ajax requests
 *
 * Processes ajax queries for the admin panel
 *
 * @copyright (C) 2007-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

define('S2_ROOT', '../');
require S2_ROOT.'_include/common.php';
require S2_ROOT.'_lang/'.S2_LANGUAGE.'/admin.php';
require 'login.php';
require 'site_lib.php';

($hook = s2_hook('rq_start')) ? eval($hook) : null;

s2_no_cache();

header('X-Powered-By: S2/'.S2_VERSION);
header('Content-Type: text/html; charset=utf-8');

ob_start();
if (S2_COMPRESS)
	ob_start('ob_gzhandler');

$session_id = isset($_COOKIE[$s2_cookie_name]) ? $_COOKIE[$s2_cookie_name] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

//=======================[Managing items]=======================================

// Drag & Drop
if ($action == 'drag')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('rq_action_drag_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['sid']) || !isset($_GET['did']) || !isset($_GET['far']))
		die('Error in GET parameters.');

	$source_id = (int) $_GET['sid'];
	$dest_id = (int) $_GET['did'];
	$far = (int) $_GET['far'];

	$query = array(
		'SELECT'	=> 'priority, parent_id, id',
		'FROM'		=> 'articles',
		'WHERE'		=> 'id IN ('.$source_id.', '.$dest_id.')'
	);
	($hook = s2_hook('rq_action_drag_pre_get_pr_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$item_num = 0;
	while ($row = $s2_db->fetch_assoc($result))
	{
		if ($row['id'] == $source_id)
		{
			$source_priority = $row['priority'];
			$source_parent_id = $row['parent_id'];
		}
		else
		{
			$dest_priority = $row['priority'];
			$dest_parent_id = $row['parent_id'];
		}
		$item_num++;
	}

	if ($item_num != 2)
		die('Items not found!');

	if ($far)
	{
		// Dragging into different folder

		$query = array(
			'UPDATE'	=> 'articles',
			'SET'		=> 'priority = priority - 1',
			'WHERE'		=> 'parent_id = '.$source_parent_id.' AND priority > '.$source_priority
		);
		($hook = s2_hook('rq_action_drag_pre_src_pr_upd_qr')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);

		$query = array(
			'UPDATE'	=> 'articles',
			'SET'		=> 'priority = priority + 1',
			'WHERE'		=> 'parent_id = '.$dest_id
		);
		($hook = s2_hook('rq_action_drag_pre_dest_priority_upd_qr')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);

		$query = array(
			'UPDATE'	=> 'articles',
			'SET'		=> 'priority = 0, parent_id = '.$dest_id,
			'WHERE'		=> 'id = '.$source_id
		);
		($hook = s2_hook('rq_action_drag_pre_parent_id_upd_qr')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);

		echo s2_get_child_branches($dest_id), '|';
	}
	else
	{
		// Dragging inside a folder

		if ($source_priority < $dest_priority)
		{
			$query = array(
				'UPDATE'	=> 'articles',
				'SET'		=> 'priority = priority - 1',
				'WHERE'		=> 'parent_id = '.$source_parent_id.' AND priority > '.$source_priority.' AND priority <= '.$dest_priority
			);
			($hook = s2_hook('rq_action_drag_pre_shift_pr_dn_upd_qr')) ? eval($hook) : null;
			$s2_db->query_build($query) or error(__FILE__, __LINE__);
		}
		else
		{
			$query = array(
				'UPDATE'	=> 'articles',
				'SET'		=> 'priority = priority + 1',
				'WHERE'		=> 'parent_id = '.$source_parent_id.' AND priority < '.$source_priority.' AND priority >= '.$dest_priority
			);
			($hook = s2_hook('rq_action_drag_pre_shift_pr_up_upd_qr')) ? eval($hook) : null;
			$s2_db->query_build($query) or error(__FILE__, __LINE__);
		}
		$query = array(
			'UPDATE'	=> 'articles',
			'SET'		=> 'priority = '.$dest_priority,
			'WHERE'		=> 'id = '.$source_id
		);
		($hook = s2_hook('rq_action_drag_pre_src_pr_dn_upd_qr')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);
	}
	echo s2_get_child_branches($source_parent_id);
}

elseif ($action == 'delete')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('rq_action_delete_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = (int) $_GET['id'];

	$query = array(
		'SELECT'	=> 'priority, parent_id',
		'FROM'		=> 'articles',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('rq_action_delete_get_parent_id_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	if ($s2_db->fetch_row($result))
		list($priority, $parent_id) = $row;
	else
		die('Item not found!');

	$query = array(
		'UPDATE'	=> 'articles',
		'SET'		=> 'priority = priority - 1',
		'WHERE'		=> 'parent_id = '.$parent_id.' AND  priority > '.$priority
	);
	($hook = s2_hook('rq_action_delete_pre_upd_pr_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	s2_delete_branch($id);

	echo s2_get_child_branches($parent_id);
}

elseif ($action == 'rename')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('rq_action_rename_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']) || !isset($_POST['title']))
		die('Error in GET and POST parameters.');
	$id = (int) $_GET['id'];

	$query = array(
		'SELECT'	=> 'parent_id',
		'FROM'		=> 'articles',
		'WHERE'		=> 'id = '.$id
	);
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	($hook = s2_hook('rq_action_rename_pre_get_parent_id_qr')) ? eval($hook) : null;

	if ($row = $s2_db->fetch_assoc($result))
		$parent_id = $row['parent_id'];
	else
		die('Item not found!');

	$query = array(
		'UPDATE'	=> 'articles',
		'SET'		=> 'title = \''.$s2_db->escape($_POST['title']).'\'',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('rq_action_rename_pre_upd_title_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);
}

elseif ($action == 'create')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('rq_action_create_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = (int) $_GET['id'];

	$query = array(
		'SELECT'	=> 'parent_id',
		'FROM'		=> 'articles',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('rq_action_create_pre_get_parent_id_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	if ($row = $s2_db->fetch_assoc($result))
		$parent_id = $row['parent_id'];
	else
		die('Item not found!');

	$query = array(
		'SELECT'	=> 'MAX(priority)',
		'FROM'		=> 'articles',
		'WHERE'		=> 'parent_id = '.$id
	);
	($hook = s2_hook('rq_action_create_pre_get_maxpr_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	$max_priority = $s2_db->result($result);

	$query = array(
		'INSERT'	=> 'parent_id, title, priority, url',
		'INTO'		=> 'articles',
		'VALUES'	=> $id.', \''.$lang_admin['New page'].'\', '.($max_priority + 1).', \'new\''
	);
	($hook = s2_hook('rq_action_create_pre_ins_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	echo s2_get_child_branches($id);
}

// Load folder tree
elseif ($action == 'load_tree')
{
	$required_rights = array('view');
	($hook = s2_hook('rq_action_load_tree_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');

	echo s2_get_child_branches((int)$_GET['id'], true, trim($_GET['search']));
}

//=======================[Pages editor]=========================================

// Loading data from DB into the editor form
elseif ($action == 'load')
{
	$required_rights = array('view');
	($hook = s2_hook('rq_action_load_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = (int) $_GET['id'];

	($hook = s2_hook('rq_action_load_pre_output')) ? eval($hook) : null;

	s2_output_article_form($id);
}

// Saving data to DB
elseif ($action == 'save')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('rq_action_save_article_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_POST['page']))
		die('Error in POST parameters.');
	$page = $_POST['page'];

	$id = (int) $page['id'];
	$favorite = (int) isset($_POST['flags']['favorite']);
	$published = (int) isset($_POST['flags']['published']);
	$commented = (int) isset($_POST['flags']['commented']);
	$children_preview = (int) isset($_POST['flags']['children_preview']);

	$create_time = isset($_POST['cr_time']) ? s2_time_from_array($_POST['cr_time']) : time();
	$modify_time = isset($_POST['m_time']) ? s2_time_from_array($_POST['m_time']) : time();

	$query = array(
		'UPDATE'	=> 'articles',
		'SET'		=> "title = '".$s2_db->escape($page['title'])."', meta_keys = '".$s2_db->escape($page['meta_keys'])."', meta_desc = '".$s2_db->escape($page['meta_desc'])."', excerpt = '".$s2_db->escape($page['excerpt'])."', pagetext = '".$s2_db->escape($page['text'])."', url = '".$s2_db->escape($page['url'])."', published = $published, favorite = $favorite, commented = $commented, create_time = $create_time, modify_time = $modify_time, children_preview = $children_preview, template = '".$s2_db->escape($page['template'])."'",
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('rq_action_save_article_pre_post_upd_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	if ($s2_db->affected_rows() == -1)
		die($lang_admin['Not saved correct']);

	s2_check_form_controls($id);

	($hook = s2_hook('rq_action_save_article_end')) ? eval($hook) : null;
}

elseif ($action == 'preview')
{
	$required_rights = array('view');
	($hook = s2_hook('rq_action_preview_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');

	$path = s2_path_from_id((int) $_GET['id'], true);
	if ($path === false)
		echo $lang_admin['Preview not found'];
	else
		header('Location: '.S2_BASE_URL.S2_URL_PREFIX.$path);
}

//=======================[Tag management]=======================================

elseif ($action == 'load_tagnames')
{
	$required_rights = array('view');
	($hook = s2_hook('rq_action_load_tagnames_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	$query = array(
		'SELECT'	=> 'tag_id, name, (SELECT count(*) FROM '.$s2_db->prefix.'article_tag as a WHERE t.tag_id = a.tag_id) as article_count',
		'FROM'		=> 'tags as t',
		'ORDER BY'	=> 'name'
	);
	($hook = s2_hook('rq_action_load_tagnames_pre_query')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$list = '';
	while ($row = $s2_db->fetch_assoc($result))
		$list .= '<li tagid="'.$row['tag_id'].'" onclick="ChooseTag(this);">'.s2_htmlencode($row['name']).' (<span>'.$row['article_count'].'</span>)</li>';

	echo $list;
}

// Articles that have specified tag
elseif ($action == 'load_tagvalues')
{
	$required_rights = array('view');
	($hook = s2_hook('rq_action_load_tagvalues_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');

	echo s2_get_tag_articles((int) $_GET['id']);
}

// Add the tag to the article
elseif ($action == 'add_to_tag')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('rq_action_add_to_tag_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['tag_id']) || !isset($_GET['article_id']))
		die('Error in GET parameters.');

	$tag_id = (int) $_GET['tag_id'];
	$article_id = (int) $_GET['article_id'];

	$query = array(
		'INSERT'	=> 'article_id, tag_id',
		'INTO'		=> 'article_tag',
		'VALUES'	=> $article_id.', '.$tag_id
	);
	($hook = s2_hook('rq_action_add_to_tag_pre_ins_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	echo s2_get_tag_articles($tag_id);
}

// Remove the tag from the article
elseif ($action == 'delete_from_tag')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('rq_action_delete_from_tag_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');

	$id = (int)$_GET['id'];

	$query = array(
		'SELECT'	=> 'tag_id',
		'FROM'		=> 'article_tag',
		'WHERE'		=> 'id = '.$id
	);

	($hook = s2_hook('rq_action_delete_from_tag_pre_get_tagid_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	if ($row = $s2_db->fetch_row($result))
		list($tag_id) = $row;
	else
		die('Can\'t find the article-tag link.');


	$query = array(
		'DELETE'	=> 'article_tag',
		'WHERE'		=> 'id = '.$id
	);

	($hook = s2_hook('rq_action_delete_from_tag_pre_del_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	echo s2_get_tag_articles($tag_id);
}

//=======================[Comment management]===================================

elseif ($action == 'load_comments')
{
	$required_rights = array('view');
	($hook = s2_hook('rq_action_load_comments_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');

	require 'comments.php';

	echo s2_comment_menu_links();
	echo s2_show_comments('all', (int) $_GET['id']);
}

elseif ($action == 'load_hidden_comments')
{
	$required_rights = array('view_hidden');
	($hook = s2_hook('rq_action_load_hidden_comments_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	require 'comments.php';

	echo s2_comment_menu_links('hidden');
	echo s2_show_comments('hidden');

	($hook = s2_hook('rq_action_load_hidden_comments_end')) ? eval($hook) : null;
}

elseif ($action == 'load_last_comments')
{
	$required_rights = array('view');
	($hook = s2_hook('rq_action_load_last_comments_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	require 'comments.php';

	echo s2_comment_menu_links('last');
	echo s2_show_comments('last');

	($hook = s2_hook('rq_action_load_last_comments_end')) ? eval($hook) : null;
}

elseif ($action == 'load_new_comments')
{
	$required_rights = array('view_hidden');
	($hook = s2_hook('rq_action_load_new_comments_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	require 'comments.php';

	echo s2_comment_menu_links('new');
	echo s2_show_comments('new');

	($hook = s2_hook('rq_action_load_new_comments_end')) ? eval($hook) : null;
}

elseif ($action == 'delete_comment')
{
	$required_rights = array('edit_comments');
	($hook = s2_hook('rq_action_delete_comment_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');

	require 'comments.php';

	s2_delete_comment((int) $_GET['id']);
}

elseif ($action == 'edit_comment')
{
	$required_rights = array('edit_comments');
	($hook = s2_hook('rq_action_edit_comment_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');

	require 'comments.php';

	s2_edit_comment((int) $_GET['id']);
}

elseif ($action == 'save_comment')
{
	$required_rights = array('edit_comments');
	($hook = s2_hook('rq_action_save_comment_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	require 'comments.php';

	s2_save_comment();
}

elseif ($action == 'hide_comment')
{
	$required_rights = array('hide_comments');
	($hook = s2_hook('rq_action_hide_comment_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');

	require 'comments.php';

	s2_toggle_hide_comment((int)$_GET['id']);
}

elseif ($action == 'mark_comment')
{
	$required_rights = array('edit_comments');
	($hook = s2_hook('rq_action_mark_comment_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');

	require 'comments.php';

	s2_toggle_mark_comment((int)$_GET['id']);
}

//=======================[User management]======================================

elseif ($action == 'login')
{
	if ($session_id != '')
		die();

	// We have POST data. Process it! Handle errors if they are.
	echo s2_ajax_login();
}

elseif ($action == 'logout')
{
	s2_logout($session_id);
}

elseif ($action == 'load_userlist')
{
	$required_rights = array('view_hidden', 'edit_users');
	($hook = s2_hook('rq_action_load_userlist_start')) ? eval($hook) : null;
	$cur_login = s2_test_user_rights($session_id, $required_rights);

	echo s2_get_user_list($cur_login);
}

elseif ($action == 'add_user')
{
	$required_rights = array('edit_users');
	($hook = s2_hook('rq_action_add_user_start')) ? eval($hook) : null;
	$cur_login = s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['name']))
		die('Error in GET parameters.');

	$login = $s2_db->escape($_GET['name']);

	// Verify if login entered already exists
	$query = array(
		'SELECT'	=> 'login',
		'FROM'		=> 'users',
		'WHERE'		=> 'login = \''.$login.'\''
	);

	($hook = s2_hook('rq_action_add_user_pre_login_verify_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	if ($s2_db->fetch_row($result))
	{
		// Exists
		printf('<div class="info-box"><p>'.$lang_admin['Username exists'].'</p></div>', s2_htmlencode($_GET['name']));
	}
	else
	{
		// New login, Ok
		$query = array(
			'INSERT'	=> 'login, password',
			'INTO'		=> 'users',
			'VALUES'	=> '\''.$login.'\', \''.md5('Life is not so easy :-)').'\''
		);
		($hook = s2_hook('rq_action_add_user_pre_ins_qr')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);
	}

	echo s2_get_user_list($cur_login, true);
}

elseif ($action == 'delete_user')
{
	$required_rights = array('edit_users');
	($hook = s2_hook('rq_action_delete_user_start')) ? eval($hook) : null;
	$cur_login = s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['name']))
		die('Error in GET parameters.');

	$login = $_GET['name'];

	$query = array(
		'SELECT'	=> 'count(*)',
		'FROM'		=> 'users',
		'WHERE'		=> 'edit_users = 1 AND NOT login = \''.$s2_db->escape($login).'\'',
	);
	($hook = s2_hook('rq_action_delete_user_pre_get_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	$allow = $s2_db->result($result) > 0;

	if ($allow)
	{
		$query = array(
			'DELETE'	=> 'users',
			'WHERE'		=> 'login = \''.$s2_db->escape($login).'\''
		);
		($hook = s2_hook('rq_action_delete_user_pre_del_qr')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);
	}
	else
		echo '<div class="info-box"><p>'.$lang_admin['No other admin delete'].'</p></div>';

	echo s2_get_user_list($cur_login, true);
}

elseif ($action == 'user_set_password')
{
	$required_rights = array('view_hidden');
	($hook = s2_hook('rq_action_user_set_password_start')) ? eval($hook) : null;
	$cur_login = s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['name']) || !isset($_POST['pass']))
		die('Error in GET and POST parameters.');

	// Extra permissions check
	$query = array(
		'SELECT'	=> 'edit_users',
		'FROM'		=> 'users',
		'WHERE'		=> 'login = \''.$s2_db->escape($cur_login).'\''
	);
	($hook = s2_hook('rq_action_user_set_password_pre_get_perm_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	$is_admin = $s2_db->result($result);

	// We allow usual users to change only their passwords
	if ($cur_login == $_GET['name'] || $is_admin)
	{
		$query = array(
			'UPDATE'	=> 'users',
			'SET'		=> 'password = \''.$s2_db->escape($_POST['pass']).'\'',
			'WHERE'		=> 'login = \''.$s2_db->escape($_GET['name']).'\'',
		);
		($hook = s2_hook('rq_action_user_set_password_pre_upd_qr')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);

		echo $lang_admin['Password changed'];
	}
	else
	{
		header('X-S2-Status: Forbidden');
		die($lang_admin['No permission']);
	}
}

elseif ($action == 'user_set_email')
{
	$required_rights = array('view_hidden');
	($hook = s2_hook('rq_action_user_set_email_start')) ? eval($hook) : null;
	$cur_login = s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['name']) || !isset($_GET['email']))
		die('Error in GET parameters.');

	// Extra permissions check
	$query = array(
		'SELECT'	=> 'edit_users',
		'FROM'		=> 'users',
		'WHERE'		=> 'login = \''.$s2_db->escape($cur_login).'\''
	);
	($hook = s2_hook('rq_action_user_set_email_pre_get_perm_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	$edit_users = $s2_db->result($result);

	// We allow usual users to change only their passwords
	if ($cur_login == $_GET['name'] || $edit_users)
	{
		$query = array(
			'UPDATE'	=> 'users',
			'SET'		=> 'email = \''.$s2_db->escape($_GET['email']).'\'',
			'WHERE'		=> 'login = \''.$s2_db->escape($_GET['name']).'\'',
		);
		($hook = s2_hook('rq_action_user_set_email_pre_upd_qr')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);
	}
	else
		echo '<div class="info-box"><p>'.$lang_admin['No permissions email'].'</p></div>';

	echo s2_get_user_list($cur_login, $edit_users);
}

elseif ($action == 'user_set_permission')
{
	$required_rights = array('edit_users');
	($hook = s2_hook('rq_action_user_set_permission_start')) ? eval($hook) : null;
	$cur_login = s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['name']) || !isset($_GET['permission']))
		die('Error in GET parameters.');

	$login = $_GET['name'];
	$permission = $_GET['permission'];

	$allow = true;

	if ($permission == 'edit_users')
	{
		$query = array(
			'SELECT'	=> 'count(*)',
			'FROM'		=> 'users',
			'WHERE'		=> 'edit_users = 1 AND NOT login = \''.$s2_db->escape($login).'\'',
		);
		($hook = s2_hook('rq_action_user_set_permission_pre_get_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
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
		$s2_db->query_build($query) or error(__FILE__, __LINE__);
	}
	else
		echo '<div class="info-box"><p>'.$lang_admin['No other admin'].'</p></div>';

	echo s2_get_user_list($cur_login, true);
}

// "Smart" replacing "\n\n" to '</p><p>' and "\n" to '<br />'
elseif ($action == 'smart_paragraph')
{
	$s = isset($_POST['data']) ? (string) $_POST['data'] : '';

	echo s2_nl2p($s);
}

//=======================[Tags tab]=============================================

elseif ($action == 'load_tags')
{
	$required_rights = array('view');
	($hook = s2_hook('rq_action_load_tags_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	$tag['name'] = '';
	$tag['description'] = '';
	$tag['id'] = 0;
	$tag['url'] = '';

	s2_output_tag_form($tag, s2_array_from_time(time()));
}

elseif ($action == 'load_tag')
{
	$required_rights = array('view');
	($hook = s2_hook('rq_action_load_tag_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = (int)$_GET['id'];

	$query = array(
		'SELECT'	=> 'tag_id as id, name, description, url, modify_time',
		'FROM'		=> 'tags',
		'WHERE'		=> 'tag_id = '.$id
	);
	($hook = s2_hook('rq_action_load_tag_pre_get_tag_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$tag = $s2_db->fetch_assoc($result);
	if (!$tag)
		die('Item not found!');

	s2_output_tag_form($tag, s2_array_from_time($tag['modify_time']));
}

elseif ($action == 'save_tag')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('rq_action_save_tag_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_POST['tag']) || !isset($_POST['m_time']))
		die('Error in POST parameters.');

	$id = isset($_POST['tag']['id']) ? (int) $_POST['tag']['id'] : 0;
	$tag_name = isset($_POST['tag']['name']) ? $s2_db->escape($_POST['tag']['name']) : '';
	$tag_url = isset($_POST['tag']['url']) ? $s2_db->escape($_POST['tag']['url']) : '';
	$tag_description = isset($_POST['tag']['description']) ? $s2_db->escape($_POST['tag']['description']) : '';

	$modify_time = isset($_POST['m_time']) ? s2_time_from_array($_POST['m_time']) : time();

	($hook = s2_hook('rq_action_save_tag_pre_id_check')) ? eval($hook) : null;

	if (!$id)
	{
		$query = array(
			'SELECT'	=> 'tag_id',
			'FROM'		=> 'tags',
			'WHERE'		=> 'name = \''.$tag_name.'\''
		);
		($hook = s2_hook('rq_action_save_tag_pre_get_id_qr')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
		if ($row = $s2_db->fetch_assoc($result))
			$id = $row['tag_id'];
	}

	if ($id)
	{
		$query = array(
			'UPDATE'	=> 'tags',
			'SET'		=> 'name = \''.$tag_name.'\', url = \''.$tag_url.'\', description = \''.$tag_description.'\', modify_time = '.$modify_time,
			'WHERE'		=> 'tag_id = '.$id
		);
		($hook = s2_hook('rq_action_save_tag_pre_upd_qr')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);
	}
	else
	{
		$query = array(
			'INSERT'	=> 'name, description, modify_time, url',
			'INTO'		=> 'tags',
			'VALUES'	=> '\''.$tag_name.'\', \''.$tag_description.'\', \''.$modify_time.'\', \''.$tag_url.'\''
		);
		($hook = s2_hook('rq_action_save_tag_pre_ins_qr')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);

		$id = $s2_db->insert_id();
	}

	$query = array(
		'SELECT'	=> 'tag_id as id, name, description, url, modify_time',
		'FROM'		=> 'tags',
		'WHERE'		=> 'tag_id = '.$id
	);
	($hook = s2_hook('rq_action_save_tag_pre_get_tag_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$tag = $s2_db->fetch_assoc($result);
	if (!$tag)
		die('Item not found!');

	s2_output_tag_form($tag, s2_array_from_time($tag['modify_time']));
}

elseif ($action == 'delete_tag')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('rq_action_delete_tag_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = (int) $_GET['id'];

	$query = array(
		'DELETE'	=> 'tags',
		'WHERE'		=> 'tag_id = '.$id,
		'LIMIT'		=> '1'
	);

	($hook = s2_hook('rq_action_delete_tag_pre_del_tag_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	$query = array(
		'DELETE'	=> 'article_tag',
		'WHERE'		=> 'tag_id = '.$id,
	);
	($hook = s2_hook('rq_action_delete_tag_pre_del_links_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	($hook = s2_hook('rq_action_delete_tag_pre_new_form_output')) ? eval($hook) : null;

	$tag['name'] = '';
	$tag['description'] = '';
	$tag['id'] = 0;
	$tag['url'] = '';

	s2_output_tag_form($tag, s2_array_from_time(time()));
}

//=======================[Options tab]==========================================

elseif ($action == 'load_options')
{
	$required_rights = array('view');
	($hook = s2_hook('rq_action_load_options_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	require 'options.php';
	require S2_ROOT.'_lang/'.S2_LANGUAGE.'/admin_opt.php';

	echo s2_get_options();
}

elseif ($action == 'save_options')
{
	$required_rights = array('edit_users');
	($hook = s2_hook('rq_action_save_options_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	require 'options.php';
	require S2_ROOT.'_lang/'.S2_LANGUAGE.'/admin_opt.php';

	$return = s2_save_options($_POST['opt']);
	echo s2_get_options($return);
}

//=======================[Extensions tab]=======================================

elseif ($action == 'load_extensions')
{
	$required_rights = array('view');
	($hook = s2_hook('rq_action_load_extensions_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	require 'extensions.php';

	echo s2_extension_list();
}

elseif ($action == 'flip_extension')
{
	$required_rights = array('edit_users');
	($hook = s2_hook('rq_action_flip_extension_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = $_GET['id'];

	require 'extensions.php';

	echo s2_flip_extension($id);
	echo s2_extension_list();
}

elseif ($action == 'uninstall_extension')
{
	$required_rights = array('edit_users');
	($hook = s2_hook('rq_action_uninstall_extension_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = $_GET['id'];

	require 'extensions.php';

	echo s2_uninstall_extension($id);
	echo s2_extension_list();
}

elseif ($action == 'install_extension')
{
	$required_rights = array('edit_users');
	($hook = s2_hook('rq_action_install_extension_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = $_GET['id'];

	require 'extensions.php';

	echo s2_install_extension($id);
	echo s2_extension_list();
}

elseif ($action == 'load_stat_info')
{
	$required_rights = array('view');
	($hook = s2_hook('rq_action_load_stat_info_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	require 'info.php';

	echo s2_stat_info();
}

elseif ($action == 'phpinfo')
{
	$required_rights = array('view');
	($hook = s2_hook('rq_action_phpinfo_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	phpinfo();
}


($hook = s2_hook('rq_custom_action')) ? eval($hook) : null;

$s2_db->close();

if (S2_COMPRESS)
	ob_end_flush();

header('Content-Length: '.ob_get_length());
ob_end_flush();