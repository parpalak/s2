<?php
/**
 * Loads common data and performs various functions necessary for the site to work properly.
 *
 * @copyright 2009-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

use Psr\Log\LoggerInterface;
use S2\Cms\Admin\AdminExtension;
use S2\Cms\CmsExtension;
use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Framework\Application;
use S2\Cms\Framework\Exception\ParameterNotFoundException;
use S2\Cms\Model\ExtensionCache;
use S2\Cms\Model\MigrationManager;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;

$s2BootTimestamp = microtime(true);

define('S2_VERSION', '2.0dev');

// Uncomment these lines for debug
//define('S2_DEBUG', 1);
//define('S2_SHOW_QUERIES', 1);

require __DIR__ . '/../_vendor/autoload.php';

// Attempt to load the configuration file config.php
if (file_exists(__DIR__ . '/../' . s2_get_config_filename())) {
    include __DIR__ . '/../' . s2_get_config_filename();
}

error_reporting(defined('S2_DEBUG') ? E_ALL : E_ALL ^ E_NOTICE);

require __DIR__ . '/../_include/setup.php';

if (defined('S2_DEBUG')) {
    $errorHandler = Debug::enable();
} else {
    $errorHandler = ErrorHandler::register();
}
HtmlErrorRenderer::setTemplate(__DIR__ . '/views/error.php');

if (!defined('S2_URL_PREFIX')) {
    define('S2_URL_PREFIX', '');
}

// If the image directory is not specified, we use the default setting
if (!defined('S2_IMG_DIR')) {
    define('S2_IMG_DIR', '_pictures');
}

if (!defined('S2_ALLOWED_EXTENSIONS')) {
    define('S2_ALLOWED_EXTENSIONS', 'gif bmp jpg jpeg png ico svg mp3 wav ogg flac mp4 avi flv mpg mpeg mkv zip 7z rar doc docx ppt pptx odt odt odp ods xlsx xls pdf txt rtf csv');
}

function collectParameters(): array
{
    global $s2BootTimestamp;
    $result = [
        'boot_timestamp'     => $s2BootTimestamp,
        'root_dir'           => dirname(__DIR__) . '/',
        'cache_dir'          => S2_CACHE_DIR,
        'allowed_extensions' => S2_ALLOWED_EXTENSIONS,
        'image_dir'          => dirname(__DIR__) . '/' . S2_IMG_DIR, // filesystem; no trailing slash in contrast to root_dir and cache_dir
        'image_path'         => defined('S2_PATH') ? S2_PATH . '/' . S2_IMG_DIR : null, // web URL prefix
        'disable_cache'      => defined('S2_DISABLE_CACHE'),
        'log_dir'            => defined('S2_LOG_DIR') ? S2_LOG_DIR : S2_CACHE_DIR,

        // full prefix for absolute web URLs, i.e. main page URL supposed to be S2_BASE_URL . S2_URL_PREFIX '/'
        'base_url'           => defined('S2_BASE_URL') ? S2_BASE_URL : null,

        // path prefix for the web URL, i.e. main page URL supposed to be 'http://example.com' . S2_PATH . S2_URL_PREFIX . '/'
        'base_path'          => defined('S2_PATH') ? S2_PATH : null,

        // one of '', '/?', '/index.php', '/index.php?'
        'url_prefix'         => defined('S2_URL_PREFIX') ? S2_URL_PREFIX : null,

        'debug'             => defined('S2_DEBUG'),
        'debug_view'        => defined('S2_DEBUG_VIEW'),
        'show_queries'      => defined('S2_SHOW_QUERIES'),
        'force_admin_https' => defined('S2_FORCE_ADMIN_HTTPS'),
        'version'           => S2_VERSION,
        'redirect_map'      => $GLOBALS['s2_redirect'] ?? [],
        'cookie_name'       => $GLOBALS['s2_cookie_name'] ?? 's2_cookie_6094033457',
    ];

    foreach (['db_type', 'db_host', 'db_name', 'db_username', 'db_password', 'db_prefix', 'p_connect'] as $globalVarName) {
        $result[$globalVarName] = $GLOBALS[$globalVarName] ?? null;
    }

    return $result;
}

$app = new Application();
$app->addExtension(new CmsExtension());
if (defined('S2_ADMIN_MODE')) {
    $app->addExtension(new AdminExtension());
}

$enabledExtensions = null;
if (!defined('S2_DISABLE_CACHE') && file_exists(S2_CACHE_DIR . ExtensionCache::CACHE_ENABLED_EXTENSIONS_FILENAME)) {
    $enabledExtensions = include S2_CACHE_DIR . ExtensionCache::CACHE_ENABLED_EXTENSIONS_FILENAME;
}

try {
    if (!is_array($enabledExtensions)) {
        $app->boot(collectParameters());
        /** @var ExtensionCache $appCache */
        $appCache          = $app->container->get(ExtensionCache::class);
        $enabledExtensions = $appCache->generateEnabledExtensionClassNames();
    }
    foreach ($enabledExtensions['cms'] as $extension) {
        $app->addExtension(new $extension());
    }
    if (defined('S2_ADMIN_MODE')) {
        foreach ($enabledExtensions['admin'] as $extension) {
            $app->addExtension(new $extension());
        }
    }

    $app->boot(collectParameters());
    /** @var ExtensionCache $appCache */
    $appCache = $app->container->get(ExtensionCache::class);
    if (!defined('S2_DISABLE_CACHE')) {
        $app->setCachedRoutesFilename($appCache->getCachedRoutesFilename());
    }

    $app->container->getParameter('base_url');
} catch (ParameterNotFoundException $e) {
    // S2 is not installed
    $configFilename   = s2_get_config_filename();
    $installationPath = substr(
            dirname(__DIR__),
            str_ends_with($_SERVER['SCRIPT_FILENAME'], $_SERVER['SCRIPT_NAME'])
                ? strlen($_SERVER['SCRIPT_FILENAME']) - strlen($_SERVER['SCRIPT_NAME'])
                : strlen($_SERVER['DOCUMENT_ROOT'] ?? '')
        ) . '/_admin/install.php';
    require 'installation_required.php';

    exit;
}
$errorHandler->setDefaultLogger($app->container->get(LoggerInterface::class));

/** @var DynamicConfigProvider $dynamicConfigProvider */
$dynamicConfigProvider = $app->container->get(DynamicConfigProvider::class);

if (defined('S2_ADMIN_MODE') && session_status() !== PHP_SESSION_ACTIVE) {
    $loginTimeoutSeconds = $dynamicConfigProvider->get('S2_LOGIN_TIMEOUT') * 60;
    ini_set('session.cookie_lifetime', $loginTimeoutSeconds);
    ini_set('session.gc_maxlifetime', $loginTimeoutSeconds);
    ini_set('session.cookie_httponly', true);
}

if ($dynamicConfigProvider->get('S2_DB_REVISION') < 23) {
    /** @var MigrationManager $migrationManager */
    $migrationManager = $app->container->get(MigrationManager::class);
    $migrationManager->migrate((int)$dynamicConfigProvider->get('S2_DB_REVISION'), 23);

    $dynamicConfigProvider->regenerate();
}
