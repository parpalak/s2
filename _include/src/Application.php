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
use S2\Cms\Framework\Container;
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
use S2\Rose\Extractor\ExtractorInterface;
use S2\Rose\Finder;
use S2\Rose\Indexer;
use S2\Rose\Stemmer\PorterStemmerEnglish;
use S2\Rose\Stemmer\PorterStemmerRussian;
use S2\Rose\Stemmer\StemmerInterface;
use S2\Rose\Storage\Database\PdoStorage;
use s2_extensions\s2_search\Fetcher;
use s2_extensions\s2_search\IndexManager;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class Application
{
    public Framework\Container $container;
    private ?RouteCollection $routes = null;

    public function boot(): void
    {
        $this->buildContainer();
    }

    private function buildContainer(): void
    {
        $this->container = new Container($this->loadParameters());

        \Container::setContainer($this->container);

        $this->container->set(DbLayer::class, function (Container $container) {
            $db_prefix = $container->getParameter('db_prefix');
            $db_type   = $container->getParameter('db_type');

            return match ($db_type) {
                'mysql' => new DbLayer($container->get(\PDO::class), $db_prefix),
                'sqlite' => new DbLayerSqlite($container->get(\PDO::class), $db_prefix),
                'pgsql' => new DbLayerPostgres($container->get(\PDO::class), $db_prefix),
                default => throw new \RuntimeException(sprintf('Unsupported db_type="%s"', $db_type)),
            };
        });
        $this->container->set(\PDO::class, function (Container $container) {
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
        $this->container->set(PdoStorage::class, function (Container $container) {
            return new PdoStorage($container->get(\PDO::class), $container->getParameter('db_prefix') . 's2_search_idx_');
        });
        $this->container->set(StemmerInterface::class, function (Container $container) {
            return new PorterStemmerRussian(new PorterStemmerEnglish());
        });
        $this->container->set(Finder::class, function (Container $container) {
            return (new Finder($container->get(PdoStorage::class), $container->get(StemmerInterface::class)))
                ->setHighlightTemplate('<span class="s2_search_highlight">%s</span>')
                ->setSnippetLineSeparator(' ⋄&nbsp;')
            ;
        });
        $this->container->set(Indexer::class, function (Container $container) {
            return new Indexer(
                $container->get(PdoStorage::class),
                $container->get(StemmerInterface::class),
                $container->get(ExtractorInterface::class),
                $container->get(LoggerInterface::class),
            );
        });
        $this->container->set(IndexManager::class, function (Container $container) {
            return new IndexManager(
                $container->getParameter('cache_dir'),
                new Fetcher($container->get(DbLayer::class)),
                $container->get(Indexer::class),
                $container->get(PdoStorage::class),
                $container->get('recommendations_cache'),
                $container->get(LoggerInterface::class)
            );
        });
        $this->container->set(ExtractorInterface::class, function (Container $container) {
            return new CustomExtractor($container->get(LoggerInterface::class));
        });

        $this->container->set(ThumbnailGenerator::class, function (Container $container) {
            return new ThumbnailGenerator(
                $container->get(QueuePublisher::class),
                S2_PATH . '/' . S2_IMG_DIR,
                S2_IMG_PATH
            );
        });
        $this->container->set(LoggerInterface::class, function (Container $container) {
            return new Logger($container->getParameter('log_dir') . 'app.log', 'app', LogLevel::INFO);
        });
        $this->container->set('recommendations_logger', function (Container $container) {
            return new Logger($container->getParameter('log_dir') . 'recommendations.log', 'recommendations', LogLevel::INFO);
        });
        $this->container->set('recommendations_cache', function (Container $container) {
            return new FilesystemAdapter('recommendations', 0, $container->getParameter('cache_dir'));
        });
        $this->container->set('config_cache', function (Container $container) {
            return new FilesystemAdapter('config', 0, $container->getParameter('cache_dir'));
        });

        $this->container->set(DynamicConfigProvider::class, function (Container $container) {
            return new DynamicConfigProvider($container->get(DbLayer::class), $container->get('config_cache'), $container->getParameter('cache_dir'));
        });

        $this->container->set(QueuePublisher::class, function (Container $container) {
            return new QueuePublisher($container->get(\PDO::class));
        });
        $this->container->set(QueueConsumer::class, function (Container $container) {
            return new QueueConsumer(
                $container->get(\PDO::class),
                $container->get(LoggerInterface::class),
                $container->get(RecommendationProvider::class),
                $container->get(ThumbnailGenerator::class)
            );
        });
        $this->container->set(RecommendationProvider::class, function (Container $container) {
            return new RecommendationProvider(
                $container->get(PdoStorage::class),
                LayoutMatcherFactory::getFourColumns($container->get('recommendations_logger')),
                $container->get('recommendations_cache'),
                $container->get(QueuePublisher::class)
            );
        });

        $this->container->set(HtmlTemplateProvider::class, function (Container $container) {
            return new HtmlTemplateProvider();
        });

        $this->container->set(NotFoundController::class, function (Container $container) {
            return new NotFoundController($container->get(HtmlTemplateProvider::class), $container->getParameter('redirect_map'));
        });
    }

    private function addRoutes(): void
    {
        $routes = new RouteCollection();

        $configProvider = $this->container->get(DynamicConfigProvider::class);
        $favoriteUrl    = $configProvider->get('S2_FAVORITE_URL');
        $tagsUrl        = $configProvider->get('S2_TAGS_URL');

        ($hook = s2_hook('idx_new_routes')) ? eval($hook) : null;

        $routes->add('rss', new Route('/rss.xml', ['_controller' => \Page_RSS::class]));
        $routes->add('sitemap', new Route('/sitemap.xml', ['_controller' => \Page_Sitemap::class]));
        $routes->add('favorite', new Route('/' . $favoriteUrl . '{slash</?>}', ['_controller' => \Page_Favorite::class]));
        $routes->add('tags', new Route('/' . $tagsUrl . '{slash</?>}', ['_controller' => \Page_Tags::class]));
        $routes->add('tag', new Route('/' . $tagsUrl . '/{name}{slash</?>}', ['_controller' => \Page_Tag::class]));
        $routes->add('common', new Route('/{path<.*>}', ['_controller' => \Page_Common::class]));

        $this->routes = $routes;
    }

    public function matchRequest(Request $request): array
    {
        if ($this->routes === null) {
            $this->addRoutes();
        }

        $context = new RequestContext();
        $context->fromRequest($request);

        $matcher = new UrlMatcher($this->routes, $context);

        $attributes = $matcher->matchRequest($request);
        $request->attributes->add($attributes);

        return $attributes;
    }

    private function loadParameters(): array
    {
        $result = [
            'cache_dir'    => S2_CACHE_DIR,
            'log_dir'      => (defined('S2_LOG_DIR') ? S2_LOG_DIR : S2_CACHE_DIR),
            'base_url'     => defined('S2_BASE_URL') ? S2_BASE_URL : null,
            'redirect_map' => $GLOBALS['s2_redirect'] ?? [],
        ];

        foreach (['db_type', 'db_host', 'db_name', 'db_username', 'db_password', 'db_prefix', 'p_connect'] as $globalVarName) {
            $result[$globalVarName] = $GLOBALS[$globalVarName] ?? null;
        }

        return $result;
    }
}
