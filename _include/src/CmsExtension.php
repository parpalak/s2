<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use S2\Cms\Comment\AkismetProxy;
use S2\Cms\Comment\SpamDetectorInterface;
use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Controller\Comment\CommentStrategyInterface;
use S2\Cms\Controller\CommentController;
use S2\Cms\Controller\CommentSentController;
use S2\Cms\Controller\CommentUnsubscribeController;
use S2\Cms\Controller\NotFoundController;
use S2\Cms\Controller\PageCommon;
use S2\Cms\Controller\PageFavorite;
use S2\Cms\Controller\PageTag;
use S2\Cms\Controller\PageTags;
use S2\Cms\Controller\RssController;
use S2\Cms\Controller\Sitemap;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\Event\NotFoundEvent;
use S2\Cms\Framework\Exception\ConfigurationException;
use S2\Cms\Framework\Exception\ServiceAlreadyDefinedException;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Framework\StatefulServiceInterface;
use S2\Cms\Http\RedirectDetector;
use S2\Cms\HttpClient\HttpClient;
use S2\Cms\Image\ThumbnailGenerator;
use S2\Cms\Logger\Logger;
use S2\Cms\Mail\CommentMailer;
use S2\Cms\Model\Article\ArticleRssStrategy;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\AuthProvider;
use S2\Cms\Model\Comment\ArticleCommentStrategy;
use S2\Cms\Model\CommentNotifier;
use S2\Cms\Model\CommentProvider;
use S2\Cms\Model\ExtensionCache;
use S2\Cms\Model\MigrationManager;
use S2\Cms\Model\TagsProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Model\User\UserProvider;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerPostgres;
use S2\Cms\Pdo\DbLayerSqlite;
use S2\Cms\Pdo\PDO;
use S2\Cms\Pdo\PdoSqliteFactory;
use S2\Cms\Queue\QueueConsumer;
use S2\Cms\Queue\QueueHandlerInterface;
use S2\Cms\Queue\QueuePublisher;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\TemplateEvent;
use S2\Cms\Template\TemplateFinalReplaceEvent;
use S2\Cms\Template\Viewer;
use S2\Cms\Translation\ExtensibleTranslator;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Contracts\Translation\TranslatorInterface;

class CmsExtension implements ExtensionInterface
{
    /**
     * @throws ServiceAlreadyDefinedException
     */
    public function buildContainer(Container $container): void
    {
        $container->set(DbLayer::class, function (Container $container) {
            $db_prefix = $container->getParameter('db_prefix');
            $db_type   = $container->getParameter('db_type');

            return match ($db_type) {
                'mysql' => new DbLayer($container->get(\PDO::class), $db_prefix),
                'sqlite' => new DbLayerSqlite($container->get(\PDO::class), $db_prefix),
                'pgsql' => new DbLayerPostgres($container->get(\PDO::class), $db_prefix),
                default => throw new \RuntimeException(\sprintf('Unsupported db_type="%s"', $db_type)),
            };
        });
        $container->set(\PDO::class, function (Container $container) {
            $db_prefix   = $container->getParameter('db_prefix');
            $db_type     = $container->getParameter('db_type');
            $db_host     = $container->getParameter('db_host');
            $db_name     = $container->getParameter('db_name');
            $db_username = $container->getParameter('db_username');
            $db_password = $container->getParameter('db_password');
            $p_connect   = $container->getParameter('p_connect');

            if (!class_exists(\PDO::class)) {
                throw new \RuntimeException('This PHP environment does not have PDO support built in. PDO support is required. Consult the PHP documentation for further assistance.');
            }

            if (!\is_string($db_type)) {
                throw new \RuntimeException('$db_type must be a string.');
            }

            if (!\in_array($db_type, \PDO::getAvailableDrivers(), true)) {
                throw new \RuntimeException('This PHP environment does not have PDO "%s" support built in. It is required if you want to use this type of database. Consult the PHP documentation for further assistance.');
            }

            return match ($db_type) {
                'mysql' => new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_username, $db_password),
                'sqlite' => PdoSqliteFactory::create($container->getParameter('root_dir') . $db_name, $p_connect),
                'pgsql' => new PDO("pgsql:host=$db_host;dbname=$db_name", $db_username, $db_password),
                default => throw new \RuntimeException(\sprintf('Unsupported db_type="%s"', $db_type)),
            };
        });

