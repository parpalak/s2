<?php /** @noinspection HtmlUnknownTarget */
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_latex
 */

declare(strict_types=1);

namespace s2_extensions\s2_latex;

use S2\Cms\Asset\AssetPack;
use S2\Cms\Controller\Rss\FeedItemRenderEvent;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Image\ThumbnailGenerateEvent;
use S2\Cms\Template\TemplateAssetEvent;
use S2\Cms\Template\TemplatePreCommentRenderEvent;
use S2\Cms\Translation\ExtensibleTranslator;
use S2\Rose\Finder;
use s2_extensions\s2_search\Event\TextNodeExtractEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Contracts\Translation\TranslatorInterface;

class Extension implements ExtensionInterface
{
    private const CUSTOM_UPMATH_PROTOCOL = 'upmath://';

    public function buildContainer(Container $container): void
    {
        $container->set('s2_latex_translator', static function (Container $container) {
            /** @var ExtensibleTranslator $translator */
            $translator = $container->get('translator');
            $translator->attachLoader('s2_latex', static function (string $lang) {
                return require ($dir = __DIR__ . '/lang/') . (file_exists($dir . $lang . '.php') ? $lang : 'English') . '.php';
            });

            return $translator;
        });

        $container->decorate(Finder::class, static function (Container $container, callable $originalFactory) {
            /** @var Finder $finder */
            $finder = $originalFactory($container);
            // same as \$\$(.*?)\$\$ but with optimizations, see https://www.rexegg.com/regex-quantifiers.php#explicit_greed
            $finder->setHighlightMaskRegexArray(['#\$\$(?:[^$]++|\$(?!\$))*+\$\$#']);

            return $finder;
        });
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        $eventDispatcher->addListener(TemplateAssetEvent::class, static function (TemplateAssetEvent $event) {
            $event->assetPack->addJs('//i.upmath.me/latex.js', [AssetPack::OPTION_MERGE, AssetPack::OPTION_DEFER]);
        });

        $eventDispatcher->addListener(TemplatePreCommentRenderEvent::class, static function (TemplatePreCommentRenderEvent $event) use ($container) {
            /** @var TranslatorInterface $translator */
            $translator = $container->get('s2_latex_translator');
            array_unshift($event->syntaxHelpItems, $translator->trans('Comment latex syntax'));
        });

        $eventDispatcher->addListener(FeedItemRenderEvent::class, static function (FeedItemRenderEvent $event) {
            $event->feedItemDto->text = self::convertLatexInHtml($event->feedItemDto->text);
        }, 10);

        // Note: Indexing is performed in the QueueConsumer, so it cannot be moved to AdminExtension right now.
        $eventDispatcher->addListener(TextNodeExtractEvent::class, [self::class, 'textNodeExtractListener']);

        // Thumbnails in search results page
        $eventDispatcher->addListener(ThumbnailGenerateEvent::class, static function (ThumbnailGenerateEvent $event) {
            $src = $event->src;
            if (str_starts_with($src, self::CUSTOM_UPMATH_PROTOCOL)) {
                $url = 'https://i.upmath.me/svg/' . self::encodeURIComponent(substr($src, \strlen(self::CUSTOM_UPMATH_PROTOCOL)));
                $event->setResult(\sprintf('<img src="%s" style="background: white" alt=""></span>', $url));
            }
        });
    }

    public static function textNodeExtractListener(TextNodeExtractEvent $event): void
    {
        /**
         * These conditions must be in sync with client script for inserting formulas
         *
         * @see https://github.com/parpalak/i.upmath.me/blob/master/src/latex.js#L146
         */
        $contentPieces = explode('$$', $event->textContent);

        if ($event->parentNode->nodeName !== 'p' || \count($event->parentNode->childNodes) >= 2) {
            return;
        }

        if (\count($contentPieces) === 3
            && preg_match('/^[ \t]*$/', $contentPieces[0]) === 1
            && preg_match('/^(?:[ \t]*\([ \t]*\S+[ \t]*\))?[ \t]*$/', $contentPieces[2]) === 1
        ) {
            // A block formula encountered. We do not index it and do not add to snippets.
            $event->stopPropagation();
            $formula = $contentPieces[1];

            // Skip non-picture formulas for thumbnails
            // TODO come up with a more complete list
            if (
                str_contains($formula, 'tikzpicture')
                || str_contains($formula, 'sequencediagram')
                || str_contains($formula, 'circuitikz')
            ) {
                /**
                 * Decode all entities. '&' was encoded before and decoded in DOM processing.
                 *
                 * @see \S2\Rose\Extractor\HtmlDom\DomExtractor::getDomDocument
                 */
                $formula = html_entity_decode($formula, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);

                $event->domState->attachImg(self::CUSTOM_UPMATH_PROTOCOL . $formula, '', '', '');
            }
        }
    }

    public static function convertLatexInHtml(string $text): string
    {
        // NOTE: maybe it would be better to use DOM here
        return preg_replace_callback('#\\$\\$([^<]*?)\\$\\$#S', static function ($matches) {
            $formula = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '<img border="0" style="vertical-align: middle;" src="//i.upmath.me/svg/' . s2_htmlencode(self::encodeURIComponent($formula)) . '" alt="' . s2_htmlencode($formula) . '" />';
        }, $text);
    }

    public static function encodeURIComponent(string $str): string
    {
        $revert = ['%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')'];

        return strtr(rawurlencode($str), $revert);
    }

    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
    }
}
