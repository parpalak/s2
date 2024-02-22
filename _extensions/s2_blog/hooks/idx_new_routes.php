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

use s2_extensions\s2_blog\Page_Post;
use s2_extensions\s2_blog\Page_Day;
use s2_extensions\s2_blog\Page_Month;
use s2_extensions\s2_blog\Page_Year;
use s2_extensions\s2_blog\Page_Tag;
use s2_extensions\s2_blog\Page_Tags;
use s2_extensions\s2_blog\Page_Favorite;
use s2_extensions\s2_blog\Page_Sitemap;
use s2_extensions\s2_blog\Page_RSS;
use s2_extensions\s2_blog\Page_Main;
use Symfony\Component\Routing\Route;


if (!defined('S2_ROOT')) {
    die;
}

$routes->add('blog_main', new Route(S2_BLOG_URL.'{slash</?>}', ['_controller' => Page_Main::class, 'page' => 0]));
$routes->add('blog_main_pages', new Route(S2_BLOG_URL.'/skip/{page<\d+>}', ['_controller' => Page_Main::class, 'slash' => '/']));

$routes->add('blog_rss', new Route(S2_BLOG_URL.'/rss.xml', ['_controller' => Page_RSS::class]));
$routes->add('blog_sitemap', new Route(S2_BLOG_URL.'/sitemap.xml', ['_controller' => Page_Sitemap::class]));

$routes->add('blog_favorite', new Route(S2_BLOG_URL.'/'.S2_FAVORITE_URL.'{slash</?>}', ['_controller' => Page_Favorite::class]));

$routes->add('blog_tags', new Route(S2_BLOG_URL.'/'.S2_TAGS_URL.'{slash</?>}', ['_controller' => Page_Tags::class]));
$routes->add('blog_tag', new Route(S2_BLOG_URL.'/'.S2_TAGS_URL.'/{tag}{slash</?>}', ['_controller' => Page_Tag::class]));

$routes->add('blog_year', new Route(S2_BLOG_URL.'/{year<\d+>}/', ['_controller' => Page_Year::class]));
$routes->add('blog_month', new Route(S2_BLOG_URL.'/{year<\d+>}/{month<\d+>}/', ['_controller' => Page_Month::class]));
$routes->add('blog_day', new Route(S2_BLOG_URL.'/{year<\d+>}/{month<\d+>}/{day<\d+>}/', ['_controller' => Page_Day::class]));
$routes->add('blog_post', new Route(S2_BLOG_URL.'/{year<\d+>}/{month<\d+>}/{day<\d+>}/{url}', ['_controller' => Page_Post::class]));
