<?php
/**
 * Caching functions.
 *
 * This file contains all of the functions used to generate the cache files used by the site.
 *
 * @copyright (C) 2009-2012 Roman Parpalak, based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


if (!defined('S2_ROOT'))
	die;

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
// Generate the config cache
//
function s2_generate_config_cache ($load = false)
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
	{
		$output .= 'define(\''.$row[0].'\', \''.str_replace('\'', '\\\'', $row[1]).'\');'."\n";
		if ($load)
			define($row[0], $row[1]);
	}

	if ($load)
		define('S2_CONFIG_LOADED', 1);

	if (defined('S2_DISABLE_CACHE'))
		return;

	// Output config as PHP code
	$fh = @fopen(S2_CACHE_DIR.'cache_config.php', 'a+b');
	if (!$fh)
	{
		// Try to remove the file if it's not writable
		@unlink(S2_CACHE_DIR.'cache_config.php');
		$fh = @fopen(S2_CACHE_DIR.'cache_config.php', 'a+b');
	}

	if ($fh)
	{
		if (flock($fh, LOCK_EX | LOCK_NB))
		{
			ftruncate($fh, 0);
			fwrite($fh, '<?php'."\n\n".'define(\'S2_CONFIG_LOADED\', 1);'."\n\n".$output."\n");
			fflush($fh);
			fflush($fh);
			flock($fh, LOCK_UN);
		}
		fclose($fh);
	}
	else
		error('Unable to write configuration cache file to cache directory. Please make sure PHP has write access to the directory \''.S2_CACHE_DIR.'\'.', __FILE__, __LINE__);
}

//
// Generate the hooks cache
//
function s2_generate_hooks_cache ()
{
	global $s2_db, $s2_hooks;

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
			'\'url\'			=> S2_PATH.\'/_extensions/'.$cur_hook['extension_id'].'\','."\n".
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
				'\'url\'			=> S2_PATH.\'/_extensions/'.$cur_dependency.'\'),'."\n";
		}

		$load_ext_info .= ')'."\n".');'."\n".'$ext_info = $GLOBALS[\'ext_info_stack\'][count($GLOBALS[\'ext_info_stack\']) - 1];';
		$unload_ext_info = 'array_pop($GLOBALS[\'ext_info_stack\']);'."\n".'$ext_info = empty($GLOBALS[\'ext_info_stack\']) ? array() : $GLOBALS[\'ext_info_stack\'][count($GLOBALS[\'ext_info_stack\']) - 1];';

		$output[$cur_hook['id']][] = $load_ext_info."\n\n".$cur_hook['code']."\n\n".$unload_ext_info."\n";
	}

	// Replace current hooks
	$s2_hooks = $output;

	if (defined('S2_DISABLE_CACHE'))
		return;

	// Output hooks as PHP code
	$fh = @fopen(S2_CACHE_DIR.'cache_hooks.php', 'a+b');
	if (!$fh)
	{
		// Try to remove the file if it's not writable
		@unlink(S2_CACHE_DIR.'cache_hooks.php');
		$fh = @fopen(S2_CACHE_DIR.'cache_hooks.php', 'a+b');
	}

	if ($fh)
	{
		if (flock($fh, LOCK_EX | LOCK_NB))
		{
			ftruncate($fh, 0);
			fwrite($fh, '<?php'."\n\n".'if (!defined(\'S2_HOOKS_LOADED\'))'."\n\t".'define(\'S2_HOOKS_LOADED\', 1);'."\n\n".'$s2_hooks = '.var_export($output, true).';');
			fflush($fh);
			fflush($fh);
			flock($fh, LOCK_UN);
		}
		fclose($fh);
	}
	else
		error('Unable to write hooks cache file to cache directory. Please make sure PHP has write access to the directory \''.S2_CACHE_DIR.'\'.', __FILE__, __LINE__);
}


//
// Generate the updates cache PHP script
//
function s2_generate_updates_cache ()
{
	global $s2_db;

	$return = ($hook = s2_hook('fn_generate_updates_cache_start')) ? eval($hook) : null;
	if ($return != null)
		return $return;
/*
	// Get a list of installed hotfix extensions
	$query = array(
		'SELECT'	=> 'e.id',
		'FROM'		=> 'extensions AS e',
		'WHERE'		=> 'e.id LIKE \'hotfix_%\''
	);

	($hook = s2_hook('fn_generate_updates_cache_qr_get_hotfixes')) ? eval($hook) : null;
	$result = $s2_db->query_build($query) or error(__FILE__, __LINE__);

	$hotfixes = array();
	while ($hotfix = $s2_db->fetch_assoc($result))
		$hotfixes[] = urlencode($hotfix['id']);

	$result = s2_get_remote_file('http://s2cms.ru/update/?type=xml&version='.urlencode(S2_VERSION).'&hotfixes='.implode(',', $hotfixes), 8);
*/
	// Contact the S2 updates service
	$result = s2_get_remote_file('http://s2cms.ru/update/index.php?version='.urlencode(S2_VERSION), 8);

	// Make sure we got everything we need
	if ($result != null && strpos($result['content'], '</s2_updates>') !== false)
	{
		if (!defined('S2_XML_FUNCTIONS_LOADED'))
			require S2_ROOT.'_include/xml.php';

		$update_info = s2_xml_to_array(trim($result['content']));

		$output['version'] = $update_info['s2_updates']['lastversion'];
		$output['cached'] = time();
		$output['fail'] = false;
	}
	else
		$output = array('cached' => time(), 'fail' => true);

	($hook = s2_hook('fn_generate_updates_cache_write')) ? eval($hook) : null;

	// Output update status as PHP code
	if (!defined('S2_DISABLE_CACHE'))
	{
		$fh = @fopen(S2_CACHE_DIR.'cache_updates.php', 'a+b');

		if (!$fh)
		{
			// Try to remove the file if it's not writable
			@unlink(S2_CACHE_DIR.'cache_updates.php');
			$fh = @fopen(S2_CACHE_DIR.'cache_updates.php', 'a+b');
		}

		if ($fh)
		{
			if (flock($fh, LOCK_EX | LOCK_NB))
			{
				ftruncate($fh, 0);
				fwrite($fh, '<?php'."\n\n".'return '.var_export($output, true).';'."\n");
				fflush($fh);
				fflush($fh);
				flock($fh, LOCK_UN);
			}
			fclose($fh);
		}
		else
			error('Unable to write updates cache file to cache directory. Please make sure PHP has write access to the directory \''.S2_CACHE_DIR.'\'.', __FILE__, __LINE__);
	}

	return $output;
}

define('S2_CACHE_FUNCTIONS_LOADED', 1);
