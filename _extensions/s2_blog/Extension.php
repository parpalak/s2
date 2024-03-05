<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog;

use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Recommendation\RecommendationProvider;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class Extension implements ExtensionInterface
{
    public function buildContainer(Container $container): void
    {
        $container->set(MainPageController::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new MainPageController(
                $container->get(DbLayer::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->get('S2_TAGS_URL'),
                $provider->get('S2_BLOG_URL'),
                $provider->get('S2_BLOG_TITLE'),
                (int)$provider->get('S2_MAX_ITEMS')
            );
        });
        $container->set(DayPageController::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new DayPageController(
                $container->get(DbLayer::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->get('S2_TAGS_URL'),
                $provider->get('S2_BLOG_URL'),
                $provider->get('S2_BLOG_TITLE'),
            );
        });
        $container->set(MonthPageController::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new MonthPageController(
                $container->get(DbLayer::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->get('S2_TAGS_URL'),
                $provider->get('S2_BLOG_URL'),
                $provider->get('S2_BLOG_TITLE'),
                $provider->get('S2_START_YEAR'),
            );
        });
        $container->set(YearPageController::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new YearPageController(
                $container->get(DbLayer::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->get('S2_TAGS_URL'),
                $provider->get('S2_BLOG_URL'),
                $provider->get('S2_BLOG_TITLE'),
                $provider->get('S2_START_YEAR'),
            );
        });
        $container->set(PostPageController::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new PostPageController(
                $container->get(DbLayer::class),
                $container->get(RecommendationProvider::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->get('S2_TAGS_URL'),
                $provider->get('S2_BLOG_URL'),
                $provider->get('S2_BLOG_TITLE'),
                $provider->get('S2_SHOW_COMMENTS') === '1',
            );
        });
        $container->set(TagsPageController::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new TagsPageController(
                $container->get(DbLayer::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->get('S2_TAGS_URL'),
                $provider->get('S2_BLOG_URL'),
                $provider->get('S2_BLOG_TITLE'),
            );
        });
        $container->set(TagPageController::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new TagPageController(
                $container->get(DbLayer::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->get('S2_TAGS_URL'),
                $provider->get('S2_BLOG_URL'),
                $provider->get('S2_BLOG_TITLE'),
                $provider->get('S2_USE_HIERARCHY') === '1',
            );
        });
        $container->set(FavoritePageController::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new FavoritePageController(
                $container->get(DbLayer::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->get('S2_TAGS_URL'),
                $provider->get('S2_BLOG_URL'),
                $provider->get('S2_BLOG_TITLE'),
            );
        });
        $container->set(BlogRss::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new BlogRss(
                $container->get('strict_viewer'),
                $container->getParameter('base_url'),
                $provider->get('S2_WEBMASTER'),
                $provider->get('S2_SITE_NAME'),
                $provider->get('S2_BLOG_URL'),
                $provider->get('S2_BLOG_TITLE'),
            );
        });
        $container->set(Sitemap::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new Sitemap(
                $container->get(DbLayer::class),
                $container->get('strict_viewer'),
                $provider->get('S2_BLOG_URL'),
            );
        });
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        $eventDispatcher->addListener(\S2\Cms\Template\HtmlTemplateCreatedEvent::class, function (\S2\Cms\Template\HtmlTemplateCreatedEvent $event) use ($container) {
            $blogPlaceholders = [];
            $template         = $event->htmlTemplate;

            foreach (['s2_blog_last_comments', 's2_blog_last_discussions', 's2_blog_last_post'] as $blogPlaceholder) {
                if ($template->hasPlaceholder('<!-- ' . $blogPlaceholder . ' -->')) {
                    $blogPlaceholders[$blogPlaceholder] = 1;
                }
            }

            if (\count($blogPlaceholders) === 0) {
                return;
            }

            \Lang::load('s2_blog', function () {
                if (file_exists(S2_ROOT . '/_extensions/s2_blog' . '/lang/' . S2_LANGUAGE . '.php'))
                    return require S2_ROOT . '/_extensions/s2_blog' . '/lang/' . S2_LANGUAGE . '.php';
                else
                    return require S2_ROOT . '/_extensions/s2_blog' . '/lang/English.php';
            });

            /** @var Viewer $viewer */
            $viewer = $container->get(Viewer::class);

            if (isset($blogPlaceholders['s2_blog_last_comments'])) {
                $recentComments = \s2_extensions\s2_blog\Placeholder::recent_comments();

                $template->registerPlaceholder('<!-- s2_blog_last_comments -->', empty($recentComments) ? '' : $viewer->render('menu_comments', [
                    'title' => \Lang::get('Last comments', 's2_blog'),
                    'menu'  => $recentComments,
                ]));
            }

            if (isset($blogPlaceholders['s2_blog_last_discussions'])) {
                $lastDiscussions = \s2_extensions\s2_blog\Placeholder::recent_discussions();

                $template->registerPlaceholder('<!-- s2_blog_last_discussions -->', empty($lastDiscussions) ? '' : $viewer->render('menu_block', [
                    'title' => \Lang::get('Last discussions', 's2_blog'),
                    'menu'  => $lastDiscussions,
                    'class' => 's2_blog_last_discussions',
                ]));
            }
            if (isset($blogPlaceholders['s2_blog_last_post'])) {
                $lastPosts = \s2_extensions\s2_blog\Lib::last_posts_array(1);

                foreach ($lastPosts as &$s2_blog_post) {
                    $s2_blog_post = $viewer->render('post_short', $s2_blog_post, 's2_blog');
                }
                unset($s2_blog_post);
                $template->registerPlaceholder('<!-- s2_blog_last_post -->', implode('', $lastPosts));
            }
        });
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
        $configProvider = $container->get(DynamicConfigProvider::class);
        $s2BlogUrl      = $configProvider->get('S2_BLOG_URL');
        $favoriteUrl    = $configProvider->get('S2_FAVORITE_URL');
        $tagsUrl        = $configProvider->get('S2_TAGS_URL');

        $routes->add('blog_main', new Route($s2BlogUrl . '{slash</?>}', ['_controller' => MainPageController::class, 'page' => 0]));
        $routes->add('blog_main_pages', new Route($s2BlogUrl . '/skip/{page<\d+>}', ['_controller' => MainPageController::class, 'slash' => '/']));

        $routes->add('blog_rss', new Route($s2BlogUrl . '/rss.xml', ['_controller' => BlogRss::class]));
        $routes->add('blog_sitemap', new Route($s2BlogUrl . '/sitemap.xml', ['_controller' => Sitemap::class]));

        $routes->add('blog_favorite', new Route($s2BlogUrl . '/' . $favoriteUrl . '{slash</?>}', ['_controller' => FavoritePageController::class]));

        $routes->add('blog_tags', new Route($s2BlogUrl . '/' . $tagsUrl . '{slash</?>}', ['_controller' => TagsPageController::class]));
        $routes->add('blog_tag', new Route($s2BlogUrl . '/' . $tagsUrl . '/{tag}{slash</?>}', ['_controller' => TagPageController::class]));

        $routes->add('blog_year', new Route($s2BlogUrl . '/{year<\d+>}/', ['_controller' => YearPageController::class]));
        $routes->add('blog_month', new Route($s2BlogUrl . '/{year<\d+>}/{month<\d+>}/', ['_controller' => MonthPageController::class]));
        $routes->add('blog_day', new Route($s2BlogUrl . '/{year<\d+>}/{month<\d+>}/{day<\d+>}/', ['_controller' => DayPageController::class]));
        $routes->add('blog_post', new Route($s2BlogUrl . '/{year<\d+>}/{month<\d+>}/{day<\d+>}/{url}', ['_controller' => PostPageController::class]));
    }
}
