<?php
/**
 * Hook rq_custom_action
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_tpl_edit
 */

 if (!defined('S2_ROOT')) {
     die;
}

if ($action == 's2_tpl_edit_load')
{
	$is_permission = $s2_user['edit_users'];
	($hook = s2_hook('rq_action_s2_tpl_edit_load_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['filename']))
		die('Error in GET parameters.');
	$filename = preg_replace('#[^0-9a-zA-Z\._\-]#', '', $_GET['filename']);

	Lang::load('s2_tpl_edit', function ()
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_tpl_edit'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_tpl_edit'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_tpl_edit'.'/lang/English.php';
	});
	require S2_ROOT.'/_extensions/s2_tpl_edit'.'/functions.php';

	header('Content-Type: application/json; charset=utf-8');
	echo s2_json_encode(s2_tpl_edit_content($filename));
}

elseif ($action == 's2_tpl_edit_save')
{
	$is_permission = $s2_user['edit_users'];
	($hook = s2_hook('rq_action_s2_tpl_edit_load_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_POST['template']))
		die('Error in POST parameters.');
	$s2_tpl_edit_template = $_POST['template'];

	Lang::load('s2_tpl_edit', function ()
	{
		if (file_exists(S2_ROOT.'/_extensions/s2_tpl_edit'.'/lang/'.S2_LANGUAGE.'.php'))
			return require S2_ROOT.'/_extensions/s2_tpl_edit'.'/lang/'.S2_LANGUAGE.'.php';
		else
			return require S2_ROOT.'/_extensions/s2_tpl_edit'.'/lang/English.php';
	});
	require S2_ROOT.'/_extensions/s2_tpl_edit'.'/functions.php';

	$s2_tpl_edit_template_id = s2_tpl_edit_save($s2_tpl_edit_template);
	echo s2_tpl_edit_file_list($s2_tpl_edit_template_id);
}