        $container->set(MigrationManager::class, function (Container $container) {
            return new MigrationManager(
                $container->get(DbLayer::class),
                $container->getParameter('db_type'),
            );
        });

        $container->set(ExtensionCache::class, function (Container $container) {
            return new ExtensionCache(
                $container->get(DbLayer::class),
                $container->getParameter('disable_cache'),
                $container->getParameter('cache_dir'),
            );
        });

        $container->set(ThumbnailGenerator::class, function (Container $container) {
            return new ThumbnailGenerator(
                $container->get(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class),
                $container->get(QueuePublisher::class),
                $container->getParameter('image_path'),
                $container->getParameter('image_dir'),
            );
        }, [QueueHandlerInterface::class]);
        $container->set(LoggerInterface::class, function (Container $container) {
            return new Logger($container->getParameter('log_dir') . 'app.log', 'app', LogLevel::INFO);
        });
        $container->set('config_cache', function (Container $container) {
            return new FilesystemAdapter('config', 0, $container->getParameter('cache_dir'));
        });

        $container->set(DynamicConfigProvider::class, function (Container $container) {
            return new DynamicConfigProvider(
                $container->get(DbLayer::class),
                $container->getParameter('cache_dir') . '/cache_config.php',
                $container->getParameter('disable_cache'),
            );
        }, [StatefulServiceInterface::class]); // TODO not enough, parameters are set into many other services

