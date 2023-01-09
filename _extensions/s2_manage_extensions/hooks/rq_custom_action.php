<?php
/**
 * Hook rq_custom_action
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_manage_extensions
 */

 if (!defined('S2_ROOT')) {
     die;
}

if ($action == 's2_manage_extensions_refresh_hooks')
{
	$is_permission = $s2_user['edit_users'];
	($hook = s2_hook('rq_action_load_extensions_start')) ? eval($hook) : null;
	s2_test_user_rights($is_permission);

	if (!isset($_GET['id']))
		die('Error in GET parameters.');
	$id = $_GET['id'];

	require 'extensions.php';
	require S2_ROOT.'/_extensions/s2_manage_extensions'.'/functions.php';

	$messages = s2_manage_extensions_refresh_hooks($id);
	if (!empty($messages))
	{
		header('X-S2-Status: Error');
		echo implode("\n", $messages);
	}
}
