<?php
/**
 * Loads common data and performs various functions necessary for the site to work properly.
 *
 * @copyright (C) 2009-2010 Roman Parpalak, based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

require S2_ROOT.'_include/functions.php';
require S2_ROOT.'_include/utf8/utf8.php';

define('S2_VERSION', '1.0a1');

// Uncomment these lines for debug
//define('S2_DEBUG', 1);
//define('S2_SHOW_QUERIES', 1);

// Reverse the effect of register_globals
s2_unregister_globals();

// Attempt to load the configuration file config.php
if (file_exists(S2_ROOT.'config.php'))
	include S2_ROOT.'config.php';

if (!defined('S2_BASE_URL'))
	error('The file \'config.php\' doesn\'t exist or is corrupt. Please run <a href="'.preg_replace('#'.(S2_ROOT == '../' ? '/[a-z_\.]*' : '').'/[a-z_]*\.php$#', '/', $_SERVER['SCRIPT_NAME']).'_admin/install.php">install.php</a> to install S2 first.');

if (defined('S2_DEBUG'))
	error_reporting(E_ALL);
else
	error_reporting(E_ALL ^ E_NOTICE);

// Turn off magic_quotes_runtime
if (get_magic_quotes_runtime())
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

define('S2_ROOT_ID', 0);

// If the cache directory is not specified, we use the default setting
if (!defined('S2_CACHE_DIR'))
	define('S2_CACHE_DIR', S2_ROOT.'_cache/');

// If the image directory is not specified, we use the default setting
if (!defined('S2_IMG_DIR'))
	define('S2_IMG_DIR', '_pictures');
define('S2_IMG_PATH', S2_ROOT.S2_IMG_DIR);

// Load DB abstraction layer and connect
require S2_ROOT.'_include/dblayer/common_db.php';

// Load cached config
if (file_exists(S2_CACHE_DIR.'cache_config.php'))
	include S2_CACHE_DIR.'cache_config.php';

if (!defined('S2_CONFIG_LOADED'))
{
	if (!defined('S2_CACHE_FUNCTIONS_LOADED'))
		require S2_ROOT.'_include/cache.php';

	s2_generate_config_cache();
	require S2_CACHE_DIR.'cache_config.php';
}

require S2_ROOT.'_lang/'.S2_LANGUAGE.'/common.php';

// Load hooks
if (file_exists(S2_CACHE_DIR.'cache_hooks.php'))
	include S2_CACHE_DIR.'cache_hooks.php';

if (!defined('S2_HOOKS_LOADED'))
{
	if (!defined('S2_CACHE_FUNCTIONS_LOADED'))
		require S2_ROOT.'_include/cache.php';

	generate_hooks_cache();
	require S2_CACHE_DIR.'cache_hooks.php';
}
