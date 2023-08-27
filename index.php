<?php
/**
 * Processing all public pages of the site.
 *
 * @copyright (C) 2009-2014 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license       http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package       S2
 */

use S2\Cms\Pdo\DbLayer;

define('S2_ROOT', './');
require S2_ROOT . '_include/common.php';

($hook = s2_hook('idx_start')) ? eval($hook) : null;

header('X-Powered-By: S2/' . S2_VERSION);

// We create our own request URI with the path removed and only the parts to rewrite included
if (isset($_SERVER['PATH_INFO']) && S2_URL_PREFIX != '')
    $request_uri = $_SERVER['PATH_INFO'];
else {
    $request_uri = substr(urldecode($_SERVER['REQUEST_URI']), strlen(s2_link()));
    if (($delimeter = strpos($request_uri, S2_URL_PREFIX != '' ? '&' : '?')) !== false)
        $request_uri = substr($request_uri, 0, $delimeter);
}

($hook = s2_hook('idx_pre_redirect')) ? eval($hook) : null;

//
// Redirect to the admin page
//
if (str_ends_with($request_uri, '---')) {
    header('Location: ' . S2_PATH . '/_admin/index.php?path=' . urlencode(substr($request_uri, 0, -3)));

    /** @var DbLayer $s2_db */
    $s2_db = \Container::getIfInstantiated(DbLayer::class);
    if ($s2_db !== null) {
        $s2_db->close();
    }
    die;
}

if (!defined('S2_COMMENTS_FUNCTIONS_LOADED'))
    require S2_ROOT . '_include/comments.php';

$router = new AltoRouter();

($hook = s2_hook('idx_new_routes')) ? eval($hook) : null;

$router->addRoutes([
    ['GET', '[/rss.xml:url]', 'Page_RSS'],
    ['GET', '[/sitemap.xml]', 'Page_Sitemap'],

    // Favorite
    [
        'GET', '/' . S2_FAVORITE_URL, function () {
        s2_permanent_redirect('/' . S2_FAVORITE_URL . '/');
    }
    ],
    ['GET', '/' . S2_FAVORITE_URL . '/', 'Page_Favorite'],

    // Tags
    [
        'GET', '/' . S2_TAGS_URL, function () {
        s2_permanent_redirect('/' . S2_TAGS_URL . '/');
    }
    ],
    ['GET', '/' . S2_TAGS_URL . '/', 'Page_Tags'],
    ['GET', '/' . S2_TAGS_URL . '/[:name][/:slash]?', 'Page_Tag'],
]);

// match current request
$match = $router->match($request_uri);

if (!empty($match)) {
    $target = $match['target'];
    if (is_callable($target)) {
        $target();
        $controller = null;
    } else {
        $controller = new $target($match['params']);
    }
} else {
    $controller = ($hook = s2_hook('idx_get_content')) ? eval($hook) : null;
    if (!$controller) {
        $controller = new Page_Common(array('request_uri' => $request_uri));
    }
}

if ($controller instanceof Page_Routable) {
    s2_no_cache();
    $controller->render();
}

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
    if (\extension_loaded('newrelic')) {
        if (is_object($controller)) {
            newrelic_name_transaction(get_class($controller));
        }
        newrelic_end_transaction();
        newrelic_start_transaction(ini_get('newrelic.appname'));
        newrelic_name_transaction('index_background');
    }
    /** @var \S2\Cms\Queue\QueueConsumer $consumer */
    $consumer = Container::get(\S2\Cms\Queue\QueueConsumer::class);
    $startedAt = microtime(true);
    while ($consumer->runQueue() && microtime(true) - $startedAt < 10);
}
