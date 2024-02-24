<?php
/**
 * Processing all public pages of the site.
 *
 * @copyright 2009-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

use S2\Cms\Controller\NotFoundController;
use S2\Cms\Framework\Exception\NotFoundException;
use S2\Cms\Pdo\DbLayer;
use Symfony\Component\HttpFoundation\Request;

define('S2_ROOT', './');
require S2_ROOT . '_include/common.php';

($hook = s2_hook('idx_start')) ? eval($hook) : null;

header('X-Powered-By: S2/' . S2_VERSION);

// We create our own request URI with the path removed and only the parts to rewrite included
if (isset($_SERVER['PATH_INFO']) && S2_URL_PREFIX != '')
    $request_uri = $_SERVER['PATH_INFO'];
else {
    $request_uri = substr(urldecode($_SERVER['REQUEST_URI']), strlen(s2_link()));
    if (!str_starts_with($request_uri, '/')) {
        // Fix for usual URLS (e.g. '/?search=1&q=text' in case of prefix === '/?')
        $request_uri = '/';
    } elseif (($delimiter = strpos($request_uri, S2_URL_PREFIX != '' ? '&' : '?')) !== false) {
        $request_uri = substr($request_uri, 0, $delimiter);
    }
    // Hack for symfony router in case of /? and /index.php? prefix.
    $_SERVER['REQUEST_URI'] = $request_uri;
}

($hook = s2_hook('idx_pre_redirect')) ? eval($hook) : null;

//
// Redirect to the admin page
//
if (str_ends_with($request_uri, '---')) {
    header('Location: ' . S2_PATH . '/_admin/index.php?path=' . urlencode(substr($request_uri, 0, -3)));

    /** @var DbLayer $s2_db */
    $s2_db = \Container::getIfInstantiated(DbLayer::class);
    $s2_db?->close();
    die;
}

if (!defined('S2_COMMENTS_FUNCTIONS_LOADED')) {
    require S2_ROOT . '_include/comments.php';
}

$request = Request::createFromGlobals();
$attributes = $app->matchRequest($request);

$controllerClass = $attributes['_controller'];
$controller      = new $controllerClass($attributes);

if ($controller instanceof Page_Routable) {
    s2_no_cache(); // TODO пожоже, это внутри if для исключения отправки заголовков в RSS. Надо понять, нужно ли это вообще. Типа, чтобы браузеры не кешировали странички без комментов?

    try {
        $controller->render($request);
        if (\extension_loaded('newrelic')) {
            newrelic_name_transaction($controllerClass);
        }
    } catch (NotFoundException $e) {
        /** @var NotFoundController $errorController */
        $errorController = $app->container->get(NotFoundController::class);
        $response = $errorController->handle($request);
        $response->prepare($request);
        $response->send(false);

        if (\extension_loaded('newrelic')) {
            newrelic_name_transaction($controllerClass . '_' . $response->getStatusCode());
        }
    }
}

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
    if (\extension_loaded('newrelic')) {
        newrelic_end_transaction();
        newrelic_start_transaction(ini_get('newrelic.appname'));
        newrelic_name_transaction('index_background');
    }
    /** @var \S2\Cms\Queue\QueueConsumer $consumer */
    $consumer = $app->container->get(\S2\Cms\Queue\QueueConsumer::class);
    $startedAt = microtime(true);
    while ($consumer->runQueue() && microtime(true) - $startedAt < 10);
}
