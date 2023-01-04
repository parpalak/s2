<?php
/**
 * Processing all public pages of the site.
 *
 * @copyright (C) 2009-2014 Roman Parpalak, partially based on code (C) 2008-2009 PunBB
 * @license       http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package       S2
 */

try {
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
    if (substr($request_uri, -3) == '---') {
        header('Location: ' . S2_PATH . '/_admin/index.php?path=' . urlencode(substr($request_uri, 0, -3)));

        $s2_db->close();
        die;
    }

    if (!defined('S2_COMMENTS_FUNCTIONS_LOADED'))
        require S2_ROOT . '_include/comments.php';

    $router = new AltoRouter([
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

    ($hook = s2_hook('idx_new_routes')) ? eval($hook) : null;

    // match current request
    $match = $router->match($request_uri);

    if (!empty($match)) {
        $target = $match['target'];
        if (is_callable($target))
            $target();
        else
            $controller = new $target($match['params']);
    } else {
        $controller = ($hook = s2_hook('idx_get_content')) ? eval($hook) : null;
        if (!$controller)
            $controller = new Page_Common(array('request_uri' => $request_uri));
    }

    if ($controller instanceof Page_Routable) {
        s2_no_cache();
        $controller->render();
    }
} catch (Exception $e) {
    error($e);
}
