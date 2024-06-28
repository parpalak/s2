<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   MIT
 * @package   s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search;

use Psr\Log\LoggerInterface;
use S2\Cms\Asset\AssetPack;
use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Queue\QueueHandlerInterface;
use S2\Cms\Rose\CustomExtractor;
use S2\Cms\Template\TemplateAssetEvent;
use S2\Cms\Template\TemplateEvent;
use S2\Rose\Extractor\ExtractorInterface;
use S2\Rose\Indexer;
use S2\Rose\Stemmer\StemmerInterface;
use S2\Rose\Storage\Database\PdoStorage;
use s2_extensions\s2_search\Controller\SearchPageController;
use s2_extensions\s2_search\Service\ArticleIndexer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class Extension implements ExtensionInterface
{
    public function buildContainer(Container $container): void
    {
        $container->set(Indexer::class, function (Container $container) {
            return new Indexer(
                $container->get(PdoStorage::class),
                $container->get(StemmerInterface::class),
                $container->get(ExtractorInterface::class),
                $container->get(LoggerInterface::class),
            );
        });

        $container->set(ArticleIndexer::class, function (Container $container) {
            return new ArticleIndexer(
                $container->get(DbLayer::class),
                $container->get(ArticleProvider::class),
                $container->get(Indexer::class),
                $container->get('recommendations_cache'),
            );
        }, [QueueHandlerInterface::class]);

        $container->set(ExtractorInterface::class, function (Container $container) {
            // TODO move CustomExtractor to s2_search package
            return new CustomExtractor($container->get(LoggerInterface::class));
        });
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        $eventDispatcher->addListener(TemplateEvent::EVENT_CREATED, function (TemplateEvent $event) {
            \Lang::load('s2_search', function () {
                if (file_exists(S2_ROOT . '/_extensions/s2_search' . '/lang/' . S2_LANGUAGE . '.php'))
                    return require S2_ROOT . '/_extensions/s2_search' . '/lang/' . S2_LANGUAGE . '.php';
                else
                    return require S2_ROOT . '/_extensions/s2_search' . '/lang/English.php';
            });
            $event->htmlTemplate->registerPlaceholder('<!-- s2_search_field -->', '<form class="s2_search_form" method="get" action="' . (S2_URL_PREFIX ? S2_PATH . S2_URL_PREFIX : S2_PATH . '/search') . '">' . (S2_URL_PREFIX ? '<input type="hidden" name="search" value="1" />' : '') . '<input type="text" name="q" id="s2_search_input" placeholder="' . \Lang::get('Search', 's2_search') . '"/></form>');
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
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
        $routes->add('search', new Route('/search', ['_controller' => SearchPageController::class]));

        // Hack for alternative URL schemes
        $routes->add('search2', new Route('/', ['_controller' => SearchPageController::class], condition: "request.query.get('search') !== null"));
    }
}
