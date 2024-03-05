<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace s2_extensions\s2_search;

use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Template\HtmlTemplateCreatedEvent;
use s2_extensions\s2_search\Controller\SearchPageController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class Extension implements ExtensionInterface
{
    public function buildContainer(Container $container): void
    {
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        $eventDispatcher->addListener(HtmlTemplateCreatedEvent::class, function (HtmlTemplateCreatedEvent $event) {
            \Lang::load('s2_search', function () {
                if (file_exists(S2_ROOT . '/_extensions/s2_search' . '/lang/' . S2_LANGUAGE . '.php'))
                    return require S2_ROOT . '/_extensions/s2_search' . '/lang/' . S2_LANGUAGE . '.php';
                else
                    return require S2_ROOT . '/_extensions/s2_search' . '/lang/English.php';
            });
            $event->htmlTemplate->registerPlaceholder('<!-- s2_search_field -->', '<form class="s2_search_form" method="get" action="' . (S2_URL_PREFIX ? S2_PATH . S2_URL_PREFIX : S2_PATH . '/search') . '">' . (S2_URL_PREFIX ? '<input type="hidden" name="search" value="1" />' : '') . '<input type="text" name="q" id="s2_search_input" placeholder="' . \Lang::get('Search', 's2_search') . '"/></form>');
        });
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
        $routes->add('search', new Route('/search', ['_controller' => SearchPageController::class]));

        // Hack for alternative URL schemes
        $routes->add('search2', new Route('/', ['_controller' => SearchPageController::class], condition: "request.query.get('search') !== null"));
    }
}
