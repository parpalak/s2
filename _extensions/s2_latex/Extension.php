<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_latex
 */

declare(strict_types=1);

namespace s2_extensions\s2_latex;

use S2\Cms\Asset\AssetPack;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Template\TemplateAssetEvent;
use S2\Cms\Template\TemplatePreCommentRenderEvent;
use S2\Cms\Translation\ExtensibleTranslator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Contracts\Translation\TranslatorInterface;

class Extension implements ExtensionInterface
{
    public function buildContainer(Container $container): void
    {
        $container->set('s2_latex_translator', function (Container $container) {
            /** @var ExtensibleTranslator $translator */
            $translator = $container->get('translator');
            $translator->load('s2_latex', function (string $lang) {
                return require ($dir = __DIR__ . '/lang/') . (file_exists($dir . $lang . '.php') ? $lang : 'English') . '.php';
            });

            return $translator;
        });
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        $eventDispatcher->addListener(TemplateAssetEvent::class, static function (TemplateAssetEvent $event) use ($container) {
            $event->assetPack->addJs('//i.upmath.me/latex.js', [AssetPack::OPTION_PRELOAD, AssetPack::OPTION_DEFER]);
        });

        $eventDispatcher->addListener(TemplatePreCommentRenderEvent::class, static function (TemplatePreCommentRenderEvent $event) use ($container) {
            /** @var TranslatorInterface $translator */
            $translator = $container->get('s2_latex_translator');
            array_unshift($event->syntaxHelpItems, $translator->trans('Comment latex syntax'));
        });
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
    }
}
