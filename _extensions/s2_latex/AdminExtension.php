<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_latex
 */

declare(strict_types=1);

namespace s2_extensions\s2_latex;

use S2\Cms\AdminYard\CustomTemplateRendererEvent;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;

class AdminExtension implements ExtensionInterface
{
    public function buildContainer(Container $container): void
    {
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        $eventDispatcher->addListener(CustomTemplateRendererEvent::class, static function (CustomTemplateRendererEvent $event) {
            $event->extraScripts[] = $event->basePath . '/_extensions/s2_latex/admin/preview.js';
        });
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
    }
}
