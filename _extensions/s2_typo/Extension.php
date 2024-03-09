<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace s2_extensions\s2_typo;

use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Template\TemplateFinalReplaceEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;

class Extension implements ExtensionInterface
{
    public function buildContainer(Container $container): void
    {
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        $eventDispatcher->addListener(TemplateFinalReplaceEvent::class, function (TemplateFinalReplaceEvent $event) use ($container) {
            $event->template = Typograph::processRussianText($event->template);
        });
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
    }
}
