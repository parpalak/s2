<?php

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

/**
 * Hook idx_new_routes
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 *
 * @var AltoRouter $router
 */

if (!defined('S2_ROOT')) {
    die;
}

$router->map('GET', '@^'.S2_BLOG_URL.'(:?(?P<slash>/)(:?skip/(?P<page>(\d)+))?)?$', Page_Main::class);

$router->map('GET', '['.S2_BLOG_URL.'/rss.xml:url]', Page_RSS::class);
$router->map('GET', '['.S2_BLOG_URL.'/sitemap.xml]', Page_Sitemap::class);

$router->map('GET', S2_BLOG_URL.'/'.S2_FAVORITE_URL.'[/:slash]?', Page_Favorite::class);

$router->map('GET', S2_BLOG_URL.'/'.S2_TAGS_URL.'[/:slash]?', Page_Tags::class);
$router->map('GET', S2_BLOG_URL.'/'.S2_TAGS_URL.'/[*:tag]([/:slash])?', Page_Tag::class);

$router->map('GET', S2_BLOG_URL.'/[i:year]/', Page_Year::class);
$router->map('GET', S2_BLOG_URL.'/[i:year]/[i:month]/', Page_Month::class);
$router->map('GET', S2_BLOG_URL.'/[i:year]/[i:month]/[i:day]/', Page_Day::class);
$router->map('GET', S2_BLOG_URL.'/[i:year]/[i:month]/[i:day]/[*:url]', Page_Post::class);
