<?php
/**
 * Installation script for S2.
 *
 * @copyright 2009-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */


use Psr\Log\LogLevel;
use S2\Cms\Admin\AdminExtension;
use S2\Cms\CmsExtension;
use S2\Cms\Framework\Application;
use S2\Cms\HttpClient\HttpClient;
use S2\Cms\HttpClient\HttpClientException;
use S2\Cms\Install\InstallExtension;
use S2\Cms\Logger\Logger;
use S2\Cms\Model\ExtensionCache;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;

define('S2_VERSION', '2.0dev');
define('S2_DB_REVISION', 22);
define('MIN_PHP_VERSION', '8.2.0');

define('S2_ROOT', '../');
define('S2_DEBUG', 1);
define('S2_SHOW_QUERIES', 1);

// We need some stuff
require S2_ROOT . '_vendor/autoload.php';

if (file_exists(S2_ROOT . s2_get_config_filename())) {
    error(sprintf(
        'The file \'%s\' already exists which would mean that S2 is already installed. You should go <a href="%s">here</a> instead.',
        s2_get_config_filename(),
        S2_ROOT
    ));
}

// Make sure we are running at least MIN_PHP_VERSION
if (!function_exists('version_compare') || version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
    error('You are running PHP version ' . PHP_VERSION . '. S2 requires at least PHP ' . MIN_PHP_VERSION . ' to run properly. You must upgrade your PHP installation before you can continue.');
}

// Disable error reporting for uninitialized variables
error_reporting(E_ALL);

// Turn off PHP time limit
@set_time_limit(0);

require S2_ROOT . '_include/setup.php';

$errorHandler = Debug::enable();
HtmlErrorRenderer::setTemplate(realpath(S2_ROOT . '_include/views/error.php'));
$errorHandler->setDefaultLogger(new Logger(S2_ROOT . '_cache/install.log', 'install', LogLevel::DEBUG));

//
// Generate output to be used for config.php
//
function generate_config_file(HttpClient $httpClient)
{
    global $db_type, $db_host, $db_name, $db_username, $db_password, $db_prefix, $base_url, $s2_cookie_name;

    foreach (array('', '/?', '/index.php', '/index.php?') as $prefix) {
        $url_prefix = $prefix;
        try {
            $response = $httpClient->fetch($base_url . $url_prefix . '/this/URL/_DoEs_/_NoT_/_eXiSt');
            if ($response->content !== null && str_contains($response->content, '<meta name="Generator" content="S2">')) {
                break;
            }
        } catch (HttpClientException $e) {
            continue;
        }
    }

    $path = preg_replace('#^[^:/]+://[^/]*#', '', $base_url);

    $use_https = false;
    if (str_starts_with($base_url, 'https://')) {
        $use_https = true;
    } else {
        try {
            $response = $httpClient->fetch('https://' . substr($base_url, 7) . $url_prefix . '/this/URL/_DoEs_/_NoT_/_eXiSt');
            if ($response->content !== null && str_contains($response->content, '<meta name="Generator" content="S2">')) {
                $use_https = true;
            }
        } catch (HttpClientException $e) {
            ; // no op
        }
    }

    return '<?php' . "\n\n" . '$db_type = \'' . $db_type . "';\n" .
        '$db_host = \'' . $db_host . "';\n" .
        '$db_name = \'' . addslashes($db_name) . "';\n" .
        '$db_username = \'' . addslashes($db_username) . "';\n" .
        '$db_password = \'' . addslashes($db_password) . "';\n" .
        '$db_prefix = \'' . addslashes($db_prefix) . "';\n" .
        '$p_connect = false;' . "\n\n" .
        'define(\'S2_BASE_URL\', \'' . $base_url . '\');' . "\n" .
        'define(\'S2_PATH\', \'' . $path . '\');' . "\n" .
        'define(\'S2_URL_PREFIX\', \'' . $url_prefix . '\');' . "\n\n" .
        ($use_https ? 'define(\'S2_FORCE_ADMIN_HTTPS\', \'1\');' . "\n\n" : '') .
        '$s2_cookie_name = ' . "'" . $s2_cookie_name . "';\n";
}

