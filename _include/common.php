<?php
/**
 * Loads common data and performs various functions necessary for the site to work properly.
 *
 * @copyright 2009-2024
 * @license MIT
 * @package S2
 */

use Psr\Log\LoggerInterface;
use S2\Cms\Application;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;

if (!defined('S2_ROOT'))
	die;

$s2_start = microtime(true);

define('S2_VERSION', '2.0dev');

// Uncomment these lines for debug
//define('S2_DEBUG', 1);
//define('S2_SHOW_QUERIES', 1);

require S2_ROOT . '_vendor/autoload.php';
require S2_ROOT . '_include/functions.php';

// Attempt to load the configuration file config.php
if (file_exists(S2_ROOT . s2_get_config_filename())) {
    include S2_ROOT . s2_get_config_filename();
}

error_reporting(defined('S2_DEBUG') ? E_ALL : E_ALL ^ E_NOTICE);

require S2_ROOT . '_include/setup.php';

if (defined('S2_DEBUG')) {
    $errorHandler = Debug::enable();
} else {
    $errorHandler = ErrorHandler::register();
}
HtmlErrorRenderer::setTemplate(realpath(S2_ROOT.'_include/views/error.php'));

$app = new Application();
$app->boot();
try {
    $app->container->getParameter('base_url');
} catch (\S2\Cms\Framework\Exception\ParameterNotFoundException $e) {
    // S2 is not installed
    error(sprintf(
        'Cannot read parameters from configuration file "%s".<br />Do you want to <a href="%s_admin/install.php">install S2</a>?',
        s2_get_config_filename(),
        preg_replace('#' . (S2_ROOT == '../' ? '/[a-z_\.]*' : '') . '/[a-z_]*\.php$#', '/', $_SERVER['SCRIPT_NAME'])
    ));
}
$errorHandler->setDefaultLogger($app->container->get(LoggerInterface::class));

if (!defined('S2_URL_PREFIX')) {
    define('S2_URL_PREFIX', '');
}

// If the image directory is not specified, we use the default setting
if (!defined('S2_IMG_DIR')) {
    define('S2_IMG_DIR', '_pictures');
}
define('S2_IMG_PATH', S2_ROOT.S2_IMG_DIR);

if (!defined('S2_ALLOWED_EXTENSIONS')) {
    define('S2_ALLOWED_EXTENSIONS', 'gif bmp jpg jpeg png ico svg mp3 wav avi flv mpg mpeg mkv zip 7z doc pdf');
}

// Load cached config
if (file_exists(S2_CACHE_DIR.'cache_config.php')) {
    include S2_CACHE_DIR . 'cache_config.php';
}

if (!defined('S2_CONFIG_LOADED')) {
    $app->container->get(\S2\Cms\Config\DynamicConfigProvider::class)->regenerate();
    include S2_CACHE_DIR.'cache_config.php';
}

define('S2_DB_LAST_REVISION', 16);
if (S2_DB_REVISION < S2_DB_LAST_REVISION) {
    include S2_ROOT . '_admin/db_update.php';
}
