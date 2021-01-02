<?php
/**
 * Manage extensions
 *
 * Server-side functions.
 *
 * @copyright (C) 2009-2013 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_manage_extensions
 */


if (!defined('S2_ROOT'))
	die;

function s2_manage_extensions_refresh_hooks ($id)
{
	global $s2_db;

	($hook = s2_hook('fn_s2_manage_extensions_refresh_hooks_start')) ? eval($hook) : null;

	$id = preg_replace('/[^0-9a-z_]/', '', $id);

	$manifest = is_readable(S2_ROOT.'_extensions/'.$id.'/manifest.xml') ? file_get_contents(S2_ROOT.'_extensions/'.$id.'/manifest.xml') : false;

	// Parse manifest.xml into an array and validate it
	$ext_data = s2_xml_to_array($manifest);
	$errors = s2_validate_manifest($ext_data, $id);

	if (!empty($errors))
		return $errors;

	$messages = array();

	// Delete the old hooks
	$query = array(
		'DELETE'	=> 'extension_hooks',
		'WHERE'		=> 'extension_id=\''.$s2_db->escape($id).'\''
	);

	($hook = s2_hook('fn_s2_manage_extensions_refresh_hooks_pre_delete_hooks')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	// Now insert the hooks
	if (isset($ext_data['extension']['hooks']['hook']))
	{
		foreach ($ext_data['extension']['hooks']['hook'] as $ext_hook)
		{
			$cur_hooks = explode(',', $ext_hook['attributes']['id']);
			foreach ($cur_hooks as $cur_hook)
			{
				$query = array(
					'INSERT'	=> 'id, extension_id, code, installed, priority',
					'INTO'		=> 'extension_hooks',
					'VALUES'	=> '\''.$s2_db->escape(trim($cur_hook)).'\', \''.$s2_db->escape($id).'\', \''.$s2_db->escape(trim($ext_hook['content'])).'\', '.time().', '.(isset($ext_hook['attributes']['priority']) ? $ext_hook['attributes']['priority'] : 5)
				);

				($hook = s2_hook('fn_s2_manage_extensions_refresh_hooks_pre_add_hook')) ? eval($hook) : null;
				$s2_db->query_build($query) or error(__FILE__, __LINE__);
			}
		}
	}


	// Regenerate the hooks cache
	if (!defined('S2_CACHE_FUNCTIONS_LOADED'))
		require S2_ROOT.'_include/cache.php';

	s2_generate_hooks_cache();

	($hook = s2_hook('manage_extensions_refresh_hooks_end')) ? eval($hook) : null;

	return $messages;
}