function get_preferred_lang($languages)
{
    if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
        return 'English';

    $langs = array();

    // Break up string into pieces (languages and q factors)
    preg_match_all('#([a-z]{1,8}(-[a-z]{1,8}))?\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?#i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);

    if (count($lang_parse[1])) {
        // Create a list like "en" => 0.8
        $langs = array_combine($lang_parse[1], $lang_parse[4]);

        // Set default to 1 for any without q factor
        foreach ($langs as $lang => $val)
            if ($val === '')
                $langs[$lang] = 1;

        // Sort list based on value
        arsort($langs, SORT_NUMERIC);
    }

    foreach ($langs as $lang => $val) {
        list($lang) = explode('-', $lang);
        foreach ($languages as $available_lang)
            if (strtolower(substr($available_lang, 0, 2)) == strtolower($lang))
                return $available_lang;
    }

    return 'English';
}

$emptyApp = new Application();
$emptyApp->addExtension(new CmsExtension());
$emptyApp->addExtension(new AdminExtension());
$emptyApp->addExtension(new InstallExtension());
$emptyApp->boot((static function (): array {
    $result = [
        'root_dir'      => S2_ROOT,
        'cache_dir'     => S2_CACHE_DIR,
        'disable_cache' => false,
        'log_dir'       => defined('S2_LOG_DIR') ? S2_LOG_DIR : S2_CACHE_DIR,
        'base_url'      => defined('S2_BASE_URL') ? S2_BASE_URL : null,
        'base_path'     => defined('S2_PATH') ? S2_PATH : null,
        'debug'         => defined('S2_DEBUG'),
        'debug_view'    => defined('S2_DEBUG_VIEW'),
        'redirect_map'  => [],
    ];

    foreach (['db_type', 'db_host', 'db_name', 'db_username', 'db_password', 'db_prefix', 'p_connect'] as $globalVarName) {
        $result[$globalVarName] = $GLOBALS[$globalVarName] ?? null;
    }

    return $result;
})());


/** @var \S2\Cms\Admin\ResourceProvider $resourceProvider */
$resourceProvider = $emptyApp->container->get(\S2\Cms\Admin\ResourceProvider::class);
$languages        = $resourceProvider->readLanguages();

$language = $_GET['lang'] ?? (isset($_POST['req_language']) ? trim($_POST['req_language']) : get_preferred_lang($languages));
$language = preg_replace('#[\.\\\/]#', '', $language);
if (!file_exists(S2_ROOT . '_lang/' . $language . '/common.php')) {
    error('The language pack you have chosen doesn\'t seem to exist or is corrupt. Please recheck and try again.');
}

/** @var \S2\Cms\Config\InstallationConfigProvider $dynamicConfigProvider */
$dynamicConfigProvider = $emptyApp->container->get(\S2\Cms\Config\DynamicConfigProvider::class);
$dynamicConfigProvider->setCallback(static function (string $paramName) use ($language) {
    return match ($paramName) {
        'S2_LANGUAGE' => $language,
        default => throw new LogicException(sprintf('Parameter "%s" is not available during installation.', $paramName))
    };
});

// Load the language files
/** @var \Symfony\Contracts\Translation\TranslatorInterface $translator */
$translator = $emptyApp->container->get('translator');
require S2_ROOT . '_admin/lang/' . $translator->getLocale() . '/install.php';

if (isset($_POST['generate_config'])) {
    header(sprintf('Content-Type: text/x-delimtext; name="%s"', s2_get_config_filename()));
    header(sprintf("Content-disposition: attachment; filename=%s", s2_get_config_filename()));

    $db_type        = $_POST['db_type'];
    $db_host        = $_POST['db_host'];
    $db_name        = $_POST['db_name'];
    $db_username    = $_POST['db_username'];
    $db_password    = $_POST['db_password'];
    $db_prefix      = $_POST['db_prefix'];
    $base_url       = $_POST['base_url'];
    $s2_cookie_name = $_POST['cookie_name'];

    echo generate_config_file($emptyApp->container->get(HttpClient::class));
    exit;
}

header('X-Powered-By: S2/' . S2_VERSION);
header('Content-Type: text/html; charset=utf-8');

function guessBaseUrl(): string
{
    $result =
        ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://')
        . preg_replace('/:80$/', '', $_SERVER['HTTP_HOST'])
        . substr(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), 0, -6);

    if (str_ends_with($result, '/')) {
        $result = substr($result, 0, -1);
    }

    return $result;
}

