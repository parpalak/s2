<?php
/**
 * Processing all public pages of the site.
 *
 * @copyright 2009-2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

use S2\Cms\Config\DynamicConfigProvider;
use Symfony\Component\HttpFoundation\Request;

define('S2_ROOT', './');
require S2_ROOT . '_include/common.php';

header('X-Powered-By: S2/' . S2_VERSION);

// We create our own request URI with the path removed and only the parts to rewrite included
if (isset($_SERVER['PATH_INFO']) && S2_URL_PREFIX != '')
    $request_uri = $_SERVER['PATH_INFO'];
else {
    $request_uri = substr(($_SERVER['REQUEST_URI']), strlen(S2_URL_PREFIX));
    if (!str_starts_with($request_uri, '/')) {
        // Fix for usual URLS (e.g. '/?search=1&q=text' in case of prefix === '/?')
        $request_uri = '/';
    } elseif (($delimiter = strpos($request_uri, S2_URL_PREFIX != '' ? '&' : '?')) !== false) {
        $request_uri = substr($request_uri, 0, $delimiter);
    }
    // Hack for symfony router in case of /? and /index.php? prefix.
    $_SERVER['REQUEST_URI'] = $request_uri;
}

//
// Redirect to the admin page
//
if (str_ends_with($request_uri, '---')) {
    header('Location: ' . S2_PATH . '/_admin/index.php?path=' . urlencode(substr($request_uri, 0, -3)));
    die;
}

$request  = Request::createFromGlobals();
$response = $app->handle($request);

// Disable cache since all the pages are generated dynamically. We only use conditional GET.
$response->headers->set('Pragma', 'no-cache');
$response->setExpires(new DateTimeImmutable('-1 day'));
$response->isNotModified($request);

$response->prepare($request);

if ($response->isInformational() || $response->isEmpty() || $response->getContent() === false || $response->getContent() === '') {
    $response->send(false);
} else {
    // Custom response sending to set Content-Length properly and to enable compression
    ob_start();

    $useCompression = $app->container->get(DynamicConfigProvider::class)->get('S2_COMPRESS');
    if ($useCompression) {
        ob_start('ob_gzhandler');
    }

    $response->sendContent();

    if ($useCompression) {
        ob_end_flush();
    }

    $response->headers->set('Content-Length', ob_get_length());
    $response->sendHeaders();

    ob_end_flush();
}

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
    if (\extension_loaded('newrelic')) {
        newrelic_end_transaction();
        newrelic_start_transaction(ini_get('newrelic.appname'));
        newrelic_name_transaction('index_background');
    }
    /** @var \S2\Cms\Queue\QueueConsumer $consumer */
    $consumer  = $app->container->get(\S2\Cms\Queue\QueueConsumer::class);
    $startedAt = microtime(true);
    while ($consumer->runQueue() && microtime(true) - $startedAt < 10) ;
}
