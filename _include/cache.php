<?php
/**
 * Caching functions.
 *
 * This file contains all of the functions used to generate the cache files used by the site.
 *
 * @copyright (C) 2009-2011 Roman Parpalak, based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


// Delete every .php file in the cache directory
function s2_clear_cache ()
{
	$file_list = array('cache_config.php', 'cache_hooks.php');

	$return = ($hook = s2_hook('fn_clear_cache_start')) ? eval($hook) : null;
	if ($return != null)
		return;

	foreach ($file_list as $entry)
		@unlink(S2_CACHE_DIR.$entry);
}

//
// Generate the config cache PHP script
//
function s2_generate_config_cache ()
{
	global $s2_db;

	$return = ($hook = s2_hook('fn_generate_config_cache_start')) ? eval($hook) : null;
	if ($return != null)
		return;

	// Get the config from the DB
	$query = array(
		'SELECT'	=> 'c.*',
		'FROM'		=> 'config AS c'
	);

	($hook = s2_hook('fn_generate_config_cache_qr_get_config')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$output = '';
	while ($row = $s2_db->fetch_row($result))
		$output .= 'define(\''.$row[0].'\', \''.str_replace('\'', '\\\'', $row[1]).'\');'."\n";

	// Output config as PHP code
	$fh = @fopen(S2_CACHE_DIR.'cache_config.php', 'wb');
	if (!$fh)
		error('Unable to write configuration cache file to cache directory. Please make sure PHP has write access to the directory \''.S2_CACHE_DIR.'\'.', __FILE__, __LINE__);

	fwrite($fh, '<?php'."\n\n".'define(\'S2_CONFIG_LOADED\', 1);'."\n\n".$output."\n");

	fclose($fh);
}

//
// Generate the hooks cache PHP script
//
function s2_generate_hooks_cache ()
{
	global $s2_db;

	$return = ($hook = s2_hook('fn_generate_hooks_cache_start')) ? eval($hook) : null;
	if ($return != null)
		return;

	// Get the hooks from the DB
	$query = array(
		'SELECT'	=> 'eh.id, eh.code, eh.extension_id, e.dependencies',
		'FROM'		=> 'extension_hooks AS eh',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'extensions AS e',
				'ON'			=> 'e.id=eh.extension_id'
			)
		),
		'WHERE'		=> 'e.disabled=0',
		'ORDER BY'	=> 'eh.priority, eh.installed'
	);

	($hook = s2_hook('fn_generate_hooks_cache_qr_s2_hooks')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$output = array();
	while ($cur_hook = $s2_db->fetch_assoc($result))
	{
		$load_ext_info = '$GLOBALS[\'ext_info_stack\'][] = array('."\n".
			'\'id\'				=> \''.$cur_hook['extension_id'].'\','."\n".
			'\'path\'			=> S2_ROOT.\'_extensions/'.$cur_hook['extension_id'].'\','."\n".
			'\'url\'			=> S2_BASE_URL.\'/_extensions/'.$cur_hook['extension_id'].'\','."\n".
			'\'dependencies\'	=> array ('."\n";

		$dependencies = explode('|', substr($cur_hook['dependencies'], 1, -1));
		foreach ($dependencies as $cur_dependency)
		{
			// This happens if there are no dependencies because explode ends up returning an array with one empty element
			if (empty($cur_dependency))
				continue;

			$load_ext_info .= '\''.$cur_dependency.'\'	=> array('."\n".
				'\'id\'				=> \''.$cur_dependency.'\','."\n".
				'\'path\'			=> S2_ROOT.\'_extensions/'.$cur_dependency.'\','."\n".
				'\'url\'			=> S2_BASE_URL.\'/_extensions/'.$cur_dependency.'\'),'."\n";
		}

		$load_ext_info .= ')'."\n".');'."\n".'$ext_info = $GLOBALS[\'ext_info_stack\'][count($GLOBALS[\'ext_info_stack\']) - 1];';
		$unload_ext_info = 'array_pop($GLOBALS[\'ext_info_stack\']);'."\n".'$ext_info = empty($GLOBALS[\'ext_info_stack\']) ? array() : $GLOBALS[\'ext_info_stack\'][count($GLOBALS[\'ext_info_stack\']) - 1];';

		$output[$cur_hook['id']][] = $load_ext_info."\n\n".$cur_hook['code']."\n\n".$unload_ext_info."\n";
	}

	// Output hooks as PHP code
	$fh = @fopen(S2_CACHE_DIR.'cache_hooks.php', 'wb');
	if (!$fh)
		error('Unable to write hooks cache file to cache directory. Please make sure PHP has write access to the directory \''.S2_CACHE_DIR.'\'.', __FILE__, __LINE__);

	fwrite($fh, '<?php'."\n\n".'if (!defined(\'S2_HOOKS_LOADED\'))'."\n\t".'define(\'S2_HOOKS_LOADED\', 1);'."\n\n".'$s2_hooks = '.var_export($output, true).';');

	fclose($fh);
}
/*

//
// Generate the updates cache PHP script
//
function generate_updates_cache()
{
	global $s2_db, $s2_config;

	$return = ($hook = s2_hook('ch_fn_generate_updates_cache_start')) ? eval($hook) : null;
	if ($return != null)
		return;

	// Get a list of installed hotfix extensions
	$query = array(
		'SELECT'	=> 'e.id',
		'FROM'		=> 'extensions AS e',
		'WHERE'		=> 'e.id LIKE \'hotfix_%\''
	);

	($hook = s2_hook('ch_fn_generate_updates_cache_qr_get_hotfixes')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);
	$num_hotfixes = $s2_db->num_rows($result);

	$hotfixes = array();
	for ($i = 0; $i < $num_hotfixes; ++$i)
		$hotfixes[] = urlencode($s2_db->result($result, $i));

	// Contact the punbb.informer.com updates service
	//$result = get_remote_file('http://punbb.informer.com/update/?type=xml&version='.urlencode($s2_config['o_cur_version']).'&hotfixes='.implode(',', $hotfixes), 8);

	// Make sure we got everything we need
	if ($result != null && strpos($result['content'], '</updates>') !== false)
	{
		if (!defined('S2_XML_FUNCTIONS_LOADED'))
			require S2_ROOT.'_include/xml.php';

		$output = xml_to_array(s2_trim($result['content']));
		$output = current($output);

		if (!empty($output['hotfix']) && is_array($output['hotfix']) && !is_array(current($output['hotfix'])))
			$output['hotfix'] = array($output['hotfix']);

		$output['cached'] = time();
		$output['fail'] = false;
	}
	else	// If the update check failed, set the fail flag
		$output = array('cached' => time(), 'fail' => true);

	// This hook could potentially (and responsibly) be used by an extension to do its own little update check
	($hook = s2_hook('ch_fn_generate_updates_cache_write')) ? eval($hook) : null;

	// Output update status as PHP code
	$fh = @fopen(S2_CACHE_DIR.'cache_updates.php', 'wb');
	if (!$fh)
		error('Unable to write updates cache file to cache directory. Please make sure PHP has write access to the directory \'_cache\'.', __FILE__, __LINE__);

	fwrite($fh, '<?php'."\n\n".'if (!defined(\'S2_UPDATES_LOADED\')) define(\'S2_UPDATES_LOADED\', 1);'."\n\n".'$s2_updates = '.var_export($output, true).';'."\n\n".'?>');

	fclose($fh);
}
*/
define('S2_CACHE_FUNCTIONS_LOADED', 1);
