<?php
/**
 * Picture ajax requests
 *
 * Processes ajax queries for the picture manager
 *
 * @copyright (C) 2007-2010 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

define('S2_ROOT', '../');

require S2_ROOT.'include/common.php';
require S2_ROOT.'_lang/'.S2_LANGUAGE.'/pictures.php';
require 'login.php';
require 'pict_lib.php';

$session_id = isset($_COOKIE[$s2_cookie_name]) ? $_COOKIE[$s2_cookie_name] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action == 'preview')
{
	$file = $_GET['file'];

	while (strpos($file, '..') !== false)
		$file = str_replace('..', '', $file);

	s2_make_thumbnail(S2_IMG_PATH.$file, 80);
	exit();
}

header('Content-Type: text/html; charset=utf-8');
s2_no_cache();

if ($action == 'create_subfolder')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('prq_action_create_subfolder_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	$path = $_GET['path'];

	while (strpos($path, '..') !== false)
		$path = str_replace('..', '', $path);

	$name = 'new_folder';
	if (file_exists(S2_IMG_PATH.$path.'/'.$name))
	{
		$i = 1;
		while (file_exists(S2_IMG_PATH.$path.'/'.$name.$i))
			$i++;
		$name = $name.$i;
	}
	mkdir(S2_IMG_PATH.$path.'/'.$name);
	chmod(S2_IMG_PATH.$path.'/'.$name, 0777);

	echo s2_walk_dir($path, $name);
}

elseif ($action == 'delete_folder')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('prq_action_delete_folder_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	$path = $_GET['path'];

	while (strpos($path, '..') !== false)
		$path = str_replace('..', '', $path);

	if ($path != '')
	{
		s2_unlink_recursive(S2_IMG_PATH.$path);
		$path = s2_dirname($path);
	}
	echo s2_walk_dir($path);
}

elseif ($action == 'delete_file')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('prq_action_delete_file_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	$path = $_GET['path'];
	while (strpos($path, '..') !== false)
		$path = str_replace('..', '', $path);

	unlink(S2_IMG_PATH.$path);

	echo s2_get_files(s2_dirname($path));
}

elseif ($action == 'rename_folder')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('prq_action_rename_folder_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	$path = $_GET['path'];
	while (strpos($path, '..') !== false)
		$path = str_replace('..', '', $path);

	$folder_name = $_GET['name'];
	$folder_name = str_replace('\\', '', $folder_name);
	$folder_name = str_replace('/', '', $folder_name);
	while (strpos($folder_name, '..') !== false)
		$folder_name = str_replace('..', '', $folder_name);

	$parent_path = s2_dirname($path);
	rename(S2_IMG_PATH.$path, S2_IMG_PATH.$parent_path.'/'.$folder_name);

	echo s2_walk_dir($parent_path), '|', s2_get_files($parent_path.'/'.$folder_name);
}

elseif ($action == 'rename_file')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('prq_action_rename_file_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	$path = $_GET['path'];
	while (strpos($path, '..') !== false)
		$path = str_replace('..', '', $path);

	$filename = $_GET['name'];
	$filename = str_replace('\\', '', $filename);
	$filename = str_replace('/', '', $filename);
	while (strpos($filename, '..') !== false)
		$filename = str_replace('..', '', $filename);

	$parent_path = s2_dirname($path);
	rename(S2_IMG_PATH.$path, S2_IMG_PATH.$parent_path.'/'.$filename);

	echo s2_get_files($parent_path);
}

elseif ($action == 'drag')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('prq_action_drag_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	$spath = $_GET['spath'];
	while (strpos($spath, '..') !== false)
		$spath = str_replace('..', '', $spath);

	$dpath = $_GET['dpath'];
	while (strpos($dpath, '..') !== false)
		$dpath = str_replace('..', '', $dpath);

	rename(S2_IMG_PATH.$spath, S2_IMG_PATH.$dpath.'/'.basename($spath));

	echo s2_walk_dir($dpath).'|'.s2_walk_dir(s2_dirname($spath));
}

elseif ($action == 'load_items')
{
	$required_rights = array('view');
	($hook = s2_hook('prq_action_load_items_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	$path = $_GET['path'];
	while (strpos($path, '..') !== false)
		$path = str_replace('..', '', $path);

	echo s2_get_files($path);
}

elseif ($action == 'move_file')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('prq_action_move_file_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	$spath = $_GET['spath'];
	while (strpos($spath, '..') !== false)
		$spath = str_replace('..', '', $spath);

	$dpath = $_GET['dpath'];
	while (strpos($dpath, '..') !== false)
		$dpath = str_replace('..', '', $dpath);

	rename(S2_IMG_PATH.$spath, S2_IMG_PATH.$dpath.'/'.basename($spath));

	echo s2_get_files(s2_dirname($spath));
}

elseif ($action == 'upload')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('prq_action_upload_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	$path = $_POST['dir'];
	while (strpos($path, '..') !== false)
		$path = str_replace('..', '', $path);

	$errors = array();
	clearstatcache();

	foreach ($_FILES['pictures']['name'] as $i => $filename)
	{
		if ($_FILES['pictures']['error'][$i] !== UPLOAD_ERR_OK)
		{
			$error_message = isset($lang_pictures[$_FILES['pictures']['error'][$i]]) ? $lang_pictures[$_FILES['pictures']['error'][$i]] : $lang_pictures['Unknown error'];
			$errors[] = $filename ? sprintf($lang_pictures['Upload file error'], $filename, $error_message) : $error_message;
			continue;
		}

		$filename = strtolower(basename($filename));
		while (strpos($filename, '..') !== false)
			$filename = str_replace('..', '', $filename);

		// Processing name collisions
		while (is_file(S2_IMG_PATH.$path.'/'.$filename))
			$filename = preg_replace_callback('#(?:|_copy|_copy\((\d+)\))(?=(?:\.[^\.]*)?$)#', create_function('$m', 'if ($m[0] == \'\') return \'_copy\'; elseif ($m[0] == \'_copy\') return \'_copy(2)\'; else return \'_copy(\'.($m[1]+1).\')\';'), $filename, 1);

		$uploadfile = S2_IMG_PATH.$path.'/'.$filename;

		if (!move_uploaded_file($_FILES['pictures']['tmp_name'][$i], $uploadfile))
			$errors[] = sprintf($lang_pictures['Move upload file error'], $filename);
	}

	$error_message = empty($errors) ? '' : ' alert(\''.sprintf($lang_pictures['Upload failed'], implode('\n', $errors)).'\');';

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo $lang_pictures['Upload file'] ?></title>
<meta http-equiv="Pragma" content="no-cache" />
</head>
<body onload="(window.parent.RefreshFiles ? window.parent : opener).RefreshFiles();<?php echo $error_message; ?>">
</body>
</html>
<?

}