function renderInstallForm(array $lang_install, array $languages, string $currentLanguage, array $values, array $validationErrors): void
{
    // Determine available database extensions
    $supportedDatabases = [
        'mysql'  => [
            'title'     => 'MySQL',
            'available' => class_exists(PDO::class) && in_array('mysql', PDO::getAvailableDrivers(), true),
        ],
        'sqlite' => [
            'title'     => 'SQLite',
            'available' => class_exists(PDO::class) && in_array('sqlite', PDO::getAvailableDrivers(), true),
        ],
        'pgsql'  => [
            'title'     => 'PostgreSQL',
            'available' => class_exists(PDO::class) && in_array('pgsql', PDO::getAvailableDrivers(), true),
        ],
    ];

    if (count(array_filter($supportedDatabases, static fn($db) => $db['available'])) === 0) {
        error($lang_install['No database support']);
    }

    // Make an educated guess regarding base_url
    $base_url_guess = guessBaseUrl();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta name="Generator" content="S2 <?php echo S2_VERSION; ?>">
        <title><?php printf($lang_install['Install S2'], S2_VERSION) ?></title>
        <link rel="stylesheet" href="<?php echo S2_ROOT ?>_admin/css/style.css">
    </head>
    <body>

    <h1><?php printf($lang_install['Install S2'], S2_VERSION) ?></h1>

    <?php

    if (count($languages) > 1) {

        ?>
        <form method="get" accept-charset="utf-8" action="install.php">
            <h2><?php echo $lang_install['Part 0'] ?></h2>
            <div class="input select">
                <label for="fld0">
                <span><?php echo $lang_install['Installer language'] ?>
                </span><select id="fld0" name="lang">
                        <?php

                        foreach ($languages as $lang) {
                            echo '<option value="' . $lang . '"' . ($currentLanguage === $lang ? ' selected="selected"' : '') . '>' . $lang . '</option>' . "\n";
                        }

                        ?>
                    </select>
                    <br/>
                    <small><?php echo $lang_install['Choose language help'] ?></small>
                </label>
            </div>
            <div class="button-wrapper"><input type="submit" name="changelang"
                                               value="<?php echo $lang_install['Choose language'] ?>"/></div>
            <input type="hidden" name="form_sent" value="1"/>
        </form>
        <?php

    }

    ?>
    <h2><?php echo $lang_install['Part1'] ?></h2>
    <div class="info-box">
        <p><?php echo $lang_install['Part1 intro'] ?></p>
    </div>
    <?php if (isset($validationErrors['db_error'])): ?>
        <div class="error-box">
            <p><?php echo s2_htmlencode(sprintf($lang_install['Database error'], $validationErrors['db_error'])); ?></p>
        </div>
    <?php endif; ?>
    <?php if (isset($validationErrors['db_is_used'])): ?>
        <div class="error-box">
            <p><?php echo $validationErrors['db_is_used']; ?></p>
            <p><?php echo $lang_install['S2 already installed 2'] ?></p>
            <p><?php echo $lang_install['S2 already installed 3'] ?></p>
            <form method="post" accept-charset="utf-8" action="install.php">
                <input type="hidden" name="generate_config" value="1">
                <input type="hidden" name="db_type" value="<?php echo s2_htmlencode($values['req_db_type']); ?>">
                <input type="hidden" name="db_host" value="<?php echo s2_htmlencode($values['req_db_host']); ?>">
                <input type="hidden" name="db_name" value="<?php echo s2_htmlencode($values['req_db_name']); ?>">
                <input type="hidden" name="db_username" value="<?php echo s2_htmlencode($values['db_username']); ?>">
                <input type="hidden" name="db_password" value="<?php echo s2_htmlencode($values['db_password']); ?>">
                <input type="hidden" name="db_prefix" value="<?php echo s2_htmlencode($values['db_prefix']); ?>">
                <input type="hidden" name="base_url" value="<?php echo s2_htmlencode($values['req_base_url']); ?>">
                <input type="hidden" name="cookie_name" value="<?php echo s2_htmlencode('s2_cookie_' . mt_rand()); ?>">
                <div class="button-wrapper"><input type="submit" value="<?php echo $lang_install['Download config'] ?>">
                </div>
            </form>
        </div>
    <?php endif; ?>
    <form name="install_form" method="post" accept-charset="utf-8" action="install.php">
        <input type="hidden" name="form_sent" value="1"/>
        <div class="input radio required">
            <label for="fld1">
                <span><?php echo $lang_install['Database type'] ?><em>*</em>
                </span><?php

                $selected = false;
                foreach ($supportedDatabases as $dbType => $dbInfo) {
                    $enabled   = $dbInfo['available'];
                    $isChecked = (isset($values['req_db_type']) ? $values['req_db_type'] === $dbType : $enabled && !$selected) ? 'checked' : '';
                    echo '<label ' . ($enabled ? '' : 'class="disabled"') . '><input type="radio" name="req_db_type" ' . ($isChecked) . ' value="' . $dbType . '" ' . ($enabled ? '' : 'disabled="disabled"') . '><span>' . $dbInfo['title'] . ($enabled ? '' : ' ' . $lang_install['Database type N/A']) . '</span></label>' . "<br>\n";
                    if ($enabled) {
                        $selected = true;
                    }
                }

                ?>
            </label>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('input[name="req_db_type"]').forEach(radio => radio.addEventListener('change', function () {
                    const isSqlite = document.forms['install_form'].elements['req_db_type'].value === 'sqlite';

                    document.getElementById('fld2').disabled = isSqlite;
                    document.getElementById('fld4').disabled = isSqlite;
                    document.getElementById('fld5').disabled = isSqlite;
                }));
                Array.from(document.forms['install_form'].elements['req_db_type']).forEach(radio => {
                    radio.dispatchEvent(new Event('change', {bubbles: true}));
                });
            });
        </script>
        <div class="input text required">
            <label for="fld2">
                <span><?php echo $lang_install['Database server'] ?><em>*</em>
                </span><input id="fld2" type="text" name="req_db_host"
                              value="<?php echo s2_htmlencode($values['req_db_host'] ?? 'localhost'); ?>" size="50"
                              maxlength="100"/>
                <?php foreach ($validationErrors['req_db_host'] ?? [] as $error) {
                    echo '<small class="error">' . $error . '</small>';
                } ?>
                <small><?php echo $lang_install['Database server help'] ?></small>
            </label>
        </div>
        <div class="input text required">
            <label for="fld3">
                <span><?php echo $lang_install['Database name'] ?><em>*</em>
                </span><input id="fld3" type="text" name="req_db_name"
                              value="<?php echo s2_htmlencode($values['req_db_name'] ?? ''); ?>" size="35"
                              maxlength="50"/>
                <?php foreach ($validationErrors['req_db_name'] ?? [] as $error) {
                    echo '<small class="error">' . $error . '</small>';
                } ?>
                <small><?php echo $lang_install['Database name help'] ?></small>
            </label>
        </div>
        <div class="input text">
            <label for="fld4">
                <span><?php echo $lang_install['Database username'] ?>
                </span><input id="fld4" type="text" name="db_username"
                              value="<?php echo s2_htmlencode($values['db_username'] ?? ''); ?>" size="35"
                              maxlength="50"/>
                <?php foreach ($validationErrors['db_username'] ?? [] as $error) {
                    echo '<small class="error">' . $error . '</small>';
                } ?>
                <small><?php echo $lang_install['Database username help'] ?></small>
            </label>
        </div>
        <div class="input text">
            <label for="fld5">
                <span><?php echo $lang_install['Database password'] ?>
                </span><input id="fld5" type="password" name="db_password"
                              value="<?php echo s2_htmlencode($values['db_password'] ?? ''); ?>" size="35"/>
                <?php foreach ($validationErrors['db_password'] ?? [] as $error) {
                    echo '<small class="error">' . $error . '</small>';
                } ?>
                <small><?php echo $lang_install['Database password help'] ?></small>
            </label>
        </div>
        <div class="input text">
            <label for="fld6">
                <span><?php echo $lang_install['Table prefix'] ?>
                </span><input id="fld6" type="text" name="db_prefix" size="20" maxlength="30"
                              value="<?php echo s2_htmlencode($values['db_prefix'] ?? ''); ?>">
                <?php foreach ($validationErrors['db_prefix'] ?? [] as $error) {
                    echo '<small class="error">' . $error . '</small>';
                } ?>
                <small><?php echo $lang_install['Table prefix help'] ?></small>
            </label>
        </div>

        <h2><?php echo $lang_install['Part2'] ?></h2>
        <div class="info-box">
            <p><?php echo $lang_install['Part2 intro'] ?></p>
        </div>
        <div class="input text required">
            <label for="fld7">
                <span><?php echo $lang_install['Admin username'] ?><em>*</em>
                </span><input id="fld7" type="text" name="req_username" size="35" maxlength="40"
                              value="<?php echo s2_htmlencode($values['req_username'] ?? 'admin'); ?>"/>
                <?php foreach ($validationErrors['req_username'] ?? [] as $error) {
                    echo '<small class="error">' . $error . '</small>';
                } ?>
            </label>
        </div>
        <div class="input text required">
            <label for="fld8">
                <span><?php echo $lang_install['Admin password'] ?>
                </span><input id="fld8" type="password" name="req_password" size="35" maxlength="200"
                              value="<?php echo s2_htmlencode($values['req_password'] ?? ''); ?>"/>
                <?php foreach ($validationErrors['req_password'] ?? [] as $error) {
                    echo '<small class="error">' . $error . '</small>';
                } ?>
            </label>
        </div>
        <div class="input text">
            <label for="fld10">
                <span><?php echo $lang_install['Admin e-mail'] ?>
                </span><input id="fld10" type="text" name="adm_email" size="50" maxlength="80"
                              value="<?php echo s2_htmlencode($values['adm_email'] ?? ''); ?>"/>
                <?php foreach ($validationErrors['adm_email'] ?? [] as $error) {
                    echo '<small class="error">' . $error . '</small>';
                } ?>
                <small><?php echo $lang_install['E-mail address help'] ?></small>
            </label>
        </div>
        <h2><?php echo $lang_install['Part3'] ?></h2>
        <div class="input text required">
            <label for="fld13">
                <span><?php echo $lang_install['Base URL'] ?><em>*</em>
                </span><input id="fld13" type="text" name="req_base_url" maxlength="100" size="50"
                              value="<?php echo s2_htmlencode($values['req_base_url'] ?? $base_url_guess); ?>">
                <?php foreach ($validationErrors['req_base_url'] ?? [] as $error) {
                    echo '<small class="error">' . $error . '</small>';
                } ?>
                <small><?php echo $lang_install['Base URL help'] ?></small>
            </label>
        </div>
        <?php

        if (count($languages) > 1) {

            ?>
            <div class="input select">
                <label for="fld14">
                <span><?php echo $lang_install['Default language'] ?>
                </span><select id="fld14" name="req_language">
                        <?php

                        foreach ($languages as $lang)
                            echo '<option value="' . $lang . '"' . (($values['req_language'] ?? $currentLanguage) === $lang ? ' selected="selected"' : '') . '>' . $lang . '</option>' . "\n";

                        ?>
                    </select>
                    <br/>
                    <?php foreach ($validationErrors['req_language'] ?? [] as $error) {
                        echo '<small class="error">' . $error . '</small>';
                    } ?>
                    <small><?php echo $lang_install['Default language help'] ?></small>
                </label>
            </div>
            <?php

        } else {

            ?>
            <input type="hidden" name="req_language" value="<?php echo $languages[0]; ?>"/>
            <?php
        }

        ?>
        <div class="button-wrapper">
            <input type="submit" name="start" class="main-button" value="<?php echo $lang_install['Start install'] ?>"/>
        </div>
    </form>

    </body>
    </html>
    <?php

}


