<?php
/**
 * Hook idx_new_routes
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

 if (!defined('S2_ROOT')) {
     die;
}

$router->map('GET', '@^/blog(:?(?P<slash>/)(:?skip/(?P<page>(\d)+))?)?$', '\\s2_extensions\\s2_blog\\Page_Main');

$router->map('GET', '['.S2_BLOG_URL.'/rss.xml:url]', '\\s2_extensions\\s2_blog\\Page_RSS');
$router->map('GET', '['.S2_BLOG_URL.'/sitemap.xml]', '\\s2_extensions\\s2_blog\\Page_Sitemap');

$router->map('GET', S2_BLOG_URL.'/'.S2_FAVORITE_URL.'[/:slash]?', '\\s2_extensions\\s2_blog\\Page_Favorite');

$router->map('GET', S2_BLOG_URL.'/'.S2_TAGS_URL.'[/:slash]?', '\\s2_extensions\\s2_blog\\Page_Tags');
$router->map('GET', S2_BLOG_URL.'/'.S2_TAGS_URL.'/[*:tag]([/:slash])?', '\\s2_extensions\\s2_blog\\Page_Tag');

$router->map('GET', S2_BLOG_URL.'/[i:year]/', '\\s2_extensions\\s2_blog\\Page_Year');
$router->map('GET', S2_BLOG_URL.'/[i:year]/[i:month]/', '\\s2_extensions\\s2_blog\\Page_Month');
$router->map('GET', S2_BLOG_URL.'/[i:year]/[i:month]/[i:day]/', '\\s2_extensions\\s2_blog\\Page_Day');
$router->map('GET', S2_BLOG_URL.'/[i:year]/[i:month]/[i:day]/[*:url]', '\\s2_extensions\\s2_blog\\Page_Post');
