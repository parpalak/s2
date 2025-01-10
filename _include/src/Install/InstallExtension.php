<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Install;

use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Config\InstallationConfigProvider;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;

class InstallExtension implements ExtensionInterface
{
    public function buildContainer(Container $container): void
    {
        $container->decorate(DynamicConfigProvider::class, static function (Container $container) {
            return new InstallationConfigProvider();
        });
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
    }
}