if (!isset($_POST['form_sent'])) {
    renderInstallForm($lang_install, $languages, $language, [], []);
    exit;
}

$db_type      = $_POST['req_db_type'];
$db_host      = trim($_POST['req_db_host']);
$db_name      = trim($_POST['req_db_name']);
$db_username  = trim($_POST['db_username']);
$db_password  = trim($_POST['db_password']);
$db_prefix    = trim($_POST['db_prefix']);
$username     = trim($_POST['req_username']);
$password     = trim($_POST['req_password']);
$email        = strtolower(trim($_POST['adm_email']));
$default_lang = preg_replace('#[\.\\\/]#', '', trim($_POST['req_language']));

// Make sure base_url doesn't end with a slash
if (str_ends_with($_POST['req_base_url'], '/')) {
    $base_url = substr($_POST['req_base_url'], 0, -1);
} else {
    $base_url = $_POST['req_base_url'];
}

// Validate form
$validationErrors = [];
if (mb_strlen($db_name) === 0) {
    $validationErrors['req_db_name'][] = $lang_install['Missing database name'];
}

// Validate prefix
if (strlen($db_prefix) > 40) {
    $validationErrors['db_prefix'][] = sprintf($lang_install['Too long table prefix'], $db_prefix);
}

if (strlen($db_prefix) > 0 && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $db_prefix)) {
    $validationErrors['db_prefix'][] = sprintf($lang_install['Invalid table prefix'], $db_prefix);
}

