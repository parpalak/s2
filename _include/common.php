<?php
/**
 * Loads common data and performs various functions necessary for the site to work properly.
 *
 * @copyright (C) 2009-2011 Roman Parpalak, based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

require S2_ROOT.'_include/functions.php';
require S2_ROOT.'_include/utf8/utf8.php';

define('S2_VERSION', '1.0a6');

// Uncomment these lines for debug
//define('S2_DEBUG', 1);
//define('S2_SHOW_QUERIES', 1);

// Reverse the effect of register_globals
s2_unregister_globals();

// Attempt to load the configuration file config.php
if (file_exists(S2_ROOT.'config.php'))
	include S2_ROOT.'config.php';

if (!defined('S2_BASE_URL'))
	error('The file \'config.php\' doesn\'t exist or is corrupt.<br />Do you want to <a href="'.preg_replace('#'.(S2_ROOT == '../' ? '/[a-z_\.]*' : '').'/[a-z_]*\.php$#', '/', $_SERVER['SCRIPT_NAME']).'_admin/install.php">install S2</a>?');
if (!defined('S2_URL_PREFIX'))
	define('S2_URL_PREFIX', '');

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

if (!defined('S2_ALLOWED_EXTENSIONS'))
	define('S2_ALLOWED_EXTENSIONS', 'gif bmp jpg jpeg png ico svg mp3 wav avi flv mpg mpeg mkv zip 7z doc pdf');

// Load the appropriate DB layer class
if ($db_type == 'mysql' || $db_type == 'mysql_innodb' ||
	$db_type == 'mysqli' || $db_type == 'mysqli_innodb' ||
	$db_type == 'pgsql' || $db_type == 'pdo_sqlite')
	require S2_ROOT.'_include/dblayer/'.$db_type.'.php';
else
	error('\''.$db_type.'\' is not a valid database type. Please check settings in config.php.', __FILE__, __LINE__);

// Create the database adapter object (and open/connect to/select db)
$s2_db = new DBLayer($db_host, $db_username, $db_password, $db_name, $db_prefix, $p_connect);

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

define('S2_DB_LAST_REVISION', 4);
if (S2_DB_REVISION < S2_DB_LAST_REVISION)
	include S2_ROOT.'_admin/db_update.php';

require S2_ROOT.'_lang/'.S2_LANGUAGE.'/common.php';

// Load hooks
if (file_exists(S2_CACHE_DIR.'cache_hooks.php'))
	include S2_CACHE_DIR.'cache_hooks.php';

if (!defined('S2_HOOKS_LOADED'))
{
	if (!defined('S2_CACHE_FUNCTIONS_LOADED'))
		require S2_ROOT.'_include/cache.php';

	s2_generate_hooks_cache();
	require S2_CACHE_DIR.'cache_hooks.php';
}
