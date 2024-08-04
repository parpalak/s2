<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_counter
 */

declare(strict_types=1);

namespace s2_extensions\s2_counter;

use S2\AdminYard\TemplateRenderer;
use S2\AdminYard\Translator;
use S2\Cms\Admin\Dashboard\DashboardBlockProviderInterface;
use S2\Cms\Admin\TranslationProviderInterface;
use S2\Cms\AdminYard\CustomMenuGeneratorEvent;
use S2\Cms\AdminYard\Signal;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use s2_extensions\s2_counter\Admin\DashboardCounterProvider;
use s2_extensions\s2_counter\Admin\TranslationProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;

class AdminExtension implements ExtensionInterface
{
    public function buildContainer(Container $container): void
    {
        $container->set(DashboardCounterProvider::class, static fn(Container $container) => new DashboardCounterProvider(
            $container->get(TemplateRenderer::class),
            $container->getParameter('root_dir'),
        ), [DashboardBlockProviderInterface::class]);

        $container->set(
            TranslationProvider::class,
            static fn(Container $container) => new TranslationProvider(),
            [TranslationProviderInterface::class]
        );
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        $eventDispatcher->addListener(CustomMenuGeneratorEvent::class, function (CustomMenuGeneratorEvent $event) use ($container) {
            if (!is_writable($container->getParameter('root_dir') . '_extensions/s2_counter/data/')) {
                /** @var Translator $translator */
                $translator = $container->get(Translator::class);
                $event->addSignal('Dashboard', Signal::createEmpty(
                    $translator->trans('Data folder not writable', [
                        '{{ dir }}' => $container->getParameter('base_path') . '/_extensions/s2_counter/data/',
                    ])
                ));
            }
        });
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
    }
}