// Check SQLite prefix collision
if ($db_type === 'sqlite' && strtolower($db_prefix) === 'sqlite_') {
    $validationErrors['db_prefix'][] = $lang_install['SQLite prefix collision'];
}


// Validate username
if (mb_strlen($username) < 2) {
    $validationErrors['req_username'][] = $lang_install['Username too short'];
}
if (mb_strlen($username) > 40) {
    $validationErrors['req_username'][] = $lang_install['Username too long'];
}

// Validate password
if (mb_strlen($password) > 100) {
    $validationErrors['req_password'][] = $lang_install['Password too long'];
}

// Validate email
if ($email !== '' && !s2_is_valid_email($email)) {
    $validationErrors['adm_email'][] = $lang_install['Invalid email'];
}

if ($base_url === '') {
    $validationErrors['req_base_url'][] = $lang_install['Missing base url'];
}

if (!file_exists(S2_ROOT . '_lang/' . $default_lang . '/common.php')) {
    $validationErrors['req_language'][] = $lang_install['Invalid language'];
}

if (count($validationErrors) === 0) {
    // Create the database object (and connect/select db)
    $p_connect = false;
    $app       = new Application();
    $app->addExtension(new CmsExtension());
    $app->boot((static function (): array {
        $result = [
            'root_dir'      => S2_ROOT,
            'cache_dir'     => S2_CACHE_DIR,
            'disable_cache' => false,
            'log_dir'       => defined('S2_LOG_DIR') ? S2_LOG_DIR : S2_CACHE_DIR,
            'base_url'      => defined('S2_BASE_URL') ? S2_BASE_URL : null,
            'base_path'     => defined('S2_PATH') ? S2_PATH : null,
            'debug'         => defined('S2_DEBUG'),
            'debug_view'    => defined('S2_DEBUG_VIEW'),
            'redirect_map'  => [],
        ];

        foreach (['db_type', 'db_host', 'db_name', 'db_username', 'db_password', 'db_prefix', 'p_connect'] as $globalVarName) {
            $result[$globalVarName] = $GLOBALS[$globalVarName] ?? null;
        }

        return $result;
    })());
    /** @var DbLayer $s2_db */
    $s2_db = $app->container->get(DbLayer::class);

    try {
        $s2_db->query('SELECT 1;');
    } catch (\Exception $e) {
        $validationErrors['db_error'] = $e->getMessage();
    }
}

