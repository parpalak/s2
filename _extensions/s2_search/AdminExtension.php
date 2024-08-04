<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search;

use Psr\Log\LoggerInterface;
use S2\AdminYard\TemplateRenderer;
use S2\AdminYard\Translator;
use S2\Cms\Admin\Dashboard\DashboardStatProviderInterface;
use S2\Cms\Admin\DynamicConfigFormExtenderInterface;
use S2\Cms\Admin\Event\AdminAjaxControllerMapEvent;
use S2\Cms\Admin\Event\VisibleEntityChangedEvent;
use S2\Cms\Admin\TranslationProviderInterface;
use S2\Cms\AdminYard\CustomMenuGeneratorEvent;
use S2\Cms\AdminYard\Signal;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Model\PermissionChecker;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Queue\QueuePublisher;
use S2\Rose\Indexer;
use S2\Rose\Storage\Database\PdoStorage;
use s2_extensions\s2_search\Admin\DashboardSearchProvider;
use s2_extensions\s2_search\Admin\DynamicConfigFormExtender;
use s2_extensions\s2_search\Admin\IndexManager;
use s2_extensions\s2_search\Admin\TranslationProvider;
use s2_extensions\s2_search\Service\ArticleBulkIndexingProvider;
use s2_extensions\s2_search\Service\BulkIndexingProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;

class AdminExtension implements ExtensionInterface
{
    public function buildContainer(Container $container): void
    {
        $container->set(DynamicConfigFormExtender::class, function (Container $container) {
            return new DynamicConfigFormExtender();
        }, [DynamicConfigFormExtenderInterface::class]);

        $container->set(TranslationProvider::class, function (Container $container) {
            return new TranslationProvider();
        }, [TranslationProviderInterface::class]);

        $container->set(DashboardSearchProvider::class, function (Container $container) {
            return new DashboardSearchProvider(
                $container->get(TemplateRenderer::class),
                $container->get(PdoStorage::class),
                $container->getParameter('root_dir')
            );
        }, [DashboardStatProviderInterface::class]);

        $container->set(ArticleBulkIndexingProvider::class, function (Container $container) {
            return new ArticleBulkIndexingProvider($container->get(DbLayer::class));
        }, [BulkIndexingProviderInterface::class]);

        $container->set(IndexManager::class, function (Container $container) {
            return new IndexManager(
                $container->getParameter('cache_dir'),
                $container->get(Indexer::class),
                $container->get(PdoStorage::class),
                $container->get('recommendations_cache'),
                $container->get(LoggerInterface::class),
                ...$container->getByTag(BulkIndexingProviderInterface::class),
            );
        });
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        $eventDispatcher->addListener(VisibleEntityChangedEvent::class, function (VisibleEntityChangedEvent $event) use ($container) {
            /** @var QueuePublisher $queuePublisher */
            $queuePublisher = $container->get(QueuePublisher::class);
            $queuePublisher->publish((string)$event->entityId, 's2_search_' . $event->entityName);
        });

        $eventDispatcher->addListener(AdminAjaxControllerMapEvent::class, function (AdminAjaxControllerMapEvent $event) use ($container) {
            $event->controllerMap['s2_search_makeindex'] = static function (PermissionChecker $p, Request $r, Container $c) {
                if (!$p->isGrantedAny(PermissionChecker::PERMISSION_CREATE_ARTICLES, PermissionChecker::PERMISSION_EDIT_SITE)) {
                    return new JsonResponse(['success' => false, 'message' => 'Permission denied.'], Response::HTTP_FORBIDDEN);
                }

                /** @var IndexManager $indexManager */
                $indexManager = $c->get(IndexManager::class);
                return new JsonResponse([
                    'success' => true,
                    'status'  => $indexManager->index(),
                ]);
            };
        });

        $eventDispatcher->addListener(CustomMenuGeneratorEvent::class, function (CustomMenuGeneratorEvent $event) use ($container) {
            try {
                /** @var PdoStorage $pdoStorage */
                $pdoStorage = $container->get(PdoStorage::class);
                $size       = $pdoStorage->getTocSize(null);
            } catch (\Exception $e) {
                $size = 0;
            }

            if ($size === 0) {
                /** @var Translator $translator */
                $translator = $container->get(Translator::class);
                $event->addSignal('Dashboard', Signal::createEmpty($translator->trans('Indexing required')));
            }
        });
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
    }
}
