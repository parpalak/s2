<?php
/**
 * Loads common data and performs various functions necessary for the site to work properly.
 *
 * @copyright (C) 2009-2014 Roman Parpalak, based on code (C) 2008-2009 PunBB
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


if (!defined('S2_ROOT'))
	die;

$s2_start = microtime(true);

define('S2_VERSION', '2.0dev');

// Uncomment these lines for debug
//define('S2_DEBUG', 1);
//define('S2_SHOW_QUERIES', 1);

// Attempt to load the configuration file config.php
if (file_exists(S2_ROOT.'config.php'))
	include S2_ROOT.'config.php';

error_reporting(defined('S2_DEBUG') ? E_ALL : E_ALL ^ E_NOTICE);

require S2_ROOT . '_vendor/autoload.php';
require S2_ROOT.'_include/setup.php';

if (!defined('S2_BASE_URL'))
	error('The file \'config.php\' doesn\'t exist or is corrupt.<br />Do you want to <a href="'.preg_replace('#'.(S2_ROOT == '../' ? '/[a-z_\.]*' : '').'/[a-z_]*\.php$#', '/', $_SERVER['SCRIPT_NAME']).'_admin/install.php">install S2</a>?');
if (!defined('S2_URL_PREFIX'))
	define('S2_URL_PREFIX', '');

// If the image directory is not specified, we use the default setting
if (!defined('S2_IMG_DIR'))
	define('S2_IMG_DIR', '_pictures');
define('S2_IMG_PATH', S2_ROOT.S2_IMG_DIR);

if (!defined('S2_ALLOWED_EXTENSIONS'))
	define('S2_ALLOWED_EXTENSIONS', 'gif bmp jpg jpeg png ico svg mp3 wav avi flv mpg mpeg mkv zip 7z doc pdf');

// Load cached config
if (file_exists(S2_CACHE_DIR.'cache_config.php'))
	include S2_CACHE_DIR.'cache_config.php';

if (!defined('S2_CONFIG_LOADED'))
	S2Cache::generate_config(true);

define('S2_DB_LAST_REVISION', 14);
if (S2_DB_REVISION < S2_DB_LAST_REVISION)
	include S2_ROOT.'_admin/db_update.php';