if (count($validationErrors) === 0) {
    // Make sure S2 isn't already installed
    $query = array(
        'SELECT' => 'count(id)',
        'FROM'   => 'users'
    );

    try {
        $result = $s2_db->buildAndQuery($query);
        if ($s2_db->fetchRow($result)) {
            $validationErrors['db_is_used'] = sprintf($lang_install['S2 already installed'], $db_prefix, $db_name);
        }
    } catch (DbLayerException|PDOException $e) {

    }
}

if (count($validationErrors) > 0) {
    renderInstallForm($lang_install, $languages, $language, [
        'req_db_type'  => $db_type,
        'req_db_host'  => $db_host,
        'req_db_name'  => $db_name,
        'db_username'  => $db_username,
        'db_password'  => $db_password,
        'db_prefix'    => $db_prefix,
        'req_username' => $username,
        'req_password' => $password,
        'adm_email'    => $email,
        'req_language' => $default_lang,
        'req_base_url' => $base_url,
    ], $validationErrors);
    exit;
}


if ($db_type !== 'mysql') {
    // Skip for MySQL, as it implicitly commits a transaction on DDL queries
    $s2_db->startTransaction();
}

$installer = new \S2\Cms\Model\Installer($s2_db);
$installer->createTables();

$now = time();

// Admin user
$query = array(
    'INSERT' => 'login, password, email, view, view_hidden, hide_comments, edit_comments, create_articles, edit_site, edit_users',
    'INTO'   => 'users',
    'VALUES' => '\'' . $s2_db->escape($username) . '\', \'' . md5($password . 'Life is not so easy :-)') . '\', \'' . $s2_db->escape($email) . '\', 1, 1, 1, 1, 1, 1, 1'
);

