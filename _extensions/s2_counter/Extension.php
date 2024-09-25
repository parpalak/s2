<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   MIT
 * @package   s2_counter
 */

declare(strict_types=1);

namespace s2_extensions\s2_counter;

use S2\Cms\Controller\Rss\RssHitEvent;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Template\TemplateEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;

class Extension implements ExtensionInterface
{
    public function buildContainer(Container $container): void
    {
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        $eventDispatcher->addListener(TemplateEvent::EVENT_PRE_REPLACE, function (TemplateEvent $event) use ($container) {
            $event->htmlTemplate->registerPlaceholder('<!-- s2_counter_img -->', '<img class="s2_counter" src="' . S2_PATH . '/_extensions/s2_counter/counter.php" width="88" height="31" />');

            if ($event->htmlTemplate->isNotFound()) {
                return;
            }

            if (!defined('S2_COUNTER_FUNCTIONS_LOADED')) {
                include __DIR__ . '/functions.php';
            }

            s2_counter_process();
        });

        $eventDispatcher->addListener(RssHitEvent::class, function (RssHitEvent $event) use ($container) {
            if (!defined('S2_COUNTER_FUNCTIONS_LOADED')) {
                include __DIR__ . '/functions.php';
            }

            s2_counter_rss_count($event->request, $event->rssStrategy);
        });
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
    }
}
