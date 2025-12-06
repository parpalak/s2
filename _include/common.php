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
use S2\Cms\Config\StaticConfigLoader;
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

$staticConfigLoader = new StaticConfigLoader();
$s2StaticConfig     = $staticConfigLoader->load(__DIR__ . '/../' . s2_get_config_filename());

$debugEnabled = defined('S2_DEBUG') || !empty($s2StaticConfig['options']['debug']);
error_reporting($debugEnabled ? E_ALL : E_ALL ^ E_NOTICE);

require __DIR__ . '/../_include/setup.php';

if ($debugEnabled) {
    $errorHandler = Debug::enable();
} else {
    $errorHandler = ErrorHandler::register();
}
HtmlErrorRenderer::setTemplate(__DIR__ . '/views/error.php');

$s2BaseStaticParameters = s2_build_base_static_parameters($s2StaticConfig);

function s2_build_base_static_parameters(array $config): array
{
    $rootDir = dirname(__DIR__) . '/';

    $cacheDir = isset($config['files']['cache_dir'])
        ? rtrim($config['files']['cache_dir'], '/') . '/'
        : s2_get_default_cache_dir();

    $logDir = isset($config['files']['log_dir']) ? rtrim($config['files']['log_dir'], '/') . '/' : $cacheDir;

    $imageDirRelative = '';
    if (isset($config['files']['image_dir']) && is_string($config['files']['image_dir'])) {
        $imageDirRelative = trim($config['files']['image_dir'], '/');
    }
    if ($imageDirRelative === '') {
        $imageDirRelative = StaticConfigLoader::DEFAULT_IMAGE_DIR;
    }
    $imageDir = $rootDir . $imageDirRelative;

    $basePath = $config['http']['base_path'] ?? null;
    $imagePath = null;
    if ($basePath !== null) {
        $imagePath = $basePath . '/' . $imageDirRelative;
    }

    $baseUrl   = $config['http']['base_url'] ?? null;
    $urlPrefix = $config['http']['url_prefix'] ?? '';

    $debug           = !empty($config['options']['debug']);
    $debugView       = !empty($config['options']['debug_view']);
    $showQueries     = !empty($config['options']['show_queries']);
    $disableCache    = !empty($config['options']['disable_cache']);
    $forceAdminHttps = !empty($config['options']['force_admin_https']);
    $canonicalUrl    = $config['options']['canonical_url'] ?? null;

    return [
        'root_dir'           => $rootDir,
        'cache_dir'          => $cacheDir,
        'allowed_extensions' => $config['files']['allowed_extensions'] ?? StaticConfigLoader::DEFAULT_ALLOWED_EXTENSIONS,
        'image_dir'          => $imageDir, // no trailing '/' for Filesystem component
        'image_path'         => $imagePath,
        'disable_cache'      => $disableCache,
        'log_dir'            => $logDir,

        // full prefix for absolute web URLs, i.e. main page URL supposed to be BASE_URL . URL_PREFIX . '/'
        'base_url'           => $baseUrl,

        // path prefix for the web URL, i.e. main page URL supposed to be 'http://example.com' . BASE_PATH . URL_PREFIX . '/'
        'base_path'          => $basePath,

        // one of '', '/?', '/index.php', '/index.php?'
        'url_prefix'         => $urlPrefix,
        'debug'              => $debug,
        'debug_view'         => $debugView,
        'show_queries'       => $showQueries,
        'force_admin_https'  => $forceAdminHttps,
        'canonical_url'      => $canonicalUrl,
        'version'            => S2_VERSION,
        'redirect_map'       => $config['redirects'] ?? [],
        'cookie_name'        => $config['cookies']['name'] ?? StaticConfigLoader::DEFAULT_COOKIE_NAME,
        'db_type'            => $config['database']['type'] ?? null,
        'db_host'            => $config['database']['host'] ?? null,
        'db_name'            => $config['database']['name'] ?? null,
        'db_username'        => $config['database']['user'] ?? null,
        'db_password'        => $config['database']['password'] ?? null,
        'db_prefix'          => $config['database']['prefix'] ?? null,
        'p_connect'          => $config['database']['p_connect'] ?? false,
    ];
}

function collectParameters(): array
{
    global $s2BootTimestamp, $s2BaseStaticParameters;

    $result                   = $s2BaseStaticParameters;
    $result['boot_timestamp'] = $s2BootTimestamp;

    return $result;
}

function s2_get_static_parameter(string $name): mixed
{
    global $s2BaseStaticParameters;

    return $s2BaseStaticParameters[$name] ?? null;
}

$app = new Application();
$app->addExtension(new CmsExtension());
if (defined('S2_ADMIN_MODE')) {
    $app->addExtension(new AdminExtension());
}

$enabledExtensions = null;
$cacheDir          = $s2BaseStaticParameters['cache_dir'];
$disableCache      = $s2BaseStaticParameters['disable_cache'];
if (!$disableCache && file_exists($cacheDir . ExtensionCache::CACHE_ENABLED_EXTENSIONS_FILENAME)) {
    $enabledExtensions = include $cacheDir . ExtensionCache::CACHE_ENABLED_EXTENSIONS_FILENAME;
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
    if (!$disableCache) {
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
    $loginTimeoutSeconds = $dynamicConfigProvider->getIntProxy('S2_LOGIN_TIMEOUT')->get() * 60;
    ini_set('session.cookie_lifetime', $loginTimeoutSeconds);
    ini_set('session.gc_maxlifetime', $loginTimeoutSeconds);
    ini_set('session.cookie_httponly', true);
}

if ($dynamicConfigProvider->getIntProxy('S2_DB_REVISION')->get() < 24) {
    /** @var MigrationManager $migrationManager */
    $migrationManager = $app->container->get(MigrationManager::class);
    $migrationManager->migrate($dynamicConfigProvider->getIntProxy('S2_DB_REVISION')->get(), 24);

    $dynamicConfigProvider->regenerate();
}
