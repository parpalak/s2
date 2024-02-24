<?php
/**
 * Hook idx_new_routes
 *
 * @copyright 2023-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 *
 * @var \Symfony\Component\Routing\RouteCollection $routes
 */

use s2_extensions\s2_search\Page;
use Symfony\Component\Routing\Route;


if (!defined('S2_ROOT')) {
    die;
}

$routes->add('search', new Route('/search', ['_controller' => Page::class]));
// Hack for alternative URL schemes
$routes->add('search2', new Route('/', ['_controller' => Page::class], condition: "request.query.get('search') !== null"));
