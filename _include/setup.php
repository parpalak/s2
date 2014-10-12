<?php
/**
 * Autoloader and proper environment setup.
 *
 * @copyright (C) 2009-2014 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


if (!defined('S2_ROOT'))
	die;

require S2_ROOT.'_include/functions.php';
require S2_ROOT.'_include/utf8/utf8.php';

// If the cache directory is not specified, we use the default setting
if (!defined('S2_CACHE_DIR'))
	define('S2_CACHE_DIR', S2_ROOT.'_cache/');

// Reverse the effect of register_globals
s2_unregister_globals();

// Turn off magic_quotes_runtime
if (get_magic_quotes_runtime() && function_exists('set_magic_quotes_runtime'))
	set_magic_quotes_runtime(0);

// Strip slashes from GET/POST/COOKIE (if magic_quotes_gpc is enabled)
if (get_magic_quotes_gpc())
{
	function stripslashes_array($array)
	{
		return is_array($array) ? array_map('stripslashes_array', $array) : stripslashes($array);
	}

	$_GET = stripslashes_array($_GET);
	$_POST = stripslashes_array($_POST);
	$_COOKIE = stripslashes_array($_COOKIE);
}

// Strip out "bad" UTF-8 characters
s2_remove_bad_characters();

spl_autoload_register(function ($class)
{
	$class = ltrim($class, '\\');
	$dir = '';
	if (strpos($class, '\\'))
	{
		$ns_array = explode('\\', $class);
		$class = array_pop($ns_array);
		if (count($ns_array) == 2 && $ns_array[0] == 's2_extensions')
			$ns_array = array('_extensions', $ns_array[1], '_include');
		$dir  = S2_ROOT . implode(DIRECTORY_SEPARATOR, $ns_array) . DIRECTORY_SEPARATOR;
	}
	$file = $dir . str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';

	require $file;
});
