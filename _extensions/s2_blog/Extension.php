<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/licenses/MIT MIT
 * @package   s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use S2\Cms\Asset\AssetPack;
use S2\Cms\Comment\SpamDecisionProviderInterface;
use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Controller\Comment\CommentStrategyInterface;
use S2\Cms\Controller\CommentController;
use S2\Cms\Controller\RssController;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Framework\StatefulServiceInterface;
use S2\Cms\Mail\CommentMailer;
use S2\Cms\Model\Article\ArticleRenderedEvent;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\AuthProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Model\User\UserProvider;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Queue\QueueHandlerInterface;
use S2\Cms\Queue\QueuePublisher;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\TemplateAssetEvent;
use S2\Cms\Template\TemplateEvent;
use S2\Cms\Template\Viewer;
use S2\Cms\Translation\ExtensibleTranslator;
use S2\Rose\Indexer;
use s2_extensions\s2_blog\Controller\DayPageController;
use s2_extensions\s2_blog\Controller\FavoritePageController;
use s2_extensions\s2_blog\Controller\MainPageController;
use s2_extensions\s2_blog\Controller\MonthPageController;
use s2_extensions\s2_blog\Controller\PostPageController;
use s2_extensions\s2_blog\Controller\Sitemap;
use s2_extensions\s2_blog\Controller\TagPageController;
use s2_extensions\s2_blog\Controller\TagsPageController;
use s2_extensions\s2_blog\Controller\YearPageController;
use s2_extensions\s2_blog\Model\BlogCommentNotifier;
use s2_extensions\s2_blog\Model\BlogCommentStrategy;
use s2_extensions\s2_blog\Model\BlogPlaceholderProvider;
use s2_extensions\s2_blog\Model\BlogRssStrategy;
use s2_extensions\s2_blog\Model\PostProvider;
use s2_extensions\s2_blog\Service\PostIndexer;
use s2_extensions\s2_blog\Service\TagsSearchProvider;
use s2_extensions\s2_search\Event\TagsSearchEvent;
use s2_extensions\s2_search\Service\BulkIndexingProviderInterface;
use s2_extensions\s2_search\Service\RecommendationProvider;
use s2_extensions\s2_search\Service\SimilarWordsDetector;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Contracts\Translation\TranslatorInterface;

