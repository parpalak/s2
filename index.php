<?php
/**
 * Processing all public pages of the site.
 *
 * @copyright (C) 2009-2024 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license       http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package       S2
 */

use S2\Cms\Pdo\DbLayer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

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

if (!defined('S2_COMMENTS_FUNCTIONS_LOADED'))
    require S2_ROOT . '_include/comments.php';

$routes = new RouteCollection();

($hook = s2_hook('idx_new_routes')) ? eval($hook) : null;

$routes->add('rss', new Route('/rss.xml',  ['_controller' => Page_RSS::class]));
$routes->add('sitemap', new Route('/sitemap.xml',  ['_controller' => Page_Sitemap::class]));

$routes->add('favorite_', new Route('/'.S2_FAVORITE_URL,  ['_controller' => static function () {
    s2_permanent_redirect('/' . S2_FAVORITE_URL . '/');
}]));
$routes->add('favorite', new Route('/'.S2_FAVORITE_URL.'/',  ['_controller' => Page_Favorite::class]));

$routes->add('tags_', new Route('/'.S2_TAGS_URL,  ['_controller' => static function () {
    s2_permanent_redirect('/' . S2_TAGS_URL . '/');
}]));
$routes->add('tags', new Route('/'.S2_TAGS_URL.'/',  ['_controller' => Page_Tags::class]));
$routes->add('tag', new Route('/'.S2_TAGS_URL.'/{name}{slash</?>}',  ['_controller' => Page_Tag::class]));
$routes->add('common', new Route('/{path<.*>}', ['_controller' => Page_Common::class, 'request_uri' => $request_uri]));


$request = Request::createFromGlobals();
$context = new RequestContext();
$context->fromRequest($request);

$matcher = new UrlMatcher($routes, $context);

$attributes = $matcher->matchRequest($request);
$request->attributes->add($attributes);

$target = $attributes['_controller'];
if (is_callable($target)) {
    $target();
    $controller = null;
} else {
    $controller = new $target($attributes);
}

if ($controller instanceof Page_Routable) {
    s2_no_cache(); // TODO пожоже, это внутри if для исключения отправки заголовков в RSS. Надо понять, нужно ли это вообще. Типа, чтобы браузеры не кешировали странички без комментов?

    try {
        $controller->render($request);
    } catch (\S2\Cms\Framework\Exception\NotFoundException $e) {
        // TODO checkRedirect
        $controller = new \S2\Cms\Controller\NotFoundController(new \S2\Cms\Template\HtmlTemplateProvider());
        $response = $controller->handle($request);
        $response->prepare($request);
        $response->send(false);

        if (\extension_loaded('newrelic')) {
            newrelic_name_transaction(get_class($this) . '_not_found');
        }
    }
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