$s2_db->buildAndQuery($query);
$admin_uid = $s2_db->insertId();

$installer->insertConfigData($lang_install['Site name'], $email, $default_lang, S2_DB_REVISION);

// Insert some other default data
$installer->insertMainPage($lang_install['Main Page'], $now);
$query = array(
    'INSERT' => 'parent_id, title, create_time, modify_time, published, template, url',
    'INTO'   => 'articles',
    'VALUES' => '1, \'' . $lang_install['Section example'] . '\', ' . $now . ', ' . $now . ', 1, \'site.php\', \'section1\''
);
$s2_db->buildAndQuery($query);

$query = array(
    'INSERT' => 'parent_id, title, create_time, modify_time, published, template, url, pagetext, excerpt, user_id',
    'INTO'   => 'articles',
    'VALUES' => '2, \'' . $lang_install['Page example'] . '\', ' . $now . ', ' . $now . ', 1, \'\', \'page1\', \'' . $s2_db->escape($lang_install['Page text']) . '\', \'' . $s2_db->escape($lang_install['Page text']) . '\', ' . $admin_uid
);

$s2_db->buildAndQuery($query);

if ($db_type !== 'mysql') {
    $s2_db->endTransaction();
}

$s2_db->close();

/** @var ExtensionCache $cache */
$cache = $app->container->get(ExtensionCache::class);
$cache->clear();

