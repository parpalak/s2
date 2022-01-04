<?php
/**
 * Picture ajax requests
 *
 * Processes ajax queries for the picture manager
 *
 * @copyright (C) 2007-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


define('S2_ROOT', '../');

define('S2_NO_POST_BAD_CHARS', 1);
require S2_ROOT.'_include/common.php';

// IIS sets HTTPS to 'off' for non-SSL requests
if (defined('S2_FORCE_ADMIN_HTTPS') && ($_SERVER['HTTPS'] ?? 'off') === 'off') {
	header('Location: https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
	die();
}

require S2_ROOT.'_admin/lang/'.Lang::admin_code().'/admin.php';
require S2_ROOT.'_admin/lang/'.Lang::admin_code().'/pictures.php';
require 'login.php';
require 'site_lib.php';
require 'pict_lib.php';

$session_id = isset($_COOKIE[$s2_cookie_name]) ? $_COOKIE[$s2_cookie_name] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Check the current user and fetch the user info
$s2_user = s2_authenticate_user($session_id);

if ($action == 'preview')
{
	$file = (string) $_GET['file'];

	while (strpos($file, '..') !== false)
		$file = str_replace('..', '', $file);

	s2_make_thumbnail(S2_IMG_PATH.$file, 80);

	$s2_db->close();
	die;
}

s2_no_cache();

header('X-Powered-By: S2/'.S2_VERSION);
header('Content-Type: text/html; charset=utf-8');

ob_start();
if (S2_COMPRESS)
	ob_start('ob_gzhandler');

if ($action == 'load_tree')
{
	$is_permission = $s2_user['view'];
	($hook = s2_hook('prq_action_load_tree_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	$path = isset($_GET['path']) ? (string) $_GET['path'] : false;
	while ($path && strpos($path, '..') !== false)
		$path = str_replace('..', '', $path);

	$return = s2_walk_dir($path);
	if ($path === false)
		$return = array(
			'data'		=> $lang_pictures['Pictures'],
			'attr'		=> array('id' => 'node_1', 'data-path' => ''),
			'children'	=> $return
		);

	header('Content-Type: application/json; charset=utf-8');
	echo s2_json_encode($return);
}

elseif ($action == 'create_subfolder')
{
	$is_permission = $s2_user['create_articles'];
	($hook = s2_hook('prq_action_create_subfolder_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['path']) || !isset($_GET['name']))
		die('Error in GET parameters.');

	$path = (string) $_GET['path'];
	while (strpos($path, '..') !== false)
		$path = str_replace('..', '', $path);

	$name = (string) $_GET['name'];
	$name = str_replace('\\', '', $name);
	$name = str_replace('/', '', $name);
	while (strpos($name, '..') !== false)
		$name = str_replace('..', '', $name);

	if (file_exists(S2_IMG_PATH.$path.'/'.$name))
	{
		$i = 1;
		while (file_exists(S2_IMG_PATH.$path.'/'.$name.$i))
			$i++;
		$name = $name.$i;
	}

	if (mkdir(S2_IMG_PATH.$path.'/'.$name))
	{
		chmod(S2_IMG_PATH.$path.'/'.$name, 0777);

		header('Content-Type: application/json; charset=utf-8');
		echo s2_json_encode(array('status' => 1, 'name' => $name, 'path' => $path.'/'.$name));
	}
	else
	{
		$s2_db->close();

		header('X-S2-Status: Error');
		printf($lang_pictures['Error creating folder'], S2_IMG_PATH.$path.'/'.$name);
	}
}

elseif ($action == 'delete_folder')
{
	$is_permission = $s2_user['edit_site'];
	($hook = s2_hook('prq_action_delete_folder_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['path']))
		die('Error in GET parameters.');

	$path = (string) $_GET['path'];
	while (strpos($path, '..') !== false)
		$path = str_replace('..', '', $path);

	if ($path != '')
		s2_unlink_recursive(S2_IMG_PATH.$path);

	header('Content-Type: application/json; charset=utf-8');
	echo s2_json_encode(array('status' => 1));
}

elseif ($action == 'delete_files')
{
	$is_permission = $s2_user['edit_site'];
	($hook = s2_hook('prq_action_delete_file_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['path']) || !isset($_GET['fname']) || !is_array($_GET['fname']))
		die('Error in GET parameters.');

	$dir = (string) $_GET['path'];
	foreach ($_GET['fname'] as $fname)
	{
		$path = $dir.'/'.((string) $fname);
		while (strpos($path, '..') !== false)
			$path = str_replace('..', '', $path);

		unlink(S2_IMG_PATH.$path);
	}

	header('Content-Type: application/json; charset=utf-8');
	echo s2_json_encode(array('status' => 1));
}

elseif ($action == 'rename_folder')
{
	$is_permission = $s2_user['edit_site'];
	($hook = s2_hook('prq_action_rename_folder_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['path']) || !isset($_GET['name']))
		die('Error in GET parameters.');

	$path = (string) $_GET['path'];
	while (strpos($path, '..') !== false)
		$path = str_replace('..', '', $path);

	$folder_name = (string) $_GET['name'];
	$folder_name = str_replace('\\', '', $folder_name);
	$folder_name = str_replace('/', '', $folder_name);
	while (strpos($folder_name, '..') !== false)
		$folder_name = str_replace('..', '', $folder_name);

	$parent_path = s2_dirname($path);

	if (file_exists(S2_IMG_PATH.$parent_path.'/'.$folder_name))
	{
		$s2_db->close();

		header('X-S2-Status: Error');
		printf($lang_pictures['File exists'], $folder_name);
		die;
	}

	if (!is_dir(S2_IMG_PATH.$path))
		die('It is not a directory');

	if (rename(S2_IMG_PATH.$path, S2_IMG_PATH.$parent_path.'/'.$folder_name))
	{
		header('Content-Type: application/json; charset=utf-8');
		echo s2_json_encode(array('status' => 1, 'new_path' => $parent_path.'/'.$folder_name));
	}
	else
	{
		$s2_db->close();

		header('X-S2-Status: Error');
		die($lang_pictures['Rename error']);
	}
}

elseif ($action == 'rename_file')
{
	$is_permission = $s2_user['edit_site'];
	($hook = s2_hook('prq_action_rename_file_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['path']) || !isset($_GET['name']))
		die('Error in GET parameters.');

	$path = (string) $_GET['path'];
	while (strpos($path, '..') !== false)
		$path = str_replace('..', '', $path);

	$filename = (string) $_GET['name'];
	$filename = str_replace('\\', '', $filename);
	$filename = str_replace('/', '', $filename);
	while (strpos($filename, '..') !== false)
		$filename = str_replace('..', '', $filename);

	$extension = '';
	if (($ext_pos = strrpos($filename, '.')) !== false)
		 $extension = substr($filename, $ext_pos + 1);

	if (!$s2_user['edit_users'] && $extension != '' && S2_ALLOWED_EXTENSIONS != '' && false === strpos(' '.S2_ALLOWED_EXTENSIONS.' ', ' '.$extension.' '))
	{
		$s2_db->close();

		header('X-S2-Status: Error');
		printf($lang_pictures['Forbidden extension'], $extension);
		die;
	}

	$parent_path = s2_dirname($path);

	if (file_exists(S2_IMG_PATH.$parent_path.'/'.$filename))
	{
		$s2_db->close();

		header('X-S2-Status: Error');
		printf($lang_pictures['File exists'], $filename);
		die;
	}

	if (rename(S2_IMG_PATH.$path, S2_IMG_PATH.$parent_path.'/'.$filename))
	{
		header('Content-Type: application/json; charset=utf-8');
		echo s2_json_encode(array('status' => 1, 'new_name' => $filename));
	}
	else
	{
		$s2_db->close();

		header('X-S2-Status: Error');
		die($lang_pictures['Rename error']);
	}
}

elseif ($action == 'move_folder')
{
	$is_permission = $s2_user['edit_site'];
	($hook = s2_hook('prq_action_drag_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['spath']) || !isset($_GET['dpath']))
		die('Error in GET parameters.');

	$spath = (string) $_GET['spath'];
	while (strpos($spath, '..') !== false)
		$spath = str_replace('..', '', $spath);

	$dpath = (string) $_GET['dpath'];
	while (strpos($dpath, '..') !== false)
		$dpath = str_replace('..', '', $dpath);

	rename(S2_IMG_PATH.$spath, S2_IMG_PATH.$dpath.'/'.s2_basename($spath));

	header('Content-Type: application/json; charset=utf-8');
	echo s2_json_encode(array('status' => 1, 'new_path' => $dpath.'/'.s2_basename($spath)));
}
elseif ($action == 'move_files')
{
	$is_permission = $s2_user['edit_site'];
	($hook = s2_hook('prq_action_drag_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['spath']) || !isset($_GET['dpath']) || !isset($_GET['fname']) || !is_array($_GET['fname']))
		die('Error in GET parameters.');

	$spath = (string) $_GET['spath'];
	while (strpos($spath, '..') !== false)
		$spath = str_replace('..', '', $spath);

	$dpath = (string) $_GET['dpath'];
	while (strpos($dpath, '..') !== false)
		$dpath = str_replace('..', '', $dpath);

	foreach ($_GET['fname'] as $fname)
	{
		$fname = (string) $fname;
		while (strpos($fname, '..') !== false)
			$fname = str_replace('..', '', $fname);

		rename(S2_IMG_PATH.$spath.'/'.s2_basename($fname), S2_IMG_PATH.$dpath.'/'.s2_basename($fname));
	}

	header('Content-Type: application/json; charset=utf-8');
	echo s2_json_encode(array('status' => 1));
}

elseif ($action == 'load_files')
{
	$is_permission = $s2_user['view'];
	($hook = s2_hook('prq_action_load_items_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['path']))
		die('Error in GET parameters.');

	$path = $_GET['path'];
	while (strpos($path, '..') !== false)
		$path = str_replace('..', '', $path);

	header('Content-Type: application/json; charset=utf-8');
	echo s2_json_encode(s2_get_files($path));
}

elseif ($action == 'upload')
{
	$is_permission = $s2_user['create_articles'];
	($hook = s2_hook('prq_action_upload_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	$errors = array();

	if (isset($_POST['dir']))
	{
		$path = $_POST['dir'];
		$path = str_replace("\0", '', $path);
		while (strpos($path, '..') !== false)
			$path = str_replace('..', '', $path);
	}
	else
	{
		$errors[] = $lang_pictures['No POST data'];
		$path = '';
	}

	clearstatcache();

	$check_uploaded = true;

	// A workaround for multipart/mixed data
	if (!isset($_FILES['pictures']) && isset($_POST['pictures'][0]))
	{
		s2_process_multipart_mixed($_POST['pictures'][0], $_FILES['pictures'], S2_IMG_PATH);
		$check_uploaded = false;
	}

	if (!isset($_FILES['pictures']))
		$errors[] = $lang_pictures['Empty files'];
	else
	{
		foreach ($_FILES['pictures']['name'] as $i => $filename)
		{
			if ($_FILES['pictures']['error'][$i] !== UPLOAD_ERR_OK)
			{
				$error_message = isset($lang_pictures[$_FILES['pictures']['error'][$i]]) ? $lang_pictures[$_FILES['pictures']['error'][$i]] : $lang_pictures['Unknown error'];
				$errors[] = $filename ? sprintf($lang_pictures['Upload file error'], $filename, $error_message) : $error_message;
				continue;
			}

			if ($check_uploaded && !is_uploaded_file($_FILES['pictures']['tmp_name'][$i]))
			{
				$error_message = $lang_pictures['Is upload file error'];
				$errors[] = $filename ? sprintf($lang_pictures['Upload file error'], $filename, $error_message) : $error_message;
				continue;
			}

			$filename = utf8_strtolower(s2_basename($filename));
			$filename = str_replace("\0", '', $filename);
			while (strpos($filename, '..') !== false)
				$filename = str_replace('..', '', $filename);

			$extension = '';
			if (($ext_pos = strrpos($filename, '.')) !== false)
				 $extension = substr($filename, $ext_pos + 1);

			if (!$s2_user['edit_users'] && $extension != '' && S2_ALLOWED_EXTENSIONS != '' && false === strpos(' '.S2_ALLOWED_EXTENSIONS.' ', ' '.$extension.' '))
			{
				$error_message = sprintf($lang_pictures['Forbidden extension'], $extension);
				$errors[] = $filename ? sprintf($lang_pictures['Upload file error'], $filename, $error_message) : $error_message;
				continue;
			}

			// Processing name collisions
			while (is_file(S2_IMG_PATH.$path.'/'.$filename))
				$filename = preg_replace_callback('#(?:|_copy|_copy\((\d+)\))(?=(?:\.[^\.]*)?$)#', create_function('$m', 'if ($m[0] == \'\') return \'_copy\'; elseif ($m[0] == \'_copy\') return \'_copy(2)\'; else return \'_copy(\'.($m[1]+1).\')\';'), $filename, 1);

			$uploadfile = S2_IMG_PATH.$path.'/'.$filename;

			$result = $check_uploaded ?
				move_uploaded_file($_FILES['pictures']['tmp_name'][$i], $uploadfile) :
				rename($_FILES['pictures']['tmp_name'][$i], $uploadfile);

			if ($result)
				chmod($uploadfile, 0644);
			else
				$errors[] = sprintf($lang_pictures['Move upload file error'], $filename);
		}
	}

	if (!empty($errors))
		echo !isset($_POST['ajax']) ? sprintf($lang_pictures['Upload failed'], implode('<br />', $errors)) : implode('<br />', $errors);
}


($hook = s2_hook('prq_custom_action')) ? eval($hook) : null;

$s2_db->close();

if (S2_COMPRESS)
	ob_end_flush();

header('Content-Length: '.ob_get_length());
ob_end_flush();