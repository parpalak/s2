<?php
/**
 * Loads common data and performs various functions necessary for the site to work properly.
 *
 * @copyright 2009-2024
 * @license MIT
 * @package S2
 */

use Psr\Log\LoggerInterface;
use S2\Cms\CmsExtension;
use S2\Cms\Framework\Application;
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
HtmlErrorRenderer::setTemplate(realpath(S2_ROOT . '_include/views/error.php'));

function collectParameters(): array
{
    $result = [
        'root_dir'     => S2_ROOT,
        'cache_dir'    => S2_CACHE_DIR,
        'log_dir'      => defined('S2_LOG_DIR') ? S2_LOG_DIR : S2_CACHE_DIR,
        'base_url'     => defined('S2_BASE_URL') ? S2_BASE_URL : null,
        'debug'        => defined('S2_DEBUG'),
        'debug_view'   => defined('S2_DEBUG_VIEW'),
        'show_queries' => defined('S2_SHOW_QUERIES'),
        'redirect_map' => $GLOBALS['s2_redirect'] ?? [],
    ];

    foreach (['db_type', 'db_host', 'db_name', 'db_username', 'db_password', 'db_prefix', 'p_connect'] as $globalVarName) {
        $result[$globalVarName] = $GLOBALS[$globalVarName] ?? null;
    }

    return $result;
}

$app = new Application();
$app->addExtension(new CmsExtension());

if (!defined('S2_DISABLE_CACHE')) {
    $app->setCachedRoutesFilename(S2Cache::CACHE_ROUTES_FILENAME);
}

$enabledExtensions = null;
if (!defined('S2_DISABLE_CACHE') && file_exists(S2Cache::CACHE_ENABLED_EXTENSIONS_FILENAME)) {
    $enabledExtensions = include S2Cache::CACHE_ENABLED_EXTENSIONS_FILENAME;
}

try {
    if (!is_array($enabledExtensions)) {
        $app->boot(collectParameters());
        \Container::setContainer($app->container);
        $enabledExtensions = S2Cache::generateEnabledExtensionClassNames($app->container->get(\S2\Cms\Pdo\DbLayer::class));
    }
    foreach ($enabledExtensions as $extension) {
        $app->addExtension(new $extension());
    }

    $app->boot(collectParameters());
    \Container::setContainer($app->container);
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
define('S2_IMG_PATH', S2_ROOT . S2_IMG_DIR);

if (!defined('S2_ALLOWED_EXTENSIONS')) {
    define('S2_ALLOWED_EXTENSIONS', 'gif bmp jpg jpeg png ico svg mp3 wav avi flv mpg mpeg mkv zip 7z doc pdf');
}

// Load cached config
if (file_exists(S2_CACHE_DIR . 'cache_config.php')) {
    include S2_CACHE_DIR . 'cache_config.php';
}

if (!defined('S2_CONFIG_LOADED')) {
    $app->container->get(\S2\Cms\Config\DynamicConfigProvider::class)->regenerate();
    include S2_CACHE_DIR . 'cache_config.php';
}

define('S2_DB_LAST_REVISION', 17);
if (S2_DB_REVISION < S2_DB_LAST_REVISION) {
    include S2_ROOT . '_admin/db_update.php';
}
