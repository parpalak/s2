<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Framework;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;

interface ExtensionInterface
{
    public function buildContainer(Container $container): void;

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void;

    public function registerRoutes(RouteCollection $routes, Container $container): void;
}
