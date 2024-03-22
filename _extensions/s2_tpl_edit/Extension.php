<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package s2_tpl_edit
 */

declare(strict_types=1);

namespace s2_extensions\s2_tpl_edit;

use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Template\TemplateBuildEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;

class Extension implements ExtensionInterface
{
    private const FILENAME_INFIX = 's2_tpl_edit_';

    public function buildContainer(Container $container): void
    {
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        $eventDispatcher->addListener(TemplateBuildEvent::EVENT_START, function (TemplateBuildEvent $event) use ($container) {
            $filename = $this->getTemplateFilename($container, $event);
            if (file_exists($filename)) {
                $event->path = $filename;
            }
        });

        $eventDispatcher->addListener(TemplateBuildEvent::EVENT_END, function (TemplateBuildEvent $event) use ($container) {
            if (!str_contains($event->path, self::FILENAME_INFIX)) {
                copy($event->path, $this->getTemplateFilename($container, $event));
            }
        });
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
    }

    private function getTemplateFilename(Container $container, TemplateBuildEvent $event): string
    {
        return $container->getParameter('cache_dir') . self::FILENAME_INFIX . $event->styleName . '_' . $event->templateId;
    }
}
