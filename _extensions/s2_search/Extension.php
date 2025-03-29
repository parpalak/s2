<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use S2\Cms\Asset\AssetPack;
use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Image\ThumbnailGenerateEvent;
use S2\Cms\Image\ThumbnailGenerator;
use S2\Cms\Logger\Logger;
use S2\Cms\Model\Article\ArticleRenderedEvent;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Queue\QueueHandlerInterface;
use S2\Cms\Queue\QueuePublisher;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\TemplateAssetEvent;
use S2\Cms\Template\TemplateEvent;
use S2\Cms\Template\Viewer;
use S2\Cms\Translation\ExtensibleTranslator;
use S2\Rose\Entity\ExternalId;
use S2\Rose\Extractor\ExtractorInterface;
use S2\Rose\Finder;
use S2\Rose\Indexer;
use S2\Rose\Stemmer\PorterStemmerEnglish;
use S2\Rose\Stemmer\PorterStemmerRussian;
use S2\Rose\Stemmer\StemmerInterface;
use S2\Rose\Storage\Database\PdoStorage;
use s2_extensions\s2_search\Controller\SearchPageController;
use s2_extensions\s2_search\Layout\LayoutMatcherFactory;
use s2_extensions\s2_search\Rose\CustomExtractor;
use s2_extensions\s2_search\Service\ArticleIndexer;
use s2_extensions\s2_search\Service\RecommendationProvider;
use s2_extensions\s2_search\Service\SimilarWordsDetector;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Contracts\Translation\TranslatorInterface;

class Extension implements ExtensionInterface
{
    public static function PdoStorageFactory(Container $container): PdoStorage {
        return new PdoStorage($container->get(\PDO::class), $container->getParameter('db_prefix') . 's2_search_idx_');
    }

    public function buildContainer(Container $container): void
    {
        $container->set(PdoStorage::class, self::PdoStorageFactory(...));
        $container->set(StemmerInterface::class, function (Container $container) {
            return new PorterStemmerRussian(new PorterStemmerEnglish());
        });
        $container->set(Finder::class, function (Container $container) {
            return (new Finder($container->get(PdoStorage::class), $container->get(StemmerInterface::class)))
                ->setHighlightTemplate('<span class="s2_search_highlight">%s</span>')
                ->setSnippetLineSeparator(' ⋄&nbsp;')
            ;
        });

        // Note: Indexing is performed in the QueueConsumer, so it cannot be moved to AdminExtension right now.
        $container->set(Indexer::class, static function (Container $container) {
            return new Indexer(
                $container->get(PdoStorage::class),
                $container->get(StemmerInterface::class),
                $container->get(ExtractorInterface::class),
                $container->get(LoggerInterface::class),
            );
        });

        $container->set(ArticleIndexer::class, static function (Container $container) {
            return new ArticleIndexer(
                $container->get(DbLayer::class),
                $container->get(ArticleProvider::class),
                $container->get(Indexer::class),
                $container->get('recommendations_cache'),
                $container->get(QueuePublisher::class),
            );
        }, [QueueHandlerInterface::class]);

        $container->set(ExtractorInterface::class, static function (Container $container) {
            return new CustomExtractor(
                $container->get(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class),
                $container->get(LoggerInterface::class),
            );
        });

        $container->set('s2_search_translator', static function (Container $container) {
            /** @var ExtensibleTranslator $translator */
            $translator = $container->get('translator');
            $translator->attachLoader('s2_search', static function (string $lang) {
                return require ($dir = __DIR__ . '/lang/') . (file_exists($dir . $lang . '.php') ? $lang : 'English') . '.php';
            });

            return $translator;
        });

        $container->set(SimilarWordsDetector::class, static function (Container $container) {
            return new SimilarWordsDetector(
                $container->get(StemmerInterface::class),
            );
        });

        $container->set(SearchPageController::class, static function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new SearchPageController(
                $container->get(Finder::class),
                $container->get(StemmerInterface::class),
                $container->get(ThumbnailGenerator::class),
                $container->get(SimilarWordsDetector::class),
                $container->get(DbLayer::class),
                $container->get(ArticleProvider::class),
                $container->get(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class),
                $container->get('s2_search_translator'),
                $container->get(UrlBuilder::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Viewer::class),
                $container->getParameter('debug_view'),
                $provider->get('S2_TAGS_URL'),
                (int)$provider->get('S2_MAX_ITEMS'),
            );
        });

