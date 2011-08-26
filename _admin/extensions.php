<?php
/**
 * Extension and hotfix management.
 *
 * Allows administrators to control the extensions and hotfixes installed in the site.
 *
 * @copyright (C) 2009-2011 Roman Parpalak, based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

if (!defined('S2_ROOT'))
	die;

if (!defined('S2_XML_FUNCTIONS_LOADED'))
	require S2_ROOT.'_include/xml.php';

require S2_ROOT.'_lang/'.S2_LANGUAGE.'/admin_ext.php';

($hook = s2_hook('aex_start')) ? eval($hook) : null;

// Make sure we have XML support
if (!function_exists('xml_parser_create'))
{
	echo '<div class="info-box"><p>'.$lang_admin_ext['No XML support'].'</p></div>';
	exit;
}

function s2_extension_list ()
{
	global $s2_db, $lang_admin_ext;

	$inst_exts = array();
	$query = array(
		'SELECT'	=> 'e.*',
		'FROM'		=> 'extensions AS e',
		'ORDER BY'	=> 'e.title'
	);

	($hook = s2_hook('fn_extension_list_qr_get_all_extensions')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_ext = $s2_db->fetch_assoc($result))
		$inst_exts[$cur_ext['id']] = $cur_ext;

	$num_exts = 0;
	$num_failed = 0;
	$item_num = 1;
	$ext_item = array();
	$ext_error = array();

	$d = dir(S2_ROOT.'_extensions');
	while (($entry = $d->read()) !== false)
	{
		if ($entry{0} == '.' || !is_dir(S2_ROOT.'_extensions/'.$entry))
			continue;

		if (preg_match('/[^0-9a-z_]/', $entry))
		{
			$ext_error[] = '<div class="extension error db'.++$item_num.'">'.
				'<h3>'.sprintf($lang_admin_ext['Extension loading error'], s2_htmlencode($entry)).'</h3>'.
				'<p>'.$lang_admin_ext['Illegal ID'].'</p>'.
				'</div>';
			++$num_failed;
			continue;
		}
		else if (!file_exists(S2_ROOT.'_extensions/'.$entry.'/manifest.xml'))
		{
			$ext_error[] = '<div class="extension error db'.++$item_num.'">'.
				'<h3>'.sprintf($lang_admin_ext['Extension loading error'], s2_htmlencode($entry)).'</h3>'.
				'<p>'.$lang_admin_ext['Missing manifest'].'</p>'.
				'</div>';
			++$num_failed;
			continue;
		}

		// Parse manifest.xml into an array
		$ext_data = is_readable(S2_ROOT.'_extensions/'.$entry.'/manifest.xml') ? s2_xml_to_array(file_get_contents(S2_ROOT.'_extensions/'.$entry.'/manifest.xml')) : '';
		if (empty($ext_data))
		{
			$ext_error[] = '<div class="extension error db'.++$item_num.'">'.
				'<h3>'.sprintf($lang_admin_ext['Extension loading error'], s2_htmlencode($entry)).'</h3>'.
				'<p>'.$lang_admin_ext['Failed parse manifest'].'</p>'.
				'</div>';
			++$num_failed;
			continue;
		}

		// Validate manifest
		$errors = s2_validate_manifest($ext_data, $entry);
		if (!empty($errors))
		{
			$ext_error[] = '<div class="extension error db'.++$item_num.'">'.
				'<h3>'.sprintf($lang_admin_ext['Extension loading error'], s2_htmlencode($entry)).'</h3>'.
				'<p>'.implode(' ', $errors).'</p>'.
				'</div>';
			++$num_failed;
		}
		else
		{
			if (!array_key_exists($entry, $inst_exts) || version_compare($inst_exts[$entry]['version'], $ext_data['extension']['version'], '!='))
			{
				$install_notes = array();
				foreach ($ext_data['extension']['note'] as $cur_note)
					if ($cur_note['attributes']['type'] == 'install')
						$install_notes[] = s2_htmlencode(addslashes($cur_note['content']));

					if (version_compare(s2_clean_version(S2_VERSION), s2_clean_version($ext_data['extension']['maxtestedon']), '>'))
					$install_notes[] = s2_htmlencode(addslashes($lang_admin_ext['Maxtestedon warning']));

				if (count($install_notes) > 1)
					foreach ($install_notes as $index => $cur_note)
						$install_notes[$index] = ($index + 1).'. '.$cur_note;

				$buttons['install'] = '<button class="bitbtn inst_ext" onclick="return InstallExtension(\''.s2_htmlencode(addslashes($entry)).'\', \''.implode('\\n', $install_notes).'\');" />'.(isset($inst_exts[$entry]['version']) ? $lang_admin_ext['Upgrade extension'] : $lang_admin_ext['Install extension']).'</button>';

				$ext_item[] = '<div class="extension available">'.
					'<div class="info"><h3>'.s2_htmlencode($ext_data['extension']['title']).sprintf($lang_admin_ext['Version'], $ext_data['extension']['version']).'</h3>'.
					'<p>'.sprintf($lang_admin_ext['Extension by'], s2_htmlencode($ext_data['extension']['author'])).'</p></div>'.
					(($ext_data['extension']['description'] != '') ? '<p class="description">'.s2_htmlencode($ext_data['extension']['description']).'</p>' : '').
					'<div class="options">'.implode('<br />', $buttons).'</div></div>';
				++$num_exts;
			}
		}
	}
	$d->close();

	($hook = s2_hook('fn_extension_list_pre_display_available')) ? eval($hook) : null;

	ob_start();

	echo '<h2>'.$lang_admin_ext['Extensions available'].'</h2>';

	if ($num_exts)
		echo implode('', $ext_item);
	else
		echo '<div class="info-box"><p>'.$lang_admin_ext['No available extensions'].'</p></div>';

	// If any of the extensions had errors
	if ($num_failed)
	{
		echo '<div class="info-box"><p class="important">'.$lang_admin_ext['Invalid extensions'].'</p></div>';
		echo implode('', $ext_error);
	}

	($hook = s2_hook('fn_extension_list_pre_display_installed')) ? eval($hook) : null;

	$installed_count = 0;
	$ext_item = array();
	foreach ($inst_exts as $id => $ext)
	{
		if (strpos($id, 'hotfix_') === 0)
			continue;

		$buttons = array(
			'flip'		=> '<button class="bitbtn flip_ext" onclick="return FlipExtension(\''.s2_htmlencode(addslashes($id)).'\');" />'.($ext['disabled'] != '1' ? $lang_admin_ext['Disable'] : $lang_admin_ext['Enable']).'</button>',
			'uninstall'	=> '<button class="bitbtn uninst_ext" onclick="return UninstallExtension(\''.s2_htmlencode(addslashes($id)).'\', \''.s2_htmlencode(addslashes($ext['uninstall_note'])).'\');" />'.$lang_admin_ext['Uninstall'].'</button>'
		);

		$extra_info = '';

		($hook = s2_hook('fn_extension_list_pre_inst_item_merge')) ? eval($hook) : null;

		$ext_item[] = '<div class="extension '.($ext['disabled'] == '1' ? 'disabled' : 'enabled').'">'.
			'<div class="info"><h3>'.s2_htmlencode($ext['title']).sprintf($lang_admin_ext['Version'], $ext['version']).'</h3>'.
			'<p>'.sprintf($lang_admin_ext['Extension by'], s2_htmlencode($ext['author'])).'</p>'.$extra_info.'</div>'.
			(($ext['description'] != '') ? '<p class="description">'.s2_htmlencode($ext['description']).'</p>' : '').
			'<div class="options">'.implode('<br />', $buttons).'</div></div>';

		$installed_count++;
	}

	echo '<h2>'.$lang_admin_ext['Installed extensions'].'</h2>';
	if ($installed_count > 0)
	{
		echo '<div class="info-box"><p class="important">'.$lang_admin_ext['Installed extensions warn'].'</p></div>';
		echo implode('', $ext_item);
	}
	else
		echo '<div class="info-box"><p>'.$lang_admin_ext['No installed extensions'].'</p></div>';

	return ob_get_clean();
}

function s2_install_extension ($id)
{
	global $s2_db, $lang_admin_ext;
// Install an extension
//if (isset($_GET['install']) || isset($_GET['install_hotfix']))

	($hook = s2_hook('aex_install_selected')) ? eval($hook) : null;

	$id = preg_replace('/[^0-9a-z_]/', '', $id);

	// Load manifest (either locally or from s2cms.com updates service)
//	if (isset($_GET['install']))
		$manifest = is_readable(S2_ROOT.'_extensions/'.$id.'/manifest.xml') ? file_get_contents(S2_ROOT.'_extensions/'.$id.'/manifest.xml') : false;
	// else
	// {
		// $remote_file = get_remote_file('http://s2cms.com/update/manifest/'.$id.'.xml', 16);
		// if (!empty($remote_file['content']))
			// $manifest = $remote_file['content'];
	// }

	// Parse manifest.xml into an array and validate it
	$ext_data = s2_xml_to_array($manifest);
	$errors = s2_validate_manifest($ext_data, $id);

	if (!empty($errors))
		return '<div class="info-box"><p class="important">Unexpected error.</p></div>';
		//message(isset($_GET['install']) ? $lang_common['Bad request'] : $lang_admin_ext['Hotfix download failed']);

	$messages = array();

	// Make sure we have an array of dependencies
	if (!isset($ext_data['extension']['dependencies']['dependency']))
		$ext_data['extension']['dependencies'] = array();
	else if (!is_array(current($ext_data['extension']['dependencies'])))
		$ext_data['extension']['dependencies'] = array($ext_data['extension']['dependencies']['dependency']);
	else
		$ext_data['extension']['dependencies'] = $ext_data['extension']['dependencies']['dependency'];

	$query = array(
		'SELECT'	=> 'e.id',
		'FROM'		=> 'extensions AS e',
		'WHERE'		=> 'e.disabled=0'
	);

	($hook = s2_hook('aex_install_check_dependencies')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$installed_ext = array();
	while ($row = $s2_db->fetch_assoc($result))
		$installed_ext[] = $row['id'];

	$broken_dependencies = array();
	foreach ($ext_data['extension']['dependencies'] as $dependency)
		if (!in_array($dependency, $installed_ext))
			$broken_dependencies[] = $dependency;

	if (!empty($broken_dependencies))
		return '<div class="info-box"><p class="important">'.sprintf($lang_admin_ext['Missing dependency'], $id, implode(', ', $broken_dependencies)).'</p></div>';

	($hook = s2_hook('aex_install_comply_form_submitted')) ? eval($hook) : null;

	// $ext_info contains some information about the extension being installed
	$ext_info = array(
		'id'			=> $id,
		'path'			=> S2_ROOT.'_extensions/'.$id,
		'url'			=> S2_BASE_URL.'/_extensions/'.$id,
		'dependencies'	=> array()
	);

	foreach ($ext_data['extension']['dependencies'] as $dependency)
	{
		$ext_info['dependencies'][$dependency] = array(
			'id'	=> $dependency,
			'path'	=> S2_ROOT.'_extensions/'.$dependency,
			'url'	=> S2_BASE_URL.'/_extensions/'.$dependency,
		);
	}

	// Is there some uninstall code to store in the db?
	$uninstall_code = (isset($ext_data['extension']['uninstall']) && trim($ext_data['extension']['uninstall']) != '') ? '\''.$s2_db->escape(trim($ext_data['extension']['uninstall'])).'\'' : 'NULL';

	// Is there an uninstall note to store in the db?
	$uninstall_note = 'NULL';
	foreach ($ext_data['extension']['note'] as $cur_note)
	{
		if ($cur_note['attributes']['type'] == 'uninstall' && trim($cur_note['content']) != '')
			$uninstall_note = '\''.$s2_db->escape(trim($cur_note['content'])).'\'';
	}

	// Is this a fresh install or an upgrade?
	$query = array(
		'SELECT'	=> 'e.version',
		'FROM'		=> 'extensions AS e',
		'WHERE'		=> 'e.id=\''.$s2_db->escape($id).'\''
	);

	($hook = s2_hook('aex_install_comply_qr_get_current_ext_version')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	if ($curr_version = $s2_db->result($result))
	{
		// EXT_CUR_VERSION will be available to the extension install routine (to facilitate extension upgrades)
		define('EXT_CUR_VERSION', $curr_version);

		// Run the author supplied install code
		if (isset($ext_data['extension']['install']) && trim($ext_data['extension']['install']) != '')
		{
			$return = eval($ext_data['extension']['install']);
			if (is_string($return))
				return '<div class="info-box"><p class="important">'.$return.'</p></div>';
		}

		// Update the existing extension
		$query = array(
			'UPDATE'	=> 'extensions',
			'SET'		=> 'title=\''.$s2_db->escape($ext_data['extension']['title']).'\', version=\''.$s2_db->escape($ext_data['extension']['version']).'\', description=\''.$s2_db->escape($ext_data['extension']['description']).'\', author=\''.$s2_db->escape($ext_data['extension']['author']).'\', uninstall='.$uninstall_code.', uninstall_note='.$uninstall_note.', dependencies=\'|'.implode('|', $ext_data['extension']['dependencies']).'|\'',
			'WHERE'		=> 'id=\''.$s2_db->escape($id).'\''
		);

		($hook = s2_hook('aex_install_comply_qr_update_ext')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);

		// Delete the old hooks
		$query = array(
			'DELETE'	=> 'extension_hooks',
			'WHERE'		=> 'extension_id=\''.$s2_db->escape($id).'\''
		);

		($hook = s2_hook('aex_install_comply_qr_update_ext_delete_hooks')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);
	}
	else
	{
		// Run the author supplied install code
		if (isset($ext_data['extension']['install']) && trim($ext_data['extension']['install']) != '')
		{
			$return = eval($ext_data['extension']['install']);
			if (is_string($return))
				return '<div class="info-box"><p class="important">'.$return.'</p></div>';
		}

		// Add the new extension
		$query = array(
			'INSERT'	=> 'id, title, version, description, author, uninstall, uninstall_note, dependencies',
			'INTO'		=> 'extensions',
			'VALUES'	=> '\''.$s2_db->escape($ext_data['extension']['id']).'\', \''.$s2_db->escape($ext_data['extension']['title']).'\', \''.$s2_db->escape($ext_data['extension']['version']).'\', \''.$s2_db->escape($ext_data['extension']['description']).'\', \''.$s2_db->escape($ext_data['extension']['author']).'\', '.$uninstall_code.', '.$uninstall_note.', \'|'.implode('|', $ext_data['extension']['dependencies']).'|\'',
		);

		($hook = s2_hook('aex_install_comply_qr_add_ext')) ? eval($hook) : null;
		$s2_db->query_build($query) or error(__FILE__, __LINE__);
	}

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

				($hook = s2_hook('aex_install_comply_qr_add_hook')) ? eval($hook) : null;
				$s2_db->query_build($query) or error(__FILE__, __LINE__);
			}
		}
	}


	// Regenerate the hooks cache
	if (!defined('S2_CACHE_FUNCTIONS_LOADED'))
		require S2_ROOT.'_include/cache.php';

	s2_clear_cache();

	s2_generate_hooks_cache();
	global $s2_hooks;
	require S2_CACHE_DIR.'cache_hooks.php';

	return implode('', $messages);
}

function s2_flip_extension ($id)
{
	global $s2_db, $lang_admin_ext;

	$id = preg_replace('/[^0-9a-z_]/', '', $id);

	($hook = s2_hook('aex_flip_selected')) ? eval($hook) : null;

	// Fetch the current status of the extension
	$query = array(
		'SELECT'	=> 'e.disabled',
		'FROM'		=> 'extensions AS e',
		'WHERE'		=> 'e.id=\''.$s2_db->escape($id).'\''
	);

	($hook = s2_hook('aex_flip_qr_get_disabled_status')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	if ($row = $s2_db->fetch_assoc($result))
	// Are we disabling or enabling?
		$disable = $row['disabled'] == '0';
	else
		return $lang_common['Bad request'];

	// Check dependancies
	if ($disable)
	{
		$query = array(
			'SELECT'	=> 'e.id',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.disabled=0 AND e.dependencies LIKE \'%|'.$s2_db->escape($id).'|%\''
		);

		($hook = s2_hook('aex_flip_qr_get_disable_dependencies')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$dependency_ids = array();
		while ($dependency = $s2_db->fetch_assoc($result))
			$dependency_ids[] = $dependency['id'];

		if (!empty($dependency_ids))
			return '<div class="info-box"><p class="important">'.sprintf($lang_admin_ext['Disable dependency'], $id, implode(', ', $dependency_ids)).'</p></div>';
	}
	else
	{
		$query = array(
			'SELECT'	=> 'e.dependencies',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.id=\''.$s2_db->escape($id).'\''
		);

		($hook = s2_hook('aex_flip_qr_get_enable_dependencies')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$dependencies = $s2_db->fetch_assoc($result);
		$dependencies = explode('|', substr($dependencies['dependencies'], 1, -1));

		$query = array(
			'SELECT'	=> 'e.id',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.disabled=0'
		);

		($hook = s2_hook('aex_flip_qr_check_dependencies')) ? eval($hook) : null;
		$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

		$installed_ext = array();
		while ($row = $s2_db->fetch_assoc($result))
			$installed_ext[] = $row['id'];

		$broken_dependencies = array();
		foreach ($dependencies as $dependency)
			if (!empty($dependency) && !in_array($dependency, $installed_ext))
				$broken_dependencies[] = $dependency;

		if (!empty($broken_dependencies))
			return '<div class="info-box"><p class="important">'.sprintf($lang_admin_ext['Disabled dependency'], $id, implode(', ', $broken_dependencies)).'</p></div>';
	}

	$query = array(
		'UPDATE'	=> 'extensions',
		'SET'		=> 'disabled='.($disable ? '1' : '0'),
		'WHERE'		=> 'id=\''.$s2_db->escape($id).'\''
	);

	($hook = s2_hook('aex_flip_qr_update_disabled_status')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the hooks cache
	if (!defined('S2_CACHE_FUNCTIONS_LOADED'))
		require S2_ROOT.'_include/cache.php';

	s2_generate_hooks_cache();
	global $s2_hooks;
	require S2_CACHE_DIR.'cache_hooks.php';

	($hook = s2_hook('aex_flip_pre_redirect')) ? eval($hook) : null;

	return '';
}

function s2_uninstall_extension ($id)
{
	global $s2_db, $lang_admin_ext;

	($hook = s2_hook('aex_uninstall_selected')) ? eval($hook) : null;

	$id = preg_replace('/[^0-9a-z_]/', '', $id);

	// Fetch info about the extension
	$query = array(
		'SELECT'	=> 'e.title, e.version, e.description, e.author, e.uninstall, e.uninstall_note',
		'FROM'		=> 'extensions AS e',
		'WHERE'		=> 'e.id=\''.$s2_db->escape($id).'\''
	);

	($hook = s2_hook('aex_uninstall_qr_get_extension')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$ext_data = $s2_db->fetch_assoc($result);
	if (!$ext_data)
		die('Extension not found.');

	// Check dependancies
	$query = array(
		'SELECT'	=> 'e.id',
		'FROM'		=> 'extensions AS e',
		'WHERE'		=> 'e.dependencies LIKE \'%|'.$s2_db->escape($id).'|%\''
	);

	($hook = s2_hook('aex_uninstall_qr_check_dependencies')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$dependencies = array();
	while ($row = $s2_db->fetch_assoc($result))
		$dependencies[] = $row['id'];

	if (!empty($dependencies))
		return '<div class="info-box"><p class="important">'.sprintf($lang_admin_ext['Uninstall dependency'], $id, implode(', ', $dependencies)).'</p></div>';

	($hook = s2_hook('aex_uninstall_comply_form_submitted')) ? eval($hook) : null;

	$ext_info = array(
		'id'			=> $id,
		'path'			=> S2_ROOT.'_extensions/'.$id,
		'url'			=> S2_BASE_URL.'/_extensions/'.$id
	);

	$messages = array();

	// Run uninstall code
	eval($ext_data['uninstall']);

	// Now delete the extension and its hooks from the db
	$query = array(
		'DELETE'	=> 'extension_hooks',
		'WHERE'		=> 'extension_id=\''.$s2_db->escape($id).'\''
	);

	($hook = s2_hook('aex_uninstall_comply_qr_uninstall_delete_hooks')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	$query = array(
		'DELETE'	=> 'extensions',
		'WHERE'		=> 'id=\''.$s2_db->escape($id).'\''
	);

	($hook = s2_hook('aex_uninstall_comply_qr_delete_extension')) ? eval($hook) : null;
	$s2_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the hooks cache
	if (!defined('S2_CACHE_FUNCTIONS_LOADED'))
		require S2_ROOT.'_include/cache.php';

	s2_clear_cache();

	s2_generate_hooks_cache();
	global $s2_hooks;
	require S2_CACHE_DIR.'cache_hooks.php';

	return implode('', $messages);
}