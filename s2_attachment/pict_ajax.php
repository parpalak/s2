<?php
/**
 * Ajax processing for the attachment extension
 *
 * @copyright (C) 2010-2011 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_attachment
 */

if ($action == 's2_attachment_upload')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('prq_action_upload_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	require $ext_info['path'].'/functions.php';
	include $ext_info['path'].'/lang/'.S2_LANGUAGE.'.php';

	$errors = array();
	if (!isset($_POST['id']))
	{
		$errors[] = $lang_pictures['No POST data'];
		$id = 0;
	}
	else
		$id = (int) $_POST['id'];

	$now = time();
	$path = '/'.date('Y', $now).'/'.$id;

	clearstatcache();

	if (!defined('S2_ATTACHMENT_SMALL_SIZE'))
		define('S2_ATTACHMENT_SMALL_SIZE', 400);

	if (!defined('S2_ATTACHMENT_MICRO_SIZE'))
		define('S2_ATTACHMENT_MICRO_SIZE', 100);

	$check_uploaded = true;

	// A workaround for multipart/mixed data
	if (!isset($_FILES['pictures']) && isset($_POST['pictures'][0]))
	{
		s2_process_multipart_mixed($_POST['pictures'][0], $_FILES['pictures']);
		$check_uploaded = false;
	}

	if (!isset($_FILES['pictures']))
		$errors[] = $lang_pictures['Empty files'];
	else
	{
		foreach ($_FILES['pictures']['name'] as $i => $filename)
		{
			// Processing errors
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

			// Cleaning up the filename and the title
			$name = basename($filename);
			$filename = str_replace(
				preg_split('##u', 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ'),
				preg_split('##u', 'ABVGDEEJZIIKLMNOPRSTUFHC466JIJEUA'),
				$name
			);
			$filename = str_replace(
				preg_split('##u', 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя '),
				preg_split('##u', 'abvgdeejziiklmnoprstufhc466jijeua_'),
				$filename
			);
			$filename = strtolower($filename);
			$filename = preg_replace('#[^0-9a-z\-_\.]#', '', $filename);
			if (strtolower($name) == $filename)
				$name = '';

			// Processing name collisions
			while (is_file(S2_IMG_PATH.$path.'/'.$filename))
				$filename = preg_replace_callback('#(?:|_copy|_copy\((\d+)\))(?=(?:\.[^\.]*)?$)#', create_function('$m', 'if ($m[0] == \'\') return \'_copy\'; elseif ($m[0] == \'_copy\') return \'_copy(2)\'; else return \'_copy(\'.($m[1]+1).\')\';'), $filename, 1);

			// Create destination folder if needed
			if (!is_dir(S2_IMG_PATH.$path))
			{
				mkdir(S2_IMG_PATH.$path, 0777, true);
				chmod(S2_IMG_PATH.$path, 0777);
			}

			// Move the file to the destination directory
			$uploadfile = S2_IMG_PATH.$path.'/'.$filename;
			if (!rename($_FILES['pictures']['tmp_name'][$i], $uploadfile))
			{
				$errors[] = sprintf($lang_pictures['Move upload file error'], $filename);
				continue;
			}

			$size = filesize($uploadfile);
			$is_picture = (int) (strpos($filename, '.') !== false && in_array(end(explode('.', $filename)), array ('gif', 'bmp', 'jpg', 'jpeg', 'png')));

			if ($is_picture)
			{
				if (!is_dir(S2_IMG_PATH.$path.'/micro'))
				{
					mkdir(S2_IMG_PATH.$path.'/micro', 0777, true);
					chmod(S2_IMG_PATH.$path.'/micro', 0777);
				}
				$result = s2_attachment_save_thumbnail($uploadfile, S2_IMG_PATH.$path.'/micro/'.$filename.'.png', S2_ATTACHMENT_MICRO_SIZE);
				if ($result)
					$errors[] = $result;

				if (!is_dir(S2_IMG_PATH.$path.'/small'))
				{
					mkdir(S2_IMG_PATH.$path.'/small', 0777, true);
					chmod(S2_IMG_PATH.$path.'/small', 0777);
				}
				$result = s2_attachment_save_thumbnail($uploadfile, S2_IMG_PATH.$path.'/small/'.$filename.'.png', S2_ATTACHMENT_SMALL_SIZE);
				if ($result)
					$errors[] = $result;
			}

			$query = array(
				'INSERT'	=> 'article_id, name, filename, time, size, is_picture, priority',
				'INTO'		=> 's2_attachment_files',
				'VALUES'	=> $id.', \''.$s2_db->escape($name).'\', \''.$s2_db->escape($filename).'\', '.$now.', '.$size.', '.$is_picture.', 0',
			);
			($hook = s2_hook('s2_attachment_pre_add_file_qr')) ? eval($hook) : null;
			$s2_db->query_build($query) or error(__FILE__, __LINE__);
		}
	}

	$error_message = empty($errors) ? '' : ' alert(\''.sprintf($lang_pictures['Upload failed'], implode('\n', $errors)).'\');';

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="S2-State-Success" content="<?php if ($id) echo 's2_attachment-State-Success'; ?>" />
<title><?php echo $lang_pictures['Upload file'] ?></title>
<meta http-equiv="Pragma" content="no-cache" />
</head>
<body onload="<?php echo $error_message; ?>"><?php if ($id) echo s2_attachment_items($id); ?></body>
</html>
<?php

}