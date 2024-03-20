<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace s2_extensions\s2_latex;

use S2\Cms\Asset\AssetPack;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Template\TemplateAssetEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;

class Extension implements ExtensionInterface
{
    public function buildContainer(Container $container): void
    {
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        $eventDispatcher->addListener(TemplateAssetEvent::class, static function (TemplateAssetEvent $event) use ($container) {
            $event->assetPack->addJs('//i.upmath.me/latex.js', [AssetPack::OPTION_PRELOAD, AssetPack::OPTION_DEFER]);
        });
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
    }
}