class Extension implements ExtensionInterface
{
    public function buildContainer(Container $container): void
    {
        $container->set(BlogUrlBuilder::class, static function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new BlogUrlBuilder(
                $container->get(UrlBuilder::class),
                $provider->getStringProxy('S2_TAGS_URL'),
                $provider->getStringProxy('S2_FAVORITE_URL'),
                $provider->getStringProxy('S2_BLOG_URL'),
            );
        }, [StatefulServiceInterface::class]);
        $container->set('s2_blog_translator', static function (Container $container) {
            /** @var ExtensibleTranslator $translator */
            $translator = $container->get('translator');
            $translator->attachLoader('s2_blog', static function (string $lang) {
                return require ($dir = __DIR__ . '/lang/') . (file_exists($dir . $lang . '.php') ? $lang : 'English') . '.php';
            });

            return $translator;
        });
        $container->set(CalendarBuilder::class, static function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new CalendarBuilder(
                $container->get(DbLayer::class),
                $container->get(BlogUrlBuilder::class),
                $container->get('s2_blog_translator'),
                $provider->getIntProxy('S2_START_YEAR'),
            );
        });
        $container->set(BlogPlaceholderProvider::class, static function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new BlogPlaceholderProvider(
                $container->get(DbLayer::class),
                $container->get(BlogUrlBuilder::class),
                $container->get('s2_blog_translator'),
                $container->get(Viewer::class),
                $container->get(RequestStack::class),
                $container->get('config_cache'),
                $provider->getBoolProxy('S2_SHOW_COMMENTS'),
                $provider->getIntProxy('S2_MAX_ITEMS'),
                $container->getParameter('url_prefix'),
            );
        });
        $container->set(MainPageController::class, static function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new MainPageController(
                $container->get(DbLayer::class),
                $container->get(CalendarBuilder::class),
                $container->get(BlogUrlBuilder::class),
                $container->get(ArticleProvider::class),
                $container->get(PostProvider::class),
                $container->get(UrlBuilder::class),
                $container->get('s2_blog_translator'),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->getStringProxy('S2_BLOG_TITLE'),
                $provider->getBoolProxy('S2_SHOW_COMMENTS'),
                $provider->getBoolProxy('S2_ENABLED_COMMENTS'),
                $provider->getIntProxy('S2_MAX_ITEMS')
            );
        });
        $container->set(DayPageController::class, static function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new DayPageController(
                $container->get(DbLayer::class),
                $container->get(CalendarBuilder::class),
                $container->get(BlogUrlBuilder::class),
                $container->get(ArticleProvider::class),
                $container->get(PostProvider::class),
                $container->get(UrlBuilder::class),
                $container->get('s2_blog_translator'),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->getStringProxy('S2_BLOG_TITLE'),
                $provider->getBoolProxy('S2_SHOW_COMMENTS'),
                $provider->getBoolProxy('S2_ENABLED_COMMENTS'),
            );
        });
        $container->set(MonthPageController::class, static function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new MonthPageController(
                $container->get(DbLayer::class),
                $container->get(CalendarBuilder::class),
                $container->get(BlogUrlBuilder::class),
                $container->get(ArticleProvider::class),
                $container->get(PostProvider::class),
                $container->get(UrlBuilder::class),
                $container->get('s2_blog_translator'),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->getStringProxy('S2_BLOG_TITLE'),
                $provider->getBoolProxy('S2_SHOW_COMMENTS'),
                $provider->getBoolProxy('S2_ENABLED_COMMENTS'),
                $provider->getIntProxy('S2_START_YEAR'),
            );
        });
        $container->set(YearPageController::class, static function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new YearPageController(
                $container->get(DbLayer::class),
                $container->get(CalendarBuilder::class),
                $container->get(BlogUrlBuilder::class),
                $container->get(ArticleProvider::class),
                $container->get(PostProvider::class),
                $container->get(UrlBuilder::class),
                $container->get('s2_blog_translator'),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->getStringProxy('S2_BLOG_TITLE'),
                $provider->getBoolProxy('S2_SHOW_COMMENTS'),
                $provider->getBoolProxy('S2_ENABLED_COMMENTS'),
                $provider->getIntProxy('S2_START_YEAR'),
            );
        });
        $container->set(PostPageController::class, static function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new PostPageController(
                $container->get(DbLayer::class),
                $container->get(CalendarBuilder::class),
                $container->get(BlogUrlBuilder::class),
                $container->get(ArticleProvider::class),
                $container->get(PostProvider::class),
                $container->get(UrlBuilder::class),
                $container->getIfDefined(RecommendationProvider::class),
                $container->get('s2_blog_translator'),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->getStringProxy('S2_BLOG_TITLE'),
                $provider->getBoolProxy('S2_SHOW_COMMENTS'),
                $provider->getBoolProxy('S2_ENABLED_COMMENTS'),
            );
        });
        $container->set(TagsPageController::class, static function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new TagsPageController(
                $container->get(DbLayer::class),
                $container->get(CalendarBuilder::class),
                $container->get(BlogUrlBuilder::class),
                $container->get(ArticleProvider::class),
                $container->get(PostProvider::class),
                $container->get(UrlBuilder::class),
                $container->get('s2_blog_translator'),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->getStringProxy('S2_BLOG_TITLE'),
                $provider->getBoolProxy('S2_SHOW_COMMENTS'),
                $provider->getBoolProxy('S2_ENABLED_COMMENTS'),
            );
        });
        $container->set(TagPageController::class, static function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new TagPageController(
                $container->get(DbLayer::class),
                $container->get(CalendarBuilder::class),
                $container->get(BlogUrlBuilder::class),
                $container->get(ArticleProvider::class),
                $container->get(PostProvider::class),
                $container->get(UrlBuilder::class),
                $container->get('s2_blog_translator'),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->getStringProxy('S2_BLOG_TITLE'),
                $provider->getBoolProxy('S2_SHOW_COMMENTS'),
                $provider->getBoolProxy('S2_ENABLED_COMMENTS'),
                $provider->getBoolProxy('S2_USE_HIERARCHY'),
            );
        });
        $container->set(FavoritePageController::class, static function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new FavoritePageController(
                $container->get(DbLayer::class),
                $container->get(CalendarBuilder::class),
                $container->get(BlogUrlBuilder::class),
                $container->get(ArticleProvider::class),
                $container->get(PostProvider::class),
                $container->get(UrlBuilder::class),
                $container->get('s2_blog_translator'),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->getStringProxy('S2_BLOG_TITLE'),
                $provider->getBoolProxy('S2_SHOW_COMMENTS'),
                $provider->getBoolProxy('S2_ENABLED_COMMENTS'),
            );
        });
        $container->set(BlogRssStrategy::class, static function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new BlogRssStrategy(
                $container->get(PostProvider::class),
                $container->get(BlogUrlBuilder::class),
                $container->get('s2_blog_translator'),
                $container->get('strict_viewer'),
                $provider->getStringProxy('S2_BLOG_TITLE'),
            );
        });
        $container->set('s2_blog.rss_controller', static function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new RssController(
                $container->get(BlogRssStrategy::class),
                $container->get(UrlBuilder::class),
                $container->get('strict_viewer'),
                $container->get(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class),
                $container->getParameter('base_path'),
                $container->getParameter('base_url'),
                $container->getParameter('version'),
                $provider->getStringProxy('S2_WEBMASTER'),
            );
        });
        $container->set(Sitemap::class, static function (Container $container) {
            return new Sitemap(
                $container->get(DbLayer::class),
                $container->get(BlogUrlBuilder::class),
                $container->get(UrlBuilder::class),
                $container->get('strict_viewer'),
            );
        });

        $container->set(BlogCommentStrategy::class, static function (Container $container) {
            return new BlogCommentStrategy(
                $container->get(DbLayer::class),
                $container->get(BlogCommentNotifier::class),
            );
        }, [CommentStrategyInterface::class]);
        $container->set('s2_blog.comment_controller', static function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new CommentController(
                $container->get(AuthProvider::class),
                $container->get(UserProvider::class),
                $container->get(BlogCommentStrategy::class),
                $container->get('comments_translator'),
                $container->get(UrlBuilder::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $container->get(LoggerInterface::class),
                $container->get(CommentMailer::class),
                $container->get(SpamDecisionProviderInterface::class),
                $provider->getBoolProxy('S2_ENABLED_COMMENTS'),
                $provider->getBoolProxy('S2_PREMODERATION'),
            );
        }, ['dynamic_config_dependent']);

        $container->set(PostProvider::class, static function (Container $container) {
            return new PostProvider(
                $container->get(DbLayer::class),
                $container->get(BlogUrlBuilder::class),
                $container->get(Viewer::class),
            );
        });

        $container->set(BlogCommentNotifier::class, static function (Container $container) {
            return new BlogCommentNotifier(
                $container->get(DbLayer::class),
                $container->get(UrlBuilder::class),
                $container->get(BlogUrlBuilder::class),
                $container->get(CommentMailer::class),
            );
        });

        $container->set(PostIndexer::class, static function (Container $container) {
            return new PostIndexer(
                $container->get(DbLayer::class),
                $container->get(BlogUrlBuilder::class),
                $container->getIfDefined(Indexer::class),
                $container->getIfDefined('recommendations_cache'),
                $container->get(QueuePublisher::class),
            );
        }, [QueueHandlerInterface::class, BulkIndexingProviderInterface::class]);

        $container->set(TagsSearchProvider::class, static function (Container $container) {
            return new TagsSearchProvider(
                $container->get(DbLayer::class),
                $container->get(SimilarWordsDetector::class),
                $container->get(BlogUrlBuilder::class),
            );
        });
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        $eventDispatcher->addListener(TemplateEvent::EVENT_CREATED, function (TemplateEvent $event) use ($container) {
            $blogPlaceholders = [];
            $template         = $event->htmlTemplate;

            foreach (['s2_blog_last_comments', 's2_blog_last_discussions', 's2_blog_last_post', 's2_blog_navigation'] as $blogPlaceholder) {
                if ($template->hasPlaceholder('<!-- ' . $blogPlaceholder . ' -->')) {
                    $blogPlaceholders[$blogPlaceholder] = 1;
                }
            }

            if (\count($blogPlaceholders) === 0) {
                return;
            }

            /** @var TranslatorInterface $translator */
            $translator = $container->get('s2_blog_translator');

            /** @var Viewer $viewer */
            $viewer = $container->get(Viewer::class);

            if (isset($blogPlaceholders['s2_blog_last_comments'])) {
                /** @var BlogPlaceholderProvider $placeholderProvider */
                $placeholderProvider = $container->get(BlogPlaceholderProvider::class);
                $recentComments      = $placeholderProvider->getRecentComments();

                $template->registerPlaceholder('<!-- s2_blog_last_comments -->', empty($recentComments) ? '' : $viewer->render('menu_comments', [
                    'title' => $translator->trans('Last blog comments'),
                    'menu'  => $recentComments,
                ]));
            }

            if (isset($blogPlaceholders['s2_blog_last_discussions'])) {
                /** @var BlogPlaceholderProvider $placeholderProvider */
                $placeholderProvider = $container->get(BlogPlaceholderProvider::class);
                $lastDiscussions     = $placeholderProvider->getRecentDiscussions();

                $template->registerPlaceholder('<!-- s2_blog_last_discussions -->', empty($lastDiscussions) ? '' : $viewer->render('menu_block', [
                    'title' => $translator->trans('Last blog discussions'),
                    'menu'  => $lastDiscussions,
                    'class' => 's2_blog_last_discussions',
                ]));
            }

            if (isset($blogPlaceholders['s2_blog_last_post'])) {
                /** @var PostProvider $postProvider */
                $postProvider = $container->get(PostProvider::class);
                $lastPosts    = $postProvider->lastPostsArray(1);

                foreach ($lastPosts as &$s2_blog_post) {
                    $s2_blog_post = $viewer->render('post_short', $s2_blog_post, 's2_blog');
                }
                unset($s2_blog_post);
                $template->registerPlaceholder('<!-- s2_blog_last_post -->', implode('', $lastPosts));
            }

            if (isset($blogPlaceholders['s2_blog_navigation'])) {
                /** @var BlogPlaceholderProvider $placeholderProvider */
                $placeholderProvider = $container->get(BlogPlaceholderProvider::class);
                $template->registerPlaceholder('<!-- s2_blog_navigation -->', $viewer->render(
                    'navigation',
                    $placeholderProvider->getBlogNavigationData(),
                    's2_blog'
                ));
            }
        });

        $eventDispatcher->addListener(ArticleRenderedEvent::class, static function (ArticleRenderedEvent $event) use ($container) {
            if ($event->template->hasPlaceholder('<!-- s2_blog_tags -->')) {
                /** @var Viewer $viewer */
                $viewer = $container->get(Viewer::class);
                /** @var TranslatorInterface $translator */
                $translator = $container->get('s2_blog_translator');
                /** @var BlogPlaceholderProvider $placeholderProvider */
                $placeholderProvider = $container->get(BlogPlaceholderProvider::class);

                $s2_blog_tags = $placeholderProvider->getBlogTagsForArticle($event->articleId);
                $event->template->registerPlaceholder('<!-- s2_blog_tags -->', empty($s2_blog_tags) ? '' : $viewer->render('menu_block', [
                    'title' => $translator->trans('See in blog'),
                    'menu'  => $s2_blog_tags,
                    'class' => 's2_blog_tags',
                ]));
            }
        });

        $eventDispatcher->addListener(TagsSearchEvent::class, static function (TagsSearchEvent $event) use ($container) {
            /** @var TagsSearchProvider $tagsSearchProvider */
            $tagsSearchProvider = $container->get(TagsSearchProvider::class);
            $blogTagLinks       = $tagsSearchProvider->findBlogTags($event->words);

            if (\count($blogTagLinks) > 0) {
                /** @var TranslatorInterface $translator */
                $translator = $container->get('s2_blog_translator');
                if ($event->getLine() !== null) {
                    $event->addShortLine(\sprintf($translator->trans('Found blog tags short'), implode(', ', $blogTagLinks)));
                } else {
                    $event->addLine(\sprintf($translator->trans('Found blog tags'), implode(', ', $blogTagLinks)));
                }
            }
        });

        $eventDispatcher->addListener(TemplateAssetEvent::class, static function (TemplateAssetEvent $event) {
            $event->assetPack->addCss('../../_extensions/s2_blog/style.css', [AssetPack::OPTION_MERGE]);
        });
    }

    /**
     * {@inheritdoc}
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
        $configProvider = $container->get(DynamicConfigProvider::class);
        $s2BlogUrl      = $configProvider->getStringProxy('S2_BLOG_URL')->get();
        $favoriteUrl    = $configProvider->getStringProxy('S2_FAVORITE_URL')->get();
        $tagsUrl        = $configProvider->getStringProxy('S2_TAGS_URL')->get();
        $priority       = 1;

        if ($s2BlogUrl !== '') {
            $routes->add('blog_main', new Route(
                $s2BlogUrl . '{slash</?>}',
                ['_controller' => MainPageController::class, 'page' => 0],
                options: ['utf8' => true],
                methods: ['GET'],
            ), $priority);
        } else {
            $routes->add('blog_main', new Route(
                '/',
                ['_controller' => MainPageController::class, 'page' => 0, 'slash' => '/'],
                options: ['utf8' => true],
                methods: ['GET'],
            ), $priority);
        }
        $routes->add('blog_main_pages', new Route(
            $s2BlogUrl . '/skip/{page<\d+>}',
            ['_controller' => MainPageController::class, 'slash' => '/'],
            options: ['utf8' => true],
            methods: ['GET'],
        ), $priority);

        $routes->add('blog_rss', new Route(
            $s2BlogUrl . '/rss.xml',
            ['_controller' => 's2_blog.rss_controller'],
            options: ['utf8' => true],
            methods: ['GET'],
        ), $priority);
        $routes->add('blog_sitemap', new Route(
            $s2BlogUrl . '/sitemap.xml',
            ['_controller' => Sitemap::class],
            options: ['utf8' => true],
            methods: ['GET'],
        ), $priority);

        $routes->add('blog_favorite', new Route(
            $s2BlogUrl . '/' . $favoriteUrl . '{slash</?>}',
            ['_controller' => FavoritePageController::class],
            options: ['utf8' => true],
            methods: ['GET'],
        ), $priority);

        $routes->add('blog_tags', new Route(
            $s2BlogUrl . '/' . $tagsUrl . '{slash</?>}',
            ['_controller' => TagsPageController::class],
            options: ['utf8' => true],
            methods: ['GET'],
        ), $priority);
        $routes->add('blog_tag', new Route(
            $s2BlogUrl . '/' . $tagsUrl . '/{tag}{slash</?>}',
            ['_controller' => TagPageController::class],
            options: ['utf8' => true],
            methods: ['GET'],
        ), $priority);

        $routes->add('blog_year', new Route(
            $s2BlogUrl . '/{year<\d+>}/',
            ['_controller' => YearPageController::class],
            options: ['utf8' => true],
            methods: ['GET'],
        ), $priority);
        $routes->add('blog_month', new Route(
            $s2BlogUrl . '/{year<\d+>}/{month<\d+>}/',
            ['_controller' => MonthPageController::class],
            options: ['utf8' => true],
            methods: ['GET'],
        ), $priority);
        $routes->add('blog_day', new Route(
            $s2BlogUrl . '/{year<\d+>}/{month<\d+>}/{day<\d+>}/',
            ['_controller' => DayPageController::class],
            options: ['utf8' => true],
            methods: ['GET'],
        ), $priority);
        $routes->add('blog_post', new Route(
            $s2BlogUrl . '/{year<\d+>}/{month<\d+>}/{day<\d+>}/{url}',
            ['_controller' => PostPageController::class],
            options: ['utf8' => true],
            methods: ['GET'],
        ), $priority);
        $routes->add('blog_comment', new Route(
            $s2BlogUrl . '/{year<\d+>}/{month<\d+>}/{day<\d+>}/{url}',
            ['_controller' => 's2_blog.comment_controller'],
            options: ['utf8' => true],
            methods: ['POST'],
        ), $priority);
    }
}