        $container->set('translator', function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            $language = $provider->get('S2_LANGUAGE');

            $translator = new ExtensibleTranslator($language);

            $translator->attachLoader('common', function (string $language, ExtensibleTranslator $translator) {
                $fileName = __DIR__ . '/../../_lang/' . $language . '/common.php';
                if (!\file_exists($fileName)) {
                    throw new ConfigurationException(\sprintf('The language pack "%s" you have chosen seems to be corrupt. Please check that file "common.php" exists.', $language));
                }
                $translations = require $fileName;
                if (!\is_array($translations)) {
                    throw new ConfigurationException(\sprintf('The language pack "%s" you have chosen seems to be corrupt. Please check that file "common.php" has the correct format.', $language));
                }
                $locale = $translations['locale'] ?? 'en';

                $translator->setLocale($locale);

                return $translations;
            });

            return $translator;
        }, [StatefulServiceInterface::class]);

        $container->set(QueuePublisher::class, function (Container $container) {
            return new QueuePublisher(
                $container->get(\PDO::class),
                $container->getParameter('db_prefix'),
            );
        });
        $container->set(QueueConsumer::class, function (Container $container) {
            return new QueueConsumer(
                $container->get(\PDO::class),
                $container->getParameter('db_prefix'),
                $container->get(LoggerInterface::class),
                ...$container->getByTag(QueueHandlerInterface::class)
            );
        });

        $container->set(UrlBuilder::class, function (Container $container) {
            return new UrlBuilder(
                $container->getParameter('base_path'),
                $container->getParameter('base_url'),
                $container->getParameter('url_prefix'),
            );
        });

        $container->set(RequestStack::class, function (Container $container) {
            return new RequestStack();
        });

        $container->set(HttpClient::class, function (Container $container) {
            return new HttpClient();
        });

        $container->set(HtmlTemplateProvider::class, function (Container $container) {
            return new HtmlTemplateProvider(
                $container->get(RequestStack::class),
                $container->get(UrlBuilder::class),
                $container->get('translator'),
                $container->get(Viewer::class),
                $container->get(HttpClient::class),
                $container->get(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class),
                $container->get(DynamicConfigProvider::class),
                $container->getParameter('debug'),
                $container->getParameter('debug_view'),
                $container->getParameter('root_dir'),
                $container->getParameter('cache_dir'),
                $container->getParameter('disable_cache'),
                $container->getParameter('base_path'),
            );
        });

        $container->set(Viewer::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new Viewer(
                $container->get('translator'),
                $container->get(UrlBuilder::class),
                $container->getParameter('root_dir'),
                $provider->get('S2_STYLE'),
                $container->getParameter('debug_view')
            );
        });

        $container->set('strict_viewer', function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new Viewer(
                $container->get('translator'),
                $container->get(UrlBuilder::class),
                $container->getParameter('root_dir'),
                $provider->get('S2_STYLE'),
                false // no HTML debug info for XML and other non-HTML content
            );
        });

        $container->set(ArticleProvider::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new ArticleProvider(
                $container->get(DbLayer::class),
                $container->get(UrlBuilder::class),
                $container->get(Viewer::class),
                $provider->get('S2_FAVORITE_URL'),
                $provider->get('S2_USE_HIERARCHY') === '1',
            );
        });

        $container->set(TagsProvider::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new TagsProvider(
                $container->get(DbLayer::class),
                $container->get(UrlBuilder::class),
                $provider->get('S2_TAGS_URL'),
            );
        });

        $container->set(CommentProvider::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new CommentProvider(
                $container->get(DbLayer::class),
                $container->get(ArticleProvider::class),
                $container->get(UrlBuilder::class),
                $container->get(Viewer::class),
                $provider->get('S2_SHOW_COMMENTS') === '1',
            );
        });

        $container->set(RedirectDetector::class, function (Container $container) {
            return new RedirectDetector(
                $container->get(UrlBuilder::class),
                $container->getParameter('redirect_map'),
            );
        });

        $container->set(NotFoundController::class, function (Container $container) {
            return new NotFoundController(
                $container->get(ArticleProvider::class),
                $container->get(UrlBuilder::class),
                $container->get('translator'),
                $container->get(HtmlTemplateProvider::class),
            );
        });

        $container->set(PageFavorite::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new PageFavorite(
                $container->get(DbLayer::class),
                $container->get(ArticleProvider::class),
                $container->get(UrlBuilder::class),
                $container->get('translator'),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->get('S2_FAVORITE_URL'),
                $provider->get('S2_USE_HIERARCHY') === '1',
            );
        });

        $container->set(PageTags::class, function (Container $container) {
            return new PageTags(
                $container->get(TagsProvider::class),
                $container->get(ArticleProvider::class),
                $container->get(UrlBuilder::class),
                $container->get('translator'),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
            );
        });

        $container->set(PageTag::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new PageTag(
                $container->get(DbLayer::class),
                $container->get(ArticleProvider::class),
                $container->get(UrlBuilder::class),
                $container->get('translator'),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->get('S2_TAGS_URL'),
                $provider->get('S2_FAVORITE_URL'),
                $provider->get('S2_USE_HIERARCHY') === '1',
            );
        });

        $container->set(PageCommon::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new PageCommon(
                $container->get(DbLayer::class),
                $container->get(ArticleProvider::class),
                $container->get(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class),
                $container->get(UrlBuilder::class),
                $container->get('translator'),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->get('S2_USE_HIERARCHY') === '1',
                $provider->get('S2_SHOW_COMMENTS') === '1',
                $provider->get('S2_TAGS_URL'),
                $provider->get('S2_FAVORITE_URL'),
                (int)$provider->get('S2_MAX_ITEMS'),
                $container->getParameter('debug'),
            );
        });

        $container->set(CommentMailer::class, function (Container $container) {
            return new CommentMailer(
                $container->get('comments_translator'),
                $container->get(DynamicConfigProvider::class)
            );
        });

        $container->set(CommentNotifier::class, function (Container $container) {
            return new CommentNotifier(
                $container->get(DbLayer::class),
                $container->get(ArticleProvider::class),
                $container->get(UrlBuilder::class),
                $container->get(CommentMailer::class),
            );
        });

        $container->set('comments_translator', function (Container $container) {
            /** @var ExtensibleTranslator $translator */
            $translator = $container->get('translator');
            $translator->attachLoader('comments', function (string $lang) {
                return require __DIR__ . '/../../_lang/' . $lang . '/comments.php';
            });

            return $translator;
        });

        $container->set(ArticleCommentStrategy::class, function (Container $container) {
            return new ArticleCommentStrategy(
                $container->get(DbLayer::class),
                $container->get(ArticleProvider::class),
                $container->get(CommentNotifier::class),
            );
        }, [CommentStrategyInterface::class]);

        $container->set(AuthProvider::class, function (Container $container) {
            return new AuthProvider(
                $container->get(DbLayer::class),
                $container->getParameter('cookie_name'),
            );
        });

        $container->set(UserProvider::class, function (Container $container) {
            return new UserProvider(
                $container->get(DbLayer::class),
            );
        });

        $container->set(SpamDetectorInterface::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new AkismetProxy(
                $container->get(HttpClient::class),
                $container->get(UrlBuilder::class),
                $container->get(LoggerInterface::class),
                $provider->get('S2_AKISMET_KEY'),
            );
        });

        $container->set(CommentController::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new CommentController(
                $container->get(AuthProvider::class),
                $container->get(UserProvider::class),
                $container->get(ArticleCommentStrategy::class),
                $container->get('comments_translator'),
                $container->get(UrlBuilder::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $container->get(LoggerInterface::class),
                $container->get(CommentMailer::class),
                $container->get(SpamDetectorInterface::class),
                $provider->get('S2_ENABLED_COMMENTS') === '1',
                $provider->get('S2_PREMODERATION') === '1',
            );
        });

        $container->set(CommentSentController::class, function (Container $container) {
            return new CommentSentController(
                $container->get(AuthProvider::class),
                $container->get(UserProvider::class),
                $container->get('comments_translator'),
                $container->get(UrlBuilder::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(CommentMailer::class),
                ...$container->getByTag(CommentStrategyInterface::class)
            );
        });

        $container->set(CommentUnsubscribeController::class, function (Container $container) {
            return new CommentUnsubscribeController(
                $container->get('comments_translator'),
                $container->get(HtmlTemplateProvider::class),
                ...$container->getByTag(CommentStrategyInterface::class)
            );
        });

        $container->set(ArticleRssStrategy::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new ArticleRssStrategy(
                $container->get(ArticleProvider::class),
                $container->get(UrlBuilder::class),
                $container->get('translator'),
                $provider->get('S2_SITE_NAME'),
            );
        });

        $container->set(RssController::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new RssController(
                $container->get(ArticleRssStrategy::class),
                $container->get(UrlBuilder::class),
                $container->get('strict_viewer'),
                $container->get(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class),
                $container->getParameter('base_path'),
                $container->getParameter('base_url'),
                $provider->get('S2_WEBMASTER'),
            );
        });

        $container->set(Sitemap::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new Sitemap(
                $container->get(DbLayer::class),
                $container->get(ArticleProvider::class),
                $container->get(UrlBuilder::class),
                $container->get('strict_viewer'),
                $provider->get('S2_USE_HIERARCHY') === '1',
            );
        });
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        $eventDispatcher->addListener(NotFoundEvent::class, function (NotFoundEvent $event) use ($container) {
            /** @var RedirectDetector $redirectDetector */
            $redirectDetector = $container->get(RedirectDetector::class);
            if (null !== ($redirectResponse = $redirectDetector->getRedirectResponse($event->request))) {
                $event->response = $redirectResponse;
                return;
            }

            if ($event->response === null) {
                /** @var NotFoundController $controller */
                $controller      = $container->get(NotFoundController::class);
                $event->response = $controller->handle($event->request);
            }
        });

        $eventDispatcher->addListener(TemplateEvent::EVENT_CREATED, function (TemplateEvent $event) use ($container) {
            $template = $event->htmlTemplate;

            if ($template->hasPlaceholder('<!-- s2_last_articles -->')) {
                /** @var ArticleProvider $articleProvider */
                $articleProvider = $container->get(ArticleProvider::class);
                $template->registerPlaceholder('<!-- s2_last_articles -->', $articleProvider->lastArticlesPlaceholder(5));
            }

            if ($template->hasPlaceholder('<!-- s2_tags_list -->')) {
                /** @var TagsProvider $tagsProvider */
                $tagsProvider = $container->get(TagsProvider::class);
                $tagsList     = $tagsProvider->tagsList();

                if (\count($tagsList) > 0) {
                    /** @var Viewer $viewer */
                    $viewer = $container->get(Viewer::class);
                    $template->registerPlaceholder('<!-- s2_tags_list -->', $viewer->render('tags_list', [
                        'tags' => $tagsList,
                    ]));
                } else {
                    $template->registerPlaceholder('<!-- s2_tags_list -->', '');
                }
            }

            if ($template->hasPlaceholder('<!-- s2_last_comments -->')) {
                /** @var CommentProvider $commentProvider */
                $commentProvider = $container->get(CommentProvider::class);
                $lastComments    = $commentProvider->lastArticleComments();

                if (\count($lastComments) > 0) {
                    /** @var Viewer $viewer */
                    $viewer = $container->get(Viewer::class);
                    /** @var TranslatorInterface $translator */
                    $translator = $container->get('translator');
                    $template->registerPlaceholder('<!-- s2_last_comments -->', $viewer->render('menu_comments', [
                        'title' => $translator->trans('Last comments'),
                        'menu'  => $lastComments,
                    ]));
                } else {
                    $template->registerPlaceholder('<!-- s2_last_comments -->', '');
                }
            }

            if ($template->hasPlaceholder('<!-- s2_last_discussions -->')) {
                /** @var CommentProvider $commentProvider */
                $commentProvider = $container->get(CommentProvider::class);
                $lastDiscussions = $commentProvider->lastDiscussions();

                if (\count($lastDiscussions) > 0) {
                    /** @var Viewer $viewer */
                    $viewer = $container->get(Viewer::class);
                    /** @var TranslatorInterface $translator */
                    $translator = $container->get('translator');
                    $template->registerPlaceholder('<!-- s2_last_discussions -->', $viewer->render('menu_block', [
                        'title' => $translator->trans('Last discussions'),
                        'menu'  => $lastDiscussions,
                    ]));
                } else {
                    $template->registerPlaceholder('<!-- s2_last_discussions -->', '');
                }
            }
        });

        $eventDispatcher->addListener(TemplateEvent::EVENT_PRE_REPLACE, function (TemplateEvent $event) use ($container) {
            $s2DebugOutput = '';
            if ($container->getParameter('show_queries')) {
                /** @var PDO $pdo */
                $pdo     = $container->getIfInstantiated(\PDO::class);
                $pdoLogs = $pdo !== null ? $pdo->cleanLogs() : [];

                /** @var Viewer $viewer */
                $viewer        = $container->get(Viewer::class);
                $s2DebugOutput = $viewer->render('debug_queries', [
                    'saved_queries' => $pdoLogs,
                ]);
            }
            $event->htmlTemplate->registerPlaceholder('<!-- s2_debug -->', $s2DebugOutput);
        });

        $eventDispatcher->addListener(TemplateFinalReplaceEvent::class, function (TemplateFinalReplaceEvent $event) use ($container) {
            $content = '';
            if ($container->getParameter('debug') || defined('S2_SHOW_TIME')) {
                /** @var Viewer $viewer */
                $viewer = $container->get(Viewer::class);

                /** @var Pdo $pdo */
                $pdo           = $container->getIfInstantiated(\PDO::class);
                $executionTime = microtime(true) - $container->getParameter('boot_timestamp');
                $content       = \sprintf(
                    't = %s; q = %d',
                    $viewer->numberFormat($executionTime, true, 3),
                    $pdo !== null ? $pdo->getQueryCount() : 0
                );
            }
            $event->replace('<!-- s2_querytime -->', $content);
        }, -256);
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
        $configProvider = $container->get(DynamicConfigProvider::class);
        $favoriteUrl    = $configProvider->get('S2_FAVORITE_URL');
        $tagsUrl        = $configProvider->get('S2_TAGS_URL');

        $routes->add('rss', new Route(
            '/rss.xml',
            ['_controller' => RssController::class],
            methods: ['GET']
        ));
        $routes->add('sitemap', new Route(
            '/sitemap.xml',
            ['_controller' => Sitemap::class],
            methods: ['GET']
        ));
        $routes->add('favorite', new Route(
            '/' . $favoriteUrl . '{slash</?>}',
            ['_controller' => PageFavorite::class],
            options: ['utf8' => true],
            methods: ['GET']
        ));
        $routes->add('tags', new Route(
            '/' . $tagsUrl . '{slash</?>}',
            ['_controller' => PageTags::class],
            options: ['utf8' => true],
            methods: ['GET']
        ));
        $routes->add('tag', new Route(
            '/' . $tagsUrl . '/{name}{slash</?>}',
            ['_controller' => PageTag::class],
            options: ['utf8' => true],
            methods: ['GET']
        ));
        $routes->add('common', new Route(
            '/{path<.*>}',
            ['_controller' => PageCommon::class],
            methods: ['GET']
        ), -1); // -1 for last route
        $routes->add('comment_sent', new Route(
            '/comment_sent',
            ['_controller' => CommentSentController::class],
            methods: ['GET']
        ));
        $routes->add('comment_unsubscribe', new Route(
            '/comment_unsubscribe',
            ['_controller' => CommentUnsubscribeController::class],
            methods: ['GET']
        ));
        $routes->add('comment', new Route(
            '/{path<.*>}',
            ['_controller' => CommentController::class],
            methods: ['POST']
        ), -1); // -1 for last route
    }
}
