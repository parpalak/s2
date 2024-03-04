<?php
/**
 * Hook idx_new_routes
 *
 * @copyright 2023-2024 Roman Parpalak
 * @license MIT
 * @package s2_blog
 *
 * @var \Symfony\Component\Routing\RouteCollection $routes
 * @var \S2\Cms\Config\DynamicConfigProvider $configProvider
 * @var string $favoriteUrl
 * @var string $tagsUrl
 */

use s2_extensions\s2_blog\Controller\BlogRss;
use s2_extensions\s2_blog\Controller\DayPageController;
use s2_extensions\s2_blog\Controller\FavoritePageController;
use s2_extensions\s2_blog\Controller\MainPageController;
use s2_extensions\s2_blog\Controller\MonthPageController;
use s2_extensions\s2_blog\Controller\PostPageController;
use s2_extensions\s2_blog\Controller\Sitemap;
use s2_extensions\s2_blog\Controller\TagPageController;
use s2_extensions\s2_blog\Controller\TagsPageController;
use s2_extensions\s2_blog\Controller\YearPageController;
use Symfony\Component\Routing\Route;


if (!defined('S2_ROOT')) {
    die;
}

$s2BlogUrl = $configProvider->get('S2_BLOG_URL');
$routes->add('blog_main', new Route($s2BlogUrl .'{slash</?>}', ['_controller' => MainPageController::class, 'page' => 0]));
$routes->add('blog_main_pages', new Route($s2BlogUrl .'/skip/{page<\d+>}', ['_controller' => MainPageController::class, 'slash' => '/']));

$routes->add('blog_rss', new Route($s2BlogUrl .'/rss.xml', ['_controller' => BlogRss::class]));
$routes->add('blog_sitemap', new Route($s2BlogUrl .'/sitemap.xml', ['_controller' => Sitemap::class]));

$routes->add('blog_favorite', new Route($s2BlogUrl .'/'.$favoriteUrl.'{slash</?>}', ['_controller' => FavoritePageController::class]));

$routes->add('blog_tags', new Route($s2BlogUrl .'/'.$tagsUrl.'{slash</?>}', ['_controller' => TagsPageController::class]));
$routes->add('blog_tag', new Route($s2BlogUrl .'/'.$tagsUrl.'/{tag}{slash</?>}', ['_controller' => TagPageController::class]));

$routes->add('blog_year', new Route($s2BlogUrl .'/{year<\d+>}/', ['_controller' => YearPageController::class]));
$routes->add('blog_month', new Route($s2BlogUrl .'/{year<\d+>}/{month<\d+>}/', ['_controller' => MonthPageController::class]));
$routes->add('blog_day', new Route($s2BlogUrl .'/{year<\d+>}/{month<\d+>}/{day<\d+>}/', ['_controller' => DayPageController::class]));
$routes->add('blog_post', new Route($s2BlogUrl .'/{year<\d+>}/{month<\d+>}/{day<\d+>}/{url}', ['_controller' => PostPageController::class]));
