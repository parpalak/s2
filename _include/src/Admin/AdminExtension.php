<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin;

use Psr\Log\LoggerInterface;
use S2\AdminYard\AdminPanel;
use S2\AdminYard\Database\PdoDataProvider;
use S2\AdminYard\Database\TypeTransformer;
use S2\AdminYard\Form\FormControlFactoryInterface;
use S2\AdminYard\Form\FormFactory;
use S2\AdminYard\MenuGenerator;
use S2\AdminYard\TemplateRenderer;
use S2\AdminYard\Transformer\ViewTransformer;
use S2\AdminYard\Translator;
use S2\Cms\Admin\Dashboard\DashboardArticleProvider;
use S2\Cms\Admin\Dashboard\DashboardBlockProviderInterface;
use S2\Cms\Admin\Dashboard\DashboardConfigExtender;
use S2\Cms\Admin\Dashboard\DashboardDatabaseProvider;
use S2\Cms\Admin\Dashboard\DashboardEnvironmentProvider;
use S2\Cms\Admin\Dashboard\DashboardStatProviderInterface;
use S2\Cms\AdminYard\CustomTemplateRenderer;
use S2\Cms\AdminYard\Form\CustomFormControlFactory;
use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Extensions\ExtensionManager;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Model\ArticleManager;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\CommentNotifier;
use S2\Cms\Model\ExtensionCache;
use S2\Cms\Model\TagsProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Rose\CustomExtractor;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Translation\TranslationProviderInterface;
use S2\Rose\Extractor\ExtractorInterface;
use S2\Rose\Indexer;
use S2\Rose\Stemmer\StemmerInterface;
use S2\Rose\Storage\Database\PdoStorage;
use s2_extensions\s2_search\Fetcher;
use s2_extensions\s2_search\IndexManager;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouteCollection;

class AdminExtension implements ExtensionInterface
{
    public function buildContainer(Container $container): void
    {
        // TODO move to s2_search
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

        $container->set(FormControlFactoryInterface::class, function (Container $container) {
            return new CustomFormControlFactory();
        });

        // Helpers
        $container->set(CommentNotifier::class, function (Container $container) {
            return new CommentNotifier(
                $container->get(DbLayer::class),
                $container->get(ArticleProvider::class),
                $container->get(UrlBuilder::class),
                $container->getParameter('base_url'),
            );
        });

        // AdminYard services
        $container->set(TypeTransformer::class, function (Container $container) {
            return new TypeTransformer();
        });

        $container->set(PdoDataProvider::class, function (Container $container) {
            return new PdoDataProvider(
                $container->get(\PDO::class),
                $container->get(TypeTransformer::class),
            );
        });

        $container->set(TranslationProvider::class, function (Container $container) {
            return new TranslationProvider($container->getParameter('root_dir'));
        }, [TranslationProviderInterface::class]);

        $container->set(Translator::class, function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            $language = $provider->get('S2_LANGUAGE');

            // TODO move mapping somewhere
            $locale       = match ($language) {
                'Russian' => 'ru',
                'English' => 'en',
                default => throw new \LogicException('Unsupported language yet'),
            };
            $translations = [];
            foreach ($container->getByTag(TranslationProviderInterface::class) as $translationProvider) {
                /** @var TranslationProviderInterface $translationProvider */
                $translations[] = $translationProvider->getTranslations($language, $locale);
            }
            return new Translator(array_merge(...$translations), $locale);
        });

        $container->set(FormFactory::class, function (Container $container) {
            return new FormFactory(
                $container->get(FormControlFactoryInterface::class),
                $container->get(Translator::class),
                $container->get(PdoDataProvider::class),
            );
        });

        $container->set(TemplateRenderer::class, function (Container $container) {
            return new CustomTemplateRenderer(
                $container->get(Translator::class),
                $container->getParameter('base_path'),
            );
        });

        $container->set(MenuGenerator::class, function (Container $container) {
            /** @var AdminConfigProvider $adminConfigProvider */
            $adminConfigProvider = $container->get(AdminConfigProvider::class);
            $adminConfig         = $adminConfigProvider->getAdminConfig();

            return new MenuGenerator(
                $adminConfig,
                $container->get(TemplateRenderer::class)
            );
        });

