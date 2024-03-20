<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Controller\NotFoundController;
use S2\Cms\Controller\PageCommon;
use S2\Cms\Controller\PageFavorite;
use S2\Cms\Controller\PageTag;
use S2\Cms\Controller\PageTags;
use S2\Cms\Controller\Rss;
use S2\Cms\Controller\Sitemap;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\Event\NotFoundEvent;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Http\RedirectDetector;
use S2\Cms\Image\ThumbnailGenerator;
use S2\Cms\Layout\LayoutMatcherFactory;
use S2\Cms\Logger\Logger;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerPostgres;
use S2\Cms\Pdo\DbLayerSqlite;
use S2\Cms\Pdo\PDO;
use S2\Cms\Pdo\PdoSqliteFactory;
use S2\Cms\Queue\QueueConsumer;
use S2\Cms\Queue\QueuePublisher;
use S2\Cms\Recommendation\RecommendationProvider;
use S2\Cms\Rose\CustomExtractor;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\TemplateEvent;
use S2\Cms\Template\TemplateFinalReplaceEvent;
use S2\Cms\Template\Viewer;
use S2\Rose\Extractor\ExtractorInterface;
use S2\Rose\Finder;
use S2\Rose\Indexer;
use S2\Rose\Stemmer\PorterStemmerEnglish;
use S2\Rose\Stemmer\PorterStemmerRussian;
use S2\Rose\Stemmer\StemmerInterface;
use S2\Rose\Storage\Database\PdoStorage;
use s2_extensions\s2_search\Controller\SearchPageController;
use s2_extensions\s2_search\Fetcher;
use s2_extensions\s2_search\IndexManager;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class CmsExtension implements ExtensionInterface
{

    public function buildContainer(Container $container): void
    {
        $container->set(DbLayer::class, function (Container $container) {
            $db_prefix = $container->getParameter('db_prefix');
            $db_type   = $container->getParameter('db_type');

            return match ($db_type) {
                'mysql' => new DbLayer($container->get(\PDO::class), $db_prefix),
                'sqlite' => new DbLayerSqlite($container->get(\PDO::class), $db_prefix),
                'pgsql' => new DbLayerPostgres($container->get(\PDO::class), $db_prefix),
                default => throw new \RuntimeException(sprintf('Unsupported db_type="%s"', $db_type)),
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
                'sqlite' => PdoSqliteFactory::create($db_name, $p_connect),
                'pgsql' => new PDO("pgsql:host=$db_host;dbname=$db_name", $db_username, $db_password),
                default => throw new \RuntimeException(sprintf('Unsupported db_type="%s"', $db_type)),
            };
        });

        // TODO move to s2_search
        $container->set(PdoStorage::class, function (Container $container) {
            return new PdoStorage($container->get(\PDO::class), $container->getParameter('db_prefix') . 's2_search_idx_');
        });
        $container->set(StemmerInterface::class, function (Container $container) {
            return new PorterStemmerRussian(new PorterStemmerEnglish());
        });
        $container->set(Finder::class, function (Container $container) {
            return (new Finder($container->get(PdoStorage::class), $container->get(StemmerInterface::class)))
                ->setHighlightTemplate('<span class="s2_search_highlight">%s</span>')
                ->setSnippetLineSeparator(' ⋄&nbsp;')
            ;
        });
        $container->set(Indexer::class, function (Container $container) {
            return new Indexer(
                $container->get(PdoStorage::class),
                $container->get(StemmerInterface::class),
                $container->get(ExtractorInterface::class),
                $container->get(LoggerInterface::class),
            );
        });
        $container->set(IndexManager::class, function (Container $container) {
            return new IndexManager(
                $container->getParameter('cache_dir'),
                new Fetcher($container->get(DbLayer::class)),
                $container->get(Indexer::class),
                $container->get(PdoStorage::class),
                $container->get('recommendations_cache'),
                $container->get(LoggerInterface::class)
            );
        });
        $container->set(ExtractorInterface::class, function (Container $container) {
            return new CustomExtractor($container->get(LoggerInterface::class));
        });
        $container->set(SearchPageController::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new SearchPageController(
                $container->get(Finder::class),
                $container->get(StemmerInterface::class),
                $container->get(DbLayer::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $container->getParameter('debug_view'),
                $provider->get('S2_TAGS_URL'),
            );
        });


        $container->set(ThumbnailGenerator::class, function (Container $container) {
            return new ThumbnailGenerator(
                $container->get(QueuePublisher::class),
                S2_PATH . '/' . S2_IMG_DIR,
                S2_IMG_PATH
            );
        });
        $container->set(LoggerInterface::class, function (Container $container) {
            return new Logger($container->getParameter('log_dir') . 'app.log', 'app', LogLevel::INFO);
        });
        $container->set('recommendations_logger', function (Container $container) {
            return new Logger($container->getParameter('log_dir') . 'recommendations.log', 'recommendations', LogLevel::INFO);
        });
        $container->set('recommendations_cache', function (Container $container) {
            return new FilesystemAdapter('recommendations', 0, $container->getParameter('cache_dir'));
        });
        $container->set('config_cache', function (Container $container) {
            return new FilesystemAdapter('config', 0, $container->getParameter('cache_dir'));
        });

        $container->set(DynamicConfigProvider::class, function (Container $container) {
            return new DynamicConfigProvider($container->get(DbLayer::class), $container->get('config_cache'), $container->getParameter('cache_dir'));
        });

        $container->set(QueuePublisher::class, function (Container $container) {
            return new QueuePublisher($container->get(\PDO::class));
        });
        $container->set(QueueConsumer::class, function (Container $container) {
            return new QueueConsumer(
                $container->get(\PDO::class),
                $container->get(LoggerInterface::class),
                $container->get(RecommendationProvider::class),
                $container->get(ThumbnailGenerator::class)
            );
        });
        $container->set(RecommendationProvider::class, function (Container $container) {
            return new RecommendationProvider(
                $container->get(PdoStorage::class),
                LayoutMatcherFactory::getFourColumns($container->get('recommendations_logger')),
                $container->get('recommendations_cache'),
                $container->get(QueuePublisher::class)
            );
        });

        $container->set(RequestStack::class, function (Container $container) {
            return new RequestStack();
        });

        $container->set(HtmlTemplateProvider::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new HtmlTemplateProvider(
                $container->get(RequestStack::class),
                $container->get(Viewer::class),
                $container->get(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class),
                $container->getParameter('debug'),
                $container->getParameter('debug_view'),
                $container->getParameter('root_dir'),
                $container->getParameter('cache_dir'),
                $provider->get('S2_STYLE'),
            );
        });

        $container->set(Viewer::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new Viewer(
                $container->getParameter('root_dir'),
                $provider->get('S2_STYLE'),
                $container->getParameter('debug_view')
            );
        });

        $container->set('strict_viewer', function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new Viewer(
                $container->getParameter('root_dir'),
                $provider->get('S2_STYLE'),
                false // no HTML debug info for XML and other non-HTML content
            );
        });

        $container->set(RedirectDetector::class, function (Container $container) {
            return new RedirectDetector(
                $container->getParameter('redirect_map')
            );
        });

        $container->set(NotFoundController::class, function (Container $container) {
            return new NotFoundController(
                $container->get(HtmlTemplateProvider::class),
            );
        });

        $container->set(PageFavorite::class, function (Container $container) {
            return new PageFavorite(
                $container->get(DbLayer::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
            );
        });

        $container->set(PageTags::class, function (Container $container) {
            return new PageTags(
                $container->get(DbLayer::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
            );
        });

        $container->set(PageTag::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new PageTag(
                $container->get(DbLayer::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $provider->get('S2_TAGS_URL'),
            );
        });

        $container->set(PageCommon::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new PageCommon(
                $container->get(DbLayer::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(RecommendationProvider::class),
                $container->get(Viewer::class),
                $provider->get('S2_USE_HIERARCHY') === '1',
                $provider->get('S2_SHOW_COMMENTS') === '1',
                $provider->get('S2_TAGS_URL'),
                (int)$provider->get('S2_MAX_ITEMS'),
                $container->getParameter('debug'),
            );
        });

        $container->set(Rss::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new Rss(
                $container->get('strict_viewer'),
                $container->getParameter('base_url'),
                $provider->get('S2_WEBMASTER'),
                $provider->get('S2_SITE_NAME'),
            );
        });

        $container->set(Sitemap::class, function (Container $container) {
            return new Sitemap(
                $container->get(DbLayer::class),
                $container->get('strict_viewer'),
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

        $eventDispatcher->addListener(TemplateEvent::EVENT_PRE_REPLACE, function (TemplateEvent $event) use ($container) {
            $pdo           = $container->getIfInstantiated(\PDO::class);
            $showQueries   = $container->getParameter('show_queries');
            $s2DebugOutput = '';
            if ($showQueries) {
                /** @var Viewer $viewer */
                $viewer        = $container->get(Viewer::class);
                $pdoLogs       = $pdo !== null ? $pdo->cleanLogs() : [];
                $s2DebugOutput = $viewer->render('debug_queries', [
                    'saved_queries' => $pdoLogs,
                ]);
            }
            $event->htmlTemplate->registerPlaceholder('<!-- s2_debug -->', $s2DebugOutput);
        });

        $eventDispatcher->addListener(TemplateFinalReplaceEvent::class, function (TemplateFinalReplaceEvent $event) use ($container) {
            global $s2_start;
            /** @var Pdo $pdo */
            $pdo = $container->getIfInstantiated(\PDO::class);
            if ($container->getParameter('debug') || defined('S2_SHOW_TIME')) {
                $time_placeholder = 't = ' . \Lang::number_format(microtime(true) - $s2_start, true, 3) . '; q = ' . ($pdo !== null ? $pdo->getQueryCount() : 0);
                $event->replace('<!-- s2_querytime -->', $time_placeholder);
            }
        }, -256);

    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
        $configProvider = $container->get(DynamicConfigProvider::class);
        $favoriteUrl    = $configProvider->get('S2_FAVORITE_URL');
        $tagsUrl        = $configProvider->get('S2_TAGS_URL');

        $routes->add('rss', new Route('/rss.xml', ['_controller' => Rss::class]));
        $routes->add('sitemap', new Route('/sitemap.xml', ['_controller' => Sitemap::class]));
        $routes->add('favorite', new Route('/' . $favoriteUrl . '{slash</?>}', ['_controller' => PageFavorite::class]));
        $routes->add('tags', new Route('/' . $tagsUrl . '{slash</?>}', ['_controller' => PageTags::class]));
        $routes->add('tag', new Route('/' . $tagsUrl . '/{name}{slash</?>}', ['_controller' => PageTag::class]));
        $routes->add('common', new Route('/{path<.*>}', ['_controller' => PageCommon::class]), -1); // -1 for last route
    }
}
