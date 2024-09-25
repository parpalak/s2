<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   MIT
 * @package   s2_typo
 */

declare(strict_types=1);

namespace s2_extensions\s2_typo;

use S2\Cms\Controller\Rss\FeedItemRenderEvent;
use S2\Cms\Controller\Rss\FeedRenderEvent;
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
        $eventDispatcher->addListener(TemplateFinalReplaceEvent::class, function (TemplateFinalReplaceEvent $event) {
            $event->template = Typograph::processRussianText($event->template);
        });

        $eventDispatcher->addListener(FeedItemRenderEvent::class, static function (FeedItemRenderEvent $event) {
            $event->feedItemDto->title = Typograph::processRussianText($event->feedItemDto->title, true);
            $event->feedItemDto->text  = Typograph::processRussianText($event->feedItemDto->text);
        }, -10);

        $eventDispatcher->addListener(FeedRenderEvent::class, static function (FeedRenderEvent $event) {
            $event->feedDto->title       = Typograph::processRussianText($event->feedDto->title, true);
            $event->feedDto->description = Typograph::processRussianText($event->feedDto->description, true);
        }, -10);
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
    }
}
