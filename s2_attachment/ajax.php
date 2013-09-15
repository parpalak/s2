<?php
/**
 * Ajax processing for the attachment extension
 *
 * @copyright (C) 2010-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_attachment
 */

if ($action == 's2_attachment_delete')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('rq_action_s2_attachment_delete_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	require $ext_info['path'].'/functions.php';
	include $ext_info['path'].'/lang/'.S2_LANGUAGE.'.php';

	$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

	// Obtain file path
	$query = array(
		'SELECT'	=> 'filename, time, article_id, is_picture',
		'FROM'		=> 's2_attachment_files',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('rq_action_s2_attachment_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$file = $s2_db->fetch_assoc($result);
	$path = S2_IMG_PATH.'/'.date('Y', $file['time']).'/'.$file['article_id'];

	// Delete file
	@unlink($path.'/'.$file['filename']);
	if ($file['is_picture'])
	{
		// Delete thumbnails
		@unlink($path.'/small/'.$file['filename'].'.png');
		@unlink($path.'/micro/'.$file['filename'].'.png');
	}

	// Remove DB entry
	$query = array(
		'DELETE'	=> 's2_attachment_files',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('rq_action_s2_attachment_delete_pre_del_qr')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	echo s2_attachment_items($file['article_id']);
}

elseif ($action == 's2_attachment_rename')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('rq_action_s2_attachment_delete_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	require $ext_info['path'].'/functions.php';
	include $ext_info['path'].'/lang/'.S2_LANGUAGE.'.php';

	$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
	$name = isset($_GET['name']) ? $_GET['name'] : '';

	// Rename
	$query = array(
		'UPDATE'	=> 's2_attachment_files',
		'SET'		=> 'name = \''.$s2_db->escape($name).'\'',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('rq_action_s2_attachment_rename_pre_upd_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'article_id',
		'FROM'		=> 's2_attachment_files',
		'WHERE'		=> 'id = '.$id
	);
	($hook = s2_hook('rq_action_s2_attachment_rename_pre_qr')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$file = $s2_db->fetch_assoc($result);
	echo s2_attachment_items($file['article_id']);
}

elseif ($action == 's2_attachment_sort')
{
	$required_rights = array('edit_site');
	($hook = s2_hook('rq_action_s2_attachment_sort_start')) ? eval($hook) : null;
	s2_test_user_rights($session_id, $required_rights);

	require $ext_info['path'].'/functions.php';

	$ids = isset($_GET['ids']) ? (string) $_GET['ids'] : '';
	s2_attachment_sort_files($ids);
}
