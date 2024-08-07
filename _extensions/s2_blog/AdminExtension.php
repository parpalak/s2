<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog;

use S2\AdminYard\TemplateRenderer;
use S2\AdminYard\Translator;
use S2\Cms\Admin\AdminConfigExtenderInterface;
use S2\Cms\Admin\Dashboard\DashboardStatProviderInterface;
use S2\Cms\Admin\DynamicConfigFormExtenderInterface;
use S2\Cms\Admin\Event\RedirectFromPublicEvent;
use S2\Cms\Admin\TranslationProviderInterface;
use S2\Cms\AdminYard\CustomMenuGeneratorEvent;
use S2\Cms\AdminYard\CustomTemplateRendererEvent;
use S2\Cms\AdminYard\Signal;
use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Model\PermissionChecker;
use S2\Cms\Model\TagsProvider;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Template\HtmlTemplateProvider;
use s2_extensions\s2_blog\Admin\AdminConfigExtender;
use s2_extensions\s2_blog\Admin\DashboardBlogProvider;
use s2_extensions\s2_blog\Admin\DynamicConfigFormExtender;
use s2_extensions\s2_blog\Admin\PathToAdminEntityConverter;
use s2_extensions\s2_blog\Admin\TranslationProvider;
use s2_extensions\s2_blog\Model\BlogCommentNotifier;
use s2_extensions\s2_blog\Model\BlogCommentProvider;
use s2_extensions\s2_blog\Model\PostProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;

class AdminExtension implements ExtensionInterface
{
    public function buildContainer(Container $container): void
    {
        $container->set(AdminConfigExtender::class, function (Container $container) {
            return new AdminConfigExtender(
                $container->get(PermissionChecker::class),
                $container->get(HtmlTemplateProvider::class),
                $container->get(Translator::class),
                $container->get(TagsProvider::class),
                $container->get(PostProvider::class),
                $container->get(BlogUrlBuilder::class),
                $container->get(BlogCommentNotifier::class),
                $container->get(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class),
                $container->getParameter('db_type'),
                $container->getParameter('db_prefix'),
            );
        }, [AdminConfigExtenderInterface::class]);

        $container->set(DynamicConfigFormExtender::class, function (Container $container) {
            return new DynamicConfigFormExtender();
        }, [DynamicConfigFormExtenderInterface::class]);

        $container->set(TranslationProvider::class, function (Container $container) {
            return new TranslationProvider();
        }, [TranslationProviderInterface::class]);

        $container->set(DashboardBlogProvider::class, function (Container $container) {
            return new DashboardBlogProvider(
                $container->get(TemplateRenderer::class),
                $container->get(DbLayer::class),
                $container->getParameter('root_dir')
            );
        }, [DashboardStatProviderInterface::class]);

        $container->set(BlogCommentProvider::class, function (Container $container) {
            return new BlogCommentProvider($container->get(DbLayer::class));
        });

        $container->set(PathToAdminEntityConverter::class, function (Container $container) {
            $provider = $container->get(DynamicConfigProvider::class);
            return new PathToAdminEntityConverter(
                $container->get(DbLayer::class),
                $provider->get('S2_BLOG_URL'),
            );
        });
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        $eventDispatcher->addListener(CustomTemplateRendererEvent::class, function (CustomTemplateRendererEvent $event) use ($container) {
            $event->extraStyles[] = $event->basePath . '/_extensions/s2_blog/admin.css';
        });

        $eventDispatcher->addListener(CustomMenuGeneratorEvent::class, function (CustomMenuGeneratorEvent $event) use ($container) {
            /** @var BlogCommentProvider $blogCommentProvider */
            $blogCommentProvider = $container->get(BlogCommentProvider::class);
            $size                = $blogCommentProvider->getPendingCommentsCount();

            if ($size > 0) {
                $event->addSignal('BlogComment', new Signal((string)$size, 'Blog new comments', '?entity=BlogComment&action=list&status=0&apply_filter=0'));
            }
        });

        $eventDispatcher->addListener(RedirectFromPublicEvent::class, function (RedirectFromPublicEvent $event) use ($container) {
            /** @var PathToAdminEntityConverter $converter */
            $converter   = $container->get(PathToAdminEntityConverter::class);
            $queryParams = $converter->getQueryParams($event->path);
            if ($queryParams !== null) {
                foreach ($queryParams as $key => $param) {
                    $event->request->query->set($key, $param);
                }
                $event->stopPropagation();
            }
        });
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
    }
}