        $container->set('recommendations_logger', function (Container $container) {
            return new Logger($container->getParameter('log_dir') . 'recommendations.log', 'recommendations', LogLevel::INFO);
        });
        $container->set('recommendations_cache', function (Container $container) {
            return new FilesystemAdapter('recommendations', 0, $container->getParameter('cache_dir'));
        });
        $container->set(RecommendationProvider::class, function (Container $container) {
            return new RecommendationProvider(
                $container->get(PdoStorage::class),
                LayoutMatcherFactory::getFourColumns($container->get('recommendations_logger')),
                $container->get('recommendations_cache'),
                $container->get(QueuePublisher::class)
            );
        }, [QueueHandlerInterface::class]);
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        $eventDispatcher->addListener(TemplateEvent::EVENT_CREATED, static function (TemplateEvent $event) use ($container) {
            /** @var TranslatorInterface $translator */
            $translator = $container->get('s2_search_translator');
            /** @var UrlBuilder $urlBuilder */
            $urlBuilder = $container->get(UrlBuilder::class);
            $event->htmlTemplate->registerPlaceholder(
                '<!-- s2_search_field -->',
                '<form class="s2_search_form" method="get" action="' . $urlBuilder->link('/search') . '">'
                . ($urlBuilder->hasPrefix() ? '<input type="hidden" name="search" value="1" />' : '')
                . '<input type="text" name="q" id="s2_search_input" placeholder="' . $translator->trans('Search') . '"/></form>'
            );
        });

        $eventDispatcher->addListener(TemplateAssetEvent::class, static function (TemplateAssetEvent $event) use ($container) {
            $event->assetPack->addCss('../../_extensions/s2_search/style.css', [AssetPack::OPTION_MERGE]);
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            if ($provider->get('S2_SEARCH_QUICK') === '1') {
                $event->assetPack
                    ->addJs('../../_extensions/s2_search/autosearch.js', [AssetPack::OPTION_MERGE])
                    ->addInlineJs('<script>var s2_search_url = "' . S2_PATH . '/_extensions/s2_search";</script>')
                ;
            }
        });

        $eventDispatcher->addListener(ArticleRenderedEvent::class, static function (ArticleRenderedEvent $event) use ($container) {
            if ($event->template->hasPlaceholder('<!-- s2_recommendations -->')) {
                /** @var RecommendationProvider $recommendationProvider */
                $recommendationProvider = $container->get(RecommendationProvider::class);
                /** @var RequestStack $requestStack */
                $requestStack = $container->get(RequestStack::class);
                $request_uri  = $requestStack->getCurrentRequest()?->getPathInfo();
                [$recommendations, $log, $rawRecommendations] = $recommendationProvider->getRecommendations($request_uri, new ExternalId($event->articleId));

                /** @var Viewer $viewer */
                $viewer = $container->get(Viewer::class);

                $event->template->putInPlaceholder('recommendations', $viewer->render('recommendations', [
                    'raw'     => $rawRecommendations,
                    'content' => $recommendations,
                    'log'     => $log,
                ], 's2_search'));
            }
        });

        // Thumbnails in search results page
        $eventDispatcher->addListener(ThumbnailGenerateEvent::class, static function (ThumbnailGenerateEvent $event) {
            $maxWidth  = $event->maxWidth;
            $maxHeight = $event->maxHeight;
            $src       = $event->src;

            if (str_starts_with($src, CustomExtractor::YOUTUBE_PROTOCOL)) {
                $src = 'https://img.youtube.com/vi/' . substr($src, \strlen(CustomExtractor::YOUTUBE_PROTOCOL)) . '/mqdefault.jpg';

                $sizeArray = ThumbnailGenerator::reduceSize('320', '180', $maxWidth, $maxHeight);

                $event->setResult(\sprintf('<span class="video-thumbnail"><img src="%s" width="%s" height="%s" alt=""></span>', $src, ...$sizeArray));
            }
        });
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
        $routes->add('search', new Route('/search', ['_controller' => SearchPageController::class]));

        // Hack for alternative URL schemes
        $routes->add('search2', new Route('/', ['_controller' => SearchPageController::class], condition: "request.query.get('search') !== null"));
    }
}