        $container->set(DynamicConfigFormBuilder::class, function (Container $container) {
            return new DynamicConfigFormBuilder(
                $container->get(Translator::class),
                $container->get(TypeTransformer::class),
                $container->get(FormFactory::class),
                $container->get(TemplateRenderer::class),
                $container->get(RequestStack::class),
                $container->getParameter('root_dir'),
                ...$container->getByTag(DynamicConfigFormExtenderInterface::class),
            );
        });

        $container->set(AdminConfigProvider::class, function (Container $container) {
            $dbType   = $container->getParameter('db_type');
            $dbPrefix = $container->getParameter('db_prefix');
            return new AdminConfigProvider(
                $container->get(HtmlTemplateProvider::class),
                $container->get(DynamicConfigFormBuilder::class),
                $container->get(DynamicConfigProvider::class),
                $container->get(Translator::class),
                $container->get(ArticleProvider::class),
                $container->get(TagsProvider::class),
                $container->get(UrlBuilder::class),
                $container->get(CommentNotifier::class),
                $container->get(ExtensionCache::class),
                $dbType,
                $dbPrefix,
                ...$container->getByTag(AdminConfigExtenderInterface::class)
            );
        });

        $container->set(AdminPanel::class, function (Container $container) {
            /** @var AdminConfigProvider $adminConfigProvider */
            $adminConfigProvider = $container->get(AdminConfigProvider::class);
            $adminConfig         = $adminConfigProvider->getAdminConfig();

            $eventDispatcher = new EventDispatcher();
            foreach ($adminConfig->getEntities() as $entityConfig) {
                foreach ($entityConfig->getListeners() as $eventName => $listeners) {
                    foreach ($listeners as $listener) {
                        $eventDispatcher->addListener('adminyard.' . $eventName, $listener);
                    }
                }
            }

            return new AdminPanel(
                $adminConfig,
                $eventDispatcher,
                $container->get(PdoDataProvider::class),
                new ViewTransformer(),
                $container->get(MenuGenerator::class),
                $container->get(Translator::class),
                $container->get(TemplateRenderer::class),
                $container->get(FormFactory::class),
            );
        });

        // Structure page
        $container->set(ArticleManager::class, function (Container $container) {
            return new ArticleManager(
                $container->get(DbLayer::class),
                $container->get(RequestStack::class),
            );
        });

        $container->set(SiteStructureExtender::class, function (Container $container) {
            return new SiteStructureExtender(
                $container->get(TemplateRenderer::class),
            );
        }, [AdminConfigExtenderInterface::class]);

        // Extensions
        $container->set(ExtensionManager::class, function (Container $container) {
            return new ExtensionManager(
                $container->get(DbLayer::class),
                $container->get(Translator::class),
                $container->get(TemplateRenderer::class),
                $container->getParameter('root_dir'),
            );
        }, [AdminConfigExtenderInterface::class]);

        // Dashboard providers
        $container->set(DashboardConfigExtender::class, function (Container $container) {
            return new DashboardConfigExtender(
                $container->getByTag(DashboardStatProviderInterface::class),
                $container->getByTag(DashboardBlockProviderInterface::class),
                $container->get(TemplateRenderer::class),
            );
        }, [AdminConfigExtenderInterface::class]);
        $container->set(DashboardEnvironmentProvider::class, function (Container $container) {
            return new DashboardEnvironmentProvider(
                $container->get(Translator::class),
                $container->get(TemplateRenderer::class),
                $container->getParameter('root_dir'),
            );
        }, [DashboardStatProviderInterface::class]);

        $container->set(DashboardDatabaseProvider::class, function (Container $container) {
            return new DashboardDatabaseProvider(
                $container->get(TemplateRenderer::class),
                $container->get(DbLayer::class),
                $container->getParameter('db_type'),
                $container->getParameter('db_name'),
                $container->getParameter('db_prefix'),
                $container->getParameter('root_dir'),
            );
        }, [DashboardStatProviderInterface::class]);

        $container->set(DashboardArticleProvider::class, function (Container $container) {
            return new DashboardArticleProvider(
                $container->get(TemplateRenderer::class),
                $container->get(DbLayer::class),
                $container->getParameter('root_dir'),
            );
        }, [DashboardStatProviderInterface::class]);
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        // TODO: Implement registerListeners() method.
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
        // TODO: Implement registerRoutes() method.
    }

}