$alerts = array();
// Check if the cache directory is writable
if (!is_writable(S2_CACHE_DIR)) {
    $alerts[] = '<li><span>' . $lang_install['No cache write'] . '</span></li>';
}

// Check if default pictures directory is writable
if (!is_writable(S2_ROOT . '_pictures/')) {
    $alerts[] = '<li><span>' . $lang_install['No pictures write'] . '</span></li>';
}

// Check if we disabled uploading pictures because file_uploads was disabled
$uploads = in_array(strtolower(@ini_get('file_uploads')), array('on', 'true', '1')) ? 1 : 0;
if (!$uploads) {
    $alerts[] = '<li><span>' . $lang_install['File upload alert'] . '</span></li>';
}

// Add some random bytes at the end of the cookie name to prevent collisions
$s2_cookie_name = 's2_cookie_' . mt_rand();

/// Generate the config.php file data
$config = generate_config_file($app->container->get(HttpClient::class));

// Attempt to write config.php and serve it up for download if writing fails
$written = false;
if (is_writable(S2_ROOT)) {
    $fh = @fopen(S2_ROOT . s2_get_config_filename(), 'wb');
    if ($fh) {
        fwrite($fh, $config);
        fclose($fh);

        $written = true;
    }
}

?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="Generator" content="S2 <?php echo S2_VERSION; ?>"/>
        <title><?php printf($lang_install['Install S2'], S2_VERSION) ?></title>
        <link rel="stylesheet" type="text/css" href="<?php echo S2_ROOT ?>_admin/css/style.css"/>
    </head>
    <body>

    <h1><?php printf($lang_install['Install S2'], S2_VERSION) ?></h1>
    <p><?php printf($lang_install['Success description'], S2_VERSION) ?></p>
    <p><?php echo $lang_install['Success welcome'] ?></p>
    <h2><?php echo $lang_install['Final instructions'] ?></h2>
    <?php

    if (!empty($alerts)) {

        ?>
        <div class="warning-box">
            <p class="warn"><strong><?php echo $lang_install['Warning'] ?></strong></p>
            <ul>
                <?php echo implode("\n\t\t\t\t", $alerts) . "\n" ?>
            </ul>
        </div>
        <?php

    }

    if (!$written) {

        ?>
        <div class="warning-box">
            <p class="warn"><?php echo $lang_install['No write info 1'] ?></p>
            <p class="warn"><?php printf($lang_install['No write info 2'], '<a href="' . S2_ROOT . '">' . $lang_install['Go to index'] . '</a>') ?></p>
        </div>
        <form method="post" accept-charset="utf-8" action="install.php">
            <input type="hidden" name="generate_config" value="1"/>
            <input type="hidden" name="db_type" value="<?php echo s2_htmlencode($db_type); ?>"/>
            <input type="hidden" name="db_host" value="<?php echo s2_htmlencode($db_host); ?>"/>
            <input type="hidden" name="db_name" value="<?php echo s2_htmlencode($db_name); ?>"/>
            <input type="hidden" name="db_username" value="<?php echo s2_htmlencode($db_username); ?>"/>
            <input type="hidden" name="db_password" value="<?php echo s2_htmlencode($db_password); ?>"/>
            <input type="hidden" name="db_prefix" value="<?php echo s2_htmlencode($db_prefix); ?>"/>
            <input type="hidden" name="base_url" value="<?php echo s2_htmlencode($base_url); ?>"/>
            <input type="hidden" name="cookie_name" value="<?php echo s2_htmlencode($s2_cookie_name); ?>"/>
            <div class="button-wrapper"><input type="submit" value="<?php echo $lang_install['Download config'] ?>"/>
            </div>
        </form>
        <?php

    } else {

        ?>
        <div class="success-box">
            <p class="warn"><?php printf($lang_install['Write info'], '<a href="' . S2_ROOT . '">' . $lang_install['Go to index'] . '</a>') ?></p>
        </div>
        <?php

    }

    ?>

    </body>
    </html>

<?php
